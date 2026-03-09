# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Registration Guard is a WordPress plugin that layers three defences against bot registration: a JavaScript nonce challenge, email double opt-in with auto-cleanup, and geo-restriction (via WooCommerce geolocation). It works with both native WordPress registration (`wp-login.php?action=register`) and WooCommerce My Account registration. WooCommerce is optional — features degrade gracefully without it.

**Requirements:** PHP 8.0+, WordPress 6.0+, WooCommerce optional

## Commands

```bash
phpcs                              # Check WordPress Coding Standards (configured in phpcs.xml)
phpcbf                             # Auto-fix coding standards violations
phpcs includes/class-plugin.php    # Check a specific file
```

No build step, test framework, or package.json. Code quality is enforced via phpcs only.

## Architecture

### Design Principles

1. **Minimal Attack Surface** — no custom rewrite rules or endpoints; use admin-ajax.php and existing WordPress/WooCommerce pages
2. **Fail Secure** — if nonce validation fails, block registration. If geo-lookup fails, apply configured default (block or allow)
3. **No Theme Interference** — interstitials use `wp_die()` which works regardless of active theme
4. **Non-Breaking** — never interfere with existing logged-in users, admin operations, REST API, WP-CLI, cron, or AJAX
5. **Checkout Safety** — never inject JS, nonce fields, or validation logic into the WooCommerce checkout flow. Checkout registrations are auto-approved.

### Entry Point & Initialisation

`registration-guard.php` → `registration_guard_run()` → `Plugin::instance()->run()` (singleton pattern). Classes are manually loaded (no autoloader). WooCommerce-specific class loaded conditionally when `class_exists( 'WooCommerce' )`.

### Hook Strategy

**Nonce Challenge:**
- `wp_enqueue_scripts` — enqueue `nonce-challenge.js` on pages with registration forms
- `register_form` — inject hidden nonce field into WordPress registration form
- `wp_ajax_nopriv_regguard_nonce` — admin-ajax endpoint for nonce generation (with referer validation)
- `registration_errors` — validate nonce on WordPress registration submission

**Email Double Opt-In:**
- `user_register` — set verification meta, generate token, send email. Skip logic:
  - `current_user_can( 'create_users' )` — admin-created user
  - `defined( 'WP_CLI' ) && WP_CLI` — CLI user creation
  - `defined( 'REST_REQUEST' ) && REST_REQUEST` — REST API (including WooCommerce REST)
  - `did_action( 'woocommerce_checkout_process' ) > 0` — checkout registration
  - `registration_guard_skip_verification` filter returns true — third-party opt-out
- `init` — handle `?regguard_verify={user_id}:{token}` verification link clicks
- `admin_init` — `wp_die()` interstitial for unverified users accessing wp-admin

**Account Cleanup:**
- `regguard_cleanup_unverified_accounts` (hourly cron) — delete expired unverified accounts (subscriber/customer roles only, batch of 50)
- `regguard_prune_event_log` (daily cron) — prune log entries older than 30 days

**WooCommerce (conditional):**
- `woocommerce_register_form` — inject hidden nonce field (My Account only, NOT checkout)
- `woocommerce_register_post` — validate nonce (My Account only, NOT checkout)
- `template_redirect` — `wp_die()` interstitial for unverified users on My Account pages
- `before_woocommerce_init` — HPOS compatibility declaration

**Geo-Restriction:**
- `registration_errors` / `woocommerce_register_post` — check country via `WC_Geolocation::geolocate_ip()`. Feature disabled without WooCommerce.

### Key Files

| File | Purpose |
|------|---------|
| `registration-guard.php` | Main plugin file, activation/deactivation hooks, class loading |
| `constants.php` | All constants: meta keys, option keys, defaults, transient keys, log event types, DB table name |
| `functions-private.php` | Namespaced helper functions: config getters, `get_now_formatted()`, `get_ip_address()` |
| `includes/class-plugin.php` | Main orchestrator: hook registration, conditional WooCommerce loading |
| `includes/class-nonce-challenge.php` | Admin-ajax nonce endpoint (with referer check), form field injection, validation |
| `includes/class-email-verification.php` | Double opt-in: token generation, verification links, resend handler, interstitial |
| `includes/class-account-cleanup.php` | Hourly cron: delete expired unverified accounts in batches |
| `includes/class-logger.php` | Custom table `{prefix}regguard_log`, event logging, daily pruning cron |
| `includes/class-geo-restriction.php` | Country allow/block list via WC_Geolocation |
| `includes/class-settings.php` | Admin settings page under Settings menu (WordPress Settings API) |
| `includes/class-woocommerce.php` | WooCommerce-specific hooks, conditionally loaded |
| `assets/js/nonce-challenge.js` | Front-end: fetch nonce via AJAX after delay, inject into registration form |
| `views/emails/verification-email.php` | Plain text verification email template |

### Data Storage

- **User meta** for per-user verification state (`_regguard_email_verified`, `_regguard_verification_token`, `_regguard_token_created`)
- **`wp_options`** for plugin settings (all prefixed `regguard_`)
- **Transients** for rate limiting (all prefixed `regguard_`)
- **Custom table** `{prefix}regguard_log` for event logging

### Verification Meta States

Three-state model for `_regguard_email_verified`:
- **No meta exists** → pre-existing/legacy user, treat as verified (never block)
- **`true`** → explicitly verified (clicked link) or auto-approved (checkout, admin-created, CLI, REST)
- **`false`** → pending verification, block wp-admin and My Account access

### Security Implementation

- **Nonce generation:** Short-lived (5 min), admin-ajax endpoint rejects requests with no/invalid referer
- **Nonce endpoint rate limiting:** Per-IP via transients
- **Token generation:** `bin2hex( random_bytes( 16 ) )` for URL-safe tokens
- **Token storage:** Hashed with `wp_hash_password()`, verified with `wp_check_password()` — never store plaintext
- **Resend rate limiting:** Single cooldown transient `regguard_resend_cooldown_{user_id}` (default 5 min)
- **Account cleanup safety:** Only delete `subscriber` and `customer` roles — never admins, editors, shop_managers

## Code Conventions

### PHP Style

- **Namespace:** `Registration_Guard` for all classes
- **No `declare(strict_types=1)`** — breaks WordPress interop
- **Single-Entry Single-Exit (SESE):** Functions should have one return at the end, not early returns
- **Constants for all magic strings/numbers** in `constants.php` — never use raw strings for meta keys, option names, transient keys, etc.
- **Type hints and return types** on all functions (PHP 8.0+)
- **Dates stored as human-readable strings** (`Y-m-d H:i:s T`), not Unix timestamps
- **Boolean options:** `filter_var()` with `FILTER_VALIDATE_BOOLEAN` — never compare against specific strings like `'yes'`

### Template Pattern (Code-First)

ALL template output — views, email templates, settings page callbacks, admin HTML — uses `printf()`/`echo` exclusively. No inline HTML mixed with PHP snippets. This is a hard rule with no exceptions.

```php
// ✅ Correct — code-first
printf(
    '<tr><th scope="row">%s</th><td><input type="text" name="%s" value="%s" /></td></tr>',
    esc_html__( 'Verification Window', 'registration-guard' ),
    esc_attr( OPT_VERIFICATION_WINDOW ),
    esc_attr( get_option( OPT_VERIFICATION_WINDOW, DEF_VERIFICATION_WINDOW ) )
);

// ❌ Wrong — inline HTML with PHP snippets
<tr>
    <th scope="row"><?php esc_html_e( 'Verification Window', 'registration-guard' ); ?></th>
    <td><input type="text" name="<?php echo esc_attr( OPT_VERIFICATION_WINDOW ); ?>" value="<?php echo esc_attr( get_option( OPT_VERIFICATION_WINDOW, DEF_VERIFICATION_WINDOW ) ); ?>" /></td>
</tr>
```

This applies to:
- Settings page field callbacks (`render_field_*` methods)
- Settings page section callbacks
- Admin page rendering
- Email templates in `views/`
- Any HTML output from PHP

### Settings Page (WordPress Settings API)

The settings page uses `register_setting()`, `add_settings_section()`, and `add_settings_field()`. All field render callbacks must use code-first `printf()`/`echo` — not inline HTML.

```php
// ✅ Correct — settings field callback
public function render_field_enabled(): void {
    $value = (bool) filter_var( get_option( OPT_NONCE_ENABLED, DEF_NONCE_ENABLED ), FILTER_VALIDATE_BOOLEAN );
    printf(
        '<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
        esc_attr( OPT_NONCE_ENABLED ),
        checked( $value, true, false ),
        esc_html__( 'Enable JavaScript nonce challenge', 'registration-guard' )
    );
}

// ❌ Wrong — inline HTML in callback
public function render_field_enabled(): void {
    $value = get_option( OPT_NONCE_ENABLED );
    ?>
    <label>
        <input type="checkbox" name="<?php echo esc_attr( OPT_NONCE_ENABLED ); ?>" value="1" <?php checked( $value ); ?> />
        <?php esc_html_e( 'Enable JavaScript nonce challenge', 'registration-guard' ); ?>
    </label>
    <?php
}
```

### Template Variables

Template variables in view files must be prefixed with `regguard_` to comply with WordPress global naming standards (phpcs requirement).

### Email Format

Verification emails are plain text only (`Content-Type: text/plain; charset=UTF-8`). No HTML email templates. This is a permanent decision — plain text has better deliverability and passes anti-spam filters.

### Commit Messages

Format: `type: brief description` where type is one of: `feat:`, `fix:`, `chore:`, `refactor:`, `docs:`, `style:`, `test:`

### Pre-Commit Workflow

1. `phpcs` — check violations
2. `phpcbf` — auto-fix
3. `phpcs` — verify clean
4. Stage and commit

### phpcs Prefixes

```xml
<rule ref="WordPress.NamingConventions.PrefixAllGlobals">
    <properties>
        <property name="prefixes" type="array">
            <element value="registration_guard"/>
            <element value="regguard"/>
            <element value="Registration_Guard"/>
            <element value="REGISTRATION_GUARD"/>
        </property>
    </properties>
</rule>
```

All global-scope identifiers use the `regguard_` prefix consistently — functions, transient keys, meta keys, cron hooks, query parameters, and database table names.

## Key Decisions

Full decision log in `dev-notes/00-project-tracker.md`. Summary:

- **Nonce endpoint:** admin-ajax.php (not REST API) with referer validation
- **Logging:** Custom table `{prefix}regguard_log`, daily pruning, 30-day retention
- **Interstitials:** `wp_die()` with contextual message (HTTP 403)
- **Checkout:** Completely untouched — no JS, no nonce, no validation. Auto-approve user.
- **Existing users:** No meta = verified (no retroactive changes on activation)
- **Email format:** Plain text only (permanent)
- **Skipped contexts:** Admin-created, WP-CLI, REST API, checkout — all write `_regguard_email_verified = true`

## Developer Documentation

Detailed pattern guides live in `dev-notes/patterns/` covering: admin tabs, caching, database, JavaScript, settings API, templates, and WooCommerce integration. The copilot instructions at `.github/copilot-instructions.md` contain comprehensive coding standards.

Project tracker and decision log: `dev-notes/00-project-tracker.md`
