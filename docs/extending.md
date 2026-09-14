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
