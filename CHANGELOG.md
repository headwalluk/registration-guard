# Changelog

All notable changes to Registration Guard will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-03-09

### Added
- Translations: `.pot` file and 8 language packs (DE, EL, EN_GB, ES, FR, IT, NL, PL)
- GitHub Actions release workflow with `.distignore`
- `registration_guard_client_ip` filter for sites behind trusted reverse proxies
- CSRF protection (WordPress nonce) on resend verification email endpoint

### Changed
- Stable release — all features complete and tested
- IP detection now uses `REMOTE_ADDR` first, proxy headers as fallback only (prevents IP spoofing)
- Resend verification endpoint restricted to authenticated users only

## [0.6.0] - 2026-03-09

### Added
- Integration architecture: `integrations/` directory with `plugins_loaded` convention for third-party plugin support
- Pluggable geo-IP provider model via `registration_guard_geolocate_ip` filter — any plugin can supply geolocation data
- Settings page shows informational notice when no geo-IP provider is available (hides geo fields)
- Suppress WordPress core "set your password" email when double opt-in is active
- Suppress WooCommerce "new account" email when double opt-in is active
- Post-verification redirect to WordPress password reset form (single-email registration flow)
- WooCommerce: redirect verified users to My Account page instead of wp-login.php
- Centralised `Nonce_Challenge::enqueue_nonce_script()` for integrations to reuse
- `registration_guard_nonce_script_data` filter for integrations to adjust front-end nonce config
- `registration_guard_verification_redirect_url` filter for integrations to customise post-verification redirect
- `registration_guard_log_row` filter for customising event log table rows
- Event log IP address enrichment with Unicode country flags when geo-IP is available
- Public API file `functions.php` with `registration_guard_get_plugin()` and `registration_guard_has_geo_provider()`
- `Nonce_Challenge::check_nonce_token()` made public for integration reuse
- `Geo_Restriction::get_country_code()` made public with optional IP parameter
- `Plugin::get_nonce_challenge()` and `Plugin::get_geo_restriction()` accessors
- Documentation: integration guide (`docs/integration-guide.md`)
- Documentation: geo-IP provider guide (`docs/geo-ip-providers.md`)

### Changed
- WooCommerce integration moved from `includes/class-woocommerce.php` to `integrations/class-integration-woocommerce.php`
- Geo-restriction no longer hardcoded to WooCommerce — uses filter-based provider model
- Geo-restriction `is_active()` checks for any geo-IP provider, not just WooCommerce
- Admin notice for geo-restriction updated to reference generic geo-IP providers
- Geo-restriction section description updated ("IP geolocation" instead of "WooCommerce geolocation")

### Removed
- Direct `WC_Geolocation` calls from `Geo_Restriction` class (replaced by filter)
- `Plugin::load_woocommerce()` method (replaced by integration architecture)

## [0.5.0] - 2026-03-09

### Added
- Tabbed admin page with Settings and Event Log tabs (hash-based navigation with deep linking)
- Event log viewer showing last 100 entries in a WordPress-native table
- Admin JS for tab switching with browser back/forward support

### Changed
- Geo-restriction country code sanitisation: split on commas and whitespace, deduplicate, sort alphabetically
- Default blocked countries placeholder updated to `BY,IQ,IR,KP,RU,SG`
- Country codes placeholder moved to `PLACEHOLDER_GEO_COUNTRIES` constant

## [0.4.0] - 2026-03-09

### Added
- Event logger with custom `{prefix}regguard_log` table, `dbDelta()` creation, and daily pruning cron
- JavaScript nonce challenge: admin-ajax endpoint with referer validation, rate limiting, minimum elapsed time, and front-end script
- Email double opt-in: tokenised verification links, hashed token storage, wp_die() interstitial, resend with rate limiting
- Skip logic for admin-created users, WP-CLI, REST API, checkout registrations, and custom filter
- Plain text verification email template
- Account cleanup cron: hourly batch deletion of expired unverified accounts (safe roles only)
- WooCommerce My Account integration: nonce field, nonce validation, unverified user blocking (checkout excluded)
- Geo-restriction: allowlist/blocklist modes via `WC_Geolocation`, configurable fail action
- Admin notice when geo-restriction enabled without WooCommerce
- Clean uninstall handler: removes all options, user meta, transients, log table, and cron hooks

## [0.3.0] - 2026-03-09

### Changed
- Centralise all hook registration in `Plugin::run()` instead of individual classes
- Replace magic numbers with named constants and WordPress time constants (`MINUTE_IN_SECONDS`, `HOUR_IN_SECONDS`)

### Removed
- HPOS compatibility declaration (plugin does not interact with WooCommerce orders)
- `Settings::run()` method (hooks now registered by Plugin class)

## [0.2.0] - 2026-03-09

### Added
- Main plugin class with hook registration and conditional WooCommerce loading
- Constants file with all option keys, meta keys, defaults, transient keys, cron hooks, and log event types
- Namespaced helper functions: config getters, IP detection, date formatting
- Admin settings page with three sections (nonce challenge, double opt-in, geo-restriction)
- Activation/deactivation hooks with default option seeding and cron scheduling
- First-run detection for MU plugin installs
- `get_plugin()` accessor function with global variable pattern

## [0.1.0] - 2026-03-09

### Added
- Project scaffolding and documentation
- `CLAUDE.md` with project-specific AI assistant instructions
- `README.md` with badges and documentation links
- `readme.txt` for WordPress.org plugin repository
- Project tracker with milestones M0-M9
- Decision log with 13 resolved architectural decisions
- Documentation for store admins, hosting providers, and contributors
