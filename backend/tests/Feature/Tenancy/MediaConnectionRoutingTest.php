<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\CentralMedia;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Fixtures\Media\CentralMediaOwner;
use Tests\Fixtures\Media\TenantMediaOwner;
use Tests\TenantTestCase;

/**
 * PKG-2: spatie/laravel-medialibrary routed between the central and tenant worlds.
 *
 *  - Uploads made FROM a tenant request INTO the platform (payment receipts of
 *    ENTI-3.3, platform assets of BRND-2) go through CentralMedia: the row is in
 *    the CENTRAL `media` table and the file is on a central disk whose root is
 *    absolute and never suffixed by FilesystemTenancyBootstrapper.
 *  - Tenant assets (shop logo, BRND-5) use the default Media model: the row is in
 *    the TENANT `media` table and the file sits under storage/tenant<id>/.
 */
#[Group('harness')]
#[Group('mysql')]
final class MediaConnectionRoutingTest extends TenantTestCase
{
    private string $centralPrivateRoot = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Same mechanism as the shipped config (absolute root resolved in central
        // context) but in a throw-away directory, so the test never touches real files.
        $base = storage_path('framework/testing/pkg2-'.Str::lower(Str::random(10)).'/central');
        $this->centralPrivateRoot = $base.'/private';
        config([
            'filesystems.disks.central_private.root' => $this->centralPrivateRoot,
            'filesystems.disks.central_public.root' => $base.'/public',
        ]);
        Storage::forgetDisk(['central_private', 'central_public']);

        $this->beforeApplicationDestroyed(function (): void {
            File::deleteDirectory(dirname($this->centralPrivateRoot, 2));
        });
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function centralDisks(): array
    {
        return [
            'central_private' => ['central_private'],
            'central_public' => ['central_public'],
        ];
    }

    #[DataProvider('centralDisks')]
    public function test_central_disks_are_not_tenancy_suffixed(string $disk): void
    {
        $this->assertNotContains($disk, (array) config('tenancy.filesystem.disks'));
        $this->assertSame('local', config("filesystems.disks.{$disk}.driver"));

        $root = (string) config("filesystems.disks.{$disk}.root");
        $this->assertNotSame('', $root);

        $tenant = $this->createTenant();

        $pathInTenant = $this->inTenant($tenant, static fn (): string => Storage::disk($disk)->path('probe.txt'));

        $this->assertStringStartsWith(rtrim($root, '\\/'), $pathInTenant);
        $this->assertStringNotContainsString($this->tenantDirName($tenant), $pathInTenant);
    }

    public function test_shipped_central_disk_roots_are_absolute_central_storage_paths(): void
    {
        // Re-read the shipped file: setUp() overrode central_private for isolation.
        /** @var array{disks: array<string, array<string, mixed>>} $filesystems */
        $filesystems = require config_path('filesystems.php');

        $this->assertSame(storage_path('app/central/private'), $filesystems['disks']['central_private']['root']);
        $this->assertSame(storage_path('app/central/public'), $filesystems['disks']['central_public']['root']);
        $this->assertFalse((bool) ($filesystems['disks']['central_private']['serve'] ?? false), 'Receipts are served through a signed route only (ENTI-3.3).');
    }

    public function test_upload_from_tenant_context_to_central_private_lands_in_central(): void
    {
        $tenant = $this->createTenant();
        $central = $this->centralConnectionName();

        $owner = CentralMediaOwner::query()->create(['key' => 'fixture_pkg2_receipt_owner', 'value' => 'x', 'type' => 'string']);

        $result = $this->inTenant($tenant, function () use ($owner): array {
            $media = $owner
                ->addMedia(UploadedFile::fake()->create('receipt.pdf', 12, 'application/pdf'))
                ->toMediaCollection('receipts', 'central_private');

            return [
                'class' => $media::class,
                'connection' => $media->getConnectionName(),
                'path' => $media->getPath(),
                'tenant_media_rows' => DB::table('media')->count(),
            ];
        });

        $this->assertSame(CentralMedia::class, $result['class']);
        $this->assertSame($central, $result['connection']);
        $this->assertSame(0, $result['tenant_media_rows'], 'The receipt row must not be written to the tenant DB.');

        $this->assertSame(1, DB::connection($central)->table('media')->where('disk', 'central_private')->count());
        $this->assertDatabaseHas('media', [
            'model_type' => CentralMediaOwner::class,
            'model_id' => $owner->getKey(),
            'collection_name' => 'receipts',
            'disk' => 'central_private',
        ], $central);

        $this->assertFileExists($result['path']);
        $this->assertStringStartsWith($this->centralPrivateRoot, $result['path']);
        $this->assertStringNotContainsString($this->tenantDirName($tenant), $result['path']);
    }

    public function test_tenant_logo_upload_lands_in_tenant_storage_and_tenant_db(): void
    {
        $tenant = $this->createTenant();
        $other = $this->createTenant();
        $central = $this->centralConnectionName();
        $storeId = $this->tenantStore($tenant)->getKey();

        $result = $this->inTenant($tenant, function () use ($storeId): array {
            $media = TenantMediaOwner::query()->findOrFail($storeId)
                ->addMedia(UploadedFile::fake()->create('logo.png', 8, 'image/png'))
                ->toMediaCollection('logo');

            return [
                'class' => $media::class,
                'disk' => $media->disk,
                'path' => $media->getPath(),
                'tenant_media_rows' => DB::table('media')->count(),
            ];
        });

        $this->assertSame(Media::class, $result['class']);
        $this->assertSame('public', $result['disk']);
        $this->assertSame(1, $result['tenant_media_rows']);
        $this->assertSame(0, DB::connection($central)->table('media')->count(), 'Tenant assets must not reach the central DB.');
        $this->assertSame(0, $this->inTenant($other, static fn (): int => DB::table('media')->count()));

        $this->assertFileExists($result['path']);
        $this->assertStringContainsString($this->tenantDirName($tenant), $result['path']);
    }

    public function test_central_media_refuses_a_tenancy_suffixed_disk(): void
    {
        $tenant = $this->createTenant();
        $owner = CentralMediaOwner::query()->create(['key' => 'fixture_pkg2_wrong_disk', 'value' => 'x', 'type' => 'string']);

        try {
            $this->inTenant($tenant, function () use ($owner): void {
                $owner
                    ->addMedia(UploadedFile::fake()->create('receipt.pdf', 4, 'application/pdf'))
                    ->toMediaCollection('receipts', 'public');
            });
            $this->fail('A central media row pointing at a tenant-suffixed disk must be rejected.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('public', $e->getMessage());
        }

        $this->assertSame(0, DB::connection($this->centralConnectionName())->table('media')->count());
    }

    public function test_central_media_stays_pinned_to_central_inside_tenancy(): void
    {
        $tenant = $this->createTenant();
        $central = $this->centralConnectionName();

        $connection = $this->inTenant($tenant, static fn (): ?string => (new CentralMedia)->getConnectionName());

        $this->assertSame($central, $connection);
        $this->assertNotSame('tenant', $connection);
    }

    public function test_deleting_central_media_removes_its_file(): void
    {
        $tenant = $this->createTenant();
        $owner = CentralMediaOwner::query()->create(['key' => 'fixture_pkg2_delete', 'value' => 'x', 'type' => 'string']);

        $path = $this->inTenant($tenant, function () use ($owner): string {
            $media = $owner
                ->addMedia(UploadedFile::fake()->create('receipt.pdf', 4, 'application/pdf'))
                ->toMediaCollection('receipts', 'central_private');

            $path = $media->getPath();
            $media->delete();

            return $path;
        });

        $this->assertFileDoesNotExist($path, 'The media observer must also run for the CentralMedia subclass.');
        $this->assertSame(0, DB::connection($this->centralConnectionName())->table('media')->count());
    }

    private function tenantDirName(Tenant $tenant): string
    {
        return config('tenancy.filesystem.suffix_base', 'tenant').$tenant->getTenantKey();
    }
}
