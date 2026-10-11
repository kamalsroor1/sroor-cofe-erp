<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Enums\TenantProvisioningStatus;
use App\Services\Tenancy\ProvisionerMySQLDatabaseManager;
use App\Support\Tenancy\ProvisioningErrorCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * OPS-2: the provisioner's identifier handling (no database needed). The live GRANT
 * behaviour is proven on MySQL by Tests\Feature\Tenancy\ProvisionWithNonRootAccountTest.
 */
final class ProvisionerMySQLDatabaseManagerTest extends TestCase
{
    public function test_grant_target_escapes_the_mysql_wildcards(): void
    {
        // `_` is a one-character wildcard in a GRANT target (vps-runbook.md §12.2).
        $this->assertSame('tenant\\_shop\\_a', ProvisionerMySQLDatabaseManager::grantTarget('tenant_shop_a'));
        $this->assertSame('tenant\\_shop-a', ProvisionerMySQLDatabaseManager::grantTarget('tenant_shop-a'));
    }

    /** @return array<string, array{string}> */
    public static function unsafeNames(): array
    {
        return [
            'backtick' => ['tenant_a`; DROP DATABASE x; --'],
            'space' => ['tenant a'],
            'percent wildcard' => ['tenant_%'],
            'backslash' => ['tenant\\a'],
            'dot' => ['tenant.a'],
            'empty' => [''],
            'too long' => [str_repeat('a', 65)],
            'non ascii' => ['tenant_متجر'],
        ];
    }

    #[DataProvider('unsafeNames')]
    public function test_unsafe_database_names_are_refused(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProvisionerMySQLDatabaseManager::assertDatabaseName($name);
    }

    public function test_only_identifier_errors_are_permanent_and_values_fit_the_column(): void
    {
        foreach (ProvisioningErrorCode::cases() as $code) {
            $this->assertLessThanOrEqual(64, strlen($code->value));
            $this->assertSame(
                in_array($code, [ProvisioningErrorCode::DatabaseExists, ProvisioningErrorCode::InvalidDatabaseName], true),
                $code->isPermanent(),
                $code->value,
            );
        }

        $this->assertSame([TenantProvisioningStatus::Failed], array_values(array_filter(
            TenantProvisioningStatus::cases(),
            static fn (TenantProvisioningStatus $status): bool => $status->canRetry(),
        )));
    }
}
