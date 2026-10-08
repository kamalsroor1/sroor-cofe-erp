<?php

declare(strict_types=1);

namespace Tests\Fixtures\Media;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * PKG-2 test fixture: a TENANT model that owns media (stands in for the shop
 * logo of BRND-5). Backed by the tenant `stores` table, so it only works
 * inside an initialized tenant, exactly like the real tenant models.
 */
final class TenantMediaOwner extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'stores';
}
