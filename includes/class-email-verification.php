<?php
/**
 * Email Verification Class
 *
 * @package Registration_Guard
 * @since 1.0.0
 */

namespace Registration_Guard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Email Verification Class.
 *
 * Implements email double opt-in for new registrations. Sends a
 * tokenised verification link, blocks unverified users from wp-admin,
 * and provides a resend mechanism with rate limiting.
 *
 * @since 1.0.0
 */
class Email_Verification {

	/**
	 * AJAX action for resending verification email.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const AJAX_RESEND = 'regguard_resend_verification';

	/**
	 * Handle new user registration.
	 *
	 * Determines whether verification is required and either marks
	 * the user as auto-approved or initiates the verification flow.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id Newly registered user ID.
	 */
	public function handle_registration( int $user_id ): void {
		if ( ! is_double_optin_enabled() ) {
			return;
		}

		if ( $this->should_skip_verification( $user_id ) ) {
			update_user_meta( $user_id, META_EMAIL_VERIFIED, 'true' );
			return;
		}

		update_user_meta( $user_id, META_EMAIL_VERIFIED, 'false' );

		$token = bin2hex( random_bytes( 16 ) );
		update_user_meta( $user_id, META_VERIFICATION_TOKEN, wp_hash_password( $token ) );
		update_user_meta( $user_id, META_TOKEN_CREATED, get_now_formatted() );

		$this->send_verification_email( $user_id, $token );

		get_plugin()->get_logger()->log(
			LOG_VERIFICATION_SENT,
			$user_id,
			__( 'Verification email sent to new user.', 'registration-guard' )
		);
	}

	/**
	 * Handle verification link clicks.
	 *
	 * Parses the query parameter, validates the token, and marks
	 * the user as verified on success.
	 *
	 * @since 1.0.0
	 */
	public function handle_verification_link(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tokenised link, not a form submission.
		$verify_param = isset( $_GET[ QUERY_VERIFY ] ) ? sanitize_text_field( wp_unslash( $_GET[ QUERY_VERIFY ] ) ) : '';

		if ( '' === $verify_param ) {
			return;
		}

		$parts = explode( ':', $verify_param, 2 );
		if ( count( $parts ) !== 2 ) {
			wp_die(
				esc_html__( 'Invalid verification link.', 'registration-guard' ),
				esc_html__( 'Verification Failed', 'registration-guard' ),
				array( 'response' => 400 )
			);
		}

		$user_id = absint( $parts[0] );
		$token   = sanitize_text_field( $parts[1] );

		$is_valid = $this->verify_token( $user_id, $token );

		if ( ! $is_valid ) {
			wp_die(
				esc_html__( 'This verification link is invalid or has expired.', 'registration-guard' ),
				esc_html__( 'Verification Failed', 'registration-guard' ),
				array( 'response' => 400 )
			);
		}

		update_user_meta( $user_id, META_EMAIL_VERIFIED, 'true' );
		delete_user_meta( $user_id, META_VERIFICATION_TOKEN );
		delete_user_meta( $user_id, META_TOKEN_CREATED );

		get_plugin()->get_logger()->log(
			LOG_VERIFICATION_SUCCESS,
			$user_id,
			__( 'Email address verified successfully.', 'registration-guard' )
		);

		wp_safe_redirect( wp_login_url() . '?regguard_verified=1' );
		exit;
	}

	/**
	 * Block unverified users from accessing wp-admin.
	 *
	 * Shows a wp_die() interstitial with resend and guidance.
	 *
	 * @since 1.0.0
	 */
	public function block_unverified_admin(): void {
		if ( ! is_double_optin_enabled() ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			return;
		}

		$user_id  = get_current_user_id();
		$verified = get_user_meta( $user_id, META_EMAIL_VERIFIED, true );

		if ( 'false' !== $verified ) {
			return;
		}

		$resend_url = add_query_arg(
			array(
				'action' => self::AJAX_RESEND,
				'uid'    => $user_id,
			),
			admin_url( 'admin-ajax.php' )
		);

		$message = sprintf(
			'<h2>%s</h2><p>%s</p><p>%s</p><p><a href="%s">%s</a> | <a href="%s">%s</a></p>',
			esc_html__( 'Email Verification Required', 'registration-guard' ),
			esc_html__( 'You must verify your email address before accessing this site. Please check your inbox for the verification email.', 'registration-guard' ),
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
	 * Handle the resend verification email AJAX request.
	 *
	 * @since 1.0.0
	 */
	public function ajax_resend_verification(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- rate-limited resend, no nonce needed for unverified users.
		$user_id = isset( $_GET['uid'] ) ? absint( $_GET['uid'] ) : 0;

		if ( 0 === $user_id ) {
			wp_die(
				esc_html__( 'Invalid request.', 'registration-guard' ),
				esc_html__( 'Error', 'registration-guard' ),
				array( 'response' => 400 )
			);
		}

		$verified = get_user_meta( $user_id, META_EMAIL_VERIFIED, true );
		if ( 'false' !== $verified ) {
			wp_die(
				esc_html__( 'This account is already verified.', 'registration-guard' ),
				esc_html__( 'Already Verified', 'registration-guard' ),
				array( 'response' => 200 )
			);
		}

		$cooldown_key = TRANSIENT_RESEND_COOLDOWN . $user_id;
		if ( false !== get_transient( $cooldown_key ) ) {
			wp_die(
				esc_html__( 'Please wait before requesting another verification email.', 'registration-guard' ),
				esc_html__( 'Please Wait', 'registration-guard' ),
				array( 'response' => 429 )
			);
		}

		$cooldown = (int) get_option( OPT_RESEND_COOLDOWN, DEF_RESEND_COOLDOWN );
		set_transient( $cooldown_key, 1, $cooldown );

		$token = bin2hex( random_bytes( 16 ) );
		update_user_meta( $user_id, META_VERIFICATION_TOKEN, wp_hash_password( $token ) );
		update_user_meta( $user_id, META_TOKEN_CREATED, get_now_formatted() );

		$this->send_verification_email( $user_id, $token );

		get_plugin()->get_logger()->log(
			LOG_VERIFICATION_RESENT,
			$user_id,
			__( 'Verification email resent by user request.', 'registration-guard' )
		);

		wp_die(
			esc_html__( 'A new verification email has been sent. Please check your inbox.', 'registration-guard' ),
			esc_html__( 'Email Sent', 'registration-guard' ),
			array( 'response' => 200 )
		);
	}

	/**
	 * Determine whether verification should be skipped for a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool True if verification should be skipped.
	 */
	private function should_skip_verification( int $user_id ): bool {
		$skip = false;

		if ( current_user_can( 'create_users' ) ) {
			$skip = true;
		} elseif ( defined( 'WP_CLI' ) && WP_CLI ) {
			$skip = true;
		} elseif ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$skip = true;
		} elseif ( did_action( 'woocommerce_checkout_process' ) > 0 ) {
			$skip = true;
		} elseif ( apply_filters( 'registration_guard_skip_verification', false, $user_id ) ) {
			$skip = true;
		}

		return $skip;
	}

	/**
	 * Send the verification email.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Plain text token (not hashed).
	 */
	private function send_verification_email( int $user_id, string $token ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$regguard_site_name    = get_bloginfo( 'name' );
		$regguard_display_name = $user->display_name;
		$regguard_verify_url   = add_query_arg( QUERY_VERIFY, $user_id . ':' . $token, home_url( '/' ) );
		$regguard_window_hours = (int) get_option( OPT_VERIFICATION_WINDOW, DEF_VERIFICATION_WINDOW );

		ob_start();
		include REGISTRATION_GUARD_PATH . 'views/emails/verification-email.php';
		$body = ob_get_clean();

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Please verify your email address', 'registration-guard' ),
			$regguard_site_name
		);

		wp_mail( $user->user_email, $subject, $body, $headers );
	}

	/**
	 * Verify a token against the stored hash.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Plain text token.
	 *
	 * @return bool True if the token is valid.
	 */
	private function verify_token( int $user_id, string $token ): bool {
		$hash   = get_user_meta( $user_id, META_VERIFICATION_TOKEN, true );
		$result = false;

		if ( '' !== $hash && wp_check_password( $token, $hash ) ) {
			$result = true;
		}

		return $result;
	}
}
