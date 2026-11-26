<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL (#232 spec C): when a plan-change request of a tenant with an agreed founder price is
 * held for the owner's manual review instead of being invoiced. Null = never held.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('plan_change_requests', 'founder_review_held_at')) {
            return;
        }

        Schema::table('plan_change_requests', function (Blueprint $table): void {
            $table->timestamp('founder_review_held_at')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('plan_change_requests', 'founder_review_held_at')) {
            return;
        }

        Schema::table('plan_change_requests', function (Blueprint $table): void {
            $table->dropColumn('founder_review_held_at');
        });
    }
};
