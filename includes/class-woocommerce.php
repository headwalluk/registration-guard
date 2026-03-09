<?php
/**
 * WooCommerce Integration Class
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
class WooCommerce {

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
				'action'    => Nonce_Challenge::AJAX_ACTION,
				'fieldName' => Nonce_Challenge::FIELD_NAME,
				'delay'     => (int) get_option( OPT_NONCE_MIN_DELAY, DEF_NONCE_MIN_DELAY ),
			)
		);
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

		$is_valid = false;

		if ( '' !== $token && false !== wp_verify_nonce( $token, Nonce_Challenge::AJAX_ACTION ) ) {
			$issued_at = get_transient( 'regguard_nonce_issued_' . $token );

			if ( false !== $issued_at ) {
				$min_delay = (int) get_option( OPT_NONCE_MIN_DELAY, DEF_NONCE_MIN_DELAY );
				$elapsed   = time() - (int) $issued_at;

				if ( $elapsed >= $min_delay ) {
					$is_valid = true;
				}

				delete_transient( 'regguard_nonce_issued_' . $token );
			}
		}

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
