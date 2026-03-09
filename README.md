# Registration Guard

[![Version](https://img.shields.io/badge/version-0.2.0-blue.svg)](CHANGELOG.md)
[![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.0+-21759B.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-optional-96588a.svg)](https://woocommerce.com/)
[![License](https://img.shields.io/badge/license-GPL--2.0+-green.svg)](LICENSE)
[![Coding Standards](https://img.shields.io/badge/phpcs-WordPress%20Standards-blue.svg)](https://github.com/WordPress/WordPress-Coding-Standards)

Lightweight bot registration protection for WordPress and WooCommerce. Three layered defences with zero configuration required.

---

## What It Does

Registration Guard blocks automated bot account creation using three independent layers:

1. **JavaScript Nonce Challenge** -- Registration forms require a time-delayed nonce fetched via AJAX. Bots that POST directly to the registration handler without loading the page are blocked.

2. **Email Double Opt-In** -- New registrations must verify their email address via a tokenised link. Unverified accounts are automatically deleted after a configurable window (default: 24 hours).

3. **Geo-Restriction** -- Limit registration to allowed countries or block specific countries. Uses WooCommerce geolocation (requires WooCommerce).

Each layer works independently. Disable any layer you don't need.

---

## Quick Start

1. Upload the `registration-guard` folder to `/wp-content/plugins/`
2. Activate through the WordPress admin
3. Configure at **Settings > Registration Guard** (optional -- sensible defaults are set on activation)

All three defences are enabled by default (geo-restriction requires WooCommerce).

---

## Documentation

| Guide | Audience |
|-------|----------|
| [Getting Started](docs/getting-started.md) | Everyone |
| [WooCommerce Store Guide](docs/woocommerce-guide.md) | Store administrators |
| [Hosting & Server Notes](docs/hosting-notes.md) | System administrators, hosting providers |
| [Contributing](docs/contributing.md) | Developers |

---

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- Working email delivery (`wp_mail`)
- WooCommerce (optional -- required only for geo-restriction)

---

## License

GPL v2 or later. See [LICENSE](LICENSE) file.
