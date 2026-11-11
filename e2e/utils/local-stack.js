// LOCAL docker dev stack helpers (compose project `sroor-dev`, docs/07-operations/local-docker.md).
//
// Everything here talks to the containers on this machine through `docker compose exec`, never to a
// server. Secrets (test passwords) travel as environment variables of the exec call, never in argv.
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
export const REPO_ROOT = path.resolve(__dirname, '../..');
const COMPOSE_FILE = path.join(REPO_ROOT, 'compose.yaml');

const HTTP_PORT = process.env.SROOR_HTTP_PORT || '8000';

/** Hosts of the local stack. `sroor.test` is mapped to 127.0.0.1 inside Chromium (host-resolver-rules). */
export const LOCAL_HOSTS = Object.freeze({
    apex: `http://sroor.test:${HTTP_PORT}`,
    loopback: `http://localhost:${HTTP_PORT}`,
    admin: `http://admin.localhost:${HTTP_PORT}`,
    tenant: (slug) => `http://${slug}.localhost:${HTTP_PORT}`,
});

/** Chromium flag that resolves the platform apex to the local nginx without touching /etc/hosts. */
export const HOST_RESOLVER_ARGS = ['--host-resolver-rules=MAP sroor.test 127.0.0.1'];

const LOCAL_HOSTNAME = /^(localhost|127\.0\.0\.1|sroor\.test|[a-z0-9-]+\.localhost)$/u;

/** Throws unless every URL points at the local stack. Called before anything is sent. */
export function assertLocalUrl(url) {
    const { hostname } = new URL(url);
    if (!LOCAL_HOSTNAME.test(hostname)) {
        throw new Error(`refusing to run the SaaS E2E flow against a non-local host: ${hostname}`);
    }
}

function composeCommand() {
    if (process.env.SROOR_COMPOSE) return process.env.SROOR_COMPOSE.split(/\s+/u);
    return ['docker', 'compose'];
}

/**
 * Runs `php artisan tinker --execute=<php>` in the app container and returns stdout.
 * `env` values are forwarded with `-e NAME` (read in PHP through getenv()), so they never show
 * up in the process list or in this repo.
 */
export function tinker(php, env = {}) {
    const [bin, ...prefix] = composeCommand();
    const envFlags = Object.keys(env).flatMap((name) => ['-e', name]);
    return execFileSync(
        bin,
        [...prefix, '-f', COMPOSE_FILE, 'exec', '-T', ...envFlags, 'app', 'php', 'artisan', 'tinker', `--execute=${php}`],
        {
            cwd: REPO_ROOT,
            env: { ...process.env, ...env },
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'pipe'],
            timeout: 120_000,
        }
    );
}

/** Reads the last `KEY=value` line a tinker snippet printed. */
export function tinkerValue(output, key) {
    const line = output
        .split('\n')
        .reverse()
        .find((row) => row.startsWith(`${key}=`));
    if (!line) throw new Error(`tinker did not print ${key}=...; output:\n${output}`);
    return line.slice(key.length + 1).trim();
}

/** Gateway address of the compose network = the client IP the app sees for host requests. */
export function composeGatewayIp() {
    const [bin] = composeCommand();
    try {
        return execFileSync(
            bin,
            ['network', 'inspect', 'sroor-dev_default', '-f', '{{(index .IPAM.Config 0).Gateway}}'],
            { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 30_000 }
        ).trim();
    } catch {
        return '';
    }
}
