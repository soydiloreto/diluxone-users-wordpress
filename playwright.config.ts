import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests against the wp-env DEV site (port 8892).
 *
 * The dev site mounts the repo root, so what runs here is the working tree,
 * not the built dist — and tests/e2e/mu-plugin/diluxone-e2e.php is mapped into
 * mu-plugins from .wp-env.json, which is what lets a test read the site's mail
 * and answer as a social network.
 *
 * Almost every spec is somebody who is NOT signed in: that is what signing in
 * means. So there is no shared storage state; the specs that need the
 * dashboard say so themselves with `test.use({ storageState: ADMIN_STATE })`.
 *
 * Override WP_BASE_URL / WP_USER / WP_PASS to point the suite elsewhere.
 */

export const ADMIN_STATE = 'build/e2e-admin.json';

export default defineConfig({
	testDir: './tests/e2e/specs',
	timeout: 60_000,
	expect: { timeout: 10_000 },
	// The suite drives one WordPress install and changes its settings, so two
	// tests at once would be two tests fighting over the same options table.
	fullyParallel: false,
	workers: 1,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [['github'], ['list']] : [['list']],
	outputDir: 'build/e2e-results',
	use: {
		baseURL: process.env.WP_BASE_URL ?? 'http://localhost:8892',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'off',
	},
	projects: [
		{
			name: 'setup',
			testDir: './tests/e2e',
			testMatch: /global\.setup\.ts/,
			teardown: 'teardown',
		},
		{
			name: 'teardown',
			testDir: './tests/e2e',
			testMatch: /global\.teardown\.ts/,
		},
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
			dependencies: ['setup'],
		},
	],
});
