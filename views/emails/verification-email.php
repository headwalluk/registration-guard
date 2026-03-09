<?php
/**
 * Verification Email Template (Plain Text)
 *
 * Template variables (all prefixed with regguard_):
 *
 * @var string $regguard_site_name    Site name.
 * @var string $regguard_display_name User display name.
 * @var string $regguard_verify_url   Verification URL.
 * @var int    $regguard_window_hours Verification window in hours.
 *
 * @package Registration_Guard
 * @since 1.0.0
 */

namespace Registration_Guard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

printf(
	/* translators: %s: site name */
	esc_html__( 'Welcome to %s!', 'registration-guard' ) . "\n\n",
	esc_html( $regguard_site_name )
);

printf(
	/* translators: %s: user display name */
	esc_html__( 'Hi %s,', 'registration-guard' ) . "\n\n",
	esc_html( $regguard_display_name )
);

echo esc_html__( 'Please verify your email address by clicking the link below:', 'registration-guard' ) . "\n\n";

echo esc_url( $regguard_verify_url ) . "\n\n";

printf(
	/* translators: %d: number of hours */
	esc_html__( 'This link will expire in %d hours. If you did not register, you can safely ignore this email.', 'registration-guard' ) . "\n\n",
	intval( $regguard_window_hours )
);

echo "---\n";

printf(
	/* translators: %s: site name */
	esc_html__( 'This email was sent by %s.', 'registration-guard' ) . "\n",
	esc_html( $regguard_site_name )
);
