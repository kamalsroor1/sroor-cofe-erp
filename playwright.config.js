// @ts-check
import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright Configuration for E2E Visual Testing & Crawling System
 */
export default defineConfig({
  testDir: './e2e',
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
    baseURL: process.env.APP_URL || 'http://localhost:8000',
    trace: 'on-first-retry',
    screenshot: 'off', // We manage explicit fullPage screenshots in our crawlers & flows
    video: 'off',
    bypassCSP: true,
    locale: 'ar-EG',
    timezoneId: 'Africa/Cairo',
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
        storageState: 'e2e/.auth/user.json',
      },
      dependencies: ['auth-setup'],
      testMatch: /crawlers\/.*\.spec\.js|flows\/.*\.spec\.js/,
    },

    // 3. Mobile Viewport (390x844)
    {
      name: 'mobile',
      use: {
        ...devices['iPhone 14 Pro'],
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true,
        storageState: 'e2e/.auth/user.json',
      },
      dependencies: ['auth-setup'],
      testMatch: /crawlers\/.*\.spec\.js|flows\/.*\.spec\.js/,
    },
  ],
});
