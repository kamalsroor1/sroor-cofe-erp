<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\Tenant;
use App\Services\Settings\TenantSettings;
use App\Support\TenantClock;
use Tests\TenantTestCase;

/** DATE-02: write documents cannot be dated in the future, and back-dating needs documents.backdate + the day limit. */
class DocumentDateLimitsApiTest extends TenantTestCase
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
    }

    /** @return array<string, mixed> */
    private function expense(string $date): array
    {
        return [
            'title' => 'Rent',
            'category' => 'operational',
            'cost_center' => 'operational',
            'amount' => 100,
            'expense_date' => $date,
            'payment_method' => 'cash',
        ];
    }

    private function businessDate(): string
    {
        return $this->inTenant($this->tenant, fn (): string => app(TenantClock::class)->businessDate());
    }

    private function daysFromToday(int $days): string
    {
        return date('Y-m-d', strtotime($this->businessDate().' '.($days >= 0 ? '+' : '').$days.' days'));
    }

    public function test_future_date_is_rejected_even_for_admin(): void
    {
        $this->tenantAdmin($this->tenant);

        $this->postJson('/api/v1/expenses', $this->expense($this->daysFromToday(1)), $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors('expense_date');
    }

    public function test_today_is_accepted_without_backdate_permission(): void
    {
        $user = $this->createTenantUser($this->tenant, permissions: ['expenses.manage']);

        $this->postJson('/api/v1/expenses', $this->expense($this->businessDate()), $this->tenantHeaders($this->tenant, $user))
            ->assertStatus(201);
    }

    public function test_old_date_without_permission_is_rejected(): void
    {
        $user = $this->createTenantUser($this->tenant, permissions: ['expenses.manage']);

        $this->postJson('/api/v1/expenses', $this->expense($this->daysFromToday(-2)), $this->tenantHeaders($this->tenant, $user))
            ->assertStatus(422)
            ->assertJsonValidationErrors('expense_date');
    }

    public function test_old_date_with_permission_is_accepted_within_limit_and_rejected_beyond_it(): void
    {
        $user = $this->createTenantUser($this->tenant, permissions: ['expenses.manage', 'documents.backdate']);
        $headers = $this->tenantHeaders($this->tenant, $user);

        $this->inTenant($this->tenant, function (): void {
            Setting::set(TenantSettings::KEY_BACKDATE_MAX_DAYS, '5');
        });

        $this->postJson('/api/v1/expenses', $this->expense($this->daysFromToday(-5)), $headers)->assertStatus(201);
        $this->postJson('/api/v1/expenses', $this->expense($this->daysFromToday(-6)), $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('expense_date');
    }

    public function test_purchase_with_future_date_is_rejected(): void
    {
        $this->tenantAdmin($this->tenant);

        $this->postJson('/api/v1/purchases', ['purchase_date' => $this->daysFromToday(30)], $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors('purchase_date');
    }

    public function test_updating_an_expense_cannot_move_its_date_outside_the_allowed_window(): void
    {
        $this->tenantAdmin($this->tenant);
        $id = $this->postJson('/api/v1/expenses', $this->expense($this->businessDate()), $this->tenantHeaders($this->tenant))
            ->assertStatus(201)
            ->json('data.id');
        $user = $this->createTenantUser($this->tenant, permissions: ['expenses.manage']);
        $headers = $this->tenantHeaders($this->tenant, $user);

        $this->putJson('/api/v1/expenses/'.$id, $this->expense($this->daysFromToday(1)), $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('expense_date');
        $this->putJson('/api/v1/expenses/'.$id, $this->expense($this->daysFromToday(-2)), $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('expense_date');
    }

    public function test_updating_an_old_expense_without_changing_its_date_is_accepted(): void
    {
        $this->tenantAdmin($this->tenant);
        $oldDate = $this->daysFromToday(-3);
        $id = $this->postJson('/api/v1/expenses', $this->expense($oldDate), $this->tenantHeaders($this->tenant))
            ->assertStatus(201)
            ->json('data.id');
        $user = $this->createTenantUser($this->tenant, permissions: ['expenses.manage']);

        $this->putJson('/api/v1/expenses/'.$id, ['title' => 'Rent (fixed)'] + $this->expense($oldDate), $this->tenantHeaders($this->tenant, $user))
            ->assertStatus(200);
    }

    public function test_stock_transfer_with_future_date_is_rejected(): void
    {
        $this->tenantAdmin($this->tenant);

        $this->postJson('/api/v1/transfers', ['transfer_date' => $this->daysFromToday(1)], $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors('transfer_date');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/v1/expenses', $this->expense($this->businessDate()), $this->tenantGuestHeaders($this->tenant))
            ->assertStatus(401);
    }
}
