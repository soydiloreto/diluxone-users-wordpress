import { test, expect, expectSignedIn, expectSignedOut } from '../support/fixtures';
import { freshEmail, linkIn, waitForMail } from '../support/api';
import {
	adminUrl,
	challengeScreen,
	linkForm,
	passwordForm,
	registerScreen,
	savePanel,
	signInWithPassword,
	ssoButton,
} from '../support/ui';
import { ADMIN_STATE } from '../../../playwright.config';

/**
 * What the dashboard saves, and what the site does about it.
 *
 * Two different questions, and the second is the one that finds bugs. A
 * setting that saves and is then read from somewhere else — or read with a
 * different default, or sanitised on the way out — looks perfect on the
 * settings screen and does nothing on the page. So each of these presses Save
 * like a person and then goes and looks at the public page.
 *
 * The first block is the cheaper guard: every tab of every screen renders
 * without a PHP notice and without WordPress's footer riding up into the
 * middle of the layout. That one catches a stray </div> the day it appears.
 *
 * Covers T-ADM-06.
 */

test.use({ storageState: ADMIN_STATE });

const SCREENS: Record<string, string[]> = {
	'diluxone-users': [],
	'diluxone-users-login': ['summary', 'page', 'ways', 'register'],
	'diluxone-users-security': ['2fa', 'passkeys', 'sessions', 'proxy'],
	'diluxone-users-social': ['providers', 'general'],
	'diluxone-users-account': ['summary', 'page', 'sections', 'handle', 'dashboard'],
	'diluxone-users-fields': ['list', 'usage'],
	'diluxone-users-design': ['brand', 'login', 'register', 'account', 'social', 'photo', 'wp'],
	'diluxone-users-notices': ['summary', 'rules', 'link'],
	'diluxone-users-status': ['status', 'tools', 'lockout'],
};

test.describe('Every settings screen renders', () => {
	for (const [screen, tabs] of Object.entries(SCREENS)) {
		for (const tab of tabs.length > 0 ? tabs : ['']) {
			test(`${screen}${tab ? ` › ${tab}` : ''}`, async ({ page }) => {
				const problems: string[] = [];

				page.on('pageerror', (error) => problems.push(`JS: ${error.message}`));
				page.on('console', (message) => {
					if (message.type() === 'error') {
						problems.push(`console: ${message.text()}`);
					}
				});

				const response = await page.goto(adminUrl(screen, tab || undefined));

				expect(response?.status()).toBe(200);

				const body = await page.locator('body').innerText();

				expect(body).not.toMatch(/Fatal error|Warning:|Notice:|Deprecated:/);
				expect(body).not.toMatch(/critical error|error crítico/i);

				// The footer sits below the content, not inside it: a stray
				// closing tag floats it up next to the cards, and nothing
				// short of a browser notices.
				const wrap = page.locator('.wrap').first();
				const footer = page.locator('#wpfooter');
				const wrapBox = await wrap.boundingBox();
				const footerBox = await footer.boundingBox();

				expect(wrapBox && footerBox).toBeTruthy();
				expect(footerBox!.y).toBeGreaterThanOrEqual(wrapBox!.y + wrapBox!.height - 2);

				expect(problems, 'browser errors').toEqual([]);
			});
		}
	}
});

test.describe('Saving a tab changes the public page', () => {
	test('Access › Ways in: “only the link” takes the password form off the sign-in page', async ({
		page,
		guest,
		pages,
		options,
	}) => {
		await options.keep(['diluxone_users_login_method']);

		await page.goto(adminUrl('diluxone-users-login', 'ways'));
		await page.locator('input[name="diluxone_users_login_method"][value="link"]').check();
		await savePanel(page);

		await guest.goto(pages.login.url);
		await expect(passwordForm(guest)).toHaveCount(0);
		await expect(linkForm(guest)).toBeVisible();

		// And back the other way, so the setting is a switch and not a trap.
		await page.goto(adminUrl('diluxone-users-login', 'ways'));
		await page.locator('input[name="diluxone_users_login_method"][value="both"]').check();
		await savePanel(page);

		await guest.goto(pages.login.url);
		await expect(passwordForm(guest)).toBeVisible();
	});

	test('Access › Ways in: turning the social buttons off takes them off the form', async ({
		page,
		guest,
		pages,
		options,
	}) => {
		await options.set({
			diluxone_e2e_sso: 1,
			diluxone_users_sso: { mock: { active: 1, id: 'a', secret: 'b', tested: 1 } },
		});
		await options.keep(['diluxone_users_sso_login']);

		await guest.goto(pages.login.url);
		await expect(ssoButton(guest, 'mock')).toBeVisible();

		await page.goto(adminUrl('diluxone-users-login', 'ways'));
		await page.locator('input[name="diluxone_users_sso_login"]').uncheck();
		await savePanel(page);

		await guest.goto(pages.login.url);
		await expect(ssoButton(guest, 'mock')).toHaveCount(0);
	});

	test('Access › Ways in: the number of minutes is the number the e-mail says', async ({
		page,
		guest,
		site,
		pages,
		options,
	}) => {
		await options.keep(['diluxone_users_login_expiry', 'diluxone_users_login_throttle']);

		await page.goto(adminUrl('diluxone-users-login', 'ways'));
		await page.locator('input[name="diluxone_users_login_expiry"]').fill('9');
		await page.locator('input[name="diluxone_users_login_throttle"]').fill('1');
		await savePanel(page);

		const email = freshEmail('expiry');
		await site.makeUser({ email });

		await guest.goto(pages.login.url);
		await guest.locator('input[name="diluxone_users_email"]').fill(email);
		await guest.locator('form.diluxone-users-form button[type="submit"]').click();
		await expect(guest.locator('.diluxone-users-login__email')).toContainText(email);

		// The screen and the e-mail quote the same setting, and they used to be
		// able to disagree.
		await expect(guest.locator('.diluxone-users-note').first()).toContainText('9');

		const mail = await waitForMail(site, email);
		expect(mail.body).toMatch(/\b9\b/);
	});

	test('Access › Registration: closing it closes the form on the registration page', async ({
		page,
		guest,
		pages,
		options,
	}) => {
		await options.keep([
			'diluxone_users_login_register',
			'diluxone_users_register_form',
			'diluxone_users_register_page',
			'diluxone_users_rewrite_version',
		]);

		await page.goto(adminUrl('diluxone-users-login', 'register'));
		await page.locator('input[name="diluxone_users_register_form"]').check();
		await page.locator('select[name="diluxone_users_register_page"]').selectOption(String(pages.register.id));
		await page.locator('input[name="diluxone_users_login_register"]').check();
		await savePanel(page);

		await guest.goto(pages.register.url);
		await expect(guest.locator('input[name="diluxone_users_email"]')).toBeVisible();

		// Now close both doors and the same page says so instead.
		await page.goto(adminUrl('diluxone-users-login', 'register'));
		await page.locator('input[name="diluxone_users_register_form"]').uncheck();
		await page.locator('input[name="diluxone_users_login_register"]').uncheck();
		await savePanel(page);

		await guest.goto(pages.register.url);
		await expect(guest.locator('input[name="diluxone_users_email"]')).toHaveCount(0);
		await expect(registerScreen(guest).locator('a[href*="e2e-login"]')).toBeVisible();
	});

	test('Design › The sign-in page: the frame and the legal line come out on the page', async ({
		page,
		guest,
		pages,
		options,
	}) => {
		await options.keep([
			'diluxone_users_login_template',
			'diluxone_users_login_legal',
			'diluxone_users_login_title',
		]);

		await page.goto(adminUrl('diluxone-users-design', 'login'));
		await page.locator('input[name="diluxone_users_login_template"][value="card"]').check();
		await page.locator('input[name="diluxone_users_login_title"]').fill('Entrá al club');

		// M-10: the legal line is the one field allowed to carry a link,
		// because the terms and the privacy policy are pages. A sanitiser that
		// strips it makes the setting useless without saying so.
		await page
			.locator('[name="diluxone_users_login_legal"]')
			.fill('Al entrar aceptás los <a href="/terms/">términos</a>.');
		await savePanel(page);

		await guest.goto(pages.login.url);

		await expect(guest.locator('.diluxone-users-login-frame--card')).toBeVisible();
		await expect(guest.locator('.diluxone-users-login__title')).toContainText('Entrá al club');
		await expect(guest.locator('.diluxone-users-login__legal a[href="/terms/"]')).toBeVisible();
	});

	test('Security › Two-step: “required” asks everybody for a code', async ({
		page,
		guest,
		site,
		pages,
		options,
	}) => {
		await options.keep(['diluxone_users_2fa_mode', 'diluxone_users_2fa_methods']);

		await page.goto(adminUrl('diluxone-users-security', '2fa'));
		await page.locator('input[name="diluxone_users_2fa_mode"][value="required"]').check();
		await page.locator('input[name="diluxone_users_2fa_methods[]"][value="email"]').check();
		await savePanel(page);

		const email = freshEmail('adm2fa');
		const password = 'e2e-Admin-2fa-1!';
		await site.makeUser({ email, password });

		await guest.goto(pages.login.url);
		await signInWithPassword(guest, email, password);

		await expect(challengeScreen(guest)).toBeVisible();
		await expectSignedOut(guest);
	});

	test('Account area › Sections: a section turned off leaves the menu', async ({
		page,
		guest,
		site,
		pages,
		options,
	}) => {
		await options.keep(['diluxone_users_account_sections']);

		const email = freshEmail('sections');
		const password = 'e2e-Sections-1!';
		await site.makeUser({ email, password });

		await guest.goto(pages.login.url);
		await signInWithPassword(guest, email, password);
		await expectSignedIn(guest, email);

		await guest.goto(pages.account.url);
		await expect(guest.locator('a[href*="/privacy"]').first()).toBeVisible();

		// The switch on the section's own card in the dashboard.
		await page.goto(adminUrl('diluxone-users-account', 'sections') + '&section=privacy');

		const toggle = page.locator('a.diluxone-users-toggle.is-on');
		await expect(toggle).toBeVisible();
		await Promise.all([page.waitForLoadState('domcontentloaded'), toggle.click()]);
		await expect(page.locator('a.diluxone-users-toggle.is-on')).toHaveCount(0);

		await guest.goto(pages.account.url);
		await expect(guest.locator('a[href*="/privacy"]')).toHaveCount(0);
	});

	test('Notifications › The sign-in e-mail: the site’s own words reach the inbox', async ({
		page,
		guest,
		site,
		pages,
		options,
	}) => {
		await options.keep([
			'diluxone_users_login_subject',
			'diluxone_users_login_body',
			'diluxone_users_login_throttle',
		]);

		await page.goto(adminUrl('diluxone-users-notices', 'link'));
		await page.locator('input[name="diluxone_users_login_subject"]').fill('Tu entrada al club');
		await page.locator('[name="diluxone_users_login_body"]').fill('Entrá: {link}');
		await savePanel(page);

		const email = freshEmail('words');
		await site.makeUser({ email });

		await guest.goto(pages.login.url);
		await guest.locator('input[name="diluxone_users_email"]').fill(email);
		await guest.locator('form.diluxone-users-form button[type="submit"]').click();
		await expect(guest.locator('.diluxone-users-login__email')).toContainText(email);

		const mail = await waitForMail(site, email);

		expect(mail.subject).toBe('Tu entrada al club');
		expect(mail.body.startsWith('Entrá: http')).toBe(true);

		// And the link in it still works, which is the part a wording setting
		// is most likely to break.
		await guest.goto(linkIn(mail));
		await expectSignedIn(guest, email);
	});
});
