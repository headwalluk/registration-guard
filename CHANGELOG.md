# Changelog

All notable changes to Registration Guard will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
