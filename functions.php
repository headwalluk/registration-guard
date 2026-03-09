<?php
/**
 * Public API Functions
 *
 * Global-scope functions for use by third-party plugins, themes,
 * and mu-plugins. These provide a stable public API without
 * requiring knowledge of the plugin's internal namespace.
 *
 * @package Registration_Guard
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Get the main Registration Guard plugin instance.
 *
 * Convenience wrapper around the namespaced `Registration_Guard\get_plugin()`.
 * Use this from themes, mu-plugins, or other plugins that need to interact
 * with Registration Guard's public API.
 *
 * @since 1.0.0
 *
 * @return Registration_Guard\Plugin The plugin instance.
 */
function registration_guard_get_plugin(): Registration_Guard\Plugin {
	return Registration_Guard\get_plugin();
}

/**
 * Check if a geo-IP provider is registered with Registration Guard.
 *
 * Returns true if any plugin has hooked into the
 * `registration_guard_geolocate_ip` filter to supply geolocation data.
 *
 * @since 1.0.0
 *
 * @return bool True if a provider is available.
 */
function registration_guard_has_geo_provider(): bool {
	return Registration_Guard\is_geo_provider_available();
}
