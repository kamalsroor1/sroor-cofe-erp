#!/usr/bin/env bash
# LOCAL DEVELOPMENT ONLY - entry point of the docker dev stack (compose.yaml).
# Never connects to a server; every command talks to the local compose project `sroor-dev`.
#
#   docker/bin/dev.sh init         backend/.env.docker (+ random dev passwords), image build, APP_KEY
#   docker/bin/dev.sh up           start everything (app, web, horizon, scheduler, vite, mysql, redis)
#   docker/bin/dev.sh setup        migrate (migrator) + central grants + tenants:migrate + db:seed
#   docker/bin/dev.sh migrate      same without the seeders
#   docker/bin/dev.sh test [args]  php artisan test inside the app container (sqlite :memory:)
#   docker/bin/dev.sh artisan ...  php artisan ... inside the app container
#   docker/bin/dev.sh shell        bash inside the app container
#   docker/bin/dev.sh db-accounts  re-apply the MySQL accounts/grants from backend/.env.docker
#   docker/bin/dev.sh ps | logs [svc] | down
#   docker/bin/dev.sh destroy      down + DELETE the mysql/redis volumes (asks first)
#
# Runtime: `docker compose` if available, else `podman compose`. Override with
# SROOR_COMPOSE="podman compose" (or any compose command).
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_EXAMPLE="$REPO/backend/.env.docker.example"
ENV_DOCKER="$REPO/backend/.env.docker"
cd "$REPO"

say() { printf '[dev.sh] %s\n' "$*"; }
die() {
    printf '[dev.sh] ERROR %s\n' "$*" >&2
    exit 1
}

# Files written into the bind-mounted repo belong to the host user.
SROOR_UID="${SROOR_UID:-$(id -u)}"
SROOR_GID="${SROOR_GID:-$(id -g)}"
export SROOR_UID SROOR_GID

if [[ -n "${SROOR_COMPOSE:-}" ]]; then
    read -r -a COMPOSE <<<"$SROOR_COMPOSE"
elif docker compose version >/dev/null 2>&1; then
    COMPOSE=(docker compose)
elif podman compose version >/dev/null 2>&1; then
    COMPOSE=(podman compose)
else
    die "neither 'docker compose' nor 'podman compose' is available"
fi

# Rootless Podman maps container UIDs to sub-UIDs; keep-id makes the image user (= host UID)
# own the files it writes into the repo. Docker keeps the runtime default.
if [[ "${COMPOSE[0]}" == "podman" && -z "${SROOR_USERNS_MODE+x}" ]]; then
    export SROOR_USERNS_MODE=keep-id
fi

compose() { "${COMPOSE[@]}" -f "$REPO/compose.yaml" "$@"; }

need_env() {
    [[ -f "$ENV_DOCKER" ]] || die "backend/.env.docker is missing: run docker/bin/dev.sh init"
}

random_value() {
    # 32 chars of [A-Za-z0-9]: the scripts/ops password rule (24+ alphanumerics).
    local value=""
    while [[ ${#value} -lt 32 ]]; do
        value+="$(head -c 256 /dev/urandom | tr -dc 'A-Za-z0-9')"
    done
    printf '%s' "${value:0:32}"
}

# Fill every EMPTY password key of backend/.env.docker. Existing values are never changed
# and no value is ever printed.
fill_passwords() {
    local key filled=0 tmp
    for key in SROOR_DEV_MYSQL_ROOT_PASSWORD DB_PASSWORD DB_MIGRATOR_PASSWORD DB_AUDIT_PRUNER_PASSWORD \
        DB_PROVISIONER_PASSWORD DB_BACKUP_PASSWORD REDIS_PASSWORD; do
        if grep -qE "^${key}=$" "$ENV_DOCKER"; then
            tmp="$(mktemp "$ENV_DOCKER.XXXXXX")"
            awk -v k="$key" -v v="$(random_value)" 'BEGIN { FS = OFS = "=" } $0 == k "=" { print k "=" v; next } { print }' \
                "$ENV_DOCKER" >"$tmp"
            chmod 600 "$tmp"
            mv "$tmp" "$ENV_DOCKER"
            filled=$((filled + 1))
        fi
    done
    say "backend/.env.docker: $filled empty password(s) filled with random dev values"
}

cmd_init() {
    if [[ -f "$ENV_DOCKER" ]]; then
        say "backend/.env.docker exists: kept as is"
    else
        cp "$ENV_EXAMPLE" "$ENV_DOCKER"
        chmod 600 "$ENV_DOCKER"
        say "backend/.env.docker created from backend/.env.docker.example"
    fi
    fill_passwords

    # The containers mount .env.docker over backend/.env, so that path must exist on the
    # host. An existing backend/.env is never touched; a missing one becomes a 1-line placeholder
    # (otherwise the container runtime would create it as an empty, root-owned file).
    if [[ ! -e "$REPO/backend/.env" ]]; then
        printf '# placeholder created by docker/bin/dev.sh: the docker stack reads backend/.env.docker\n' >"$REPO/backend/.env"
        say "backend/.env did not exist: created a comment-only placeholder (mount point)"
    fi

    say "building the PHP 8.5 image (uid $SROOR_UID, gid $SROOR_GID)"
    compose build app

    if grep -qE '^APP_KEY=$' "$ENV_DOCKER"; then
        # key:generate needs no database: one-off container without dependencies.
        compose run --rm --no-deps app php artisan key:generate --force --no-interaction >/dev/null
        say "APP_KEY generated in backend/.env.docker"
    fi
    say "next: docker/bin/dev.sh up && docker/bin/dev.sh setup"
}

cmd_up() {
    need_env
    compose up -d "$@"
    say "web  http://localhost:${SROOR_HTTP_PORT:-8000}   tenant http://demo.localhost:${SROOR_HTTP_PORT:-8000}"
    say "vite http://localhost:5173 (HMR)"
}

app_exec() {
    need_env
    compose exec app "$@"
}

cmd_setup() {
    # Optional seed-time values from YOUR shell (never stored): SEED_DEMO_TENANT_PASSWORD,
    # SEED_SUPER_ADMIN_PASSWORD, SEED_DEMO_TENANT_SLUG. Unset = the seeders generate a password
    # and print it once. `-e NAME` forwards the value through the environment, not argv.
    local pass=() var
    for var in SEED_DEMO_TENANT_PASSWORD SEED_SUPER_ADMIN_PASSWORD SEED_DEMO_TENANT_SLUG; do
        [[ -n "${!var:-}" ]] && pass+=(-e "$var")
    done
    need_env
    compose exec "${pass[@]}" app bash /srv/sroor/docker/php/bin/sroor-dev-migrate --seed
}

cmd_migrate() {
    app_exec bash /srv/sroor/docker/php/bin/sroor-dev-migrate
}

cmd_db_accounts() {
    need_env
    compose exec mysql bash /docker-entrypoint-initdb.d/10-sroor-accounts.sh
    say "accounts re-applied; restoring the per-table central grants"
    cmd_migrate
}

cmd_destroy() {
    need_env
    read -r -p "Delete the sroor-dev MySQL + Redis volumes (all local dev data)? type DESTROY: " answer
    [[ "$answer" == "DESTROY" ]] || die "aborted"
    compose down -v
}

case "${1:-}" in
    init) cmd_init ;;
    up)
        shift
        cmd_up "$@"
        ;;
    setup) cmd_setup ;;
    migrate) cmd_migrate ;;
    test)
        shift
        app_exec php artisan test "$@"
        ;;
    artisan)
        shift
        app_exec php artisan "$@"
        ;;
    shell) app_exec bash ;;
    db-accounts) cmd_db_accounts ;;
    ps) compose ps ;;
    logs)
        shift
        compose logs -f --tail=100 "$@"
        ;;
    down) compose down ;;
    destroy) cmd_destroy ;;
    *)
        sed -n '2,17p' "${BASH_SOURCE[0]}"
        exit 2
        ;;
esac
