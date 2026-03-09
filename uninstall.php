<?php
/**
 * Uninstall Handler
 *
 * Removes all plugin data when the plugin is deleted via the WordPress admin.
 * This file is called by WordPress automatically — not by the plugin itself.
 *
 * @package Registration_Guard
 * @since 1.0.0
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die();
}

// =============================================================================
// Delete all regguard_* options.
// =============================================================================

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup, no caching needed.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'regguard\_%'" );

// =============================================================================
// Delete all _regguard_* user meta.
// =============================================================================

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup.
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '\_regguard\_%'" );

// =============================================================================
// Delete all regguard_* transients.
// =============================================================================

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_regguard\_%'" );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_regguard\_%'" );

// =============================================================================
// Drop the log table.
// =============================================================================

$regguard_table_name = $wpdb->prefix . 'regguard_log';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- uninstall teardown.
$wpdb->query( "DROP TABLE IF EXISTS $regguard_table_name" );

// =============================================================================
// Unschedule cron hooks.
// =============================================================================

wp_clear_scheduled_hook( 'regguard_cleanup_unverified_accounts' );
wp_clear_scheduled_hook( 'regguard_prune_event_log' );
