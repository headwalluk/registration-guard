# Project Tracker

**Version:** 0.3.0-dev
**Last Updated:** 2026-03-09
**Current Phase:** M2 (Event Logger)
**Overall Progress:** 15%

---

## Overview

Registration Guard is a lightweight WordPress plugin that layers three defences against bot registration: a JavaScript nonce challenge, email double opt-in with auto-cleanup, and geo-restriction (via WooCommerce geolocation). It works with both native WordPress registration and WooCommerce My Account registration.

---

## Active TODO Items

- [x] Resolve open questions
- [x] Create decision log (`dev-notes/01-decisions.md`)
- [x] Create `CLAUDE.md` with project-specific instructions
- [x] Finalise requirement docs before scaffolding code

---

## Milestones

### M0: Planning & Requirements ✓

- [x] Resolve all open questions (see Decision Log below)
- [x] Create `dev-notes/01-decisions.md` with full decision log
- [x] Create `CLAUDE.md` with project-specific instructions
- [x] Review Quick 2FA reference patterns for reuse

### M1: Scaffold & Bootstrap ✓

- [x] Create `registration-guard.php` (plugin header, bootstrap, class loading)
- [x] Create `constants.php` (all option keys, meta keys, defaults, transient key patterns, DB table name)
- [x] Create `functions-private.php` (namespaced helper functions, config getters)
- [x] Create `phpcs.xml` (WordPress standards, prefix config)
- [x] Create `includes/class-plugin.php` (orchestrator, hook registration, conditional WooCommerce loading)
- [x] Create `includes/class-settings.php` (settings page with three sections, nine fields)
- [x] Verify bootstrap loads cleanly (no errors on activation)
- [x] Run `phpcs` — clean pass

### M2: Event Logger

- [ ] Create `includes/class-logger.php`
  - [ ] Custom table `{prefix}regguard_log` via `dbDelta()` on activation
  - [ ] Schema: `id` (bigint auto), `event_type` (varchar), `user_id` (bigint), `message` (text), `ip_address` (varchar), `created_at` (datetime)
  - [ ] `log( string $event_type, int $user_id, string $message, string $ip = '' ): void`
  - [ ] Public method for querying log entries (for future admin UI)
- [ ] Daily WP-Cron to prune log entries older than 30 days
  - [ ] Cron hook: `regguard_prune_event_log`
  - [ ] Batch delete (1000 per run) to avoid timeouts
- [ ] Schedule prune cron on activation, unschedule on deactivation
- [ ] Define event type constants in `constants.php`
  - [ ] `LOG_USER_REGISTERED` — new user entered verification flow
  - [ ] `LOG_VERIFICATION_SENT` — double opt-in email sent
  - [ ] `LOG_VERIFICATION_RESENT` — user requested resend
  - [ ] `LOG_VERIFICATION_SUCCESS` — user clicked verification link
  - [ ] `LOG_VERIFICATION_EXPIRED` — unverified account auto-deleted
  - [ ] `LOG_NONCE_REJECTED` — registration blocked by nonce challenge
  - [ ] `LOG_GEO_BLOCKED` — registration blocked by geo-restriction
  - [ ] `LOG_CHECKOUT_AUTOAPPROVED` — checkout registration auto-approved
- [ ] Run `phpcs` — clean pass

### M3: JavaScript Nonce Challenge

- [ ] Create `includes/class-nonce-challenge.php`
  - [ ] Admin-ajax endpoint (`wp_ajax_nopriv_regguard_nonce`)
  - [ ] Referer validation — reject requests with no/invalid referer (do not issue nonce)
  - [ ] Cache-Control / no-cache headers on endpoint response
  - [ ] Rate limiting on nonce endpoint per IP (transient-based)
  - [ ] Minimum elapsed time validation (configurable, default 1s)
  - [ ] Nonce expiry (5 minutes)
  - [ ] Hidden field injection into `register_form` (wp-login.php only)
  - [ ] Nonce validation on `registration_errors`
  - [ ] Log rejected registrations via Logger
- [ ] Create `assets/js/nonce-challenge.js`
  - [ ] Wait for DOMContentLoaded + configurable delay
  - [ ] AJAX request to admin-ajax.php to fetch nonce
  - [ ] Inject nonce into hidden form field
- [ ] Add nonce challenge settings to `class-settings.php`
  - [ ] Enable/disable toggle (default: enabled)
  - [ ] Minimum delay setting (default: 1 second)
- [ ] Test with WordPress native registration form
- [ ] Run `phpcs` — clean pass

### M4: Email Double Opt-In

- [ ] Create `includes/class-email-verification.php`
  - [ ] `user_register` hook — skip logic (see Decision Log D4/D5/D7):
    - [ ] Skip if `current_user_can( 'create_users' )` (admin-created)
    - [ ] Skip if `defined( 'WP_CLI' ) && WP_CLI`
    - [ ] Skip if `defined( 'REST_REQUEST' ) && REST_REQUEST`
    - [ ] Skip if `did_action( 'woocommerce_checkout_process' ) > 0` (checkout — write `_regguard_email_verified = true`)
    - [ ] Skip if `registration_guard_skip_verification` filter returns true
  - [ ] For non-skipped: set `_regguard_email_verified = false`, generate & store hashed token, store `_regguard_token_created`
  - [ ] Send plain text verification email with tokenised link (`?regguard_verify={user_id}:{token}`)
  - [ ] Log `LOG_VERIFICATION_SENT` via Logger
  - [ ] `init` hook: handle `?regguard_verify=` link clicks, validate token with `wp_check_password()`
  - [ ] On success: set `_regguard_email_verified = true`, clean up token meta, log `LOG_VERIFICATION_SUCCESS`
  - [ ] `admin_init` hook: `wp_die()` interstitial for unverified users accessing wp-admin
    - [ ] Include "Resend verification email" link
    - [ ] Include "Check your spam folder" guidance
  - [ ] AJAX endpoint for "resend verification email"
  - [ ] Rate limiting on resend: single cooldown transient `regguard_resend_cooldown_{user_id}` (default: 5 minutes)
  - [ ] Log `LOG_VERIFICATION_RESENT` via Logger
- [ ] Create `views/emails/verification-email.php` (plain text template)
- [ ] Add double opt-in settings to `class-settings.php`
  - [ ] Enable/disable toggle (default: enabled)
  - [ ] Verification window before auto-deletion (default: 24 hours, range: 1-72)
  - [ ] Resend cooldown (default: 5 minutes)
- [ ] Run `phpcs` — clean pass

### M5: Account Cleanup Cron

- [ ] Create `includes/class-account-cleanup.php`
  - [ ] WP-Cron scheduled hook (`regguard_cleanup_unverified_accounts`, hourly)
  - [ ] Query users where `_regguard_email_verified = false` AND `_regguard_token_created` older than verification window
  - [ ] Only delete safe roles (`customer`, `subscriber`) — never admins, editors, shop_managers, etc.
  - [ ] Batch processing (50 per run) to avoid timeouts
  - [ ] Log each deletion via Logger (`LOG_VERIFICATION_EXPIRED`)
- [ ] Schedule cleanup cron on plugin activation
- [ ] Unschedule cleanup cron on plugin deactivation
- [ ] Run `phpcs` — clean pass

### M6: WooCommerce Integration

- [ ] Create `includes/class-woocommerce.php` (conditionally loaded when WooCommerce active)
  - [ ] Hidden nonce field injection into `woocommerce_register_form` (My Account only — NOT checkout)
  - [ ] Nonce validation on `woocommerce_register_post` (My Account only — NOT checkout)
  - [ ] `template_redirect` hook: `wp_die()` interstitial for unverified users on My Account pages
  - [ ] HPOS compatibility declaration
- [ ] Add WooCommerce detection and conditional loading in `class-plugin.php`
- [ ] Test with WooCommerce My Account registration form
- [ ] Test checkout registration auto-approval (user created with `_regguard_email_verified = true`)
- [ ] Test that checkout flow is completely unaffected (no JS, no nonce, no blocking)
- [ ] Run `phpcs` — clean pass

### M7: Geo-Restriction

- [ ] Create `includes/class-geo-restriction.php`
  - [ ] Country detection via `WC_Geolocation::geolocate_ip()`
  - [ ] Allowlist / blocklist mode checking
  - [ ] Geo fail action (block or allow, configurable)
  - [ ] Validation on `registration_errors` (and `woocommerce_register_post` if Woo active)
  - [ ] Feature disabled when WooCommerce not active (no bundled GeoIP)
  - [ ] Log blocked registrations via Logger (`LOG_GEO_BLOCKED`)
- [ ] Add geo-restriction settings to `class-settings.php`
  - [ ] Enable/disable toggle (default: disabled)
  - [ ] Mode selector: allowlist / blocklist
  - [ ] Country codes input (comma-separated ISO 3166-1 alpha-2, placeholder: `RU,CN,IR,IN`)
  - [ ] Geo fail action: block or allow
  - [ ] Admin notice: "Geo-restriction requires WooCommerce" when Woo not active
- [ ] Test allowlist mode
- [ ] Test blocklist mode
- [ ] Test geo-lookup failure handling
- [ ] Run `phpcs` — clean pass

### M8: Uninstall & Activation/Deactivation

- [ ] Create `uninstall.php`
  - [ ] Delete all `regguard_*` options from `wp_options`
  - [ ] Delete all `_regguard_*` user meta
  - [ ] Delete all `regguard_*` transients
  - [ ] Drop `{prefix}regguard_log` table
  - [ ] Unschedule `regguard_cleanup_unverified_accounts` cron
  - [ ] Unschedule `regguard_prune_event_log` cron
- [ ] Activation hook: set default options, create log table, schedule crons, store `regguard_version`
- [ ] Deactivation hook: unschedule all crons
- [ ] Run `phpcs` — clean pass

### M9: Polish & Release Prep

- [ ] i18n audit — all user-facing strings use text domain `registration-guard`
- [ ] Admin notices (e.g., "Geo-restriction requires WooCommerce")
- [ ] Final `phpcs` pass across entire codebase
- [ ] Create `readme.txt` (WordPress.org format)
- [ ] Create `CHANGELOG.md`
- [ ] Bump version to 1.0.0
- [ ] Tag release

---

## Decision Log

Resolved 2026-03-09:

| ID | Question | Decision | Rationale |
|----|----------|----------|-----------|
| D1 | Nonce endpoint: REST API vs admin-ajax? | **admin-ajax.php** with referer validation | REST API is already abused by bots. Admin-ajax is universally excluded by page caches and reverse proxies. Endpoint must reject requests with no/invalid referer — don't even issue a nonce. |
| D2 | Deletion logging mechanism? | **Custom table** (`{prefix}regguard_log`) | Logs key events (verification sent, account deleted, nonce rejected, geo blocked, etc.). Daily cron prunes entries older than 30 days. Simple schema: event_type, user_id, message, ip, timestamp. |
| D3 | Interstitial for unverified users? | **`wp_die()`** with contextual message | Works regardless of theme. Include "Resend verification email" link and "Check your spam folder" guidance. HTTP 403 response code. |
| D4 | Nonce challenge on checkout? | **No — dedicated registration forms only** | Checkout must be completely unaffected. No JS enqueued, no nonce fields, no backend validation. Checkout has its own CSRF nonce. Risk of disrupting sales is unacceptable. |
| D5 | Existing users on activation? | **No meta = verified** (no retroactive changes) | Don't write meta to existing users. Verification check logic: `_regguard_email_verified` exists AND equals `false` → block. No meta → pass through. This also handles pre-existing users naturally. |
| D6 | Verification email format? | **Plain text only** (permanent decision) | Better deliverability, passes anti-spam filters, simpler. No HTML version planned. Content-Type: `text/plain; charset=UTF-8`. |
| D7 | REST API / admin user creation? | **Exclude from verification** | REST API requires `create_users` cap (WordPress) or API key auth (WooCommerce). Detect via `REST_REQUEST` constant. Admin-created users detected via `current_user_can('create_users')`. WP-CLI detected via `WP_CLI` constant. All write `_regguard_email_verified = true` to distinguish from legacy users. |

Additional decisions from discussion:

| ID | Question | Decision | Rationale |
|----|----------|----------|-----------|
| D8 | Checkout registration handling? | **Auto-approve** — write `_regguard_email_verified = true` | Detect via `did_action( 'woocommerce_checkout_process' ) > 0`. Payment acts as proof of legitimacy. Write explicit `true` meta to distinguish from legacy "no meta" users. |
| D9 | Resend rate limiting approach? | **Single cooldown** (default 5 minutes) | Simpler than max-attempts-per-window. One transient `regguard_resend_cooldown_{user_id}` with TTL = cooldown setting. If transient exists, block resend. One setting instead of two. |
| D10 | Email change re-verification? | **Not in v1** (conscious exclusion) | Primary threat model is bot account creation, not email changes. Would add significant complexity. Can revisit in v2 if needed. |
| D11 | Password reset for unverified users? | **Not blocked in v1** (conscious exclusion) | Low priority — unverified accounts are auto-deleted anyway. Could be a spam vector but risk is minimal. Can revisit if it becomes a real problem. |
| D12 | Verification meta states? | **Three-state model** | No meta → pre-existing/legacy user (treat as verified). `_regguard_email_verified = true` → explicitly verified or auto-approved. `_regguard_email_verified = false` → pending verification (block access). |
| D13 | WooCommerce REST API users? | **Covered by `REST_REQUEST` constant** | WooCommerce REST API (`/wc/v3/customers`) uses its own auth but still sets the `REST_REQUEST` constant. No separate detection needed. |

---

## Conscious v1 Exclusions

- **Multisite support** — single-site only
- **Email change re-verification** (D10)
- **Password reset blocking for unverified users** (D11)
- **Bundled GeoIP database** — geo-restriction requires WooCommerce
- **HTML verification emails** — plain text only (D6, permanent)
- **Nonce challenge on checkout** — checkout is completely untouched (D4, permanent)

---

## Technical Debt

_None yet — greenfield project._

---

## Notes for Development

- Reference Quick 2FA patterns at `/var/www/devx.headwall.tech/web/wp-content/plugins/quick-2fa/` for bootstrap, constants, settings, security, and email patterns.
- Quick 2FA uses plain text emails with `Content-Type: text/plain; charset=UTF-8` — follow this pattern.
- Quick 2FA uses `wp_die()` for security blocks and full HTML renders for login-flow pages — we use `wp_die()` for interstitials.
- WooCommerce features degrade gracefully — the plugin must activate and function without WooCommerce.
- Target single-site WordPress only for v1 (no multisite support).
- PHP 8.0+ required — use type hints, union types, nullable types throughout.
