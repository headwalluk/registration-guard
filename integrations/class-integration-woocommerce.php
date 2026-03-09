<?php
/**
 * WooCommerce Integration
 *
 * Conditionally loads WooCommerce-specific functionality when WooCommerce
 * is active. Uses the `plugins_loaded` hook to detect WooCommerce availability.
 *
 * @package Registration_Guard
 * @since 1.0.0
 */

namespace Registration_Guard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * WooCommerce Integration Class.
 *
 * Extends Registration Guard protection to WooCommerce My Account
 * registration forms. Checkout registration is deliberately excluded —
 * accounts created during checkout are auto-approved.
 *
 * @since 1.0.0
 */
class Integration_WooCommerce {

	/**
	 * Bootstrap the WooCommerce integration.
	 *
	 * Called on `plugins_loaded`. Checks whether WooCommerce is active
	 * and registers hooks if so.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$instance = new self();

		add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_script' ) );
		add_action( 'woocommerce_register_form', array( $instance, 'render_nonce_field' ) );
		add_filter( 'woocommerce_register_post', array( $instance, 'validate_nonce' ), 10, 3 );
		add_action( 'template_redirect', array( $instance, 'block_unverified_myaccount' ) );
		add_filter( 'woocommerce_email_enabled_customer_new_account', array( $instance, 'maybe_suppress_new_account_email' ) );
		add_filter( 'registration_guard_verification_redirect_url', array( $instance, 'verification_redirect_url' ), 10, 2 );
		add_filter( 'registration_guard_geolocate_ip', array( $instance, 'geolocate_ip' ), 10, 2 );

		$geo = get_plugin()->get_geo_restriction();
		add_filter( 'woocommerce_register_post', array( $geo, 'validate_woocommerce_registration' ), 10, 3 );
	}

	/**
	 * Resolve an IP address to a country code via WooCommerce geolocation.
	 *
	 * Hooks into `registration_guard_geolocate_ip` to provide geo-IP
	 * data using WooCommerce's built-in `WC_Geolocation` class.
	 *
	 * @since 1.0.0
	 *
	 * @param string $country_code Current country code (empty if no provider has resolved it).
	 * @param string $ip           IP address to look up.
	 *
	 * @return string ISO 3166-1 alpha-2 country code, or empty string.
	 */
	public function geolocate_ip( string $country_code, string $ip ): string {
		if ( '' !== $country_code ) {
			return $country_code;
		}

		if ( ! class_exists( '\WC_Geolocation' ) ) {
			return '';
		}

		$geo  = \WC_Geolocation::geolocate_ip( $ip );
		$code = isset( $geo['country'] ) ? strtoupper( $geo['country'] ) : '';

		return $code;
	}

	/**
	 * Inject a hidden nonce field into the WooCommerce My Account registration form.
	 *
	 * Only renders on the My Account page, never on checkout.
	 *
	 * @since 1.0.0
	 */
	public function render_nonce_field(): void {
		if ( ! is_nonce_challenge_enabled() ) {
			return;
		}

		if ( $this->is_checkout() ) {
			return;
		}

		printf(
			'<input type="hidden" name="%s" value="" />',
			esc_attr( Nonce_Challenge::FIELD_NAME )
		);
	}

	/**
	 * Enqueue the nonce challenge script on WooCommerce My Account pages.
	 *
	 * Only enqueues on the My Account page, never on checkout.
	 * Delegates to the central `Nonce_Challenge::enqueue_nonce_script()`.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_script(): void {
		if ( ! is_nonce_challenge_enabled() ) {
			return;
		}

		if ( $this->is_checkout() ) {
			return;
		}

		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		get_plugin()->get_nonce_challenge()->enqueue_nonce_script();
	}

	/**
	 * Validate the nonce on WooCommerce My Account registration.
	 *
	 * Does not run during checkout. Uses the same nonce verification
	 * logic as the WordPress native registration form.
	 *
	 * @since 1.0.0
	 *
	 * @param string    $username Username.
	 * @param string    $email    Email address.
	 * @param \WP_Error $errors   Validation errors object.
	 *
	 * @return \WP_Error Modified errors object.
	 */
	public function validate_nonce( string $username, string $email, \WP_Error $errors ): \WP_Error {
		if ( $this->is_checkout() ) {
			return $errors;
		}

		if ( ! is_nonce_challenge_enabled() ) {
			return $errors;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- this method IS the nonce verifier.
		$token = isset( $_POST[ Nonce_Challenge::FIELD_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ Nonce_Challenge::FIELD_NAME ] ) ) : '';

		$is_valid = get_plugin()->get_nonce_challenge()->check_nonce_token( $token );

		if ( ! $is_valid ) {
			$errors->add(
				'regguard_nonce_failed',
				__( '<strong>Error:</strong> Security verification failed. Please reload the page and try again.', 'registration-guard' )
			);

			get_plugin()->get_logger()->log(
				LOG_NONCE_REJECTED,
				0,
				__( 'WooCommerce registration blocked: nonce challenge failed.', 'registration-guard' )
			);
		}

		return $errors;
	}

	/**
	 * Block unverified users from accessing WooCommerce My Account pages.
	 *
	 * @since 1.0.0
	 */
	public function block_unverified_myaccount(): void {
		if ( ! is_double_optin_enabled() ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		$user_id  = get_current_user_id();
		$verified = get_user_meta( $user_id, META_EMAIL_VERIFIED, true );

		if ( 'false' !== $verified ) {
			return;
		}

		$resend_url = add_query_arg(
			array(
				'action' => Email_Verification::AJAX_RESEND,
				'uid'    => $user_id,
			),
			admin_url( 'admin-ajax.php' )
		);

		$message = sprintf(
			'<h2>%s</h2><p>%s</p><p>%s</p><p><a href="%s">%s</a> | <a href="%s">%s</a></p>',
			esc_html__( 'Email Verification Required', 'registration-guard' ),
			esc_html__( 'You must verify your email address before accessing your account. Please check your inbox for the verification email.', 'registration-guard' ),
			esc_html__( 'If you cannot find the email, check your spam or junk folder.', 'registration-guard' ),
			esc_url( $resend_url ),
			esc_html__( 'Resend verification email', 'registration-guard' ),
			esc_url( wp_logout_url() ),
			esc_html__( 'Log out', 'registration-guard' )
		);

		wp_die(
			$message, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above in sprintf.
			esc_html__( 'Email Verification Required', 'registration-guard' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Suppress WooCommerce's "new account" email when our double opt-in
	 * is handling verification.
	 *
	 * WooCommerce sends a "set your password" email on registration.
	 * When our double opt-in is active, we send our own verification
	 * email instead — sending both would confuse users. This filter
	 * disables the WooCommerce email for registrations that go through
	 * our verification flow, but leaves it enabled for checkout
	 * registrations and other skip-verification contexts where we
	 * don't send our email.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $enabled Whether the email is enabled.
	 *
	 * @return bool False to suppress, original value otherwise.
	 */
	public function maybe_suppress_new_account_email( bool $enabled ): bool {
		if ( ! $enabled ) {
			return $enabled;
		}

		if ( ! is_double_optin_enabled() ) {
			return $enabled;
		}

		// Don't suppress for contexts where we skip verification
		// (checkout, admin-created, CLI, REST) — Woo's email is
		// the only email those users receive.
		if ( did_action( 'woocommerce_checkout_process' ) > 0 ) {
			return $enabled;
		}

		if ( current_user_can( 'create_users' ) ) {
			return $enabled;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return $enabled;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $enabled;
		}

		return false;
	}

	/**
	 * Redirect verified users to the WooCommerce My Account page.
	 *
	 * Replaces the default wp-login.php redirect with the front-end
	 * My Account login form, which is where WooCommerce users expect
	 * to log in.
	 *
	 * @since 1.0.0
	 *
	 * @param string $redirect_url Default redirect URL.
	 * @param int    $user_id      The verified user's ID.
	 *
	 * @return string Modified redirect URL.
	 */
	public function verification_redirect_url( string $redirect_url, int $user_id ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- filter signature, $user_id available to downstream filters.
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$myaccount_url = wc_get_page_permalink( 'myaccount' );

			if ( $myaccount_url ) {
				$redirect_url = add_query_arg( 'regguard_verified', '1', $myaccount_url );
			}
		}

		return $redirect_url;
	}

	/**
	 * Check if the current request is a WooCommerce checkout.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if on checkout.
	 */
	private function is_checkout(): bool {
		$result = false;

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$result = true;
		} elseif ( did_action( 'woocommerce_checkout_process' ) > 0 ) {
			$result = true;
		}

		return $result;
	}
}

add_action( 'plugins_loaded', array( Integration_WooCommerce::class, 'init' ) );
