import { defineConfig, devices } from '@playwright/test';
const baseURL = process.env.E2E_BASE_URL || 'http://127.0.0.1:8087';
if (!['frontend', '127.0.0.1', 'localhost'].includes(new URL(baseURL).hostname)) {
  throw new Error('Live E2E may run only on the isolated local fixture.');
}
export default defineConfig({
  testDir: './e2e/live',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 45000,
  expect: { timeout: 10000 },
  forbidOnly: true,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'reports/e2e-live/html', open: 'never' }],
    ['json', { outputFile: 'reports/e2e-live/results.json' }]
  ],
  outputDir: 'reports/e2e-live/artifacts',
  use: { baseURL, trace: 'retain-on-failure', screenshot: 'only-on-failure', video: 'retain-on-failure' },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }]
});
