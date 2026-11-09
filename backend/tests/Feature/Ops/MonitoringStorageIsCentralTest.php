<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Tests\TestCase;

/**
 * OPS-10: Pulse and Telescope persist into the CENTRAL database only. The default connection is
 * swapped to `tenant` by stancl while tenancy is initialised, so a null/default connection would
 * write monitoring tables into every shop database.
 */
final class MonitoringStorageIsCentralTest extends TestCase
{
    public function test_pulse_storage_is_pinned_to_the_central_connection(): void
    {
        $connection = config('pulse.storage.database.connection');

        $this->assertNotNull($connection, 'Pulse must not fall back to the (tenant-swappable) default connection.');
        $this->assertSame(config('tenancy.database.central_connection'), $connection);
        $this->assertNotSame('tenant', $connection);
    }

    public function test_telescope_storage_is_pinned_to_the_central_connection(): void
    {
        $this->assertSame(
            config('tenancy.database.central_connection'),
            config('telescope.storage.database.connection'),
        );
    }

    public function test_pulse_connection_survives_the_default_connection_swap(): void
    {
        $central = (string) config('tenancy.database.central_connection');
        config(['database.default' => 'tenant']);

        $this->assertSame($central, config('pulse.storage.database.connection'));
    }
}
