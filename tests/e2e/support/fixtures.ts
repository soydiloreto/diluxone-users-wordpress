import { test as base, expect, Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { OptionBag, SeedPages, Site } from './api';

/**
 * What every spec gets handed.
 *
 *   site     the REST side door — the mailbox, the settings, the accounts
 *   pages    where the three shortcodes live, read once by the global setup
 *   options  settings that are put back the moment the test ends
 *
 * The third one is the rule the whole suite rests on: a test says what it
 * needs changed, and the change is undone whether the test passed, failed or
 * threw halfway through. Without it the second run of a suite is a different
 * suite.
 */

export const BASELINE_FILE = 'build/e2e-baseline.json';
export const PAGES_FILE = 'build/e2e-pages.json';

export interface Options {
	/** Writes settings now and schedules the old values to be written back. */
	set(values: Record<string, unknown>): Promise<void>;

	/**
	 * Writes nothing, but promises to put these back.
	 *
	 * For the tests that change a setting the way a person does — by pressing
	 * Save on a dashboard screen. Nothing here can intercept that, so what
	 * they were is written down beforehand instead.
	 */
	keep(keys: string[]): Promise<void>;
}

export const test = base.extend<{
	site: Site;
	pages: SeedPages['pages'];
	options: Options;
	guest: Page;
	freshCounters: void;
}>({
	site: async ({ request }, use) => {
		await use(new Site(request));
	},

	/**
	 * Every test starts with the plugin's counters at zero.
	 *
	 * The throttles are real — six accounts an hour from one machine, one link
	 * a minute per address — and the whole suite comes from one machine. Without
	 * this the tenth test in a run is testing the rate limiter instead of what
	 * it says it tests, and which test that is depends on the order.
	 */
	freshCounters: [
		async ({ site }, use) => {
			await site.setOptions({}, { forgetTransients: true });
			await use();
		},
		{ auto: true },
	],

	/**
	 * A second browser with nobody signed in.
	 *
	 * The sign-in and registration shortcodes draw nothing for somebody who is
	 * already in — which is right, and which means a spec that saves a setting
	 * from the dashboard cannot then look at the public page in the same
	 * browser. This is that other browser.
	 */
	guest: async ({ browser, baseURL }, use) => {
		const context = await browser.newContext({ baseURL, storageState: undefined });
		const page = await context.newPage();

		await use(page);
		await context.close();
	},

	pages: async ({}, use) => {
		await use(JSON.parse(readFileSync(PAGES_FILE, 'utf8')) as SeedPages['pages']);
	},

	options: async ({ site }, use) => {
		// Only the FIRST value seen for a key is kept: two changes to the same
		// setting inside one test must restore what was there before the first,
		// not what the first one left.
		const original: OptionBag = {};

		const remember = (values: OptionBag) => {
			for (const [key, was] of Object.entries(values)) {
				if (!(key in original)) {
					original[key] = was;
				}
			}
		};

		await use({
			async set(values: Record<string, unknown>) {
				remember(await site.setOptions(values));
			},

			async keep(keys: string[]) {
				remember(await site.getOptions(keys));
			},
		});

		if (Object.keys(original).length > 0) {
			await site.setOptions(original, { forgetTransients: true });
		}
	},
});

export { expect };

/** The address of the sign-in page, as the plugin resolves it. */
export function loginUrl(pages: SeedPages['pages']): string {
	return pages.login.url;
}

/**
 * Whether this browser is signed in, asked of the site rather than of a cookie.
 *
 * `/wp-admin/profile.php` answers with the profile to somebody with a session
 * and sends everybody else to wp-login.php, which is the shortest honest
 * question there is. It is used instead of looking for the admin bar, which a
 * theme can hide and a setting in this very plugin can take away.
 */
export async function signedInAs(page: Page): Promise<string | null> {
	const response = await page.request.get('/wp-admin/profile.php');

	// The PATH and not the whole address: with no session WordPress sends this
	// to wp-login.php with `?redirect_to=…/profile.php`, so anything looking
	// for "profile.php" in the URL finds it and reads a stranger as signed in.
	if (!new URL(response.url()).pathname.endsWith('/wp-admin/profile.php')) {
		return null;
	}

	const html = await response.text();
	const found = html.match(/name="email"[^>]*value="([^"]*)"/);

	return found ? found[1] : '';
}

/** Asserts a session, and says whose. */
export async function expectSignedIn(page: Page, email?: string): Promise<void> {
	const who = await signedInAs(page);

	expect(who, 'expected a session and there is none').not.toBeNull();

	if (email !== undefined) {
		expect(who?.toLowerCase()).toBe(email.toLowerCase());
	}
}

/** Asserts no session at all. */
export async function expectSignedOut(page: Page): Promise<void> {
	expect(await signedInAs(page), 'expected no session and there is one').toBeNull();
}

/** The `diluxone-users` state a plugin screen carries in its address. */
export function stateOf(url: string): string {
	return new URL(url).searchParams.get('diluxone-users') ?? '';
}
