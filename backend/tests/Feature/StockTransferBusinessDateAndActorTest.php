<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockTransferService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Tests\TenantTestCase;

/**
 * Lane 2H: StockTransferService used to write `Auth::id() ?? 1` and stamp the server date.
 *
 * Now: the authenticated user, else the explicit actor, else NULL ("system") — on the
 * transfer and on every (reversal) movement; transfer_date and the TRF number carry the
 * tenant BUSINESS date (timezone + business_day_cutoff).
 *
 * Fixed instant: 2026-10-09 01:30 Africa/Cairo with cutoff 03:00 -> business day 2026-10-08.
 */
final class StockTransferBusinessDateAndActorTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(CarbonImmutable::parse('2026-10-09 01:30:00', 'Africa/Cairo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_transfer_without_auth_or_actor_is_attributed_to_nobody_and_stamped_with_the_business_date(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($tenant): void {
            Setting::set('business_day_cutoff', '03:00');
            $this->assertNull(Auth::id());
            [$itemId, $fromId, $toId] = $this->fixture($tenant);

            $transfer = app(StockTransferService::class)->createTransfer($this->payload($itemId, $fromId, $toId));

            $this->assertNull(StockTransfer::query()->whereKey($transfer->id)->value('user_id'));
            $this->assertSame('2026-10-08', StockTransfer::query()->whereKey($transfer->id)->firstOrFail()->transfer_date->toDateString());
            $this->assertStringStartsWith('TRF-20261008-', $transfer->transfer_number);
            $this->assertSame([null, null], $this->movementActors($transfer->transfer_number));
            $this->assertSame('7.500', (string) StoreStock::query()->where('store_id', $fromId)->where('item_id', $itemId)->value('quantity'));
            $this->assertSame('2.500', (string) StoreStock::query()->where('store_id', $toId)->where('item_id', $itemId)->value('quantity'));

            app(StockTransferService::class)->cancelTransfer($transfer, 'test');

            $this->assertSame([null, null, null, null], $this->movementActors($transfer->transfer_number));
            $this->assertSame('10.000', (string) StoreStock::query()->where('store_id', $fromId)->where('item_id', $itemId)->value('quantity'));
            $this->assertSame('0.000', (string) StoreStock::query()->where('store_id', $toId)->where('item_id', $itemId)->value('quantity'));
        });
    }

    public function test_transfer_and_cancel_from_a_job_use_the_explicit_actor(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($tenant): void {
            [$itemId, $fromId, $toId] = $this->fixture($tenant);
            $actorId = (int) $this->tenantAdmin($tenant)->getKey();

            $transfer = app(StockTransferService::class)->createTransfer(
                $this->payload($itemId, $fromId, $toId) + ['user_id' => $actorId],
            );
            app(StockTransferService::class)->cancelTransfer($transfer, null, $actorId);

            $this->assertSame($actorId, (int) StockTransfer::query()->whereKey($transfer->id)->value('user_id'));
            $this->assertSame(
                [$actorId, $actorId, $actorId, $actorId],
                array_map('intval', $this->movementActors($transfer->transfer_number)),
            );
        });
    }

    public function test_the_authenticated_user_wins_over_the_explicit_actor(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($tenant): void {
            [$itemId, $fromId, $toId] = $this->fixture($tenant);
            $admin = $this->tenantAdmin($tenant);
            $clerk = User::factory()->create(['phone' => '01000007611', 'is_active' => true]);

            Auth::login($clerk);
            try {
                $transfer = app(StockTransferService::class)->createTransfer(
                    $this->payload($itemId, $fromId, $toId) + ['user_id' => $admin->getKey()],
                );
                app(StockTransferService::class)->cancelTransfer($transfer, null, (int) $admin->getKey());
            } finally {
                Auth::logout();
            }

            $this->assertSame((int) $clerk->id, (int) StockTransfer::query()->whereKey($transfer->id)->value('user_id'));
            $this->assertSame(
                array_fill(0, 4, (int) $clerk->id),
                array_map('intval', $this->movementActors($transfer->transfer_number)),
            );
        });
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function fixture(Tenant $tenant): array
    {
        $from = $this->tenantStore($tenant);
        $to = Store::create(['name' => 'فرع التحويل', 'code' => 'TRF-TO-'.uniqid(), 'is_active' => true]);

        $item = Item::create([
            'name' => 'بن تحويل',
            'code' => 'TRF-ITEM-'.uniqid(),
            'unit' => 'كجم',
            'cost_price' => '40.000',
            'selling_price' => '55.000',
            'current_stock' => '10.000',
            'is_active' => true,
        ]);
        StoreStock::create(['store_id' => $from->getKey(), 'item_id' => $item->id, 'quantity' => '10.000']);

        return [(int) $item->id, (int) $from->getKey(), (int) $to->id];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $itemId, int $fromId, int $toId): array
    {
        return [
            'from_store_id' => $fromId,
            'to_store_id' => $toId,
            'items' => [['item_id' => $itemId, 'quantity' => '2.500']],
        ];
    }

    /**
     * @return list<int|null>
     */
    private function movementActors(string $documentNumber): array
    {
        return StockMovement::query()->where('document_number', $documentNumber)->orderBy('id')->pluck('user_id')->all();
    }
}
