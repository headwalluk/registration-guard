<?php
/**
 * Plugin Functions
 *
 * Helper functions, config getters, and utilities.
 *
 * @package Registration_Guard
 * @since 1.0.0
 */

namespace Registration_Guard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Get the main plugin instance.
 *
 * @since 1.0.0
 *
 * @return Plugin The plugin instance.
 */
function get_plugin(): Plugin {
	global $regguard_plugin;
	return $regguard_plugin;
}

// =============================================================================
// Settings & Configuration Functions
// =============================================================================

/**
 * Get default plugin settings.
 *
 * @since 1.0.0
 *
 * @return array<string, mixed> Default settings keyed by option name.
 */
function get_default_settings(): array {
	return array(
		OPT_NONCE_ENABLED       => DEF_NONCE_ENABLED,
		OPT_NONCE_MIN_DELAY     => DEF_NONCE_MIN_DELAY,
		OPT_DOUBLE_OPTIN        => DEF_DOUBLE_OPTIN,
		OPT_VERIFICATION_WINDOW => DEF_VERIFICATION_WINDOW,
		OPT_RESEND_COOLDOWN     => DEF_RESEND_COOLDOWN,
		OPT_GEO_ENABLED         => DEF_GEO_ENABLED,
		OPT_GEO_MODE            => DEF_GEO_MODE,
		OPT_GEO_COUNTRIES       => DEF_GEO_COUNTRIES,
		OPT_GEO_FAIL_ACTION     => DEF_GEO_FAIL_ACTION,
	);
}

/**
 * Check if the nonce challenge feature is enabled.
 *
 * @since 1.0.0
 *
 * @return bool True if enabled.
 */
function is_nonce_challenge_enabled(): bool {
	return (bool) filter_var( get_option( OPT_NONCE_ENABLED, DEF_NONCE_ENABLED ), FILTER_VALIDATE_BOOLEAN );
}

/**
 * Check if the double opt-in feature is enabled.
 *
 * @since 1.0.0
 *
 * @return bool True if enabled.
 */
function is_double_optin_enabled(): bool {
	return (bool) filter_var( get_option( OPT_DOUBLE_OPTIN, DEF_DOUBLE_OPTIN ), FILTER_VALIDATE_BOOLEAN );
}

/**
 * Check if geo-restriction is enabled.
 *
 * @since 1.0.0
 *
 * @return bool True if enabled.
 */
function is_geo_enabled(): bool {
	return (bool) filter_var( get_option( OPT_GEO_ENABLED, DEF_GEO_ENABLED ), FILTER_VALIDATE_BOOLEAN );
}

/**
 * Check if a geo-IP provider is available.
 *
 * Returns true if any plugin has hooked into the
 * `registration_guard_geolocate_ip` filter to supply geolocation data.
 *
 * @since 1.0.0
 *
 * @return bool True if a provider is registered.
 */
function is_geo_provider_available(): bool {
	return has_filter( 'registration_guard_geolocate_ip' ) !== false;
}

// =============================================================================
// Utility Functions
// =============================================================================

/**
 * Get current timestamp in human-readable format.
 *
 * @since 1.0.0
 *
 * @param string $format Date format string.
 *
 * @return string Formatted date/time string.
 */
function get_now_formatted( string $format = 'Y-m-d H:i:s T' ): string {
	$now = new \DateTime( 'now', wp_timezone() );
	return $now->format( $format );
}

/**
 * Get client IP address.
 *
 * Uses REMOTE_ADDR by default (not spoofable). Falls back to proxy headers
 * only when REMOTE_ADDR is unavailable (e.g. some containerised environments).
 *
 * Sites behind trusted reverse proxies can override via the
 * `registration_guard_client_ip` filter.
 *
 * @since 1.0.0
 *
 * @return string IP address, or empty string if unavailable.
 */
function get_ip_address(): string {
	$ip = '';

	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		$ips       = explode( ',', $forwarded );
		$ip        = trim( $ips[0] );
	} elseif ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
	}

	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 ) ) {
		$ip = '';
	}

	/**
	 * Filter the detected client IP address.
	 *
	 * Sites behind trusted reverse proxies (e.g. Cloudflare, AWS ALB) can use
	 * this filter to read the real client IP from a trusted header.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ip Detected IP address (from REMOTE_ADDR).
	 */
	$ip = (string) apply_filters( 'registration_guard_client_ip', $ip );

	return $ip;
}

/**
 * Get the full log table name including WordPress prefix.
 *
 * @since 1.0.0
 *
 * @return string Full table name.
 */
function get_log_table_name(): string {
	global $wpdb;
	return $wpdb->prefix . DB_TABLE_LOG;
}
