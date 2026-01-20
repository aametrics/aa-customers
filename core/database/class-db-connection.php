<?php
/**
 * Database Connection Handler for AA Customers
 *
 * Manages connection to separate AA Customers database.
 * Uses separate credentials and database from WordPress core.
 *
 * @package AA_Customers
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database Connection class
 */
class AA_Customers_DB_Connection {

	/**
	 * Database connection instance
	 *
	 * @var wpdb
	 */
	private static $db = null;

	/**
	 * Get database connection
	 *
	 * Returns wpdb instance connected to AA Customers database.
	 * Falls back to WordPress database if separate DB not configured.
	 *
	 * @return wpdb Database connection.
	 */
	public static function get_connection() {
		// Re-check constants to handle late-loading scenarios.
		if ( null !== self::$db ) {
			global $wpdb;
			$is_using_wp_db     = ( self::$db === $wpdb );
			$constants_defined  = defined( 'AA_CUSTOMER_DB_NAME' ) && defined( 'AA_CUSTOMER_DB_USER' ) && defined( 'AA_CUSTOMER_DB_PASS' ) && defined( 'AA_CUSTOMER_DB_HOST' );

			// If using WP DB but constants ARE defined, reinitialize.
			if ( $is_using_wp_db && $constants_defined ) {
				self::$db = null;
			}

			// Check if prefix is wrong.
			if ( null !== self::$db && defined( 'AA_CUSTOMER_DB_PREFIX' ) ) {
				$expected_prefix = AA_CUSTOMER_DB_PREFIX;
				if ( self::$db->prefix !== $expected_prefix ) {
					self::$db = null;
				}
			}
		}

		if ( null === self::$db ) {
			self::initialize_connection();
		}

		return self::$db;
	}

	/**
	 * Initialize database connection
	 *
	 * Creates new wpdb instance with AA Customers database credentials.
	 * If separate database credentials not defined, falls back to WordPress DB.
	 */
	private static function initialize_connection() {
		// Check if separate database credentials are defined.
		if ( defined( 'AA_CUSTOMER_DB_NAME' ) && defined( 'AA_CUSTOMER_DB_USER' ) && defined( 'AA_CUSTOMER_DB_PASS' ) && defined( 'AA_CUSTOMER_DB_HOST' ) ) {

			// Use separate AA Customers database.
			self::$db = new wpdb(
				AA_CUSTOMER_DB_USER,
				AA_CUSTOMER_DB_PASS,
				AA_CUSTOMER_DB_NAME,
				AA_CUSTOMER_DB_HOST
			);

			// Verify connection.
			if ( ! self::$db->dbh ) {
				error_log( 'AA Customers: ERROR - Failed to connect to separate database! Falling back to WordPress DB.' );
				global $wpdb;
				self::$db = $wpdb;
				return;
			}

			// Set charset.
			if ( defined( 'AA_CUSTOMER_DB_CHARSET' ) ) {
				self::$db->set_charset( self::$db->dbh, AA_CUSTOMER_DB_CHARSET );
			} else {
				self::$db->set_charset( self::$db->dbh, 'utf8mb4' );
			}

			// Set table prefix.
			self::$db->prefix = defined( 'AA_CUSTOMER_DB_PREFIX' ) ? AA_CUSTOMER_DB_PREFIX : 'aac_';

			error_log( sprintf(
				'AA Customers: Connected to database: %s with prefix: %s',
				self::$db->dbname,
				self::$db->prefix
			) );
		} else {
			// Fallback to WordPress database.
			global $wpdb;
			self::$db = $wpdb;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'AA Customers: Using WordPress database (separate DB constants not defined)' );
			}
		}
	}

	/**
	 * Reset connection (for testing/debugging)
	 *
	 * @return void
	 */
	public static function reset_connection() {
		self::$db = null;
	}

	/**
	 * Test database connection
	 *
	 * @return bool True if connection successful.
	 */
	public static function test_connection() {
		$db     = self::get_connection();
		$result = $db->get_var( 'SELECT 1' );
		return 1 === (int) $result;
	}

	/**
	 * Get database name
	 *
	 * @return string Database name.
	 */
	public static function get_database_name() {
		if ( defined( 'AA_CUSTOMER_DB_NAME' ) ) {
			return AA_CUSTOMER_DB_NAME;
		}

		global $wpdb;
		return $wpdb->dbname;
	}

	/**
	 * Get table prefix
	 *
	 * @return string Table prefix.
	 */
	public static function get_prefix() {
		$db = self::get_connection();
		return $db->prefix;
	}

	/**
	 * Check if using separate database
	 *
	 * @return bool True if using separate database.
	 */
	public static function is_separate_database() {
		return defined( 'AA_CUSTOMER_DB_NAME' ) && defined( 'AA_CUSTOMER_DB_USER' );
	}
}
