<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A tenant database name an operator may set (store tenant / update DB config):
 *  - starts with config('tenancy.database.prefix') and is a plain identifier
 *    ([A-Za-z0-9_], at most 64 chars, the MySQL limit);
 *  - is never the central database;
 *  - is not used by another tenant, neither as an explicit `tenancy_db_name` nor as the
 *    default name (prefix + tenant id) of a tenant without one.
 *
 * Messages are the standard `validation.regex|not_in|unique` translations.
 */
final class TenantDatabaseName implements ValidationRule
{
    private const MAX_LENGTH = 64;

    public function __construct(private readonly ?string $ignoreTenantId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.regex')->translate();

            return;
        }

        $prefix = (string) config('tenancy.database.prefix', '');

        if (strlen($value) > self::MAX_LENGTH
            || preg_match('/^'.preg_quote($prefix, '/').'[A-Za-z0-9_]+$/', $value) !== 1) {
            $fail('validation.regex')->translate();

            return;
        }

        if (strcasecmp($value, $this->centralDatabaseName()) === 0) {
            $fail('validation.not_in')->translate();

            return;
        }

        if ($this->takenByAnotherTenant($value, $prefix)) {
            $fail('validation.unique')->translate();
        }
    }

    private function takenByAnotherTenant(string $name, string $prefix): bool
    {
        // stancl stores the generated name WITH the driver suffix (".sqlite" on sqlite).
        $suffix = (string) config('tenancy.database.suffix', '');
        $candidates = array_values(array_unique([$name, $name.$suffix]));

        $explicit = Tenant::query()->whereIn('data->tenancy_db_name', $candidates);
        if ($this->ignoreTenantId !== null) {
            $explicit->whereKeyNot($this->ignoreTenantId);
        }

        if ($explicit->exists()) {
            return true;
        }

        // The default database of a tenant without a stored name: prefix + id (+ suffix).
        $id = substr($name, strlen($prefix));
        if ($id === '' || $id === $this->ignoreTenantId) {
            return false;
        }

        $owner = Tenant::query()->find($id);

        return $owner instanceof Tenant
            && ($owner->tenancy_db_name === null || $owner->tenancy_db_name === '' || in_array($owner->tenancy_db_name, $candidates, true));
    }

    private function centralDatabaseName(): string
    {
        $central = (string) config('tenancy.database.central_connection', config('database.default'));

        return basename((string) config("database.connections.{$central}.database", ''));
    }
}
