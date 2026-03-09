# Building an Integration

This guide explains how to extend Registration Guard to protect registration forms from other plugins (BuddyPress, Ultimate Member, etc.). The WooCommerce integration is used as a reference throughout.

---

## Architecture

Integrations live in the `integrations/` directory. Each integration:

1. Is always `require`d from `registration-guard.php` (no conditional loading at file level)
2. Registers a `plugins_loaded` hook that checks whether the target plugin is active
3. Only instantiates its class and registers WordPress hooks if the target plugin is present

This means zero overhead when the target plugin is not installed -- the file is loaded, but the `plugins_loaded` callback exits immediately.

---

## File Structure

```
integrations/
└── class-integration-woocommerce.php    # WooCommerce (ships with plugin)
└── class-integration-buddypress.php     # BuddyPress (hypothetical)
```

File names must follow WordPress coding standards: `class-integration-{slug}.php`.

---

## Skeleton

Every integration follows this pattern:

```php
<?php
/**
 * {Plugin Name} Integration
 *
 * @package Registration_Guard
 * @since 1.0.0
 */

namespace Registration_Guard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * {Plugin Name} Integration Class.
 *
 * @since 1.0.0
 */
class Integration_{Name} {

    /**
     * Bootstrap the integration.
     *
     * Called on `plugins_loaded`. Checks whether the target plugin
     * is active and registers hooks if so.
     *
     * @since 1.0.0
     */
    public static function init(): void {
        if ( ! class_exists( 'Target_Plugin_Class' ) ) {
            return;
        }

        $instance = new self();

        // Register your hooks here.
    }

    // Integration methods...
}

add_action( 'plugins_loaded', array( Integration_{Name}::class, 'init' ) );
```

Then add one line to `registration-guard.php`:

```php
// Integrations — each file registers its own `plugins_loaded` hook.
require_once REGISTRATION_GUARD_PATH . 'integrations/class-integration-woocommerce.php';
require_once REGISTRATION_GUARD_PATH . 'integrations/class-integration-buddypress.php';  // new
```

---

## What an Integration Typically Does

Registration Guard has three protection layers. An integration hooks each layer into the target plugin's registration flow:

| Layer | What to hook | What to call |
|-------|-------------|-------------|
| **Nonce challenge** | Render hidden field, enqueue JS, validate on submit | See below |
| **Email verification** | Block unverified users from protected pages | Check `_regguard_email_verified` meta |
| **Geo-restriction** | Validate on registration submit | `get_plugin()->get_geo_restriction()` |

Not every integration needs all three. The WooCommerce integration implements all of them; a simpler integration might only need nonce challenge validation.

---

## Available Plugin APIs

Access the plugin instance from anywhere via `Registration_Guard\get_plugin()`. This returns the `Plugin` object with these public accessors:

```php
$plugin = Registration_Guard\get_plugin();

$plugin->get_nonce_challenge();   // Nonce_Challenge instance
$plugin->get_logger();            // Logger instance
$plugin->get_geo_restriction();   // Geo_Restriction instance
```

### Helper Functions

All in the `Registration_Guard` namespace:

```php
Registration_Guard\is_nonce_challenge_enabled();  // bool
Registration_Guard\is_double_optin_enabled();     // bool
Registration_Guard\is_geo_enabled();              // bool
Registration_Guard\get_ip_address();              // string
```

### Constants

Defined in `constants.php`. Key ones for integrations:

```php
Registration_Guard\META_EMAIL_VERIFIED   // '_regguard_email_verified'
Registration_Guard\Nonce_Challenge::FIELD_NAME   // 'regguard_nonce_token'
Registration_Guard\Nonce_Challenge::AJAX_ACTION  // 'regguard_nonce'
Registration_Guard\LOG_NONCE_REJECTED    // 'nonce_rejected'
Registration_Guard\LOG_GEO_BLOCKED       // 'geo_blocked'
```

---

## Nonce Challenge

The nonce challenge is the most common thing an integration needs. Registration Guard provides two centralised methods so integrations don't duplicate logic.

### Enqueue the Script

Call `enqueue_nonce_script()` from your front-end enqueue hook. This registers the JS file and passes configuration via `wp_localize_script()`:

```php
public function enqueue_script(): void {
    if ( ! is_nonce_challenge_enabled() ) {
        return;
    }

    // Your plugin-specific guards (e.g. only on registration pages).
    if ( ! $this->is_registration_page() ) {
        return;
    }

    get_plugin()->get_nonce_challenge()->enqueue_nonce_script();
}
```

The script automatically:
- Waits for `DOMContentLoaded` plus a configurable delay
- Fetches a nonce from `admin-ajax.php`
- Injects it into the hidden field named `regguard_nonce_token`

### Render the Hidden Field

The nonce JS needs a hidden input to write into. Add this to your registration form hook:

```php
public function render_nonce_field(): void {
    if ( ! is_nonce_challenge_enabled() ) {
        return;
    }

    printf(
        '<input type="hidden" name="%s" value="" />',
        esc_attr( Nonce_Challenge::FIELD_NAME )
    );
}
```

### Validate on Submission

Read the token from `$_POST` and delegate to `check_nonce_token()`:

```php
public function validate_registration( \WP_Error $errors ): \WP_Error {
    if ( ! is_nonce_challenge_enabled() ) {
        return $errors;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- this method IS the nonce verifier.
    $token = isset( $_POST[ Nonce_Challenge::FIELD_NAME ] )
        ? sanitize_text_field( wp_unslash( $_POST[ Nonce_Challenge::FIELD_NAME ] ) )
        : '';

    $is_valid = get_plugin()->get_nonce_challenge()->check_nonce_token( $token );

    if ( ! $is_valid ) {
        $errors->add(
            'regguard_nonce_failed',
            __( '<strong>Error:</strong> Security verification failed. Please reload the page and try again.', 'registration-guard' )
        );

        get_plugin()->get_logger()->log(
            LOG_NONCE_REJECTED,
            0,
            __( 'MyPlugin registration blocked: nonce challenge failed.', 'registration-guard' )
        );
    }

    return $errors;
}
```

### Script Data Filter

The front-end config array passes through the `registration_guard_nonce_script_data` filter. An integration can use this to adjust behaviour:

```php
add_filter( 'registration_guard_nonce_script_data', function ( array $data ): array {
    // Example: increase delay on a specific page.
    if ( is_page( 'custom-register' ) ) {
        $data['delay'] = 3;
    }
    return $data;
} );
```

The filter receives and should return an array with these keys:

| Key | Type | Description |
|-----|------|-------------|
| `ajaxUrl` | string | Admin AJAX URL |
| `action` | string | AJAX action name |
| `fieldName` | string | Hidden form field name |
| `delay` | int | Seconds to wait before fetching nonce |

---

## Email Verification

Email verification is handled automatically by the core plugin's `user_register` hook. Integrations don't need to trigger it -- any new user created via `wp_create_user()` or `wp_insert_user()` will go through verification automatically.

What integrations **do** need is to block unverified users from accessing protected pages:

```php
public function block_unverified_user(): void {
    if ( ! is_double_optin_enabled() ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        return;
    }

    // Your plugin-specific page check.
    if ( ! $this->is_protected_page() ) {
        return;
    }

    $user_id  = get_current_user_id();
    $verified = get_user_meta( $user_id, META_EMAIL_VERIFIED, true );

    // Three-state check: 'false' means pending. Empty/no meta means
    // legacy user (treat as verified). 'true' means explicitly verified.
    if ( 'false' !== $verified ) {
        return;
    }

    // Build a resend URL and show an interstitial.
    $resend_url = add_query_arg(
        array(
            'action' => Email_Verification::AJAX_RESEND,
            'uid'    => $user_id,
        ),
        admin_url( 'admin-ajax.php' )
    );

    $message = sprintf(
        '<h2>%s</h2><p>%s</p><p>%s</p><p><a href="%s">%s</a> | <a href="%s">%s</a></p>',
        esc_html__( 'Email Verification Required', 'registration-guard' ),
        esc_html__( 'You must verify your email address before accessing this page.', 'registration-guard' ),
        esc_html__( 'If you cannot find the email, check your spam or junk folder.', 'registration-guard' ),
        esc_url( $resend_url ),
        esc_html__( 'Resend verification email', 'registration-guard' ),
        esc_url( wp_logout_url() ),
        esc_html__( 'Log out', 'registration-guard' )
    );

    wp_die(
        $message, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        esc_html__( 'Email Verification Required', 'registration-guard' ),
        array( 'response' => 403 )
    );
}
```

### Skip Verification Filter

If your integration has registration paths that should bypass verification (e.g. paid signups, social login), use the built-in filter:

```php
add_filter( 'registration_guard_skip_verification', function ( bool $skip, int $user_id ): bool {
    if ( my_plugin_is_trusted_registration() ) {
        return true;
    }
    return $skip;
}, 10, 2 );
```

---

## Geo-Restriction

If the target plugin has its own registration validation hook, wire it up to the geo-restriction instance:

```php
public static function init(): void {
    // ... other hooks ...

    $geo = get_plugin()->get_geo_restriction();
    add_filter(
        'my_plugin_register_validation',
        array( $geo, 'validate_woocommerce_registration' ),
        10,
        3
    );
}
```

The `validate_woocommerce_registration()` method expects the standard WooCommerce signature `( $username, $email, $errors )`. If your target plugin uses a different hook signature, write a thin wrapper:

```php
public function validate_geo( \WP_Error $errors ): \WP_Error {
    $geo = get_plugin()->get_geo_restriction();
    return $geo->validate_registration( $errors );
}
```

The `validate_registration()` method accepts a `\WP_Error` object (the same signature as WordPress core's `registration_errors` filter).

---

## Logging

Use the logger to record blocked registrations. This keeps all security events in one place (the Event Log tab in the admin):

```php
get_plugin()->get_logger()->log(
    LOG_NONCE_REJECTED,                              // Event type constant.
    0,                                                // User ID (0 if unknown).
    __( 'BuddyPress registration blocked.', 'registration-guard' )  // Message.
);
```

Available event type constants: `LOG_NONCE_REJECTED`, `LOG_GEO_BLOCKED`, `LOG_VERIFICATION_SENT`, `LOG_VERIFICATION_RESENT`, `LOG_VERIFICATION_SUCCESS`, `LOG_VERIFICATION_EXPIRED`, `LOG_USER_REGISTERED`, `LOG_CHECKOUT_AUTOAPPROVED`.

---

## WooCommerce Integration Walkthrough

The bundled WooCommerce integration at `integrations/class-integration-woocommerce.php` is the reference implementation. Here's how it maps to the patterns above.

### Bootstrap (`init`)

```php
public static function init(): void {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    $instance = new self();

    add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_script' ) );
    add_action( 'woocommerce_register_form', array( $instance, 'render_nonce_field' ) );
    add_filter( 'woocommerce_register_post', array( $instance, 'validate_nonce' ), 10, 3 );
    add_action( 'template_redirect', array( $instance, 'block_unverified_myaccount' ) );

    $geo = get_plugin()->get_geo_restriction();
    add_filter( 'woocommerce_register_post', array( $geo, 'validate_woocommerce_registration' ), 10, 3 );
}
```

Five hooks covering all three layers:
- **Nonce:** enqueue script, render field, validate on submit
- **Email verification:** block unverified users on My Account pages
- **Geo-restriction:** validate country on submit

### Checkout Exclusion

WooCommerce is unique in that it has two registration paths: My Account and Checkout. The integration guards every method with an `is_checkout()` check to ensure checkout is never affected. Other integrations likely won't need this pattern unless they have similarly distinct registration contexts.

---

## Checklist

When building a new integration:

- [ ] Create `integrations/class-integration-{slug}.php`
- [ ] Use `Registration_Guard` namespace
- [ ] Add `plugins_loaded` hook with `class_exists()` gate
- [ ] Add `require_once` line in `registration-guard.php`
- [ ] Hook nonce field rendering into the target form
- [ ] Hook `enqueue_nonce_script()` into the target page
- [ ] Hook nonce validation into the target registration handler
- [ ] Block unverified users from protected pages (if applicable)
- [ ] Wire up geo-restriction (if the target has a validation hook)
- [ ] Log blocked registrations via `get_plugin()->get_logger()`
- [ ] Run `phpcs` -- clean pass
- [ ] Test with the target plugin active and inactive
