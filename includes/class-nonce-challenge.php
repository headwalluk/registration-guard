<?php
/**
 * Nonce Challenge Class
 *
 * @package Registration_Guard
 * @since 1.0.0
 */

namespace Registration_Guard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Nonce Challenge Class.
 *
 * Provides a JavaScript nonce challenge for registration forms.
 * Bots that POST directly without loading the page are blocked.
 *
 * @since 1.0.0
 */
class Nonce_Challenge {

	/**
	 * AJAX action name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const AJAX_ACTION = 'regguard_nonce';

	/**
	 * Hidden form field name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const FIELD_NAME = 'regguard_nonce_token';

	/**
	 * Enqueue the nonce challenge script on pages with registration forms.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_script(): void {
		if ( ! is_nonce_challenge_enabled() ) {
			return;
		}

		wp_enqueue_script(
			'regguard-nonce-challenge',
			REGISTRATION_GUARD_URL . 'assets/js/nonce-challenge.js',
			array(),
			REGISTRATION_GUARD_VERSION,
			true
		);

		wp_localize_script(
			'regguard-nonce-challenge',
			'regguardNonce',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'action'    => self::AJAX_ACTION,
				'fieldName' => self::FIELD_NAME,
				'delay'     => (int) get_option( OPT_NONCE_MIN_DELAY, DEF_NONCE_MIN_DELAY ),
			)
		);
	}

	/**
	 * Inject a hidden nonce field into the WordPress registration form.
	 *
	 * @since 1.0.0
	 */
	public function render_nonce_field(): void {
		if ( ! is_nonce_challenge_enabled() ) {
			return;
		}

		printf(
			'<input type="hidden" name="%s" value="" />',
			esc_attr( self::FIELD_NAME )
		);
	}

	/**
	 * Handle the AJAX nonce request.
	 *
	 * Validates the referer, checks rate limits, enforces minimum
	 * elapsed time, and returns a time-limited nonce.
	 *
	 * @since 1.0.0
	 */
	public function ajax_generate_nonce(): void {
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$referer = wp_get_referer();
		if ( ! $referer || ! wp_validate_redirect( $referer, false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid referer.' ), 403 );
		}

		$ip = get_ip_address();
		if ( '' !== $ip && $this->is_rate_limited( $ip ) ) {
			wp_send_json_error( array( 'message' => 'Rate limit exceeded.' ), 429 );
		}

		if ( '' !== $ip ) {
			$this->increment_rate_counter( $ip );
		}

		$nonce     = wp_create_nonce( self::AJAX_ACTION );
		$issued_at = time();

		set_transient(
			'regguard_nonce_issued_' . $nonce,
			$issued_at,
			NONCE_EXPIRY
		);

		wp_send_json_success( array( 'nonce' => $nonce ) );
	}

	/**
	 * Validate the nonce on registration submission.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Error $errors Registration errors object.
	 *
	 * @return \WP_Error Modified errors object.
	 */
	public function validate_nonce( \WP_Error $errors ): \WP_Error {
		if ( ! is_nonce_challenge_enabled() ) {
			return $errors;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- this method IS the nonce verifier.
		$token = isset( $_POST[ self::FIELD_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::FIELD_NAME ] ) ) : '';

		$is_valid = $this->check_nonce_token( $token );

		if ( ! $is_valid ) {
			$errors->add(
				'regguard_nonce_failed',
				__( '<strong>Error:</strong> Security verification failed. Please reload the page and try again.', 'registration-guard' )
			);

			get_plugin()->get_logger()->log(
				LOG_NONCE_REJECTED,
				0,
				__( 'Registration blocked: nonce challenge failed.', 'registration-guard' )
			);
		}

		return $errors;
	}

	/**
	 * Check whether a nonce token is valid.
	 *
	 * Verifies the WordPress nonce, checks that it was issued by our
	 * endpoint, and enforces the minimum elapsed time.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token The nonce token from the form submission.
	 *
	 * @return bool True if the token is valid.
	 */
	private function check_nonce_token( string $token ): bool {
		$result = false;

		if ( '' !== $token && false !== wp_verify_nonce( $token, self::AJAX_ACTION ) ) {
			$issued_at = get_transient( 'regguard_nonce_issued_' . $token );

			if ( false !== $issued_at ) {
				$min_delay = (int) get_option( OPT_NONCE_MIN_DELAY, DEF_NONCE_MIN_DELAY );
				$elapsed   = time() - (int) $issued_at;

				if ( $elapsed >= $min_delay ) {
					$result = true;
				}

				delete_transient( 'regguard_nonce_issued_' . $token );
			}
		}

		return $result;
	}

	/**
	 * Check if an IP address has exceeded the rate limit.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ip IP address.
	 *
	 * @return bool True if rate limited.
	 */
	private function is_rate_limited( string $ip ): bool {
		$key   = TRANSIENT_NONCE_RATE . md5( $ip );
		$count = (int) get_transient( $key );

		return $count >= RATE_LIMIT_NONCE_MAX;
	}

	/**
	 * Increment the rate limit counter for an IP address.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ip IP address.
	 */
	private function increment_rate_counter( string $ip ): void {
		$key   = TRANSIENT_NONCE_RATE . md5( $ip );
		$count = (int) get_transient( $key );

		if ( 0 === $count ) {
			set_transient( $key, 1, RATE_LIMIT_NONCE_WINDOW );
		} else {
			set_transient( $key, $count + 1, RATE_LIMIT_NONCE_WINDOW );
		}
	}
}
