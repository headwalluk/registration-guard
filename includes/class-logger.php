<?php
/**
 * Event Logger Class
 *
 * @package Registration_Guard
 * @since 1.0.0
 */

namespace Registration_Guard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Event Logger Class.
 *
 * Logs security events to a custom database table and provides
 * methods for querying and pruning log entries.
 *
 * @since 1.0.0
 */
class Logger {

	/**
	 * Create the log table if it does not exist.
	 *
	 * Uses dbDelta() for safe, idempotent table creation.
	 * Called on plugin activation and first-run detection.
	 *
	 * @since 1.0.0
	 */
	public function create_table(): void {
		global $wpdb;

		$table_name      = get_log_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(50) NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			message text NOT NULL,
			ip_address varchar(45) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Log an event.
	 *
	 * @since 1.0.0
	 *
	 * @param string $event_type One of the LOG_* constants.
	 * @param int    $user_id    WordPress user ID, or 0 for anonymous events.
	 * @param string $message    Human-readable event description.
	 * @param string $ip         IP address. Auto-detected if empty.
	 */
	public function log( string $event_type, int $user_id, string $message, string $ip = '' ): void {
		global $wpdb;

		if ( '' === $ip ) {
			$ip = get_ip_address();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom log table, no WP API available.
		$wpdb->insert(
			get_log_table_name(),
			array(
				'event_type' => $event_type,
				'user_id'    => $user_id,
				'message'    => $message,
				'ip_address' => $ip,
				'created_at' => get_now_formatted(),
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Query log entries.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string $event_type Filter by event type.
	 *     @type int    $user_id    Filter by user ID.
	 *     @type int    $limit      Maximum entries to return. Default 50.
	 *     @type int    $offset     Number of entries to skip. Default 0.
	 * }
	 *
	 * @return array Array of log entry objects.
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'event_type' => '',
			'user_id'    => 0,
			'limit'      => 50,
			'offset'     => 0,
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array();
		$values = array();

		if ( '' !== $args['event_type'] ) {
			$where[]  = 'event_type = %s';
			$values[] = $args['event_type'];
		}

		if ( $args['user_id'] > 0 ) {
			$where[]  = 'user_id = %d';
			$values[] = $args['user_id'];
		}

		$where_clause = '';
		if ( count( $where ) > 0 ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where );
		}

		$table = get_log_table_name();
		$sql   = "SELECT * FROM $table $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom log table, no caching needed, SQL built safely above.
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Prune log entries older than the retention period.
	 *
	 * Deletes in batches to avoid database timeouts on large tables.
	 * Intended to be called by the daily pruning cron.
	 *
	 * @since 1.0.0
	 */
	public function prune(): void {
		global $wpdb;

		$table    = get_log_table_name();
		$cutoff   = new \DateTime( 'now', wp_timezone() );
		$interval = new \DateInterval( 'P' . LOG_RETENTION_DAYS . 'D' );
		$cutoff->sub( $interval );
		$cutoff_str = $cutoff->format( 'Y-m-d H:i:s T' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom log table, $table from get_log_table_name().
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE created_at < %s LIMIT %d", $cutoff_str, LOG_PRUNE_BATCH ) );
	}

	/**
	 * Drop the log table.
	 *
	 * Called during plugin uninstall to remove all data.
	 *
	 * @since 1.0.0
	 */
	public function drop_table(): void {
		global $wpdb;

		$table = get_log_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom log table teardown.
		$wpdb->query( "DROP TABLE IF EXISTS $table" );
	}
}
