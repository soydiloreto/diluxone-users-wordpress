# DiluxOne Users+

> Everything about the people who use a WordPress site — custom fields, a front-end account area, passwordless sign-in, social login, two-step verification, passkeys and session control — in one plugin that depends on no other.

[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

## What is this?

DiluxOne Users+ is a WordPress plugin that covers the whole of a person's relationship with a site: the details you ask them for, how they sign in, what they see of their own, and what they can do with it.

In most sites that gets solved again every time, with four plugins that do not talk to each other — one for custom fields, one for social login, one for two-factor, one for the front-end profile. Here it lives once, in a single admin section and a single set of user-meta keys.

## For end users

If you just want to **install and use** the plugin on your WordPress site, get it from the official directory:

🔗 **[wordpress.org/plugins/diluxone-users](https://wordpress.org/plugins/diluxone-users/)** *(coming soon — pending wp.org review)*

User-facing documentation (features, installation, configuration, FAQ) lives in [`readme.txt`](readme.txt) — that's the version rendered on the wp.org plugin page.

## For developers

This README and the rest of this repository are aimed at developers who want to **contribute, fork, or run the plugin from source**.

## Quick start (developers)

```bash
git clone https://github.com/soydiloreto/diluxone-users-wordpress.git
cd diluxone-users-wordpress
make install     # composer install — populate vendor/
make env         # boots wp-env at http://localhost:8888
```

When it finishes, open <http://localhost:8888>. Log in with `admin` / `password`, then activate the plugin from the **Plugins** screen.

`make help` lists every available target. For the full setup walkthrough, the day-to-day commands, the Docker plumbing, and the `wp-env` configuration, see [`docs/development.md`](docs/development.md).

## What it does

- **User fields** defined from the dashboard: name, type, whether it is required, where it goes, and who can change it and how many times. The ones WordPress already has — first and last name — are in the same list and follow the same rules.
- **An account area on the front end**: Home, Your details, Linked accounts, Security, Privacy and Notifications, with tabs on top or a menu down the side. Sections can be renamed, reordered, turned off and added; one of your own is a name, an address and a shortcode.
- **How people get in**: a link sent to their e-mail with no password at all, username and password, or both — with control over what happens to WordPress's own registration and profile screens.
- **Social login** with twelve providers, a step-by-step guide for each console, buttons with the real brand marks, and a live test before you turn one on.
- **Two-step verification**: a code by e-mail, an authenticator app with a QR code, and backup codes, with a policy per role and per way in.
- **Passkeys** (WebAuthn), each one with a name of its own.
- **Sessions**: how long they last, where they are open and how to close them.
- **Privacy**: the export and erasure requests WordPress already knows how to handle, put where people look for them.

None of it depends on another plugin. What belongs to someone else — a course, a membership, a forum — comes in through a filter or a shortcode.

## Principles

1. **What an administrator turns off disappears from the front end.** With no social provider enabled there is no "Linked accounts" section at all; with neither data download nor account deletion allowed there is no "Privacy" section.
2. **The plugin does not know what a course is.** Nor a membership, nor a forum. What is not its own is added from outside and can be removed without touching it.
3. **It works with any theme.** It ships its own styles, its templates are overridable from the theme, and its colours come from CSS custom properties a site can redefine.
4. **Nothing it shows is a lie.** If a notice says the second factor is not being asked for, it is because no door the site has open is asking for it.

## Architecture overview

| Path | What it contains |
|------|------------------|
| `diluxone-users.php` | Main plugin file — defines the constants and loads `includes/`. |
| `includes/` | One file per concern, each one standing alone and only registering hooks: fields, account area, sign-in, social providers, 2FA, passkeys, sessions, admin screens. |
| `templates/` | Front-end views, overridable from the theme at `wp-content/themes/<theme>/diluxone-users/`. |
| `assets/` | Plugin runtime assets — JS and CSS bundled with the plugin. |
| `languages/` | Translations: `de_DE`, `es_AR`, `es_ES`, `es_MX`, `fr_FR`, `it_IT`, `pt_BR`, `pt_PT`. |
| `.wordpress-org/` | wp.org listing visuals — banner, icon, screenshots. Uploaded by CI to the SVN `assets/` directory, **not** to the published plugin zip. |
| `readme.txt` | wp.org plugin page content (description, FAQ, changelog). |
| `.github/workflows/` | CI/CD: deploy to wp.org SVN on tag push, plus PR checks. |
| `.distignore` | List of paths excluded from the wp.org deploy (this README, dev tooling, etc. live in GitHub but are never shipped to wp.org). |

## Documentation

For developers working on the plugin itself:

- [`CONTRIBUTING.md`](CONTRIBUTING.md) — branch naming, PR workflow, commit conventions, coding rules.
- [`docs/development.md`](docs/development.md) — local dev setup (`wp-env`, Docker, Make targets).
- [`docs/testing-and-quality.md`](docs/testing-and-quality.md) — PHPUnit, PHPCS, PHPStan, Psalm, i18n. What each layer enforces and how to run it.
- [`docs/ai-tooling.md`](docs/ai-tooling.md) — what AI tooling the project uses, what it costs, and what it's configured to enforce.
- [`docs/ai-policy.md`](docs/ai-policy.md) — rules for contributors using AI agents to author code.
- [`docs/release.md`](docs/release.md) — version bump flow, the `-dev` suffix convention, the wp.org SVN deploy.

## Contributing

Contributions are welcome — bug reports, feature requests, and pull requests. See [`CONTRIBUTING.md`](CONTRIBUTING.md) to get started.

## Reporting security issues

⚠️ **Please do not open public issues for security vulnerabilities.** See [SECURITY.md](SECURITY.md) for the private reporting process via GitHub Security Advisories.

## About

This plugin is **free and open-source software** under the GPL-2.0-or-later licence — the fields engine, the account area, the sign-in methods, every test, every CI workflow.

It was created and is currently maintained by **Pablo Di Loreto** ([@soydiloreto](https://github.com/soydiloreto)). Contributions, issues, and forks are all welcome.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
