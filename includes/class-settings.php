<?php
/**
 * Settings Page Class
 *
 * @package Registration_Guard
 * @since 1.0.0
 */

namespace Registration_Guard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Settings Page Class.
 *
 * Registers the admin settings page under Settings menu
 * and all plugin options via the WordPress Settings API.
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * Add settings page to WordPress admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'Registration Guard Settings', 'registration-guard' ),
			__( 'Registration Guard', 'registration-guard' ),
			'manage_options',
			'registration-guard',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings(): void {

		// =====================================================================
		// Nonce Challenge Section
		// =====================================================================

		add_settings_section(
			'regguard_nonce_section',
			__( 'JavaScript Nonce Challenge', 'registration-guard' ),
			array( $this, 'render_nonce_section' ),
			'registration-guard'
		);

		register_setting(
			'regguard_settings',
			OPT_NONCE_ENABLED,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => DEF_NONCE_ENABLED,
			)
		);

		add_settings_field(
			'regguard_nonce_enabled',
			__( 'Enable Nonce Challenge', 'registration-guard' ),
			array( $this, 'render_field_nonce_enabled' ),
			'registration-guard',
			'regguard_nonce_section'
		);

		register_setting(
			'regguard_settings',
			OPT_NONCE_MIN_DELAY,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_nonce_min_delay' ),
				'default'           => DEF_NONCE_MIN_DELAY,
			)
		);

		add_settings_field(
			'regguard_nonce_min_delay',
			__( 'Minimum Delay (seconds)', 'registration-guard' ),
			array( $this, 'render_field_nonce_min_delay' ),
			'registration-guard',
			'regguard_nonce_section'
		);

		// =====================================================================
		// Double Opt-In Section
		// =====================================================================

		add_settings_section(
			'regguard_optin_section',
			__( 'Email Double Opt-In', 'registration-guard' ),
			array( $this, 'render_optin_section' ),
			'registration-guard'
		);

		register_setting(
			'regguard_settings',
			OPT_DOUBLE_OPTIN,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => DEF_DOUBLE_OPTIN,
			)
		);

		add_settings_field(
			'regguard_double_optin',
			__( 'Enable Double Opt-In', 'registration-guard' ),
			array( $this, 'render_field_double_optin' ),
			'registration-guard',
			'regguard_optin_section'
		);

		register_setting(
			'regguard_settings',
			OPT_VERIFICATION_WINDOW,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_verification_window' ),
				'default'           => DEF_VERIFICATION_WINDOW,
			)
		);

		add_settings_field(
			'regguard_verification_window',
			__( 'Verification Window (hours)', 'registration-guard' ),
			array( $this, 'render_field_verification_window' ),
			'registration-guard',
			'regguard_optin_section'
		);

		register_setting(
			'regguard_settings',
			OPT_RESEND_COOLDOWN,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_resend_cooldown' ),
				'default'           => DEF_RESEND_COOLDOWN,
			)
		);

		add_settings_field(
			'regguard_resend_cooldown',
			__( 'Resend Cooldown (seconds)', 'registration-guard' ),
			array( $this, 'render_field_resend_cooldown' ),
			'registration-guard',
			'regguard_optin_section'
		);

		// =====================================================================
		// Geo-Restriction Section
		// =====================================================================

		add_settings_section(
			'regguard_geo_section',
			__( 'Geo-Restriction', 'registration-guard' ),
			array( $this, 'render_geo_section' ),
			'registration-guard'
		);

		register_setting(
			'regguard_settings',
			OPT_GEO_ENABLED,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => DEF_GEO_ENABLED,
			)
		);

		add_settings_field(
			'regguard_geo_enabled',
			__( 'Enable Geo-Restriction', 'registration-guard' ),
			array( $this, 'render_field_geo_enabled' ),
			'registration-guard',
			'regguard_geo_section'
		);

		register_setting(
			'regguard_settings',
			OPT_GEO_MODE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_geo_mode' ),
				'default'           => DEF_GEO_MODE,
			)
		);

		add_settings_field(
			'regguard_geo_mode',
			__( 'Mode', 'registration-guard' ),
			array( $this, 'render_field_geo_mode' ),
			'registration-guard',
			'regguard_geo_section'
		);

		register_setting(
			'regguard_settings',
			OPT_GEO_COUNTRIES,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_geo_countries' ),
				'default'           => DEF_GEO_COUNTRIES,
			)
		);

		add_settings_field(
			'regguard_geo_countries',
			__( 'Country Codes', 'registration-guard' ),
			array( $this, 'render_field_geo_countries' ),
			'registration-guard',
			'regguard_geo_section'
		);

		register_setting(
			'regguard_settings',
			OPT_GEO_FAIL_ACTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_geo_fail_action' ),
				'default'           => DEF_GEO_FAIL_ACTION,
			)
		);

		add_settings_field(
			'regguard_geo_fail_action',
			__( 'If Geolocation Fails', 'registration-guard' ),
			array( $this, 'render_field_geo_fail_action' ),
			'registration-guard',
			'regguard_geo_section'
		);
	}

	// =========================================================================
	// Section Renderers
	// =========================================================================

	/**
	 * Render nonce challenge section description.
	 *
	 * @since 1.0.0
	 */
	public function render_nonce_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Blocks bots that POST directly to the registration handler without loading the page.', 'registration-guard' )
		);
	}

	/**
	 * Render double opt-in section description.
	 *
	 * @since 1.0.0
	 */
	public function render_optin_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Requires new registrations to verify their email address. Unverified accounts are automatically deleted.', 'registration-guard' )
		);
	}

	/**
	 * Render geo-restriction section description.
	 *
	 * @since 1.0.0
	 */
	public function render_geo_section(): void {
		$description = __( 'Restrict registration by country using WooCommerce geolocation.', 'registration-guard' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			$description .= ' ' . __( 'This feature requires WooCommerce and is currently unavailable.', 'registration-guard' );
		}

		printf( '<p>%s</p>', esc_html( $description ) );
	}

	// =========================================================================
	// Field Renderers
	// =========================================================================

	/**
	 * Render nonce enabled checkbox.
	 *
	 * @since 1.0.0
	 */
	public function render_field_nonce_enabled(): void {
		$value = (bool) filter_var( get_option( OPT_NONCE_ENABLED, DEF_NONCE_ENABLED ), FILTER_VALIDATE_BOOLEAN );

		printf(
			'<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
			esc_attr( OPT_NONCE_ENABLED ),
			checked( $value, true, false ),
			esc_html__( 'Require a JavaScript nonce for registration form submissions', 'registration-guard' )
		);
	}

	/**
	 * Render nonce minimum delay field.
	 *
	 * @since 1.0.0
	 */
	public function render_field_nonce_min_delay(): void {
		$value = (int) get_option( OPT_NONCE_MIN_DELAY, DEF_NONCE_MIN_DELAY );

		printf(
			'<input type="number" name="%s" value="%s" min="%s" max="%s" class="small-text" /> <p class="description">%s</p>',
			esc_attr( OPT_NONCE_MIN_DELAY ),
			esc_attr( $value ),
			esc_attr( DEF_NONCE_MIN_DELAY ),
			esc_attr( DEF_NONCE_MAX_DELAY ),
			esc_html__( 'Seconds to wait after page load before the nonce can be fetched (1-10).', 'registration-guard' )
		);
	}

	/**
	 * Render double opt-in enabled checkbox.
	 *
	 * @since 1.0.0
	 */
	public function render_field_double_optin(): void {
		$value = (bool) filter_var( get_option( OPT_DOUBLE_OPTIN, DEF_DOUBLE_OPTIN ), FILTER_VALIDATE_BOOLEAN );

		printf(
			'<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
			esc_attr( OPT_DOUBLE_OPTIN ),
			checked( $value, true, false ),
			esc_html__( 'Require email verification for new registrations', 'registration-guard' )
		);
	}

	/**
	 * Render verification window field.
	 *
	 * @since 1.0.0
	 */
	public function render_field_verification_window(): void {
		$value = (int) get_option( OPT_VERIFICATION_WINDOW, DEF_VERIFICATION_WINDOW );

		printf(
			'<input type="number" name="%s" value="%s" min="1" max="%s" class="small-text" /> <p class="description">%s</p>',
			esc_attr( OPT_VERIFICATION_WINDOW ),
			esc_attr( $value ),
			esc_attr( MAX_VERIFICATION_WINDOW ),
			esc_html__( 'Hours before unverified accounts are automatically deleted (1-72).', 'registration-guard' )
		);
	}

	/**
	 * Render resend cooldown field.
	 *
	 * @since 1.0.0
	 */
	public function render_field_resend_cooldown(): void {
		$value = (int) get_option( OPT_RESEND_COOLDOWN, DEF_RESEND_COOLDOWN );

		printf(
			'<input type="number" name="%s" value="%s" min="%s" max="%s" class="small-text" /> <p class="description">%s</p>',
			esc_attr( OPT_RESEND_COOLDOWN ),
			esc_attr( $value ),
			esc_attr( MINUTE_IN_SECONDS ),
			esc_attr( MAX_RESEND_COOLDOWN ),
			esc_html__( 'Minimum seconds between verification email resends per user (60-3600).', 'registration-guard' )
		);
	}

	/**
	 * Render geo-restriction enabled checkbox.
	 *
	 * @since 1.0.0
	 */
	public function render_field_geo_enabled(): void {
		$value    = (bool) filter_var( get_option( OPT_GEO_ENABLED, DEF_GEO_ENABLED ), FILTER_VALIDATE_BOOLEAN );
		$disabled = ! class_exists( 'WooCommerce' ) ? 'disabled' : '';

		printf(
			'<label><input type="checkbox" name="%s" value="1" %s %s /> %s</label>',
			esc_attr( OPT_GEO_ENABLED ),
			checked( $value, true, false ),
			esc_attr( $disabled ),
			esc_html__( 'Restrict registration by country', 'registration-guard' )
		);
	}

	/**
	 * Render geo mode select.
	 *
	 * @since 1.0.0
	 */
	public function render_field_geo_mode(): void {
		$value = get_option( OPT_GEO_MODE, DEF_GEO_MODE );

		printf(
			'<select name="%s"><option value="%s" %s>%s</option><option value="%s" %s>%s</option></select>',
			esc_attr( OPT_GEO_MODE ),
			esc_attr( GEO_MODE_ALLOWLIST ),
			selected( $value, GEO_MODE_ALLOWLIST, false ),
			esc_html__( 'Allowlist — only allow listed countries', 'registration-guard' ),
			esc_attr( GEO_MODE_BLOCKLIST ),
			selected( $value, GEO_MODE_BLOCKLIST, false ),
			esc_html__( 'Blocklist — block listed countries', 'registration-guard' )
		);
	}

	/**
	 * Render geo countries input.
	 *
	 * @since 1.0.0
	 */
	public function render_field_geo_countries(): void {
		$value = get_option( OPT_GEO_COUNTRIES, DEF_GEO_COUNTRIES );

		printf(
			'<input type="text" name="%s" value="%s" class="regular-text" placeholder="%s" /> <p class="description">%s</p>',
			esc_attr( OPT_GEO_COUNTRIES ),
			esc_attr( $value ),
			esc_attr( 'RU,CN,IR,IN' ),
			esc_html__( 'Comma-separated ISO 3166-1 alpha-2 country codes (e.g. GB,US,FR,DE).', 'registration-guard' )
		);
	}

	/**
	 * Render geo fail action select.
	 *
	 * @since 1.0.0
	 */
	public function render_field_geo_fail_action(): void {
		$value = get_option( OPT_GEO_FAIL_ACTION, DEF_GEO_FAIL_ACTION );

		printf(
			'<select name="%s"><option value="%s" %s>%s</option><option value="%s" %s>%s</option></select> <p class="description">%s</p>',
			esc_attr( OPT_GEO_FAIL_ACTION ),
			esc_attr( GEO_FAIL_BLOCK ),
			selected( $value, GEO_FAIL_BLOCK, false ),
			esc_html__( 'Block registration', 'registration-guard' ),
			esc_attr( GEO_FAIL_ALLOW ),
			selected( $value, GEO_FAIL_ALLOW, false ),
			esc_html__( 'Allow registration', 'registration-guard' ),
			esc_html__( 'Action to take when the visitor\'s country cannot be determined.', 'registration-guard' )
		);
	}

	// =========================================================================
	// Sanitization Callbacks
	// =========================================================================

	/**
	 * Sanitize nonce minimum delay.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Input value.
	 *
	 * @return int Sanitized delay (1-10 seconds).
	 */
	public function sanitize_nonce_min_delay( mixed $value ): int {
		$val    = (int) $value;
		$result = DEF_NONCE_MIN_DELAY;

		if ( $val >= DEF_NONCE_MIN_DELAY && $val <= DEF_NONCE_MAX_DELAY ) {
			$result = $val;
		}

		return $result;
	}

	/**
	 * Sanitize verification window.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Input value.
	 *
	 * @return int Sanitized window (1-72 hours).
	 */
	public function sanitize_verification_window( mixed $value ): int {
		$val    = (int) $value;
		$result = DEF_VERIFICATION_WINDOW;

		if ( $val >= 1 && $val <= MAX_VERIFICATION_WINDOW ) {
			$result = $val;
		}

		return $result;
	}

	/**
	 * Sanitize resend cooldown.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Input value.
	 *
	 * @return int Sanitized cooldown (60-3600 seconds).
	 */
	public function sanitize_resend_cooldown( mixed $value ): int {
		$val    = (int) $value;
		$result = DEF_RESEND_COOLDOWN;

		if ( $val >= MINUTE_IN_SECONDS && $val <= MAX_RESEND_COOLDOWN ) {
			$result = $val;
		}

		return $result;
	}

	/**
	 * Sanitize geo mode.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Input value.
	 *
	 * @return string Sanitized mode.
	 */
	public function sanitize_geo_mode( mixed $value ): string {
		$valid  = array( GEO_MODE_ALLOWLIST, GEO_MODE_BLOCKLIST );
		$result = DEF_GEO_MODE;

		if ( in_array( $value, $valid, true ) ) {
			$result = $value;
		}

		return $result;
	}

	/**
	 * Sanitize geo countries.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Input value.
	 *
	 * @return string Sanitized comma-separated country codes.
	 */
	public function sanitize_geo_countries( mixed $value ): string {
		$raw   = sanitize_text_field( (string) $value );
		$codes = array_map( 'trim', explode( ',', $raw ) );
		$valid = array();

		foreach ( $codes as $code ) {
			$code = strtoupper( $code );
			if ( preg_match( '/^[A-Z]{2}$/', $code ) ) {
				$valid[] = $code;
			}
		}

		return implode( ',', $valid );
	}

	/**
	 * Sanitize geo fail action.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Input value.
	 *
	 * @return string Sanitized fail action.
	 */
	public function sanitize_geo_fail_action( mixed $value ): string {
		$valid  = array( GEO_FAIL_BLOCK, GEO_FAIL_ALLOW );
		$result = DEF_GEO_FAIL_ACTION;

		if ( in_array( $value, $valid, true ) ) {
			$result = $value;
		}

		return $result;
	}

	// =========================================================================
	// Settings Page Renderer
	// =========================================================================

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf( '<div class="wrap">' );
		printf( '<h1>%s</h1>', esc_html( get_admin_page_title() ) );
		printf( '<form method="post" action="options.php">' );

		settings_fields( 'regguard_settings' );
		do_settings_sections( 'registration-guard' );
		submit_button();

		printf( '</form>' );
		printf( '</div>' );
	}
}
