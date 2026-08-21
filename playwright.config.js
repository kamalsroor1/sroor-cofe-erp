// @ts-check
import { defineConfig, devices } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname);
const authStorageFile = path.resolve(rootDir, 'e2e/.auth/user.json');

/**
 * Playwright Configuration for E2E Visual Testing & Crawling System
 */
export default defineConfig({
  testDir: path.resolve(rootDir, 'e2e'),
  timeout: 45000,
  expect: {
    timeout: 8000,
  },
  fullyParallel: false,
  retries: 0,
  workers: 1,
  reporter: [
    ['list'],
  ],
  use: {
    baseURL: process.env.APP_URL || 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
    screenshot: 'off',
    video: 'off',
    bypassCSP: true,
    locale: 'ar-EG',
    timezoneId: 'Africa/Cairo',
  },

  webServer: {
    command: 'php backend/artisan serve --host=127.0.0.1 --port=8000',
    url: 'http://127.0.0.1:8000',
    reuseExistingServer: true,
    timeout: 30000,
    cwd: rootDir,
  },

  projects: [
    // 1. Authentication Setup
    {
      name: 'auth-setup',
      testMatch: /login\.setup\.js/,
    },

    // 2. Desktop Viewport (1920x1080)
    {
      name: 'desktop',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1920, height: 1080 },
        storageState: authStorageFile,
      },
      dependencies: ['auth-setup'],
      testMatch: /crawlers\/.*\.spec\.js|flows\/.*\.spec\.js/,
    },

    // 3. Mobile Viewport (390x844 Pixel 7 / Chromium)
    {
      name: 'mobile',
      use: {
        ...devices['Pixel 7'],
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true,
        storageState: authStorageFile,
      },
      dependencies: ['auth-setup'],
      testMatch: /crawlers\/.*\.spec\.js|flows\/.*\.spec\.js/,
    },
  ],
});
