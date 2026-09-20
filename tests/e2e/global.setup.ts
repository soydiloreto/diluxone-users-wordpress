import { test as setup, expect } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { Site } from './support/api';
import { BASELINE_FILE, PAGES_FILE } from './support/fixtures';
import { ADMIN_STATE } from '../../playwright.config';

/**
 * Puts the site into the state every spec assumes, and remembers what it was.
 *
 * The environment this runs in is somebody's development site, not a scratch
 * one: whatever is configured there is configured for a reason. So nothing is
 * wiped — the settings the suite depends on are written down, their old values
 * go into a file, and the teardown writes them back.
 */

/**
 * The starting point every spec is written against.
 *
 * A spec that needs something else changes it through the `options` fixture,
 * which puts it back when the test ends — so this is what each of them finds.
 */
const BASELINE = (pages: { login: number; register: number; account: number }) => ({
	diluxone_users_login_page: pages.login,
	diluxone_users_register_page: pages.register,
	diluxone_users_account_page: pages.account,

	diluxone_users_login_method: 'both',
	diluxone_users_wp_screens: 'auto',
	diluxone_users_lost_password: 'wp',
	diluxone_users_login_register: 1,
	diluxone_users_register_form: 0,
	diluxone_users_login_role: 'subscriber',
	diluxone_users_login_expiry: 15,
	// A throttle counted in minutes would make a suite that asks for several
	// links a suite that waits. It is one second, which still proves the key
	// exists without anybody watching a clock.
	diluxone_users_login_throttle: 1,
	// Every way in on the screen at once. Left unsaid this is 'auto', which
	// with a link, a password and the social buttons is three ways in and so
	// tabs — and then every spec that is about something else entirely fills
	// a box inside a closed tab and waits a minute for it to appear. The
	// arrangement is a subject of its own: login-ways.spec.ts and the picture
	// of the tabbed sign-in page ask for it by name, and they are the only
	// two that should.
	diluxone_users_login_layout: 'stack',
	diluxone_users_login_template: 'plain',
	diluxone_users_login_title: '',
	diluxone_users_login_intro: '',
	diluxone_users_login_legal: '',

	diluxone_users_2fa_mode: 'optional',
	diluxone_users_2fa_methods: ['totp', 'email'],
	diluxone_users_2fa_link: 'auto',
	diluxone_users_2fa_remember_days: 30,
	diluxone_users_2fa_scope: 'all',

	diluxone_users_sso_login: 1,
	diluxone_users_sso_register: 1,
	diluxone_users_sso_link_by_email: 1,
	diluxone_users_sso_verified_only: 0,
	diluxone_users_sso_scope: 'all',
	diluxone_users_sso_roles: [],

	diluxone_users_passkey_enabled: 0,
	diluxone_users_handle_enabled: 0,
	diluxone_users_handle_login: 0,

	// The fake social network is off until the SSO spec asks for it, so nobody
	// using this environment by hand finds a network called "Mock" on it.
	diluxone_e2e_sso: 0,
});

setup('seed the site and remember what it was', async ({ baseURL }) => {
	const site = await Site.open(baseURL!);

	const seeded = await site.seed();

	mkdirSync('build', { recursive: true });
	writeFileSync(PAGES_FILE, JSON.stringify(seeded.pages, null, 2));

	const previous = await site.setOptions(
		BASELINE({
			login: seeded.pages.login.id,
			register: seeded.pages.register.id,
			account: seeded.pages.account.id,
		}),
		{ flush: true, forgetTransients: true }
	);

	writeFileSync(BASELINE_FILE, JSON.stringify(previous, null, 2));

	await site.clearMail();

	// The sign-in page has to be the one the plugin resolves, or every spec is
	// looking at the wrong screen and saying so in nine different ways.
	expect(seeded.pages.login.url).toContain('e2e-login');
});

setup('keep an administrator session for the specs that need one', async ({ page, context }) => {
	const user = process.env.WP_USER ?? 'admin';
	const pass = process.env.WP_PASS ?? 'password';

	// The escape hatch, always: whatever a previous run left behind — including
	// "only a link, no passwords" — this is the door that stays open, and it is
	// the one an administrator would use in the same situation.
	//
	// By name and not by label: this site is in Spanish and the plugin ships
	// eight locales — the markup is the contract, the wording is a setting.
	await page.goto('/wp-login.php?diluxone-users-admin=1');

	const login = page.locator('input[name="log"]');

	await expect(login, 'wp-login.php has to draw its own form for the hatch').toBeVisible();
	await login.fill(user);
	await page.locator('input[name="pwd"]').fill(pass);

	await Promise.all([page.waitForURL(/wp-admin/), page.locator('#wp-submit').click()]);
	await context.storageState({ path: ADMIN_STATE });
});
