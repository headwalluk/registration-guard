<?php
/**
 * Geo-Restriction Class
 *
 * @package Registration_Guard
 * @since 1.0.0
 */

namespace Registration_Guard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Geo-Restriction Class.
 *
 * Restricts registration by country. Supports allowlist and blocklist
 * modes with a configurable fail action when geolocation is unavailable.
 * Country lookup is delegated to the `registration_guard_geolocate_ip`
 * filter, which geo-IP providers (e.g. WooCommerce) hook into.
 *
 * @since 1.0.0
 */
class Geo_Restriction {

	/**
	 * Validate registration against geo-restriction rules.
	 *
	 * Hooks into `registration_errors` for WordPress registration.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Error $errors Registration errors object.
	 *
	 * @return \WP_Error Modified errors object.
	 */
	public function validate_registration( \WP_Error $errors ): \WP_Error {
		if ( ! $this->is_active() ) {
			return $errors;
		}

		$is_allowed = $this->check_country();

		if ( ! $is_allowed ) {
			$errors->add(
				'regguard_geo_blocked',
				__( '<strong>Error:</strong> Registration is not available from your location.', 'registration-guard' )
			);

			get_plugin()->get_logger()->log(
				LOG_GEO_BLOCKED,
				0,
				sprintf(
					/* translators: %s: detected country code or "unknown" */
					__( 'Registration blocked by geo-restriction. Country: %s', 'registration-guard' ),
					$this->get_country_code()
				)
			);
		}

		return $errors;
	}

	/**
	 * Validate WooCommerce registration against geo-restriction rules.
	 *
	 * Hooks into `woocommerce_register_post` for My Account registration.
	 *
	 * @since 1.0.0
	 *
	 * @param string    $username   Username.
	 * @param string    $email      Email address.
	 * @param \WP_Error $errors    Validation errors object.
	 *
	 * @return \WP_Error Modified errors object.
	 */
	public function validate_woocommerce_registration( string $username, string $email, \WP_Error $errors ): \WP_Error {
		return $this->validate_registration( $errors );
	}

	/**
	 * Check whether geo-restriction is active.
	 *
	 * Requires both the setting to be enabled and a geo-IP provider
	 * to be available.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if active.
	 */
	private function is_active(): bool {
		$result = false;

		if ( is_geo_enabled() && is_geo_provider_available() ) {
			$result = true;
		}

		return $result;
	}

	/**
	 * Check whether the visitor's country is allowed to register.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if registration is allowed.
	 */
	private function check_country(): bool {
		$country_code = $this->get_country_code();
		$mode         = get_option( OPT_GEO_MODE, DEF_GEO_MODE );
		$countries    = $this->get_country_list();
		$fail_action  = get_option( OPT_GEO_FAIL_ACTION, DEF_GEO_FAIL_ACTION );

		if ( '' === $country_code ) {
			$result = ( GEO_FAIL_ALLOW === $fail_action );
			return $result;
		}

		if ( GEO_MODE_ALLOWLIST === $mode ) {
			$result = in_array( $country_code, $countries, true );
		} else {
			$result = ! in_array( $country_code, $countries, true );
		}

		return $result;
	}

	/**
	 * Get the visitor's country code via a geo-IP provider.
	 *
	 * Delegates to the `registration_guard_geolocate_ip` filter so
	 * any plugin can supply geolocation data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ip Optional. IP address to look up. Defaults to the current visitor's IP.
	 *
	 * @return string ISO 3166-1 alpha-2 country code, or empty string if unavailable.
	 */
	public function get_country_code( string $ip = '' ): string {
		if ( '' === $ip ) {
			$ip = get_ip_address();
		}

		if ( '' === $ip ) {
			return '';
		}

		/**
		 * Resolve an IP address to a two-letter country code.
		 *
		 * Geo-IP providers hook into this filter to supply country
		 * data. Return an uppercase ISO 3166-1 alpha-2 code (e.g. "GB")
		 * or an empty string if the IP cannot be resolved.
		 *
		 * @since 1.0.0
		 *
		 * @param string $country_code Country code (empty by default).
		 * @param string $ip           IP address to look up.
		 */
		$code = apply_filters( 'registration_guard_geolocate_ip', '', $ip );

		return strtoupper( (string) $code );
	}

	/**
	 * Get the configured country list as an array.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of uppercase country codes.
	 */
	private function get_country_list(): array {
		$raw   = get_option( OPT_GEO_COUNTRIES, DEF_GEO_COUNTRIES );
		$codes = array_map( 'trim', explode( ',', $raw ) );
		$codes = array_map( 'strtoupper', $codes );
		$codes = array_filter( $codes );

		return array_values( $codes );
	}
}
