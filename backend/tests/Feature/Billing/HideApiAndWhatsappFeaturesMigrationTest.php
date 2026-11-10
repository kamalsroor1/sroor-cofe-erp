<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Models\PlanFeature;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * PRD-10 follow-up: api.access and whatsapp.share are hidden from the catalog like
 * quotations.manage, and quotations.manage stops being core. Keys and stored plan values stay.
 */
#[Group('billing')]
final class HideApiAndWhatsappFeaturesMigrationTest extends TenantTestCase
{
    private function migration(): Migration
    {
        $migration = require database_path('migrations/2026_11_13_100200_hide_api_access_and_whatsapp_share_features.php');
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    /** An installation that still has the pre-decision registry. */
    private function legacyRegistry(): void
    {
        $this->seed(PlansAndFeaturesSeeder::class);
        $db = DB::connection($this->centralConnectionName());
        $db->table('plan_features')->whereIn('key', ['api.access', 'whatsapp.share'])->update(['is_public' => true]);
        $db->table('plan_features')->where('key', 'quotations.manage')->update(['is_core' => true]);
    }

    public function test_up_hides_the_features_keeps_the_keys_and_down_restores(): void
    {
        $this->legacyRegistry();
        $plan = Plan::query()->where('slug', 'enterprise')->firstOrFail();
        $plan->update(['features' => array_merge($plan->features ?? [], ['api.access' => true, 'whatsapp.share' => true])]);
        $before = Plan::query()->findOrFail($plan->id)->features;

        $migration = $this->migration();
        $migration->up();
        $migration->up();

        $public = PlanFeature::query()->public()->pluck('key')->all();
        $this->assertNotContains('api.access', $public);
        $this->assertNotContains('whatsapp.share', $public);
        $this->assertNotContains('quotations.manage', $public);
        $this->assertFalse((bool) PlanFeature::query()->where('key', 'quotations.manage')->value('is_core'));
        $this->assertSame(17, PlanFeature::query()->where('is_core', true)->count());
        $this->assertContains('pos.access', $public, 'Other features stay public.');

        $this->assertSame($before, Plan::query()->findOrFail($plan->id)->features, 'Stored plan values are untouched.');

        $resource = (new PlanResource(Plan::query()->findOrFail($plan->id)))->toArray(new Request);
        $this->assertArrayNotHasKey('api.access', $resource['features']);
        $this->assertArrayNotHasKey('whatsapp.share', $resource['features']);
        $this->assertArrayHasKey('pos.access', $resource['features']);

        $migration->down();
        $this->assertTrue((bool) PlanFeature::query()->where('key', 'api.access')->value('is_public'));
        $this->assertTrue((bool) PlanFeature::query()->where('key', 'whatsapp.share')->value('is_public'));
        $this->assertTrue((bool) PlanFeature::query()->where('key', 'quotations.manage')->value('is_core'));
    }
}
