<?php
/**
 * Plugin Name: Registration Guard
 * Plugin URI: https://github.com/headwalluk/registration-guard
 * Description: Lightweight bot registration protection for WordPress and WooCommerce. Three layered defences with zero configuration required.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Paul Faulkner
 * Author URI: https://power-plugins.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: registration-guard
 * Domain Path: /languages
 *
 * @package Registration_Guard
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

define( 'REGISTRATION_GUARD_VERSION', '0.1.0' );
define( 'REGISTRATION_GUARD_FILE', __FILE__ );
define( 'REGISTRATION_GUARD_PATH', plugin_dir_path( __FILE__ ) );
define( 'REGISTRATION_GUARD_URL', plugin_dir_url( __FILE__ ) );
define( 'REGISTRATION_GUARD_BASENAME', plugin_basename( __FILE__ ) );

require_once REGISTRATION_GUARD_PATH . 'constants.php';
require_once REGISTRATION_GUARD_PATH . 'functions-private.php';

require_once REGISTRATION_GUARD_PATH . 'includes/class-plugin.php';
require_once REGISTRATION_GUARD_PATH . 'includes/class-settings.php';

/**
 * Activation hook.
 *
 * Sets default options and schedules cron jobs.
 *
 * @since 1.0.0
 */
function registration_guard_activate(): void {
	$defaults = Registration_Guard\get_default_settings();

	foreach ( $defaults as $key => $value ) {
		if ( false === get_option( $key ) ) {
			add_option( $key, $value, '', 'yes' );
		}
	}

	add_option( Registration_Guard\OPT_VERSION, REGISTRATION_GUARD_VERSION, '', 'yes' );

	if ( ! wp_next_scheduled( Registration_Guard\CRON_CLEANUP_ACCOUNTS ) ) {
		wp_schedule_event( time(), 'hourly', Registration_Guard\CRON_CLEANUP_ACCOUNTS );
	}

	if ( ! wp_next_scheduled( Registration_Guard\CRON_PRUNE_LOG ) ) {
		wp_schedule_event( time(), 'daily', Registration_Guard\CRON_PRUNE_LOG );
	}
}
register_activation_hook( __FILE__, 'registration_guard_activate' );

/**
 * Deactivation hook.
 *
 * Unschedules cron jobs. Data is preserved for reactivation.
 *
 * @since 1.0.0
 */
function registration_guard_deactivate(): void {
	wp_clear_scheduled_hook( Registration_Guard\CRON_CLEANUP_ACCOUNTS );
	wp_clear_scheduled_hook( Registration_Guard\CRON_PRUNE_LOG );
}
register_deactivation_hook( __FILE__, 'registration_guard_deactivate' );

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 */
function registration_guard_run(): void {
	$plugin = Registration_Guard\Plugin::instance();
	$plugin->run();
}
registration_guard_run();
