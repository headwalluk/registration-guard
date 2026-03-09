# Registration Guard — Project Specification

**Plugin Name:** Registration Guard
**Slug:** `registration-guard`
**Namespace:** `Registration_Guard`
**Text Domain:** `registration-guard`
**Prefix:** `rg_` / `registration_guard` / `regguard`
**Package:** `Registration_Guard`
**Requirements:** PHP 8.0+, WordPress 6.0+, WooCommerce optional (features degrade gracefully)
**Author:** Paul Faulkner
**License:** GPL v2 or later

---

## Problem Statement

WordPress and WooCommerce sites with public user registration enabled are targeted by botnets that mass-create sleeper accounts. These accounts sit dormant until a plugin vulnerability is disclosed, then the bot operator exploits it using the pre-created accounts. reCaptcha is the common mitigation but it has poor UX and customers frequently mismanage API keys.

## Solution

A lightweight plugin that layers three defences against bot registration:

1. **JavaScript Nonce Challenge** — registration form submissions require a nonce fetched via AJAX after page load, defeating dumb POST-based bots
2. **Email Double Opt-In** — new registrations must verify their email via a tokenised link; unverified accounts are auto-deleted after a configurable window
3. **Geo-Restriction** — limit registration to allowed countries or block specific countries (WooCommerce geolocation when available)

---

## Feature Specifications

### Feature 1: JavaScript Nonce Challenge

**Goal:** Prevent bot submissions that POST directly to the registration handler without loading the page.

**How it works:**
1. On pages containing a registration form, enqueue `assets/js/nonce-challenge.js`
2. The JS waits a short delay after DOMContentLoaded (1-2 seconds), then makes an AJAX request to a REST endpoint or `admin-ajax.php` to fetch a time-limited nonce
3. The nonce is injected into a hidden field in the registration form
4. On form submission, the server validates the nonce. Missing or invalid nonce = rejection with a user-friendly error message
5. The nonce endpoint itself should validate that the request includes a page-load timestamp and enforce a minimum elapsed time (e.g., 1 second) to prevent scripted nonce harvesting

**Hook points:**
- `wp_enqueue_scripts` — enqueue JS on pages with registration forms
- `woocommerce_register_form` — add hidden nonce field to Woo registration form
- `register_form` — add hidden nonce field to WordPress registration form
- `wp_ajax_nopriv_rg_nonce` or REST API — nonce generation endpoint
- `woocommerce_register_post` / `registration_errors` — validate nonce on submission

**Security considerations:**
- Nonces should be short-lived (5 minutes)
- The endpoint should not be cacheable (appropriate `Cache-Control` headers)
- Rate-limit the nonce endpoint per IP (use transients)

**Settings:**
- Enable/disable nonce challenge (default: enabled)
- Minimum delay before nonce is fetchable (default: 1 second)

---

### Feature 2: Email Double Opt-In

**Goal:** Ensure the registering user controls the email address they provide. Auto-delete accounts that never verify.

**How it works:**
1. On successful registration, mark the user with meta `_rg_email_verified = false` and `_rg_verification_token` (hashed) and `_rg_token_created` (timestamp as `Y-m-d H:i:s T`)
2. Send a verification email with a tokenised link: `site.com/?rg_verify={user_id}:{token}`
3. When the user clicks the link, validate the token (using `wp_check_password()` against the stored hash), mark `_rg_email_verified = true`, clean up token meta
4. If an unverified user tries to access WooCommerce My Account pages or wp-admin, show an interstitial: "Please verify your email. [Resend verification email]"
5. A WP-Cron job runs periodically (hourly) to delete user accounts where `_rg_email_verified = false` AND `_rg_token_created` is older than the configured window

**Hook points:**
- `user_register` — set unverified meta, generate token, send email
- `template_redirect` — intercept unverified users accessing My Account (WooCommerce)
- `admin_init` — intercept unverified users accessing wp-admin
- `init` — handle `?rg_verify=` verification link clicks
- WP-Cron — scheduled cleanup of expired unverified accounts
- Custom AJAX endpoint — "resend verification email" handler

**Token security:**
- Tokens generated with `wp_generate_password( 32, false )` for URL safety, or `bin2hex( random_bytes( 16 ) )`
- Stored as hash via `wp_hash_password()`, verified with `wp_check_password()`
- Never store plaintext tokens in the database
- Tokens are single-use: consumed on verification

**Rate limiting (resend):**
- Max 3 resend requests per 15 minutes per user
- Use transient: `rg_resend_limit_{user_id}`

**Settings:**
- Enable/disable double opt-in (default: enabled)
- Verification window before auto-deletion (default: 24 hours, range: 1-72 hours)
- Resend rate limit max attempts (default: 3)
- Resend rate limit window (default: 15 minutes)

**Auto-deletion safety:**
- Only delete users with the `customer` or `subscriber` role — never delete administrators, editors, shop_managers, etc.
- Log deletions (option or custom log)
- The cron job should process in batches (e.g., 50 at a time) to avoid timeouts

---

### Feature 3: Geo-Restriction

**Goal:** Restrict registration to specific countries or block registrations from specific countries.

**How it works:**
1. On registration submission, determine the user's country
2. Check against the configured allow-list or block-list
3. If blocked, reject registration with a generic error ("Registration is not available in your region")

**Country detection:**
- If WooCommerce is active: use `WC_Geolocation::geolocate_ip()` which returns a country code
- If WooCommerce is not active: this feature is disabled (do not bundle a GeoIP database). Show a notice in settings: "Geo-restriction requires WooCommerce for IP geolocation"

**Settings:**
- Enable/disable geo-restriction (default: disabled)
- Mode: `allowlist` or `blocklist`
- Country codes: comma-separated ISO 3166-1 alpha-2 codes (e.g., `GB,US,FR,DE`)
- Default blocklist suggestion (shown as placeholder): `RU,CN,IR,IN`

**Hook points:**
- `woocommerce_register_post` / `registration_errors` — check country on registration
- Settings page — country code input with validation

---

## Architecture

### File Structure

```
registration-guard/
├── registration-guard.php          # Main plugin file, bootstrap, class loading
├── constants.php                   # All constants: meta keys, option keys, defaults
├── functions.php                   # Helper functions, config getters
├── uninstall.php                   # Clean removal of all plugin data
├── phpcs.xml                       # WordPress coding standards config
├── CLAUDE.md                       # AI assistant instructions
├── CHANGELOG.md                    # Release history
├── readme.txt                      # WordPress.org readme
├── includes/
│   ├── class-plugin.php            # Main orchestrator: hook registration, init
│   ├── class-nonce-challenge.php   # AJAX nonce endpoint, form field injection, validation
│   ├── class-email-verification.php # Double opt-in: tokens, emails, verification links
│   ├── class-account-cleanup.php   # Cron job: delete expired unverified accounts
│   ├── class-geo-restriction.php   # Country allow/block list checking
│   ├── class-settings.php          # Admin settings page under Settings menu
│   └── class-woocommerce.php       # Woo-specific hooks, conditionally loaded
├── assets/
│   ├── js/
│   │   └── nonce-challenge.js      # Front-end AJAX nonce fetcher
│   └── css/
│       └── verification-notice.css # Styling for email verification interstitial
└── views/
    ├── verification-required.php   # "Please verify your email" interstitial page
    └── emails/
        └── verification-email.php  # Verification email HTML template
```

### Design Principles

Carry forward from Quick 2FA:

1. **Minimal Attack Surface** — no custom rewrite rules or endpoints where possible; use existing WordPress/Woo pages
2. **Fail Secure** — if nonce validation fails, block registration. If geo-lookup fails, apply the configured default (block or allow — make this a setting)
3. **No Theme Interference** — interstitial pages should work regardless of theme
4. **Non-Breaking** — never interfere with existing logged-in users, admin operations, REST API, WP-CLI, cron, or AJAX

### WooCommerce Conditional Loading

```php
// In class-plugin.php or bootstrap
if ( class_exists( 'WooCommerce' ) ) {
    require_once RG_PATH . 'includes/class-woocommerce.php';
}
```

Features without WooCommerce:
- Nonce challenge: works (hooks into `register_form` on wp-login.php)
- Double opt-in: works (hooks into `user_register`, interstitial on `admin_init`)
- Geo-restriction: disabled (requires Woo's `WC_Geolocation`)

### Data Storage

**User meta:**
| Key | Type | Purpose |
|-----|------|---------|
| `_rg_email_verified` | `bool` string | Whether user has verified email |
| `_rg_verification_token` | `string` | Hashed verification token |
| `_rg_token_created` | `string` | Token creation datetime (`Y-m-d H:i:s T`) |

**Options (`wp_options`):**
| Key | Type | Default | Purpose |
|-----|------|---------|---------|
| `regguard_nonce_challenge_enabled` | `bool` | `true` | Enable JS nonce challenge |
| `regguard_nonce_min_delay` | `int` | `1` | Seconds before nonce is fetchable |
| `regguard_double_optin_enabled` | `bool` | `true` | Enable email double opt-in |
| `regguard_verification_window` | `int` | `24` | Hours before unverified accounts deleted |
| `regguard_resend_limit_max` | `int` | `3` | Max resend attempts per window |
| `regguard_resend_limit_window` | `int` | `900` | Resend rate limit window (seconds) |
| `regguard_geo_enabled` | `bool` | `false` | Enable geo-restriction |
| `regguard_geo_mode` | `string` | `blocklist` | `allowlist` or `blocklist` |
| `regguard_geo_countries` | `string` | `''` | Comma-separated country codes |
| `regguard_geo_fail_action` | `string` | `block` | What to do if geolocation fails: `block` or `allow` |
| `regguard_version` | `string` | `''` | Installed plugin version |

**Transients:**
| Key Pattern | Expiry | Purpose |
|-------------|--------|---------|
| `rg_resend_limit_{user_id}` | 15 min | Rate limit resend verification emails |
| `rg_nonce_rate_{ip_hash}` | 5 min | Rate limit nonce endpoint per IP |

**Cron:**
| Hook | Schedule | Purpose |
|------|----------|---------|
| `rg_cleanup_unverified_accounts` | Hourly | Delete unverified accounts past verification window |

---

## Code Conventions

Carry these forward exactly from Quick 2FA:

- **Namespace:** `Registration_Guard`
- **No `declare(strict_types=1)`** — breaks WordPress interop
- **Single-Entry Single-Exit (SESE):** one return per function, at the end
- **Constants for all magic strings/numbers** in `constants.php`
- **Type hints and return types** on all functions (PHP 8.0+)
- **Dates as human-readable strings** (`Y-m-d H:i:s T`), not timestamps
- **Template pattern:** `printf()`/`echo` only, no inline HTML mixed with PHP
- **Template variables** prefixed with `rg_` for phpcs global naming compliance
- **Boolean options:** `filter_var()` with `FILTER_VALIDATE_BOOLEAN`
- **Commit messages:** `type: brief description` (feat/fix/chore/refactor/docs/style/test)
- **Pre-commit:** `phpcs` → `phpcbf` → `phpcs` → stage → commit
- **No inline JS** — all JavaScript in external files under `assets/js/`
- **Security:** tokens hashed with `wp_hash_password()`, verified with `wp_check_password()`, generated with `random_bytes()` or `random_int()`

### phpcs.xml Prefixes

```xml
<rule ref="WordPress.NamingConventions.PrefixAllGlobals">
    <properties>
        <property name="prefixes" type="array">
            <element value="registration_guard"/>
            <element value="regguard"/>
            <element value="rg"/>
            <element value="Registration_Guard"/>
            <element value="REGISTRATION_GUARD"/>
        </property>
    </properties>
</rule>
```

---

## Implementation Order

### Phase 1: Scaffold & Nonce Challenge
1. Create plugin bootstrap (`registration-guard.php`, `constants.php`, `functions.php`)
2. Create `phpcs.xml` with WordPress standards
3. Create `CLAUDE.md` for the new plugin
4. Implement `class-plugin.php` (singleton, hook registration)
5. Implement `class-nonce-challenge.php` (AJAX endpoint, form hooks, validation)
6. Create `assets/js/nonce-challenge.js`
7. Implement `class-settings.php` (start with nonce challenge settings only)
8. Test with WordPress registration form (`wp-login.php?action=register`)

### Phase 2: Email Double Opt-In
1. Implement `class-email-verification.php` (token generation, verification link handler, resend handler)
2. Create email template `views/emails/verification-email.php`
3. Create interstitial template `views/verification-required.php`
4. Implement `class-account-cleanup.php` (cron job)
5. Add double opt-in settings to settings page
6. Test full flow: register → receive email → click link → access granted

### Phase 3: WooCommerce Integration
1. Implement `class-woocommerce.php` (Woo-specific form hooks, My Account interception)
2. Add WooCommerce detection and conditional loading
3. Test with WooCommerce My Account registration form

### Phase 4: Geo-Restriction
1. Implement `class-geo-restriction.php` (country checking with `WC_Geolocation`)
2. Add geo-restriction settings to settings page
3. Test allow-list and block-list modes

### Phase 5: Polish
1. `uninstall.php` — clean removal of all options, user meta, transients, cron jobs
2. Activation/deactivation hooks (schedule/unschedule cron, set defaults)
3. Admin notices (e.g., "Geo-restriction requires WooCommerce")
4. i18n — ensure all strings use text domain `registration-guard`
5. Final `phpcs` pass

---

## Edge Cases & Gotchas

1. **Existing users on activation:** Don't retroactively mark existing users as unverified. Only apply double opt-in to registrations that occur after the plugin is activated.

2. **WooCommerce checkout registration:** WooCommerce allows registration during checkout. These users should probably be auto-verified (they're completing a purchase with a real email). Make this configurable: "Skip email verification for checkout registrations" (default: yes). Hook: `woocommerce_checkout_process` or check for `woocommerce_checkout` context.

3. **Admin-created users:** Users created via wp-admin Users > Add New should be auto-verified. Check `current_user_can('create_users')` in the `user_register` hook.

4. **WP-CLI user creation:** `wp user create` should not trigger verification. Check `defined('WP_CLI') && WP_CLI`.

5. **REST API user creation:** Consider whether REST API user creation should trigger verification. Probably not — it requires authentication already.

6. **Programmatic user creation:** Plugins that create users (membership plugins, etc.) — provide a filter: `registration_guard_skip_verification` that other plugins can hook into.

7. **Multisite:** For v1, target single-site only. Multisite network activation can be a future enhancement.

8. **Cron reliability:** WordPress cron depends on site traffic. For high-volume sites this is fine. For low-traffic sites, unverified accounts might persist slightly longer than the configured window. This is acceptable — it's a cleanup task, not a security-critical timer.

9. **Email deliverability:** If verification emails don't arrive, users are stuck. The resend mechanism and the interstitial page with clear messaging are important. Consider showing "check your spam folder" guidance.

10. **Nonce challenge + page caching:** Full-page caching (Varnish, WP Super Cache, etc.) could cache the registration page without a nonce. The JS fetches the nonce via AJAX (which bypasses page cache), so this should work. But document this: the AJAX endpoint must not be cached.

---

## Reference: Quick 2FA Patterns

This plugin should follow the same patterns established in Quick 2FA. Key reference files:

- **Bootstrap pattern:** `quick-2fa/quick-2fa.php` — singleton init, manual class loading, conditional CLI loading
- **Constants pattern:** `quick-2fa/constants.php` — namespaced constants for all keys
- **Settings pattern:** `quick-2fa/includes/class-settings.php` — WordPress Settings API usage
- **Security pattern:** `quick-2fa/includes/class-verification-code-handler.php` — hashing, rate limiting
- **Email pattern:** `quick-2fa/includes/class-email-handler.php` — template-based email sending
- **phpcs config:** `quick-2fa/phpcs.xml` — WordPress standards with prefix configuration
