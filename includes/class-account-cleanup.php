<?php
/**
 * Account Cleanup Class
 *
 * @package Registration_Guard
 * @since 1.0.0
 */

namespace Registration_Guard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Account Cleanup Class.
 *
 * Deletes unverified user accounts that have exceeded the
 * verification window. Runs via hourly WP-Cron.
 *
 * @since 1.0.0
 */
class Account_Cleanup {

	/**
	 * Run the cleanup of expired unverified accounts.
	 *
	 * Queries users with `_regguard_email_verified = false` whose
	 * token was created longer ago than the verification window.
	 * Only deletes safe roles (subscriber, customer) in batches.
	 *
	 * @since 1.0.0
	 */
	public function cleanup(): void {
		if ( ! is_double_optin_enabled() ) {
			return;
		}

		$window_hours = (int) get_option( OPT_VERIFICATION_WINDOW, DEF_VERIFICATION_WINDOW );
		$cutoff       = new \DateTime( 'now', wp_timezone() );
		$cutoff->sub( new \DateInterval( 'PT' . $window_hours . 'H' ) );
		$cutoff_str = $cutoff->format( 'Y-m-d H:i:s T' );

		$users = get_users(
			array(
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- necessary for verification state check.
					'relation' => 'AND',
					array(
						'key'   => META_EMAIL_VERIFIED,
						'value' => 'false',
					),
					array(
						'key'     => META_TOKEN_CREATED,
						'value'   => $cutoff_str,
						'compare' => '<',
						'type'    => 'CHAR',
					),
				),
				'number'      => CLEANUP_BATCH_SIZE,
				'count_total' => false,
			)
		);

		$logger = get_plugin()->get_logger();

		foreach ( $users as $user ) {
			$has_safe_role = $this->has_safe_role( $user );

			if ( $has_safe_role ) {
				$logger->log(
					LOG_VERIFICATION_EXPIRED,
					$user->ID,
					sprintf(
						/* translators: %s: user email */
						__( 'Unverified account deleted: %s', 'registration-guard' ),
						$user->user_email
					)
				);

				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $user->ID );
			}
		}
	}

	/**
	 * Check if a user has only safe roles that can be auto-deleted.
	 *
	 * Returns true only if ALL of the user's roles are in the
	 * safe roles list. A user with both 'subscriber' and 'editor'
	 * roles would not be deleted.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $user User object.
	 *
	 * @return bool True if the user has only safe roles.
	 */
	private function has_safe_role( \WP_User $user ): bool {
		$result = false;

		if ( count( $user->roles ) > 0 ) {
			$unsafe_roles = array_diff( $user->roles, CLEANUP_SAFE_ROLES );
			$result       = 0 === count( $unsafe_roles );
		}

		return $result;
	}
}
