<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Actions\Tenants\PurgeTenantAction;
use App\Console\Commands\PurgeTenantCommand;
use App\Providers\TenancyServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\TestCase;

/**
 * OPS-9 (CTO Q-B13 / Q2): NO automatic purge, ever.
 *
 *  - only `tenants:purge` (PurgeTenantCommand) uses PurgeTenantAction; no job, listener,
 *    observer, controller or route reaches it;
 *  - `tenants:purge` is never scheduled and has no --force;
 *  - the only code dropping a tenant database is PurgeTenantAction and the provisioning
 *    job's cleanup of a database IT created (ProvisionTenantJob::failed());
 *  - stancl's DeleteDatabase job is wired to no tenancy event (OPS-11);
 *  - the purge ledger rows that make a purge auditable exist (audit events).
 */
final class NoAutomaticPurgeTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../../app';

    private const ROUTES_DIR = __DIR__.'/../../../routes';

    /** Files allowed to call ->deleteDatabase( on a tenant (relative to app/). */
    private const DATABASE_DROPPERS = [
        'Actions/Tenants/PurgeTenantAction.php',
        // Drops only a database the provisioning job itself created (failed provisioning).
        'Jobs/ProvisionTenantJob.php',
    ];

    public function test_only_the_purge_command_uses_the_purge_action(): void
    {
        $users = [];
        foreach ($this->phpFiles(self::APP_DIR) as $relative => $source) {
            if ($relative === 'Actions/Tenants/PurgeTenantAction.php') {
                continue;
            }
            if (preg_match('/\bPurgeTenantAction\b/', $source) === 1) {
                $users[] = $relative;
            }
        }

        $this->assertSame(['Console/Commands/PurgeTenantCommand.php'], $users);
    }

    public function test_no_route_job_or_listener_mentions_the_purge(): void
    {
        foreach ($this->phpFiles(self::ROUTES_DIR) as $relative => $source) {
            $this->assertDoesNotMatchRegularExpression('/tenants:purge|PurgeTenant/i', $source, "routes/{$relative} must not reach the purge.");
        }

        foreach ($this->phpFiles(self::APP_DIR) as $relative => $source) {
            if (! preg_match('#^(Jobs|Listeners|Observers|Http|Providers|Health)/#', $relative)) {
                continue;
            }
            $this->assertStringNotContainsString('tenants:purge', $source, "{$relative} must not run tenants:purge.");
            $this->assertStringNotContainsString('PurgeTenant', $source, "{$relative} must not reach the purge.");
        }
    }

    public function test_the_purge_command_is_never_scheduled(): void
    {
        $this->app->make(Kernel::class)->bootstrap();
        $schedule = $this->app->make(Schedule::class);

        foreach ($schedule->events() as $event) {
            $this->assertStringNotContainsString('tenants:purge', (string) $event->command, 'tenants:purge must never be scheduled.');
            $this->assertStringNotContainsString('PurgeTenant', (string) $event->description, 'No scheduled closure may purge.');
        }

        $this->assertStringNotContainsString('purge', strtolower(self::withoutComments((string) file_get_contents(self::ROUTES_DIR.'/console.php'))));
    }

    public function test_the_purge_command_has_no_force_option_and_requires_typed_confirmation(): void
    {
        $command = $this->app->make(PurgeTenantCommand::class);
        $definition = $command->getDefinition();

        $this->assertSame('tenants:purge', $command->getName());
        $this->assertFalse($definition->hasOption('force'), 'tenants:purge must not have --force.');
        $this->assertTrue($definition->hasOption('confirm'));
        $this->assertTrue($definition->hasOption('yes-i-understand'));
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertTrue($definition->hasArgument('tenant'));
    }

    public function test_only_the_purge_and_the_failed_provisioning_cleanup_drop_a_tenant_database(): void
    {
        $droppers = [];
        foreach ($this->phpFiles(self::APP_DIR) as $relative => $source) {
            if (preg_match('/->deleteDatabase\(/', $source) === 1) {
                $droppers[] = $relative;
            }
        }
        sort($droppers);

        $expected = self::DATABASE_DROPPERS;
        sort($expected);

        $this->assertSame($expected, $droppers);
        $this->assertTrue(class_exists(PurgeTenantAction::class));
    }

    public function test_no_tenancy_event_runs_delete_database(): void
    {
        $provider = new TenancyServiceProvider($this->app);

        foreach ($provider->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $this->assertNotContains(DeleteDatabase::class, $listener->jobs, "DeleteDatabase is wired to {$event}");
                }
            }
        }
    }

    /**
     * Code only: comments and docblocks are stripped (they may explain the purge).
     *
     * @return array<string, string> relative path (forward slashes) => source without comments
     */
    private function phpFiles(string $directory): array
    {
        $base = (string) realpath($directory);
        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = str_replace('\\', '/', substr((string) $file->getRealPath(), strlen($base) + 1));
            $files[$relative] = self::withoutComments((string) file_get_contents((string) $file->getRealPath()));
        }
        ksort($files);

        return $files;
    }

    private static function withoutComments(string $source): string
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        return $code;
    }
}
