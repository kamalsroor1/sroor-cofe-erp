/**
 * Display helpers for a tenant's subscription status (App\Enums\TenantStatus) on the platform console.
 * Labels come from lang/{ar,en}/subscription.php -> statuses.*; colours are dark/light pairs.
 */

export const TENANT_STATUSES = Object.freeze([
    'trial',
    'active',
    'past_due',
    'read_only',
    'suspended',
    'cancelled',
    'archived',
]);

const BADGE_CLASSES = Object.freeze({
    active: 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-400',
    trial: 'bg-theme-light border-theme-border text-theme-primary',
    past_due: 'bg-amber-500/10 border-amber-500/30 text-amber-700 dark:text-amber-400',
    read_only: 'bg-orange-500/10 border-orange-500/30 text-orange-700 dark:text-orange-400',
    suspended: 'bg-rose-500/10 border-rose-500/30 text-rose-700 dark:text-rose-400',
    cancelled: 'bg-slate-500/10 border-slate-500/30 text-slate-700 dark:text-slate-300',
    archived: 'bg-zinc-500/15 border-zinc-500/40 text-zinc-700 dark:text-zinc-300',
});

const FALLBACK_BADGE_CLASS = 'bg-slate-500/10 border-slate-500/30 text-slate-600 dark:text-slate-400';

export const tenantStatusBadgeClass = (status) => BADGE_CLASSES[status] || FALLBACK_BADGE_CLASS;

/**
 * @param {string|null|undefined} status
 * @param {(key: string) => string} t
 * @returns {string} translated label; an unknown status is shown as its raw code
 */
export const tenantStatusLabel = (status, t) =>
    TENANT_STATUSES.includes(status) ? t(`subscription.statuses.${status}`) : String(status ?? '');

/** True when the tenant is archived but not purged (the only state the server can unarchive). */
export const canRestoreFromArchive = (tenant) =>
    tenant?.status === 'archived' && !tenant?.purged_at && !tenant?.archive?.purged_at;
