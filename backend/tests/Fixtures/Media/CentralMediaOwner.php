<?php

declare(strict_types=1);

namespace Tests\Fixtures\Media;

use App\Models\Concerns\InteractsWithCentralMedia;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

/**
 * PKG-2 test fixture: a CENTRAL model that owns media (stands in for
 * BillingPayment receipts / platform assets until ENTI-3.3 and BRND-2 land).
 * Backed by the central `platform_settings` table so no extra schema is needed.
 */
final class CentralMediaOwner extends Model implements HasMedia
{
    use InteractsWithCentralMedia;
    use UsesCentralConnection;

    protected $table = 'platform_settings';

    /** @var list<string> */
    protected $fillable = ['key', 'value', 'type'];
}
