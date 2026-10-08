<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Support\Media\ServerGeneratedFileNamer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Fixtures\Media\CentralMediaOwner;
use Tests\Fixtures\Media\TenantMediaOwner;
use Tests\TenantTestCase;

/**
 * W1 hardening note 5: medialibrary never stores a file under the client's upload name.
 * The base name is a server-generated ULID (App\Support\Media\ServerGeneratedFileNamer),
 * for central uploads (receipts, platform assets) and tenant uploads (shop logo) alike.
 */
final class MediaServerGeneratedFileNameTest extends TenantTestCase
{
    private const GENERATED = '/^[0-9a-z]{26}\.(pdf|png)$/';

    private string $centralRoot = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralRoot = storage_path('framework/testing/w1-5-'.Str::lower(Str::random(10)));
        config([
            'filesystems.disks.central_private.root' => $this->centralRoot.'/central/private',
            'filesystems.disks.central_public.root' => $this->centralRoot.'/central/public',
        ]);
        Storage::forgetDisk(['central_private', 'central_public']);

        $this->beforeApplicationDestroyed(function (): void {
            File::deleteDirectory($this->centralRoot);
        });
    }

    public function test_the_server_side_file_namer_is_configured(): void
    {
        $this->assertSame(ServerGeneratedFileNamer::class, config('media-library.file_namer'));
    }

    public function test_central_upload_is_stored_under_a_generated_name(): void
    {
        $tenant = $this->createTenant();
        $owner = CentralMediaOwner::query()->create(['key' => 'fixture_w1_5_receipt_owner', 'value' => 'x', 'type' => 'string']);

        [$fileName, $path] = $this->inTenant($tenant, function () use ($owner): array {
            $media = $owner
                ->addMedia(UploadedFile::fake()->create('Ahmed Ali receipt 0100.pdf', 12, 'application/pdf'))
                ->toMediaCollection('receipts', 'central_private');

            return [$media->file_name, $media->getPath()];
        });

        $this->assertMatchesRegularExpression(self::GENERATED, $fileName);
        $this->assertStringNotContainsStringIgnoringCase('ahmed', $fileName);
        $this->assertSame($fileName, basename($path));
        $this->assertFileExists($path);
    }

    public function test_tenant_upload_is_stored_under_a_generated_name(): void
    {
        $tenant = $this->createTenant();
        $storeId = $this->tenantStore($tenant)->getKey();

        [$fileName, $path] = $this->inTenant($tenant, function () use ($storeId): array {
            $media = TenantMediaOwner::query()->findOrFail($storeId)
                ->addMedia(UploadedFile::fake()->create('my shop logo.png', 8, 'image/png'))
                ->toMediaCollection('logo');

            return [$media->file_name, $media->getPath()];
        });

        $this->assertMatchesRegularExpression(self::GENERATED, $fileName);
        $this->assertStringNotContainsStringIgnoringCase('logo', $fileName);
        $this->assertSame($fileName, basename($path));
        $this->assertFileExists($path);
    }

    public function test_two_uploads_with_the_same_client_name_get_different_names(): void
    {
        $tenant = $this->createTenant();
        $owner = CentralMediaOwner::query()->create(['key' => 'fixture_w1_5_receipt_owner_2', 'value' => 'x', 'type' => 'string']);

        $names = $this->inTenant($tenant, function () use ($owner): array {
            $names = [];
            for ($i = 0; $i < 2; $i++) {
                $names[] = $owner
                    ->addMedia(UploadedFile::fake()->create('receipt.pdf', 4, 'application/pdf'))
                    ->toMediaCollection('receipts', 'central_private')
                    ->file_name;
            }

            return $names;
        });

        $this->assertNotSame($names[0], $names[1]);
    }
}
