import type { Locator, Page } from '@playwright/test';
import { test, expect } from '../support/fixtures';
import { adminUrl, savePanel } from '../support/ui';
import { ADMIN_STATE } from '../../../playwright.config';

/**
 * Your brand: one question, three answers, and what hangs off each of them.
 *
 * The screen used to be six blocks in a column and it was read as six
 * questions, which is what it was. Now it is one — where the plugin's look
 * comes from — and everything else is a child of the answer that needs it.
 * That shape is only true if the browser actually shows and hides the right
 * things, and if the thing shown is in a state somebody can act on: an accent
 * picker that stays switched off after the answer changed, or a row of colour
 * squares with nothing in them, is a screen that answers 200 and still cannot
 * be used.
 *
 * Nothing in here is found by its words. The site this runs against is in
 * Spanish and the plugin ships eight locales, so every hold is a name, an id
 * or a class: the radio posts `diluxone_users_look`, the fine tuning carries
 * `data-diluxone-users-brand-map`, and the squares are
 * `.diluxone-users-palette__chip`.
 */

test.use({ storageState: ADMIN_STATE });

const BRAND = adminUrl('diluxone-users-design', 'brand');

/** The three answers, by the value each one posts. */
type Look = 'theme' | 'own' | 'site';

function look(page: Page, answer: Look): Locator {
	return page.locator(`input[name="diluxone_users_look"][value="${answer}"]`);
}

/** The card of one answer and everything indented under it. */
function branch(page: Page, answer: Look): Locator {
	return page.locator('.du-choice-group').filter({ has: look(page, answer) });
}

/** What belongs to "I choose it here". */
function accent(page: Page): Locator {
	return page.locator('#diluxone_users_style_accent');
}

/** What belongs to "from your theme": the fine tuning, folded. */
function fineTuning(page: Page): Locator {
	return page.locator('details[data-diluxone-users-brand-map]');
}

/** What belongs to "the site writes it": the properties, to copy. */
function properties(page: Page): Locator {
	return branch(page, 'site').locator('.du-note');
}

test.describe('Your brand › one question, and what hangs off the answer', () => {
	test('each answer shows its own and hides the other two', async ({ page, options }) => {
		// From a known answer rather than from whatever the last run left: the
		// assertion is about what changes when the answer changes.
		await options.set({ diluxone_users_styles: 1, diluxone_users_colors: 'own' });

		await page.goto(BRAND);

		await expect(look(page, 'own')).toBeChecked();
		await expect(accent(page)).toBeVisible();
		await expect(fineTuning(page)).toBeHidden();
		await expect(properties(page)).toBeHidden();

		await look(page, 'theme').check();

		await expect(fineTuning(page)).toBeVisible();
		await expect(accent(page)).toBeHidden();
		await expect(properties(page)).toBeHidden();

		await look(page, 'site').check();

		await expect(properties(page)).toBeVisible();
		await expect(accent(page)).toBeHidden();
		await expect(fineTuning(page)).toBeHidden();
	});

	/*
	 * Which colour of a theme's palette plays which part is not a question the
	 * screen is asking: it is the answer to "the guess was wrong", and on a
	 * theme that names its colours the guess is right. Drawn open it reads as
	 * seven things left undone.
	 */
	test('the fine tuning starts folded away', async ({ page, options }) => {
		await options.set({ diluxone_users_styles: 1, diluxone_users_colors: 'theme' });

		await page.goto(BRAND);

		await expect(fineTuning(page)).toBeVisible();
		expect(await fineTuning(page).evaluate((box: HTMLDetailsElement) => box.open)).toBe(false);
	});

	test('with the theme’s colours the accent is not editable, and pressing the other answer frees it', async ({
		page,
		options,
	}) => {
		await options.set({ diluxone_users_styles: 1, diluxone_users_colors: 'theme' });

		await page.goto(BRAND);

		await expect(look(page, 'theme')).toBeChecked();
		await expect(accent(page)).toBeDisabled();

		// The server drew it switched off because of the saved answer, and
		// that answer has just stopped being true. Waiting for the save to
		// free the picker would be a screen that has to be saved to be used.
		await look(page, 'own').check();

		await expect(accent(page)).toBeEnabled();
	});

	/**
	 * The squares beside the seven parts, which is where the screen was worst.
	 *
	 * A part nobody mapped was painted `transparent`: a bordered square with
	 * nothing in it, seven of them in a column, indistinguishable from a
	 * checkbox nobody has ticked. The claim is not that a particular colour is
	 * in there — that is the theme's business — but that there is one.
	 */
	test('no colour square is left empty', async ({ page, options }) => {
		await options.set({
			diluxone_users_styles: 1,
			diluxone_users_colors: 'theme',
			// The guess and nothing else, so the parts a theme says nothing
			// about are genuinely left to the plugin — which is the case that
			// used to come out blank.
			diluxone_users_color_map: [],
		});

		await page.goto(BRAND);
		await fineTuning(page).locator('summary').click();

		const chips = page.locator('.diluxone-users-palette__chip');

		await expect(chips.first()).toBeVisible();

		// The case this test exists for has to actually be on screen: at least
		// one part with no colour of the theme's chosen for it.
		const unmapped = await page
			.locator('.diluxone-users-palette__row select')
			.evaluateAll((boxes: Element[]) =>
				boxes.filter((box) => (box as HTMLSelectElement).value === '').length
			);

		expect(unmapped).toBeGreaterThan(0);

		const painted = await chips.evaluateAll((squares: Element[]) =>
			squares.map((square) => window.getComputedStyle(square).backgroundColor)
		);

		expect(painted.length).toBe(7);

		for (const colour of painted) {
			expect(colour).not.toBe('rgba(0, 0, 0, 0)');
			expect(colour).not.toBe('transparent');
		}
	});
});

test.describe('Your brand › what the one question is kept as', () => {
	const KEYS = ['diluxone_users_styles', 'diluxone_users_colors'];

	/**
	 * One question on the screen, two settings underneath it.
	 *
	 * The stylesheet being loaded and the colours coming from the theme are
	 * read in two other files, and they stay where they are read. This is the
	 * proof that the screen's single question still writes both of them — the
	 * failure it guards is a screen that looks right and saves nothing.
	 */
	test('each answer writes the two settings that are actually read', async ({
		page,
		site,
		options,
	}) => {
		await options.keep(KEYS);

		const expected: Array<[Look, string, string]> = [
			['site', '0', 'own'],
			['theme', '1', 'theme'],
			['own', '1', 'own'],
		];

		for (const [answer, styles, colors] of expected) {
			await page.goto(BRAND);
			await look(page, answer).check();
			await savePanel(page);

			const got = await site.getOptions(KEYS);

			expect(String(got.diluxone_users_styles), answer).toBe(styles);
			expect(String(got.diluxone_users_colors), answer).toBe(colors);
		}
	});

	/**
	 * Trying the theme's palette for an afternoon does not cost you your colour.
	 *
	 * The picker is switched off while the colours are the theme's, and a
	 * switched-off control is not posted at all — so the save read an absence
	 * as "no colour" and wrote the accent away. Whoever went back to choosing
	 * it here found the plugin's blue and no way of knowing what had happened.
	 */
	test('the accent survives a save made while the theme’s palette is in use', async ({
		page,
		site,
		options,
	}) => {
		await options.set({
			diluxone_users_styles: 1,
			diluxone_users_colors: 'theme',
			diluxone_users_style_accent: '#123456',
		});

		await page.goto(BRAND);

		await expect(accent(page)).toBeDisabled();

		await savePanel(page);

		const got = await site.getOptions(['diluxone_users_style_accent']);

		expect(String(got.diluxone_users_style_accent)).toBe('#123456');
	});
});
