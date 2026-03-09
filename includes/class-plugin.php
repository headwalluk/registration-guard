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
	 * Plugin settings instance.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Event logger instance.
	 *
	 * @since 1.0.0
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Nonce challenge instance.
	 *
	 * @since 1.0.0
	 * @var Nonce_Challenge
	 */
	private Nonce_Challenge $nonce_challenge;

	/**
	 * Email verification instance.
	 *
	 * @since 1.0.0
	 * @var Email_Verification
	 */
	private Email_Verification $email_verification;

	/**
	 * Account cleanup instance.
	 *
	 * @since 1.0.0
	 * @var Account_Cleanup
	 */
	private Account_Cleanup $account_cleanup;

	/**
	 * Geo-restriction instance.
	 *
	 * @since 1.0.0
	 * @var Geo_Restriction
	 */
	private Geo_Restriction $geo_restriction;

	/**
	 * Initialize hooks and load dependencies.
	 *
	 * @since 1.0.0
	 */
	public function run(): void {
		$this->settings           = new Settings();
		$this->logger             = new Logger();
		$this->nonce_challenge    = new Nonce_Challenge();
		$this->email_verification = new Email_Verification();
		$this->account_cleanup    = new Account_Cleanup();
		$this->geo_restriction    = new Geo_Restriction();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'check_first_run' ), 1 );
		add_action( 'admin_init', array( $this->settings, 'register_settings' ) );
		add_action( 'admin_menu', array( $this->settings, 'add_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this->settings, 'enqueue_admin_assets' ) );
		add_action( CRON_PRUNE_LOG, array( $this->logger, 'prune' ) );
		add_action( CRON_CLEANUP_ACCOUNTS, array( $this->account_cleanup, 'cleanup' ) );

		// Nonce challenge hooks.
		add_action( 'login_enqueue_scripts', array( $this->nonce_challenge, 'enqueue_script' ) );
		add_action( 'register_form', array( $this->nonce_challenge, 'render_nonce_field' ) );
		add_action( 'wp_ajax_nopriv_' . Nonce_Challenge::AJAX_ACTION, array( $this->nonce_challenge, 'ajax_generate_nonce' ) );
		add_filter( 'registration_errors', array( $this->nonce_challenge, 'validate_nonce' ) );

		// Email verification hooks.
		add_action( 'user_register', array( $this->email_verification, 'handle_registration' ) );
		add_action( 'init', array( $this->email_verification, 'handle_verification_link' ) );
		add_action( 'admin_init', array( $this->email_verification, 'block_unverified_admin' ) );
		add_action( 'wp_ajax_' . Email_Verification::AJAX_RESEND, array( $this->email_verification, 'ajax_resend_verification' ) );
		add_action( 'wp_ajax_nopriv_' . Email_Verification::AJAX_RESEND, array( $this->email_verification, 'ajax_resend_verification' ) );

		// Geo-restriction hooks.
		add_filter( 'registration_errors', array( $this->geo_restriction, 'validate_registration' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

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
			$defaults = get_default_settings();

			foreach ( $defaults as $key => $value ) {
				if ( false === get_option( $key ) ) {
					add_option( $key, $value, '', 'yes' );
				}
			}

			$this->logger->create_table();
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
	 * Get the logger instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Logger The logger instance.
	 */
	public function get_logger(): Logger {
		return $this->logger;
	}

	/**
	 * Load WooCommerce-specific functionality.
	 *
	 * Registers hooks for WooCommerce My Account registration
	 * protection. Checkout is deliberately excluded.
	 *
	 * @since 1.0.0
	 */
	private function load_woocommerce(): void {
		$woo = new WooCommerce();

		add_action( 'wp_enqueue_scripts', array( $woo, 'enqueue_script' ) );
		add_action( 'woocommerce_register_form', array( $woo, 'render_nonce_field' ) );
		add_filter( 'woocommerce_register_post', array( $woo, 'validate_nonce' ), 10, 3 );
		add_action( 'template_redirect', array( $woo, 'block_unverified_myaccount' ) );
		add_filter( 'woocommerce_register_post', array( $this->geo_restriction, 'validate_woocommerce_registration' ), 10, 3 );
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

		if ( is_geo_enabled() && ! class_exists( 'WooCommerce' ) ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'Registration Guard:', 'registration-guard' ),
				esc_html__( 'Geo-restriction requires WooCommerce for IP geolocation. This feature is currently inactive.', 'registration-guard' )
			);
		}
	}
}
