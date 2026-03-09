# Project Tracker

**Version:** 0.4.0-dev
**Last Updated:** 2026-03-09
**Current Phase:** M9 (Polish & Release Prep)
**Overall Progress:** 90%

---

## Overview

Registration Guard is a lightweight WordPress plugin that layers three defences against bot registration: a JavaScript nonce challenge, email double opt-in with auto-cleanup, and geo-restriction (via WooCommerce geolocation). It works with both native WordPress registration and WooCommerce My Account registration.

---

## Active TODO Items

- [x] Resolve open questions
- [x] Create decision log (`dev-notes/01-decisions.md`)
- [x] Create `CLAUDE.md` with project-specific instructions
- [x] Finalise requirement docs before scaffolding code
- [x] Implement all core features (M2-M8)
- [ ] M9: Polish & release prep

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

### M2: Event Logger ✓

- [x] Create `includes/class-logger.php`
  - [x] Custom table `{prefix}regguard_log` via `dbDelta()` on activation
  - [x] Schema: id, event_type, user_id, message, ip_address, created_at
  - [x] `log()` method with auto IP detection
  - [x] Public `query()` method for querying log entries
  - [x] `prune()` method for daily cleanup (batch delete)
  - [x] `drop_table()` for uninstall
- [x] Daily WP-Cron to prune log entries older than 30 days
- [x] Table creation on activation and first-run detection
- [x] Run `phpcs` — clean pass

### M3: JavaScript Nonce Challenge ✓

- [x] Create `includes/class-nonce-challenge.php`
  - [x] Admin-ajax endpoint (`wp_ajax_nopriv_regguard_nonce`)
  - [x] Referer validation — reject requests with no/invalid referer
  - [x] Cache-Control / no-cache headers on endpoint response
  - [x] Rate limiting on nonce endpoint per IP (transient-based)
  - [x] Minimum elapsed time validation (configurable, default 1s)
  - [x] Nonce expiry (5 minutes)
  - [x] Hidden field injection into `register_form` (wp-login.php only)
  - [x] Nonce validation on `registration_errors`
  - [x] Log rejected registrations via Logger
- [x] Create `assets/js/nonce-challenge.js`
  - [x] Wait for DOMContentLoaded + configurable delay
  - [x] AJAX request to admin-ajax.php to fetch nonce
  - [x] Inject nonce into hidden form field
- [x] Settings already implemented in M1
- [x] Run `phpcs` — clean pass

### M4: Email Double Opt-In ✓

- [x] Create `includes/class-email-verification.php`
  - [x] `user_register` hook — skip logic (D4/D5/D7):
    - [x] Skip if `current_user_can( 'create_users' )` (admin-created)
    - [x] Skip if `defined( 'WP_CLI' ) && WP_CLI`
    - [x] Skip if `defined( 'REST_REQUEST' ) && REST_REQUEST`
    - [x] Skip if `did_action( 'woocommerce_checkout_process' ) > 0` (auto-approve)
    - [x] Skip if `registration_guard_skip_verification` filter returns true
  - [x] Set `_regguard_email_verified = false`, generate & store hashed token
  - [x] Send plain text verification email with tokenised link
  - [x] Log `LOG_VERIFICATION_SENT` via Logger
  - [x] `init` hook: handle verification link clicks, validate token
  - [x] On success: set verified, clean up meta, log, redirect to login
  - [x] `admin_init` hook: `wp_die()` interstitial for unverified users
    - [x] Include "Resend verification email" link
    - [x] Include "Check your spam folder" guidance
  - [x] AJAX endpoint for "resend verification email"
  - [x] Rate limiting on resend via cooldown transient
  - [x] Log `LOG_VERIFICATION_RESENT` via Logger
- [x] Create `views/emails/verification-email.php` (plain text template)
- [x] Settings already implemented in M1
- [x] Run `phpcs` — clean pass

### M5: Account Cleanup Cron ✓

- [x] Create `includes/class-account-cleanup.php`
  - [x] WP-Cron scheduled hook (`regguard_cleanup_unverified_accounts`, hourly)
  - [x] Query users where `_regguard_email_verified = false` AND token expired
  - [x] Only delete safe roles (`customer`, `subscriber`)
  - [x] Batch processing (50 per run)
  - [x] Log each deletion via Logger (`LOG_VERIFICATION_EXPIRED`)
- [x] Cleanup cron scheduled on activation, unscheduled on deactivation
- [x] Run `phpcs` — clean pass

### M6: WooCommerce Integration ✓

- [x] Create `includes/class-woocommerce.php` (conditionally loaded)
  - [x] Hidden nonce field injection into `woocommerce_register_form` (My Account only)
  - [x] Nonce validation on `woocommerce_register_post` (My Account only)
  - [x] Script enqueue on My Account pages only
  - [x] `template_redirect` hook: `wp_die()` interstitial for unverified users on My Account
  - [x] Checkout detection and exclusion throughout
- [x] WooCommerce detection and conditional loading in `class-plugin.php`
- [x] Geo-restriction validation on `woocommerce_register_post`
- [x] Run `phpcs` — clean pass

### M7: Geo-Restriction ✓

- [x] Create `includes/class-geo-restriction.php`
  - [x] Country detection via `WC_Geolocation::geolocate_ip()`
  - [x] Allowlist / blocklist mode checking
  - [x] Geo fail action (block or allow, configurable)
  - [x] Validation on `registration_errors` and `woocommerce_register_post`
  - [x] Feature disabled when WooCommerce not active
  - [x] Log blocked registrations via Logger (`LOG_GEO_BLOCKED`)
- [x] Settings already implemented in M1
- [x] Admin notice for WooCommerce requirement
- [x] Run `phpcs` — clean pass

### M8: Uninstall & Activation/Deactivation ✓

- [x] Create `uninstall.php`
  - [x] Delete all `regguard_*` options from `wp_options`
  - [x] Delete all `_regguard_*` user meta
  - [x] Delete all `regguard_*` transients
  - [x] Drop `{prefix}regguard_log` table
  - [x] Unschedule all cron hooks
- [x] Activation hook: set defaults, create log table, schedule crons
- [x] Deactivation hook: unschedule all crons
- [x] Run `phpcs` — clean pass

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
