<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Exceptions\CentralConnectionNotConfiguredException;
use App\Models\Plan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * W1 hardening note 7: UsesCentralConnection never falls back to the default connection
 * (inside a tenant request that is the tenant DB). An empty central connection is a
 * configuration error that must fail loudly.
 */
final class UsesCentralConnectionTest extends TestCase
{
    public function test_model_uses_the_configured_central_connection(): void
    {
        config(['tenancy.database.central_connection' => 'central_probe']);

        $this->assertSame('central_probe', (new Plan)->getConnectionName());
    }

    public function test_configured_central_connection_wins_over_the_default_connection(): void
    {
        config([
            'tenancy.database.central_connection' => 'central_probe',
            'database.default' => 'tenant_probe',
        ]);

        $this->assertSame('central_probe', (new Plan)->getConnectionName());
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function emptyConnections(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'whitespace' => ['   '],
            'not a string' => [['sqlite']],
        ];
    }

    #[DataProvider('emptyConnections')]
    public function test_empty_central_connection_throws_instead_of_falling_back(mixed $value): void
    {
        config(['tenancy.database.central_connection' => $value]);

        $this->expectException(CentralConnectionNotConfiguredException::class);
        $this->expectExceptionMessage('tenancy.database.central_connection');
        $this->expectExceptionMessage(Plan::class);

        (new Plan)->getConnectionName();
    }
}
