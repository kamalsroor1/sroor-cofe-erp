// @ts-check
// LOCAL smoke suite: real backend + throwaway sqlite DB + seeded demo tenant. Never a remote URL.
//
// Run from the repo root:   npx playwright test --config=e2e/smoke/smoke.config.js
// or from backend/:         npx playwright test --config=../e2e/smoke/smoke.config.js
//
// The web server is started (and stopped) by Playwright through serve-local-smoke.mjs.
import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { BASE_URL, assertLocalUrl, repoRoot } from './smoke-env.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));

assertLocalUrl(BASE_URL);

export default defineConfig({
    testDir: here,
    testMatch: /.*\.smoke\.spec\.js/,
    timeout: 90_000,
    expect: { timeout: 15_000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list']],
    outputDir: path.join(repoRoot, 'e2e', 'test-results', 'smoke'),
    globalTeardown: path.join(here, 'global-teardown.mjs'),
    use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 900 },
        baseURL: BASE_URL,
        locale: 'ar-EG',
        timezoneId: 'Africa/Cairo',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        storageState: { cookies: [], origins: [] },
    },
    webServer: {
        command: `node "${path.join(here, 'serve-local-smoke.mjs')}"`,
        url: `${BASE_URL}/up`,
        // A server already on this port would not be the throwaway one.
        reuseExistingServer: false,
        timeout: 240_000,
        stdout: 'ignore',
        stderr: 'pipe',
    },
});
