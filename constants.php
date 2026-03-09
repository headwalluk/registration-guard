<?php
/**
 * Plugin Constants
 *
 * @package Registration_Guard
 * @since 1.0.0
 */

namespace Registration_Guard;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

// =============================================================================
// User Meta Keys
// =============================================================================

/**
 * User meta keys used by the plugin.
 *
 * @since 1.0.0
 */
const META_EMAIL_VERIFIED     = '_regguard_email_verified';
const META_VERIFICATION_TOKEN = '_regguard_verification_token';
const META_TOKEN_CREATED      = '_regguard_token_created';

// =============================================================================
// Option Keys (wp_options)
// =============================================================================

/**
 * Option keys used by the plugin.
 *
 * @since 1.0.0
 */
const OPT_NONCE_ENABLED       = 'regguard_nonce_challenge_enabled';
const OPT_NONCE_MIN_DELAY     = 'regguard_nonce_min_delay';
const OPT_DOUBLE_OPTIN        = 'regguard_double_optin_enabled';
const OPT_VERIFICATION_WINDOW = 'regguard_verification_window';
const OPT_RESEND_COOLDOWN     = 'regguard_resend_cooldown';
const OPT_GEO_ENABLED         = 'regguard_geo_enabled';
const OPT_GEO_MODE            = 'regguard_geo_mode';
const OPT_GEO_COUNTRIES       = 'regguard_geo_countries';
const OPT_GEO_FAIL_ACTION     = 'regguard_geo_fail_action';
const OPT_VERSION             = 'regguard_version';

// =============================================================================
// Geo-Restriction Mode Values
// =============================================================================

/**
 * Geo-restriction mode values.
 *
 * @since 1.0.0
 */
const GEO_MODE_ALLOWLIST = 'allowlist';
const GEO_MODE_BLOCKLIST = 'blocklist';

/**
 * Geo-restriction fail action values.
 *
 * @since 1.0.0
 */
const GEO_FAIL_BLOCK = 'block';
const GEO_FAIL_ALLOW = 'allow';

// =============================================================================
// Default Values
// =============================================================================

/**
 * Default settings values.
 *
 * @since 1.0.0
 */
const DEF_NONCE_ENABLED         = true;
const DEF_NONCE_MIN_DELAY       = 1;
const DEF_NONCE_MAX_DELAY       = 10;
const DEF_DOUBLE_OPTIN          = true;
const DEF_VERIFICATION_WINDOW   = 24;
const MAX_VERIFICATION_WINDOW   = 72;
const DEF_RESEND_COOLDOWN       = 5 * MINUTE_IN_SECONDS;
const MAX_RESEND_COOLDOWN       = HOUR_IN_SECONDS;
const DEF_GEO_ENABLED           = false;
const DEF_GEO_MODE              = GEO_MODE_BLOCKLIST;
const DEF_GEO_COUNTRIES         = '';
const PLACEHOLDER_GEO_COUNTRIES = 'BY,IQ,IR,KP,RU,SG';
const DEF_GEO_FAIL_ACTION       = GEO_FAIL_BLOCK;

// =============================================================================
// Rate Limiting
// =============================================================================

/**
 * Rate limiting constants.
 *
 * @since 1.0.0
 */
const RATE_LIMIT_NONCE_MAX    = 20;
const RATE_LIMIT_NONCE_WINDOW = 5 * MINUTE_IN_SECONDS;

// =============================================================================
// Transient Key Prefixes
// =============================================================================

/**
 * Transient key prefixes.
 *
 * @since 1.0.0
 */
const TRANSIENT_NONCE_RATE      = 'regguard_nonce_rate_';
const TRANSIENT_RESEND_COOLDOWN = 'regguard_resend_cooldown_';

// =============================================================================
// Nonce / AJAX
// =============================================================================

/**
 * Nonce challenge constants.
 *
 * @since 1.0.0
 */
const NONCE_EXPIRY = 5 * MINUTE_IN_SECONDS;

// =============================================================================
// Query Parameters
// =============================================================================

/**
 * Query parameter for verification links.
 *
 * @since 1.0.0
 */
const QUERY_VERIFY = 'regguard_verify';

// =============================================================================
// Cron Hooks
// =============================================================================

/**
 * Cron hook names.
 *
 * @since 1.0.0
 */
const CRON_CLEANUP_ACCOUNTS = 'regguard_cleanup_unverified_accounts';
const CRON_PRUNE_LOG        = 'regguard_prune_event_log';

// =============================================================================
// Account Cleanup
// =============================================================================

/**
 * Account cleanup constants.
 *
 * @since 1.0.0
 */
const CLEANUP_BATCH_SIZE = 50;
const CLEANUP_SAFE_ROLES = array( 'subscriber', 'customer' );

// =============================================================================
// Database
// =============================================================================

/**
 * Database table name suffix (prefixed with $wpdb->prefix at runtime).
 *
 * @since 1.0.0
 */
const DB_TABLE_LOG = 'regguard_log';

/**
 * Log retention period in days.
 *
 * @since 1.0.0
 */
const LOG_RETENTION_DAYS = 30;
const LOG_PRUNE_BATCH    = 1000;

// =============================================================================
// Log Event Types
// =============================================================================

/**
 * Log event types.
 *
 * @since 1.0.0
 */
const LOG_USER_REGISTERED       = 'user_registered';
const LOG_VERIFICATION_SENT     = 'verification_sent';
const LOG_VERIFICATION_RESENT   = 'verification_resent';
const LOG_VERIFICATION_SUCCESS  = 'verification_success';
const LOG_VERIFICATION_EXPIRED  = 'verification_expired';
const LOG_NONCE_REJECTED        = 'nonce_rejected';
const LOG_GEO_BLOCKED           = 'geo_blocked';
const LOG_CHECKOUT_AUTOAPPROVED = 'checkout_autoapproved';
