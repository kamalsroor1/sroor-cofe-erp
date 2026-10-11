<?php

declare(strict_types=1);

namespace App\DTOs\Tenants;

use InvalidArgumentException;

/**
 * Input of App\Actions\Tenants\PurgeTenantAction (OPS-9), built by `tenants:purge` once every
 * safety rail passed (eligibility, verified + restore-drilled backup, typed confirmation).
 *
 * $purgedBy: `central_user:<id>` or `cli:<os user>` (stored on tenants.purged_by).
 * $backupId / $backupSha256: the proven backup; null only when a resumed purge finds the
 * database already gone (nothing left to protect).
 */
final readonly class PurgeTenantDTO
{
    private const PURGED_BY_PATTERN = '/^(central_user:\d{1,20}|cli:[A-Za-z0-9._@-]{1,80})$/';

    public function __construct(
        public string $tenantId,
        public string $purgedBy,
        public ?int $backupId,
        public ?string $backupSha256,
    ) {
        if ($tenantId === '') {
            throw new InvalidArgumentException('A purge needs a tenant id.');
        }

        if (preg_match(self::PURGED_BY_PATTERN, $purgedBy) !== 1) {
            throw new InvalidArgumentException('purged_by must be central_user:<id> or cli:<os user>.');
        }
    }

    /**
     * @param  array{tenant_id: string, purged_by: string, backup_id?: int|string|null, backup_sha256?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $backupId = $data['backup_id'] ?? null;
        $sha = $data['backup_sha256'] ?? null;

        return new self(
            trim((string) $data['tenant_id']),
            trim((string) $data['purged_by']),
            $backupId === null || $backupId === '' ? null : (int) $backupId,
            $sha === null || $sha === '' ? null : (string) $sha,
        );
    }

    /**
     * @return array{tenant_id: string, purged_by: string, backup_id: int|null, backup_sha256: string|null}
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'purged_by' => $this->purgedBy,
            'backup_id' => $this->backupId,
            'backup_sha256' => $this->backupSha256,
        ];
    }

    /** `cli:<os user>` from the process owner, reduced to a safe identifier. */
    public static function cliActor(): string
    {
        $user = getenv('SUDO_USER') ?: getenv('USER') ?: getenv('USERNAME') ?: get_current_user();
        $user = (string) preg_replace('/[^A-Za-z0-9._@-]/', '', (string) $user);

        return 'cli:'.($user === '' ? 'unknown' : substr($user, 0, 80));
    }
}
