# WooCommerce Store Guide

Registration Guard is designed to work seamlessly with WooCommerce. This guide covers WooCommerce-specific behaviour and configuration.

---

## How It Works With WooCommerce

### My Account Registration

The WooCommerce My Account page (`/my-account/`) includes a registration form. Registration Guard protects this form with:

- **JavaScript nonce challenge** -- same invisible protection as the WordPress registration form
- **Email double opt-in** -- same verification email flow

An unverified user who tries to access My Account pages will see a "Please verify your email" message with a resend link.

### Checkout Registration

**Registration Guard does not interfere with checkout in any way.**

If your store creates user accounts during checkout (either automatically or via the "Create an account?" checkbox), those accounts are **automatically approved**. No nonce challenge, no verification email, no blocking.

The payment itself acts as proof that a real person is completing the registration. This is a deliberate design decision -- disrupting checkout to verify an email would risk lost sales.

Technically, checkout-created accounts have their verification status set to "verified" immediately, so they will never be prompted for email verification.

### Geo-Restriction

When WooCommerce is active, the geo-restriction feature becomes available. It uses `WC_Geolocation::geolocate_ip()` to determine the visitor's country.

**Without WooCommerce, geo-restriction is not available.** The plugin does not bundle its own GeoIP database. A notice on the settings page explains this.

---

## Recommended Settings for WooCommerce Stores

### If you sell internationally

- **Geo-restriction:** Disabled (default), or use a blocklist for countries you definitely don't serve
- **Geo fail action:** Allow -- don't block customers whose IP can't be resolved

### If you sell to specific countries only

- **Geo-restriction:** Enabled, allowlist mode
- **Country codes:** List the countries you sell to (e.g. `GB,US,IE,AU,NZ`)
- **Geo fail action:** Your choice -- "block" is safer, "allow" reduces false positives

### General recommendations

- **Nonce challenge:** Leave enabled. It's invisible to customers and blocks the majority of bots.
- **Verification window:** 24 hours is suitable for most stores. Increase to 48-72 hours if your customer base is in regions with unreliable email delivery.
- **Resend cooldown:** 5 minutes is a reasonable default.

---

## Things That Are NOT Affected

Registration Guard does not interfere with:

- Checkout process (payment, order creation, account creation)
- WooCommerce REST API (`/wc/v3/` endpoints)
- Webhooks
- WooCommerce admin operations
- Order processing and fulfilment
- Customer account management by shop managers
- Any WooCommerce cron jobs or background processes

---

## HPOS Compatibility

Registration Guard is compatible with WooCommerce High-Performance Order Storage (HPOS). The plugin does not access order data, so there are no HPOS-related concerns.

---

## Troubleshooting

### "Registration is not available in your region"

This message appears when geo-restriction blocks a registration. Check your country code list and mode (allowlist vs blocklist) at **Settings > Registration Guard**.

### Customers not receiving verification emails

This is usually an email delivery issue, not a Registration Guard issue. Check:

1. Your site's general email delivery (try the [WP Mail SMTP](https://wordpress.org/plugins/wp-mail-smtp/) plugin)
2. Customer spam/junk folders
3. Whether your domain has SPF, DKIM, and DMARC records configured

### Verified customers still being blocked

If a customer verified their email but is still seeing the interstitial, check their user meta in the database. The `_rg_email_verified` meta value should be `true`. If it's missing or `false`, you can manually set it to `true` via the WordPress admin or WP-CLI.
