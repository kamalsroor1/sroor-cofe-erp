<?php

declare(strict_types=1);

namespace App\Observers\Landing;

use App\Support\PublicCatalogCache;
use Illuminate\Database\Eloquent\Model;

/**
 * PRD-07: any change to a catalog row (plan, feature, add-on, plan/add-on availability)
 * invalidates the cached public pricing payload.
 */
final class PublicCatalogObserver
{
    public function saved(Model $model): void
    {
        PublicCatalogCache::forget();
    }

    public function deleted(Model $model): void
    {
        PublicCatalogCache::forget();
    }
}
