=== Registration Guard ===
Contributors: paulfaulkner
Tags: security, registration, anti-spam, bot-protection, woocommerce
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight bot registration protection for WordPress and WooCommerce. Three layered defences with zero configuration required.

== Description ==

Registration Guard protects your WordPress and WooCommerce site from automated bot account creation. Botnets mass-create sleeper accounts on sites with public registration, then exploit them when plugin vulnerabilities are disclosed. Registration Guard stops this without requiring reCAPTCHA or complex configuration.

= Three Layers of Protection =

* **JavaScript Nonce Challenge** -- Registration forms require a time-delayed nonce fetched via AJAX after the page loads. Bots that POST directly to the registration handler without loading the page are blocked automatically.
* **Email Double Opt-In** -- New registrations must verify their email address by clicking a tokenised link. Unverified accounts are automatically deleted after a configurable window (default: 24 hours).
* **Geo-Restriction** -- Limit registration to allowed countries or block specific countries using IP geolocation. Requires a geo-IP provider (e.g. WooCommerce, or any plugin that hooks `registration_guard_geolocate_ip`).

Each layer works independently. Enable or disable any combination to suit your site.

= WooCommerce Compatible =

* Protects WooCommerce My Account registration forms
* Checkout registrations are auto-approved (payment acts as verification)
* Geo-restriction uses pluggable IP geolocation (WooCommerce or custom provider)
* Works perfectly without WooCommerce (geo-restriction requires a separate geo-IP provider)

= Non-Breaking Design =

Registration Guard never interferes with:

* Existing logged-in users
* Admin-created user accounts
* WP-CLI user creation
* REST API operations
* WooCommerce checkout (completely untouched)
* Cron jobs and background processes

= Perfect For =

* WooCommerce stores targeted by bot registrations
* WordPress sites with open registration
* Hosting providers deploying to client sites
* Anyone who wants bot protection without reCAPTCHA

== Installation ==

= Automatic Installation =

1. Go to Plugins > Add New
2. Search for "Registration Guard"
3. Click "Install Now" and then "Activate"
4. Plugin works immediately with sensible defaults

= Manual Installation =

1. Download the plugin zip file
2. Upload to `/wp-content/plugins/` and extract
3. Activate through the Plugins menu
4. Configure at Settings > Registration Guard (optional)

== Frequently Asked Questions ==

= Does this replace reCAPTCHA? =

Yes. Registration Guard provides bot protection without the poor user experience of CAPTCHA challenges. The JavaScript nonce challenge is invisible to legitimate users, and email verification is a familiar pattern.

= Does this work without WooCommerce? =

Yes. The nonce challenge and email double opt-in work on the standard WordPress registration form at `wp-login.php?action=register`. Only the geo-restriction feature requires WooCommerce (for IP geolocation).

= What happens to existing users when I activate the plugin? =

Nothing. Existing user accounts are not affected. The plugin only applies to new registrations that occur after activation.

= Will this break WooCommerce checkout? =

No. Registration Guard does not inject any JavaScript, nonce fields, or validation logic into the checkout process. If a user account is created during checkout, it is automatically approved -- the payment itself acts as proof of legitimacy.

= What happens if a user doesn't verify their email? =

Unverified accounts are automatically deleted after the configured verification window (default: 24 hours). Only accounts with the `subscriber` or `customer` role are eligible for auto-deletion -- administrator, editor, and shop manager accounts are never deleted.

= Can users resend the verification email? =

Yes. If an unverified user tries to access wp-admin or WooCommerce My Account, they see a message with a "Resend verification email" link. Resends are rate-limited (default: one every 5 minutes).

= What if the verification email doesn't arrive? =

The interstitial page includes "check your spam folder" guidance and a resend link. If emails consistently fail, check your site's email delivery configuration. Registration Guard uses standard `wp_mail()`.

= Does this work with page caching? =

Yes. The nonce is fetched via an AJAX request to `admin-ajax.php`, which bypasses page caching. The registration page itself can be safely cached.

= Can other plugins bypass verification? =

Yes. Registration Guard provides a `registration_guard_skip_verification` filter that other plugins can use to bypass email verification for specific registrations.

== Screenshots ==

1. Settings page -- Configure all three protection layers
2. Verification email -- Clean plain text email with tokenised link
3. Interstitial page -- Shown to unverified users attempting to access wp-admin

== Changelog ==

= 1.0.0 =
* Stable release -- all features complete and tested
* Translations: 8 language packs (DE, EL, EN_GB, ES, FR, IT, NL, PL)
* GitHub Actions release workflow

= 0.6.0 =
* Integration architecture: WooCommerce moved to `integrations/` with `plugins_loaded` convention
* Pluggable geo-IP provider model via `registration_guard_geolocate_ip` filter
* Geo-restriction no longer hardcoded to WooCommerce -- any geo-IP source works
* Settings page hides geo-restriction fields when no provider is available
* Suppress duplicate registration emails (WordPress core and WooCommerce)
* Post-verification redirect to password reset form (set your password flow)
* WooCommerce: redirect verified users to My Account instead of wp-login.php
* Centralised nonce script enqueue with `registration_guard_nonce_script_data` filter
* Event log IP enrichment with country flags (Unicode, zero dependencies)
* Filterable event log rows via `registration_guard_log_row`
* Public API: `functions.php` with `registration_guard_get_plugin()` and `registration_guard_has_geo_provider()`
* New docs: integration guide, geo-IP provider guide

= 0.5.0 =
* Tabbed admin page with Settings and Event Log tabs
* Robust geo-restriction country code sanitisation
* Updated default blocked countries placeholder

= 0.4.0 =
* Event logger with custom database table and daily pruning
* JavaScript nonce challenge for registration form protection
* Email double opt-in with tokenised verification links
* Automatic cleanup of expired unverified accounts
* WooCommerce My Account registration protection
* Geo-restriction via WooCommerce geolocation
* Clean uninstall handler

= 0.3.0 =
* Centralise hook registration in Plugin class
* Replace magic numbers with named constants
* Remove unnecessary HPOS compatibility declaration

= 0.2.0 =
* Bootstrap and settings page implementation
* Main plugin class, constants, helper functions
* Admin settings page with three sections (nonce challenge, double opt-in, geo-restriction)
* Activation/deactivation hooks with cron scheduling

= 0.1.0 =
* Initial development release
* Project scaffolding and documentation

== Upgrade Notice ==

= 1.0.0 =
First stable release with all three protection layers, WooCommerce integration, and pluggable geo-IP support.

= 0.6.0 =
Integration architecture, pluggable geo-IP providers, and single-email registration flow.

= 0.5.0 =
Tabbed admin page and improved country code sanitisation.

= 0.4.0 =
All three protection layers implemented with full WooCommerce integration.

= 0.3.0 =
Code quality improvements and cleanup.

= 0.2.0 =
Bootstrap and settings page implementation.

= 0.1.0 =
Initial development release.

== Privacy Policy ==

Registration Guard stores the following data:

* **User meta:** Email verification status, hashed verification tokens, token creation timestamps
* **Event log:** Registration events, verification events, blocked registrations (custom database table, pruned after 30 days)
* **Transients:** Rate limiting data for nonce endpoint and email resend (short-lived, auto-expiring)

Verification tokens are hashed and never stored in plain text. IP addresses are logged in the event log for security auditing. No data is sent to external services.
