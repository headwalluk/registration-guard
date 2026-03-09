<?php
/**
 * Main Plugin Class
 *
 * @package Registration_Guard
 * @since 1.0.0
 */

namespace Registration_Guard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Main Plugin Class.
 *
 * Orchestrates hook registration and conditional class loading.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Single instance of the class.
	 *
	 * @since 1.0.0
	 * @var ?Plugin
	 */
	private static ?Plugin $instance = null;

	/**
	 * Plugin settings instance.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Get single instance of the class.
	 *
	 * @since 1.0.0
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize hooks and load dependencies.
	 *
	 * @since 1.0.0
	 */
	public function run(): void {
		$this->settings = new Settings();
		$this->settings->run();

		add_action( 'admin_init', array( $this, 'check_first_run' ), 1 );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( class_exists( 'WooCommerce' ) ) {
			$this->load_woocommerce();
		}
	}

	/**
	 * Load plugin text domain for translations.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'registration-guard', false, dirname( REGISTRATION_GUARD_BASENAME ) . '/languages' );
	}

	/**
	 * Check if this is the first run and initialize default options.
	 *
	 * Ensures defaults are set even when installed as MU plugin
	 * (where activation hooks don't fire).
	 *
	 * @since 1.0.0
	 */
	public function check_first_run(): void {
		if ( false === get_option( OPT_VERSION ) ) {
			$defaults = regguard_get_default_settings();

			foreach ( $defaults as $key => $value ) {
				if ( false === get_option( $key ) ) {
					add_option( $key, $value, '', 'yes' );
				}
			}

			add_option( OPT_VERSION, REGISTRATION_GUARD_VERSION, '', 'yes' );

			if ( ! wp_next_scheduled( CRON_CLEANUP_ACCOUNTS ) ) {
				wp_schedule_event( time(), 'hourly', CRON_CLEANUP_ACCOUNTS );
			}

			if ( ! wp_next_scheduled( CRON_PRUNE_LOG ) ) {
				wp_schedule_event( time(), 'daily', CRON_PRUNE_LOG );
			}
		} elseif ( get_option( OPT_VERSION ) !== REGISTRATION_GUARD_VERSION ) {
			update_option( OPT_VERSION, REGISTRATION_GUARD_VERSION );
		}
	}

	/**
	 * Load WooCommerce-specific functionality.
	 *
	 * @since 1.0.0
	 */
	private function load_woocommerce(): void {
		// WooCommerce class will be loaded here in M6.
		// HPOS compatibility declaration.
		add_action(
			'before_woocommerce_init',
			function (): void {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
						'custom_order_tables',
						REGISTRATION_GUARD_FILE,
						true
					);
				}
			}
		);
	}

	/**
	 * Display admin notices.
	 *
	 * @since 1.0.0
	 */
	public function admin_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( regguard_is_geo_enabled() && ! class_exists( 'WooCommerce' ) ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'Registration Guard:', 'registration-guard' ),
				esc_html__( 'Geo-restriction requires WooCommerce for IP geolocation. This feature is currently inactive.', 'registration-guard' )
			);
		}
	}
}
