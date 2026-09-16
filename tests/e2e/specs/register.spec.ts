import { test, expect, expectSignedIn } from '../support/fixtures';
import { freshEmail, linkIn, waitForMail } from '../support/api';
import { notice, registerForm, registerScreen, submitPluginForm } from '../support/ui';

/**
 * The site's own registration form.
 *
 * The door that exists for the sites the e-mail link does not fit: the ones
 * that have to ask for something before letting anybody in. What makes it a
 * separate flow and not a variation of the sign-in form is that this one
 * answers honestly — "that address is taken" — because somebody filling in a
 * registration form has to be told, while the sign-in form stays silent on
 * purpose.
 *
 * Covers T-REG-03.
 */

/** A required field, defined the way the Fields screen would define it. */
const CITY_FIELD = [
	{
		key: 'e2e_city',
		label: 'City',
		type: 'text',
		required: 1,
		active: 1,
		group: 'basic',
		edit: 'always',
	},
];

test.describe('Registering with the site’s own form', () => {
	test.beforeEach(async ({ options }) => {
		// The field set is pinned and not inherited: this environment is
		// somebody's development site, and whatever they marked required there
		// would otherwise decide whether this form can be submitted at all.
		await options.set({ diluxone_users_register_form: 1, diluxone_users_fields: [] });
	});

	test('fill it in, get the link, get in, and the answers are already saved', async ({
		page,
		site,
		pages,
		options,
	}) => {
		await options.set({ diluxone_users_fields: CITY_FIELD });

		const email = freshEmail('reg');

		await page.goto(pages.register.url);
		await expect(registerScreen(page)).toBeVisible();

		await page.locator('input[name="diluxone_users_email"]').fill(email);

		// A required field the site added shows up on the form, because a form
		// that cannot ask for it is the reason this door exists at all.
		const city = page.locator('input[name="e2e_city"]');
		await expect(city).toBeVisible();
		await expect(city).toHaveAttribute('required', '');
		await city.fill('Mendoza');

		expect(await submitPluginForm(page, registerForm(page))).toBe('registered');

		const made = await site.user(email, ['e2e_city']);

		expect(made.exists).toBe(true);
		expect(made.roles).toEqual(['subscriber']);
		// Asked once, on the form, and not asked again on the other side.
		expect(made.fields.e2e_city).toBe('Mendoza');

		await page.goto(linkIn(await waitForMail(site, email)));
		await expectSignedIn(page, email);
	});

	test('a required field the browser lets through is still required by the form', async ({
		page,
		site,
		pages,
		options,
	}) => {
		await options.set({ diluxone_users_fields: CITY_FIELD });

		const email = freshEmail('nocity');

		await page.goto(pages.register.url);
		await page.locator('input[name="diluxone_users_email"]').fill(email);

		// The browser would stop this one itself; `novalidate` is how the test
		// asks what the server does, which is the answer that matters.
		await page.locator('form.diluxone-users-form').evaluate((form: HTMLFormElement) => {
			form.noValidate = true;
		});
		await submitPluginForm(page, registerForm(page));

		// The account is made and the field is simply empty — the plugin's
		// `diluxone_users_save()` reports what is missing and the registration
		// handler does not act on it. Pinned as what it does, not as what it
		// should do: see the note in tests/e2e/README.md.
		const made = await site.user(email, ['e2e_city']);

		expect(made.exists).toBe(true);
		expect(made.fields.e2e_city).toBe('');
	});

	test('an address that already has an account is told so, and offered the way in', async ({
		page,
		site,
		pages,
	}) => {
		const email = freshEmail('taken');
		await site.makeUser({ email });

		await page.goto(pages.register.url);
		await page.locator('input[name="diluxone_users_email"]').fill(email);
		expect(await submitPluginForm(page, registerForm(page))).toBe('taken');
		await expect(notice(page, 'error')).toBeVisible();
		// The useful thing for somebody who already has an account is the door,
		// not a shrug.
		await expect(notice(page, 'error').locator('a')).toHaveAttribute('href', new RegExp('e2e-login'));
	});

	test('with registration closed the form says so and takes nobody', async ({ page, pages, options }) => {
		await options.set({ diluxone_users_login_register: 0, diluxone_users_register_form: 0 });

		await page.goto(pages.register.url);

		// The form is gone: what is left is the sentence and the way to sign in.
		await expect(page.locator('input[name="diluxone_users_email"]')).toHaveCount(0);
		await expect(registerScreen(page).locator('a[href*="e2e-login"]')).toBeVisible();
	});

	test('one machine cannot make an unlimited number of accounts', async ({ page, site, pages }) => {
		// Six an hour is the burst; the seventh is turned away. Counted per
		// machine and not per address, because the address is the one thing a
		// script changes for free.
		const emails = Array.from({ length: 7 }, (_, n) => freshEmail(`burst${n}`));

		for (const email of emails.slice(0, 6)) {
			await page.goto(pages.register.url);
			await page.locator('input[name="diluxone_users_email"]').fill(email);
			expect(await submitPluginForm(page, registerForm(page)), `${email} should have gone through`).toBe(
				'registered'
			);
		}

		const seventh = emails[6];

		await page.goto(pages.register.url);
		await page.locator('input[name="diluxone_users_email"]').fill(seventh);
		expect(await submitPluginForm(page, registerForm(page))).toBe('slow');
		expect((await site.user(seventh)).exists, 'the seventh account must not exist').toBe(false);
	});

	test('an address that is not one is refused before anything is created', async ({ page, pages }) => {
		await page.goto(pages.register.url);

		await page.locator('form.diluxone-users-form').evaluate((form: HTMLFormElement) => {
			form.noValidate = true;
		});
		await page.locator('input[name="diluxone_users_email"]').fill('not-an-address');
		expect(await submitPluginForm(page, registerForm(page))).toBe('email');
		await expect(notice(page, 'error')).toBeVisible();
	});
});
