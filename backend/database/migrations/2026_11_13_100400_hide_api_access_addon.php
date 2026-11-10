<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner decision (central DB): the api.access add-on must not be sold, so it is hidden from the
 * public add-on catalog (addons.is_public = false), like custom.domain. The row, its prices,
 * plan_addon availability and existing subscriptions are untouched, so nobody loses access.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setPublic(false);
    }

    public function down(): void
    {
        $this->setPublic(true);
    }

    private function setPublic(bool $public): void
    {
        if (! Schema::hasTable('addons') || ! Schema::hasColumn('addons', 'is_public')) {
            return;
        }

        DB::table('addons')->where('key', 'api.access')->update(['is_public' => $public]);
    }
};
