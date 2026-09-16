import { test, expect, expectSignedIn, expectSignedOut } from '../support/fixtures';
import { freshEmail } from '../support/api';
import { linkForm, passwordForm, signInWithPassword } from '../support/ui';

/**
 * The password, in each of the three answers to "how do people get in".
 *
 * `both` and `password` have to let somebody in with one; `link` has to stop
 * them — and stopping them means stopping the POST to wp-login.php, not only
 * hiding the form, because the form is one curl away for anybody who wants it.
 *
 * The case worth having a test for is H-05: while the site's own screens are
 * taken over (`wp_screens = mine`) and a password is still a way in, the form
 * the plugin itself draws posts to wp-login.php — so that POST must go
 * through. It answered 403 and locked everybody out of their own site. The
 * fix is `diluxone_users_wp_login_post_allowed()`; this is the test that would
 * have caught it, and the one that keeps it caught.
 *
 * Covers T-PWL-02.
 */

const PASSWORD = 'e2e-Password-123!';

test.describe('Signing in with a password', () => {
	test('with both ways in, the page draws both forms and the password works', async ({
		page,
		site,
		pages,
		options,
	}) => {
		await options.set({ diluxone_users_login_method: 'both' });

		const email = freshEmail('both');
		await site.makeUser({ email, password: PASSWORD });

		await page.goto(pages.login.url);

		await expect(linkForm(page)).toBeVisible();
		await expect(passwordForm(page)).toBeVisible();

		await signInWithPassword(page, email, PASSWORD);
		await expectSignedIn(page, email);
	});

	test('with only the password, the link form is gone and the password still works', async ({
		page,
		site,
		pages,
		options,
	}) => {
		await options.set({ diluxone_users_login_method: 'password' });

		const email = freshEmail('pass');
		await site.makeUser({ email, password: PASSWORD });

		await page.goto(pages.login.url);

		await expect(linkForm(page)).toHaveCount(0);
		await expect(passwordForm(page)).toBeVisible();

		await signInWithPassword(page, email, PASSWORD);
		await expectSignedIn(page, email);
	});

	test('with only the link, the password form is gone and wp-login.php refuses one', async ({
		page,
		site,
		pages,
		options,
	}) => {
		await options.set({ diluxone_users_login_method: 'link' });

		const email = freshEmail('linkonly');
		await site.makeUser({ email, password: PASSWORD });

		await page.goto(pages.login.url);

		await expect(linkForm(page)).toBeVisible();
		await expect(passwordForm(page)).toHaveCount(0);

		// Opening wp-login.php lands on the site's own page instead.
		await page.goto('/wp-login.php');
		await expect(page).toHaveURL(new RegExp(pages.login.url.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));

		// And the endpoint behind that form is closed too: hiding a form that
		// still accepts posts is not closing a door.
		const posted = await page.request.post('/wp-login.php', {
			form: { log: email, pwd: PASSWORD },
			maxRedirects: 0,
		});

		expect(posted.status(), 'a password POST must be refused, not quietly redirected').toBe(403);
		await expectSignedOut(page);
	});

	test('H-05: with the screens taken over and a password still a way in, the POST goes through', async ({
		page,
		site,
		pages,
		options,
	}) => {
		await options.set({ diluxone_users_login_method: 'both', diluxone_users_wp_screens: 'mine' });

		const email = freshEmail('mine');
		await site.makeUser({ email, password: PASSWORD });

		// The plugin's own page draws WordPress's form, and that form posts to
		// wp-login.php. This is the whole bug: the screen is taken over, the
		// endpoint is not.
		await page.goto(pages.login.url);
		await expect(passwordForm(page)).toBeVisible();

		await signInWithPassword(page, email, PASSWORD);
		await expectSignedIn(page, email);
	});

	test('with the screens taken over, wp-login.php still sends people to the site page', async ({
		page,
		pages,
		options,
	}) => {
		await options.set({ diluxone_users_login_method: 'both', diluxone_users_wp_screens: 'mine' });

		await page.goto('/wp-login.php');

		await expect(page).toHaveURL(new RegExp(pages.login.url.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
		await expect(passwordForm(page)).toBeVisible();
	});

	test('the escape hatch shows WordPress’s own form and lets an administrator in', async ({
		page,
		site,
		options,
	}) => {
		// The worst case the hatch exists for: every screen taken over AND the
		// password closed. Whoever administers still has to get in.
		await options.set({ diluxone_users_login_method: 'link', diluxone_users_wp_screens: 'mine' });

		const email = freshEmail('hatch');
		await site.makeUser({ email, password: PASSWORD, role: 'administrator' });

		await page.goto('/wp-login.php?diluxone-users-admin=1');

		// Not redirected: the native form is there, with the hatch carried in a
		// hidden field so pressing the button does not lose it.
		await expect(page.locator('#loginform')).toBeVisible();
		await expect(page.locator('#loginform input[name="diluxone-users-admin"]')).toHaveCount(1);

		await signInWithPassword(page, email, PASSWORD);
		await expectSignedIn(page, email);
	});

	test('logging out keeps working while the screens are taken over', async ({ page, site, pages, options }) => {
		await options.set({ diluxone_users_login_method: 'both', diluxone_users_wp_screens: 'mine' });

		const email = freshEmail('logout');
		await site.makeUser({ email, password: PASSWORD });

		await page.goto(pages.login.url);
		await signInWithPassword(page, email, PASSWORD);
		await expectSignedIn(page, email);

		// `logout` is on the list of actions that are never taken over, and it
		// is the one that would strand somebody if it were.
		await page.goto('/wp-admin/');
		const logout = await page.locator('a[href*="action=logout"]').first().getAttribute('href');

		expect(logout, 'the dashboard has to offer a way out').toBeTruthy();

		await page.goto(logout!);
		await expectSignedOut(page);
	});
});
