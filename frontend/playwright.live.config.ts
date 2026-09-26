import { readdirSync, readFileSync } from 'node:fs';
import { defineConfig, devices } from '@playwright/test';
const baseURL = process.env.E2E_BASE_URL || 'http://127.0.0.1:8087';
if (!['frontend', '127.0.0.1', 'localhost'].includes(new URL(baseURL).hostname)) {
  throw new Error('Live E2E may run only on the isolated local fixture.');
}
// Independent groups: `make test-e2e` gives every group its own fresh stand; files inside a group keep the name order.
// On a single stand the groups run one after another, which repeats the former order of all files.
const liveDir = new URL('./e2e/live/', import.meta.url);
const groups = JSON.parse(readFileSync(new URL('groups.json', liveDir), 'utf8')) as Record<string, string[]>;
const assigned = Object.values(groups).flat();
const specs = readdirSync(liveDir)
  .filter((file) => file.endsWith('.spec.ts'))
  .map((file) => file.slice(0, -'.spec.ts'.length));
const misplaced = [...new Set([...specs, ...assigned])].filter(
  (spec) => !specs.includes(spec) || assigned.filter((name) => name === spec).length !== 1
);
if (misplaced.length) {
  throw new Error('Every live spec must belong to exactly one group in e2e/live/groups.json: ' + misplaced.join(', '));
}
export default defineConfig({
  testDir: './e2e/live',
  // The #33/#34 media bench uploads a heavy batch and runs only on request.
  testIgnore: process.env.E2E_MEDIA_BENCH ? [] : ['**/zz-media-bench.spec.ts'],
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
  use: {
    baseURL,
    // A returning visitor: the in-flow cookie notice is already closed, so it does not shift the pages under test.
    // legal.spec.ts opens a clean browser and checks the notice itself.
    storageState: {
      cookies: [],
      origins: [{ origin: new URL(baseURL).origin, localStorage: [{ name: 'morefoto:cookie-notice:v1', value: 'e2e' }] }]
    },
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure'
  },
  projects: Object.entries(groups).map(([name, files]) => ({
    name,
    testMatch: files.map((file) => `**/${file}.spec.ts`),
    use: { ...devices['Desktop Chrome'] }
  }))
});
