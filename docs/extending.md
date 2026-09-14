# Extending the plugin

Everything on this page is **public API**. It does not change shape without a
major version, and the plugin's own features use these same seams — a feature
that ships inside the plugin and one that arrives from somewhere else are
built the same way.

If something you need is not here, that is the bug: say so instead of copying
a template or editing `includes/`.

## The rule

Add on top; do not reach in. Nothing in here asks you to replace a file or
copy one. If you find yourself copying a template to change a sentence or a
class, there is a seam missing.

---

## Screens and tabs

A tab is registered, not written down. Register on `diluxone_users_register_panels`,
which fires on `admin_menu` before the menu is built — by then every plugin
has loaded, so nothing depends on which file came first.

```php
add_action( 'diluxone_users_register_panels', function () {
    diluxone_users_register_panel(
        'diluxone-users-security',       // the screen
        'sms',                           // the tab slug, which ends up in the URL
        array(
            'label'    => __( 'By SMS', 'my-addon' ),
            'position' => 25,            // 10, 20, 30… the plugin's own leave gaps
            'render'   => 'my_addon_sms_fields',
            'save'     => 'my_addon_sms_save',   // omit for a tab with nothing to save
            'preview'  => 'my_addon_sms_preview', // optional: shown beside the fields
        )
    );
} );
```

The screen supplies the form, the nonce, the submit button and the "Saved."
notice. `render` prints the fields and nothing else.

Screens that take panels: `diluxone-users-login`, `diluxone-users-security`,
`diluxone-users-design`.

A tab exists because something registered it. Nothing registered means no tab
— not an empty one.

## Social login providers

`includes/sso.php` is a generic OAuth2 engine with no branch per provider:
everything comes from a table. Adding one is a filter and nothing else.

```php
add_filter( 'diluxone_users_sso_providers', function ( array $providers ): array {
    $providers['twitch'] = array(
        'name'      => 'Twitch',
        'authorize' => 'https://id.twitch.tv/oauth2/authorize',
        'token'     => 'https://id.twitch.tv/oauth2/token',
        'userinfo'  => 'https://api.twitch.tv/helix/users',
        'scope'     => 'user:read:email',
        'brand'     => '#9146FF',
    );

    return $providers;
} );
```

Its icon goes through `diluxone_users_sso_icon_paths`, and the step-by-step
for its console through `diluxone_users_sso_guides`. It then appears on the
Social login screen with its card, its redirect URL and its live test, like
any other.

## Account area sections

```php
add_action( 'diluxone_users_register_sections', function () {
    diluxone_users_register_section(
        'orders',
        array(
            'label'    => __( 'My orders', 'my-addon' ),
            'position' => 60,
            'render'   => 'my_addon_orders',        // prints the section
            'summary'  => 'my_addon_orders_card',   // optional: its card on the front page
            'available' => 'my_addon_has_shop',     // optional: hide it when there is nothing to show
        )
    );
} );
```

`summary` returns `array( 'value' => …, 'note' => …, 'cta' => … )`. A card with
an empty `value` is not drawn, but it still appears in the admin as something
that can be switched off — the list of switches does not change shape
depending on who is looking at it.

To add a card without a section of your own, filter `diluxone_users_summaries`
and give each card an `id`: that is the key the admin remembers when somebody
turns one off. Without one, the plugin makes it from the label, and the label
changes.

## Second-factor methods

```php
add_filter( 'diluxone_users_2fa_methods', function ( array $methods ): array {
    $methods['sms'] = array(
        'channel'  => 'sms',      // 'email' means "does not add anything to an e-mail link"
        'label'    => __( 'A code by SMS', 'my-addon' ),
        'help'     => __( 'We text the number on the account.', 'my-addon' ),
        'ready'    => 'my_addon_has_number',   // can this person use it?
        'send'     => 'my_addon_sms_send',
        'verify'   => 'my_addon_sms_verify',
        'position' => 30,
    );

    return $methods;
} );
```

A method registered this way appears on **Security → Two-step verification**
as one more box to tick, and it does **not** work until the site ticks it:
which methods a site offers is the site's decision, not the add-on's. The
option holding the ticked ones happens to share the filter's name — the
filter says what exists, the option says what is offered.

## Profile fields

Three seams, and you will usually want all three.

```php
// 1. The type shows up in the admin.
add_filter( 'diluxone_users_field_types', function ( array $types ): array {
    $types['signature'] = __( 'Signature', 'my-addon' );
    return $types;
} );

// 2. How it is drawn. Return '' to leave it to the plugin, which draws a text
//    box — the right answer for most of what anybody would add.
add_filter( 'diluxone_users_field_input', function ( string $html, array $field, string $value, string $id ): string {
    return 'signature' === $field['type'] ? my_addon_signature_pad( $field, $value, $id ) : $html;
}, 10, 4 );

// 3. How the value is cleaned. Return null to leave it to the plugin.
//    Whatever answers is responsible for the value being safe to store.
add_filter( 'diluxone_users_sanitize_value', function ( $clean, string $value, array $field ) {
    return 'signature' === $field['type'] ? my_addon_clean_signature( $value ) : $clean;
}, 10, 3 );
```

The markup from the second one goes through `wp_kses` with form tags and the
attributes they need: you can replace a text box, not turn it into a script
tag.

## Looks

```php
// Another shape for the sign-in page. The id becomes a class on the frame:
// .diluxone-users-login-frame--neon — bring your own CSS.
add_filter( 'diluxone_users_login_templates', function ( array $t ): array {
    $t['neon'] = array(
        'label'   => __( 'Neon', 'my-addon' ),
        'help'    => __( 'Dark, with a glow.', 'my-addon' ),
        'picture' => false,   // true gives it the picture field
    );
    return $t;
} );

// And for the account area: .diluxone-users-account--<id>
add_filter( 'diluxone_users_account_templates', function ( array $t ): array { … } );
```

Colours and corners are CSS custom properties — `--diluxone-users-accent`,
`--diluxone-users-radius` and the rest — so a site or an add-on can repoint
them from a stylesheet without any PHP at all.

## Styling it from a site

**Set a value. Do not write a selector.** This is the one rule on this page
that is about CSS, and it is here because breaking it broke a real site.

A site had written, reasonably:

```css
.diluxone-users-account--cover .diluxone-users-account__nav { gap: 36px }
```

meaning *the tab bar under the cover* — at the time, a cover implied a row of
tabs. Then the menu learnt to go down the side. The rule was still true to the
letter and no longer true to the intention: 36px of gap on a stacked menu is a
hundred pixels between items, and nobody had touched either the site or that
rule.

A property applies in every state the plugin will ever have. A selector is a
claim about which states exist, and that claim expires the next time one is
added. So the measurements are properties too, not only the colours:

| Property | What it is | Default |
| --- | --- | --- |
| `--diluxone-users-read` | The reading column, when the content is held to one | `980px` |
| `--diluxone-users-bleed` | How far the cover breaks out of the theme's column | `calc(50% - 50vw)` |
| `--diluxone-users-cover-pad` | The air inside the cover band | `40px` |
| `--diluxone-users-header-gap` | Between the picture and the name | `18px` / `24px` |
| `--diluxone-users-avatar` | The picture | `64px` / `96px` |
| `--diluxone-users-bar-bg` | Behind the menu's strip | `transparent` |
| `--diluxone-users-bar-rule` | Its rule, whole: `1px solid #ddd` | none |
| `--diluxone-users-bar-pad` | Inside the strip, above and below the menu | `10px` |
| `--diluxone-users-bar-gap` | Under the strip | `28px` |
| `--diluxone-users-bare-top` | Above the area when it has no header | `24px` |
| `--diluxone-users-nav-gap` | Between menu items | by the shape |
| `--diluxone-users-tab-pad` | Inside one | by the shape |
| `--diluxone-users-side-w` | The side menu's column | `minmax(180px, 220px)` |
| `--diluxone-users-nav-min` | Narrowest item when the menu wraps on a phone | `140px` |
| `--diluxone-users-avatar-ring` | The ring around the picture on a cover | `3px solid rgba(255,255,255,.35)` |
| `--diluxone-users-cover-at` | Where the cover picture is anchored | `center` |
| `--diluxone-users-cover-over` | What is laid over it | the cover colour at 78% |
| `--diluxone-users-sent-icon` | The circle on "check your email" | `88px` |
| `--diluxone-users-dial-w` | The dial-code column of a phone field | `minmax(0, 9rem)` |
| `--diluxone-users-border-w` | A control's edge — inputs and buttons | `1px` |
| `--diluxone-users-accent-soft` | The accent with the volume down | the accent at 12% |
| `--diluxone-users-body-end` | Under the content, on a cover | `64px` |

The last two are also asked from the admin — Design → Your brand — so a site
that is not writing CSS at all still gets them. Everything on this page can be
set from a stylesheet as well; the admin is the same values with a screen in
front of them.

Two defaults in a row means the plugin's own differ by shape. Setting the
property wins in both: none of them are declared on `:root`, each is read
where it is used with the default for that place, so yours is never shadowed.
Set them wherever you like — `:root`, the page, the block:

```css
:root {
	--diluxone-users-bleed: 0px;   /* this site is already full width */
	--diluxone-users-nav-gap: 36px;
	--diluxone-users-avatar: 104px;
}
```

### Headings are the theme's

There is no property here for the size of a heading, and there will not be
one. The person's name is an `h1` and a section's heading is an `h2`, and the
plugin says nothing about how big they are — so they come out in the theme's
own typography and the page reads as one page.

It used to say. `font-size: 1.5em` on the name was the single thing that made
this block look pasted in: a site whose headings were a display face at 42px
got everything else on the page in it and this in one-and-a-half times the
body face. That is why the site was writing a rule — not to customise the
plugin, but to undo it.

What the plugin does keep is the layout of its own header: the margins around
the name, the gap beside the picture, the air at the end of a cover. Those are
this block's, not the theme's.

### The one thing that is not a property

Where the area's three rows — the header's contents, the menu across the top,
the body — line up with the rest of the page. That is asked from Design →
Account area instead, and the rule is printed only when it has been answered.

It was a property for a day and it was wrong, in a way worth writing down.
The rule read `padding-inline: var(--diluxone-users-pad, 0px)`, and the
neutral default is not neutral: this sheet loads after the site's, so `0` won,
and a site that had been lining those rows up with its own grid lost the
alignment without anybody touching it. **A value that means "nothing" still
beats a value that means something.** The only declaration that cannot
overrule anybody is the one that is not printed.

So where the plugin has no opinion, it prints no rule — and where a site
wants one, it answers the question and the plugin prints exactly that.

When you do need a selector — your own typeface on the name, your own colour
on the open item — **style what the thing is, not what a template implies it
is**. Every axis is a class on the element it describes, so there is always
one that means exactly what you mean:

| Axis | Classes | On |
| --- | --- | --- |
| Which shape | `--plain` `--cover` | the area |
| Where the menu goes | `--tabs` `--side` | the area |
| How wide | `--contained` `--full` | the area |
| Which way the menu runs | `--row` `--column` | the menu |
| What the menu looks like | `--pills` `--underline` `--plain` | the menu |

`.diluxone-users-account__nav--row` is a row of items, in every template there
will ever be. `.diluxone-users-account--cover .diluxone-users-account__nav`
was a row of items *until Tuesday*.

## Templates

`diluxone_users_template` filters the path of any template before it is
loaded. A theme does not need it: dropping a file in
`wp-content/themes/<theme>/diluxone-users/` is enough, and the list of files
is on the Status screen.

## Options

`diluxone_users_option` filters any setting as it is read. A site that pins
one from code gets it pinned for good — and the admin says so, with the
function and file doing the pinning, rather than showing a control that does
nothing.

## Things that happen

- `diluxone_users_logged_in` — somebody got in. Receives the user and how.
- `diluxone_users_fields_saved` — a person's fields were saved.
- `diluxone_users_register_sections`, `diluxone_users_register_panels` — the
  moments to register.

## What the plugin will not promise

Anything not on this page. Function names, file layout and markup can change
between versions; these seams cannot.
