# Changelog

All notable changes to Registration Guard will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-03-09

### Added
- Main plugin class with hook registration and conditional WooCommerce loading
- Constants file with all option keys, meta keys, defaults, transient keys, cron hooks, and log event types
- Namespaced helper functions: config getters, IP detection, date formatting
- Admin settings page with three sections (nonce challenge, double opt-in, geo-restriction)
- Activation/deactivation hooks with default option seeding and cron scheduling
- First-run detection for MU plugin installs
- HPOS compatibility declaration for WooCommerce
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
