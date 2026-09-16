import { Locator, Page, expect } from '@playwright/test';

/**
 * Where things are on the screen, said once.
 *
 * Everything here points at a name, an id or a class and never at a sentence.
 * The site this runs against is in Spanish, the plugin ships eight locales,
 * and a suite that looks for "Sign in" passes on one machine and fails on the
 * next. The markup is the contract; the wording is a setting.
 *
 * Where a test does have to prove that a particular message appeared, it
 * checks the class the template gives that message — `--error`, `--ok` — and
 * the `diluxone-users` state in the address, which is the plugin's own name
 * for what happened and does not translate.
 */

/* ── The sign-in page the plugin draws ─────────────────────────────── */

/** The "send me a link" form. */
export function linkForm(page: Page): Locator {
	return page.locator('form.diluxone-users-form').filter({ has: page.locator('input[name="diluxone_users_email"]') });
}

export function emailField(page: Page): Locator {
	return page.locator('input[name="diluxone_users_email"]');
}

/** WordPress's own password form, which the plugin's page draws inside itself. */
export function passwordForm(page: Page): Locator {
	return page.locator('form#loginform');
}

export function userField(page: Page): Locator {
	return page.locator('input[name="log"]');
}

export function passField(page: Page): Locator {
	return page.locator('input[name="pwd"]');
}

/** The screen that says a link is on its way. */
export function sentScreen(page: Page): Locator {
	return page.locator('.diluxone-users-login__email');
}

/** The second-step screen. */
export function challengeScreen(page: Page): Locator {
	return page.locator('.diluxone-users-login--2fa');
}

export function challengeCode(page: Page): Locator {
	return page.locator('input[name="diluxone_users_2fa_code"]');
}

/** The "choose a new password" screen the plugin draws on its own page. */
export function resetScreen(page: Page): Locator {
	return page.locator('.diluxone-users-login--reset');
}

/** The registration form. */
export function registerForm(page: Page): Locator {
	return page.locator('form.diluxone-users-form').filter({ has: page.locator('input[name="diluxone_users_email"]') });
}

export function registerScreen(page: Page): Locator {
	return page.locator('.diluxone-users-register');
}

/** A message the plugin put on the screen, by what kind it is. */
export function notice(page: Page, kind: 'error' | 'ok' | 'any' = 'any'): Locator {
	return page.locator(
		kind === 'any' ? '.diluxone-users-notice' : `.diluxone-users-notice--${kind}`
	);
}

/** The social buttons, whichever networks are on. */
export function ssoButtons(page: Page): Locator {
	return page.locator('.diluxone-users-sso a, a.diluxone-users-sso__button, .diluxone-users-sso__button');
}

/* ── Doing things ──────────────────────────────────────────────────── */

/**
 * Presses a plugin form's button and waits to land on the answer.
 *
 * Every one of these forms posts to admin-post.php and comes back as a
 * redirect carrying `diluxone-users=<what happened>`. Reading the address
 * straight after the click reads the address before the trip: the wait is what
 * makes the assertion about the answer instead of about the timing.
 */
export async function submitPluginForm(page: Page, form: Locator): Promise<string> {
	await Promise.all([
		page.waitForURL(/[?&]diluxone-users=/, { waitUntil: 'domcontentloaded' }),
		form.locator('button[type="submit"], input[type="submit"]').first().click(),
	]);

	return new URL(page.url()).searchParams.get('diluxone-users') ?? '';
}

/** Asks for a sign-in link and waits for the screen that confirms it. */
export async function askForLink(page: Page, loginUrl: string, email: string): Promise<void> {
	await page.goto(loginUrl);
	await emailField(page).fill(email);
	await linkForm(page).locator('button[type="submit"]').click();
	await expect(sentScreen(page)).toContainText(email);
}

/** Signs in with a password through whichever form is on the page. */
export async function signInWithPassword(page: Page, user: string, pass: string): Promise<void> {
	await userField(page).fill(user);
	await passField(page).fill(pass);
	await passwordForm(page).locator('input[type="submit"], button[type="submit"]').first().click();
}

/** Signs out, whatever the session is. */
export async function signOut(page: Page): Promise<void> {
	await page.context().clearCookies();
}

/* ── The dashboard ─────────────────────────────────────────────────── */

/** One of the plugin's settings screens, on one of its tabs. */
export function adminUrl(screen: string, tab?: string): string {
	return `/wp-admin/admin.php?page=${screen}${tab ? `&tab=${tab}` : ''}`;
}

/**
 * Presses the button a panel's form ends with.
 *
 * `submit_button()` gives it id="submit"; the value is translated and the
 * class is shared with half the dashboard, so the id is the only stable hold.
 */
export async function savePanel(page: Page): Promise<void> {
	await page.locator('#submit').click();
	await expect(page.locator('.notice, .updated, .diluxone-users-notice')).toBeVisible();
}
