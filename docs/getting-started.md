# Getting Started

## Installation

### From WordPress Admin

1. Go to **Plugins > Add New**
2. Search for "Registration Guard"
3. Click **Install Now**, then **Activate**

### Manual Upload

1. Download the plugin zip file
2. Upload the `registration-guard` folder to `/wp-content/plugins/`
3. Activate through the **Plugins** menu

### Verify It's Working

After activation, visit **Settings > Registration Guard**. All three protection layers are shown with their current status.

---

## Default Configuration

Registration Guard works immediately with sensible defaults:

| Feature | Default | Notes |
|---------|---------|-------|
| JavaScript Nonce Challenge | Enabled | Minimum delay: 1 second |
| Email Double Opt-In | Enabled | 24-hour verification window |
| Geo-Restriction | Disabled | Requires WooCommerce |
| Resend Cooldown | 5 minutes | Time between verification email resends |

Most sites won't need to change any settings.

---

## How Each Layer Works

### Layer 1: JavaScript Nonce Challenge

When a visitor loads your registration page, the plugin waits a short delay (default: 1 second) then fetches a time-limited nonce via AJAX. This nonce is injected into a hidden form field.

When the form is submitted, the server validates the nonce. If it's missing or invalid, registration is blocked.

**Why it works:** Bots typically POST directly to the registration handler without loading the page. No page load means no JavaScript execution, which means no nonce.

**User impact:** None. The process is invisible to legitimate users.

### Layer 2: Email Double Opt-In

After a successful registration, the new user receives a plain text email with a verification link. They must click the link within the verification window (default: 24 hours) to activate their account.

Until verified:
- Access to wp-admin is blocked with a "Please verify your email" message
- Access to WooCommerce My Account is blocked (if WooCommerce is active)
- The message includes a "Resend verification email" link

After the verification window expires, unverified accounts are automatically deleted. Only `subscriber` and `customer` roles are deleted -- administrator and editor accounts are never auto-deleted.

**User impact:** One extra step -- clicking a link in an email. This is a familiar pattern that most users expect.

### Layer 3: Geo-Restriction

Limit registrations by country. Choose between:
- **Allowlist:** Only allow registrations from specified countries
- **Blocklist:** Block registrations from specified countries

Country detection uses WooCommerce's built-in `WC_Geolocation` class. This feature is only available when WooCommerce is active.

If geolocation fails (e.g. the IP can't be resolved), you can configure whether to block or allow the registration.

---

## What Happens to Existing Users?

Nothing. Registration Guard only applies to new registrations that occur after the plugin is activated. Existing user accounts are unaffected and will never be asked to verify their email.

---

## Disabling the Plugin

If you need to disable Registration Guard:

1. Go to **Plugins** in the WordPress admin
2. Click **Deactivate** under Registration Guard

All protection is immediately removed. User accounts and verification statuses are preserved -- if you reactivate the plugin later, previously verified users remain verified.

To completely remove all plugin data, delete the plugin from the **Plugins** page (not just deactivate).

---

## Next Steps

- **WooCommerce stores:** See [WooCommerce Store Guide](woocommerce-guide.md)
- **Hosting providers:** See [Hosting & Server Notes](hosting-notes.md)
- **Developers:** See [Contributing](contributing.md)
