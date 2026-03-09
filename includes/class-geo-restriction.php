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
 * Restricts registration by country using WooCommerce's built-in
 * geolocation. Supports allowlist and blocklist modes with a
 * configurable fail action when geolocation is unavailable.
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
	 * Requires both the setting to be enabled and WooCommerce to be available.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if active.
	 */
	private function is_active(): bool {
		$result = false;

		if ( is_geo_enabled() && class_exists( 'WooCommerce' ) && class_exists( '\WC_Geolocation' ) ) {
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
	 * Get the visitor's country code via WooCommerce geolocation.
	 *
	 * @since 1.0.0
	 *
	 * @return string ISO 3166-1 alpha-2 country code, or empty string if unavailable.
	 */
	private function get_country_code(): string {
		$ip   = get_ip_address();
		$code = '';

		if ( '' !== $ip && class_exists( '\WC_Geolocation' ) ) {
			$geo  = \WC_Geolocation::geolocate_ip( $ip );
			$code = isset( $geo['country'] ) ? strtoupper( $geo['country'] ) : '';
		}

		return $code;
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
