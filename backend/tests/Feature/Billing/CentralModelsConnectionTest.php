<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Addon;
use App\Models\AppVersion;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingSequence;
use App\Models\CentralActivity;
use App\Models\CentralAuditLog;
use App\Models\CentralMedia;
use App\Models\CentralPersonalAccessToken;
use App\Models\CentralUser;
use App\Models\Concerns\UsesCentralConnection;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\PlanAddon;
use App\Models\PlanFeature;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Models\TenantCreditLedgerEntry;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionMethod;
use Tests\TenantTestCase;

/**
 * ENTI-1.9 guard: every CENTRAL model stays pinned to the central connection while a tenant
 * is initialised (stancl switches the default connection to the tenant DB, so an unpinned
 * central model would read the tenant DB: "table not found" at best, a cross-tenant leak at
 * worst). Removing UsesCentralConnection (or the model's own getConnectionName()) from any
 * model below fails this test.
 *
 * The inventory test fails when a NEW model on a central-only table is added to app/Models
 * without being listed here.
 */
#[Group('billing')]
#[Group('mysql')]
final class CentralModelsConnectionTest extends TenantTestCase
{
    private const VIA_TRAIT = 'trait';

    private const VIA_OWN_METHOD = 'own';

    private const VIA_STANCL = 'stancl';

    /**
     * Central models that exist but are not guarded by this test yet.
     *
     * TODO(ENTI-1.9 / IDEN-3.2): add App\Models\TenantLifecycleEvent to centralModels()
     * (pinned via UsesCentralConnection) once the IDEN-3.2 lane that is creating it lands.
     *
     * @var list<string>
     */
    private const PENDING = [
        'App\\Models\\TenantLifecycleEvent',
    ];

    /**
     * @return array<string, array{0: class-string<Model>, 1: string}>
     */
    public static function centralModels(): array
    {
        return [
            'Plan' => [Plan::class, self::VIA_TRAIT],
            'PlanFeature' => [PlanFeature::class, self::VIA_TRAIT],
            'Subscription' => [Subscription::class, self::VIA_TRAIT],
            'Addon' => [Addon::class, self::VIA_TRAIT],
            'PlanAddon' => [PlanAddon::class, self::VIA_TRAIT],
            'SubscriptionAddon' => [SubscriptionAddon::class, self::VIA_TRAIT],
            'BillingSequence' => [BillingSequence::class, self::VIA_TRAIT],
            'BillingInvoice' => [BillingInvoice::class, self::VIA_TRAIT],
            'BillingPayment' => [BillingPayment::class, self::VIA_TRAIT],
            'TenantCreditLedgerEntry' => [TenantCreditLedgerEntry::class, self::VIA_TRAIT],
            'CentralMedia' => [CentralMedia::class, self::VIA_TRAIT],
            'CentralActivity' => [CentralActivity::class, self::VIA_TRAIT],
            'CentralUser' => [CentralUser::class, self::VIA_OWN_METHOD],
            'CentralPersonalAccessToken' => [CentralPersonalAccessToken::class, self::VIA_OWN_METHOD],
            'CentralAuditLog' => [CentralAuditLog::class, self::VIA_OWN_METHOD],
            'PlatformSetting' => [PlatformSetting::class, self::VIA_OWN_METHOD],
            'AppVersion' => [AppVersion::class, self::VIA_OWN_METHOD],
            'Tenant' => [Tenant::class, self::VIA_STANCL],
            'Domain' => [Domain::class, self::VIA_STANCL],
        ];
    }

    /**
     * @param  class-string<Model>  $class
     */
    #[DataProvider('centralModels')]
    public function test_model_is_pinned_to_the_central_connection_inside_a_tenant(string $class, string $via): void
    {
        $central = $this->centralConnectionName();
        $tenant = $this->createTenant();

        $seen = $this->inTenant($tenant, function () use ($class): array {
            $model = new $class;
            $queryConnection = $model->newQuery()->getConnection();

            return [
                'default' => DB::getDefaultConnection(),
                'name' => $model->getConnectionName(),
                'connection' => $model->getConnection()->getName(),
                'query' => $queryConnection instanceof Connection ? $queryConnection->getName() : null,
                'table_on_connection' => Schema::connection($model->getConnection()->getName())->hasTable($model->getTable()),
            ];
        });

        $this->assertNotSame($central, $seen['default'], 'Tenancy must switch the default connection for this test to mean anything.');
        $this->assertSame($central, $seen['name'], "{$class}::getConnectionName() must return the central connection.");
        $this->assertSame($central, $seen['connection'], "{$class} must resolve the central connection.");
        $this->assertSame($central, $seen['query'], "{$class} queries must run on the central connection.");
        $this->assertTrue($seen['table_on_connection'], "{$class}'s table must exist on the central connection.");
    }

    /**
     * @param  class-string<Model>  $class
     */
    #[DataProvider('centralModels')]
    public function test_model_declares_how_it_is_pinned(string $class, string $via): void
    {
        $declaring = (new ReflectionMethod($class, 'getConnectionName'))->getDeclaringClass();

        match ($via) {
            self::VIA_TRAIT => $this->assertContains(
                UsesCentralConnection::class,
                class_uses_recursive($class),
                "{$class} must use App\\Models\\Concerns\\UsesCentralConnection.",
            ),
            self::VIA_OWN_METHOD => $this->assertSame(
                $class,
                $declaring->getName(),
                "{$class} must declare its own getConnectionName() (or use UsesCentralConnection).",
            ),
            self::VIA_STANCL => $this->assertNotSame(
                Model::class,
                $declaring->getName(),
                "{$class} must keep stancl's central-connection pinning.",
            ),
            default => $this->fail("Unknown pinning [{$via}]."),
        };
    }

    public function test_every_model_on_a_central_only_table_is_guarded(): void
    {
        $central = $this->centralConnectionName();
        $tenant = $this->createTenant();

        $centralTables = array_column(Schema::connection($central)->getTables(), 'name');
        $tenantTables = $this->inTenant($tenant, fn (): array => array_column(Schema::getTables(), 'name'));
        $centralOnly = array_values(array_diff($centralTables, $tenantTables));

        $guarded = array_map(static fn (array $row): string => $row[0], self::centralModels());
        $unguarded = [];

        foreach ((array) glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename((string) $file, '.php');
            if (! class_exists($class) || in_array($class, self::PENDING, true)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            /** @var Model $model */
            $model = $reflection->newInstance();
            if (in_array($model->getTable(), $centralOnly, true) && ! in_array($class, $guarded, true)) {
                $unguarded[] = $class.' ('.$model->getTable().')';
            }
        }

        $this->assertSame([], $unguarded, 'Central models missing from CentralModelsConnectionTest::centralModels().');
    }
}
