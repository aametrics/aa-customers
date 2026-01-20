<?php
/**
 * Database Schema for AA Customers
 *
 * Manages AA Customers database schema in separate database.
 * Uses aac_ prefix for all tables.
 *
 * Tables:
 * - aac_members: Member profiles linked to WP users
 * - aac_products: Memberships and events for sale
 * - aac_events: Event metadata (dates, locations, capacity)
 * - aac_purchases: All transactions
 * - aac_registrations: Event registrations
 * - aac_options: Plugin settings (Xero tokens, etc.)
 * - aac_zap: Secure configuration storage
 *
 * @package AA_Customers
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database Schema class
 */
class AA_Customers_Database {

	/**
	 * Maybe create tables (called on every init, but only creates once)
	 *
	 * SAFETY: Will NEVER create tables unless in the correct database with correct prefix.
	 *
	 * @return void
	 */
	public static function maybe_create_tables() {
		// Safety check: Do not proceed if constants aren't defined.
		if ( ! defined( 'AA_CUSTOMER_DB_NAME' ) || ! defined( 'AA_CUSTOMER_DB_USER' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'AA Customers: DB constants not defined. Tables will not be created.' );
			}
			return;
		}

		// Safety check: Ensure we're not in the WordPress database.
		global $wpdb;
		if ( AA_CUSTOMER_DB_NAME === $wpdb->dbname ) {
			error_log( 'AA Customers: CRITICAL - AA_CUSTOMER_DB_NAME matches WordPress database! Tables will NOT be created.' );
			return;
		}

		// Force fresh connection check.
		AA_Customers_DB_Connection::reset_connection();

		$aac_wpdb = AA_Customers_DB_Connection::get_connection();

		// Verify we're connected to the right database.
		if ( $aac_wpdb->dbname !== AA_CUSTOMER_DB_NAME ) {
			error_log( sprintf(
				'AA Customers: Database mismatch! Expected: %s, Got: %s. ABORTING.',
				AA_CUSTOMER_DB_NAME,
				$aac_wpdb->dbname
			) );
			return;
		}

		// Check if core table exists (members table as indicator).
		$table_name   = $aac_wpdb->prefix . 'members';
		$table_exists = $aac_wpdb->get_var( $aac_wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		if ( ! $table_exists ) {
			error_log( 'AA Customers: Tables not found, creating schema...' );
			self::create_tables();
		}
	}

	/**
	 * Create all database tables
	 *
	 * @return void
	 */
	private static function create_tables() {
		$wpdb            = AA_Customers_DB_Connection::get_connection();
		$charset_collate = $wpdb->get_charset_collate();

		// Members table (linked to wp_users via wp_user_id).
		$table_members = $wpdb->prefix . 'members';
		$sql_members   = "CREATE TABLE $table_members (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			wp_user_id BIGINT UNSIGNED NOT NULL,
			stripe_customer_id VARCHAR(255) NULL,

			-- Contact info (duplicated from WP for convenience)
			full_name VARCHAR(255) NOT NULL,
			email VARCHAR(255) NOT NULL,
			phone VARCHAR(50) NULL,
			address_line_1 VARCHAR(255) NULL,
			address_line_2 VARCHAR(255) NULL,
			city VARCHAR(100) NULL,
			postcode VARCHAR(20) NULL,
			country VARCHAR(100) DEFAULT 'United Kingdom',

			-- Membership
			membership_status ENUM('active', 'pending', 'past_due', 'cancelled', 'expired', 'none') DEFAULT 'none',
			membership_type_id BIGINT UNSIGNED NULL,
			membership_start DATE NULL,
			membership_expiry DATE NULL,
			stripe_subscription_id VARCHAR(255) NULL,

			-- Directory profile
			show_in_directory BOOLEAN DEFAULT FALSE,
			practitioner_bio TEXT NULL,
			practitioner_specialties TEXT NULL,
			practitioner_location VARCHAR(255) NULL,
			practitioner_website VARCHAR(255) NULL,
			practitioner_photo_url VARCHAR(500) NULL,

			-- Xero
			xero_contact_id VARCHAR(255) NULL,

			-- Meta
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

			PRIMARY KEY (id),
			UNIQUE KEY uk_wp_user (wp_user_id),
			UNIQUE KEY uk_email (email),
			KEY idx_status (membership_status),
			KEY idx_expiry (membership_expiry),
			KEY idx_stripe_customer (stripe_customer_id)
		) $charset_collate;";

		// Products table (memberships, events, etc.).
		$table_products = $wpdb->prefix . 'products';
		$sql_products   = "CREATE TABLE $table_products (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			type ENUM('membership', 'event') NOT NULL,
			name VARCHAR(255) NOT NULL,
			slug VARCHAR(255) NOT NULL,
			description TEXT NULL,
			price DECIMAL(10,2) NOT NULL,
			member_price DECIMAL(10,2) NULL,
			stripe_product_id VARCHAR(255) NULL,
			stripe_price_id VARCHAR(255) NULL,
			is_recurring BOOLEAN DEFAULT FALSE,
			recurring_interval ENUM('month', 'year') DEFAULT 'year',
			allow_donation BOOLEAN DEFAULT TRUE,
			status ENUM('active', 'draft', 'archived') DEFAULT 'draft',
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

			PRIMARY KEY (id),
			UNIQUE KEY uk_slug (slug),
			KEY idx_type (type),
			KEY idx_status (status)
		) $charset_collate;";

		// Events table (extra metadata for event-type products).
		$table_events = $wpdb->prefix . 'events';
		$sql_events   = "CREATE TABLE $table_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			event_date DATE NOT NULL,
			event_time TIME NULL,
			end_date DATE NULL,
			end_time TIME NULL,
			location VARCHAR(255) NULL,
			location_details TEXT NULL,
			capacity INT UNSIGNED NULL,
			registration_deadline DATE NULL,
			additional_info TEXT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

			PRIMARY KEY (id),
			KEY idx_product (product_id),
			KEY idx_date (event_date)
		) $charset_collate;";

		// Purchases table (all transactions).
		$table_purchases = $wpdb->prefix . 'purchases';
		$sql_purchases   = "CREATE TABLE $table_purchases (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL,

			-- Transaction
			amount DECIMAL(10,2) NOT NULL,
			donation_amount DECIMAL(10,2) DEFAULT 0,
			total_amount DECIMAL(10,2) NOT NULL,
			currency VARCHAR(3) DEFAULT 'GBP',

			-- Stripe
			stripe_payment_intent_id VARCHAR(255) NULL,
			stripe_invoice_id VARCHAR(255) NULL,
			payment_status ENUM('pending', 'succeeded', 'failed', 'refunded') DEFAULT 'pending',

			-- Xero
			xero_invoice_id VARCHAR(255) NULL,

			-- Type-specific
			is_renewal BOOLEAN DEFAULT FALSE,

			-- Meta
			purchase_date DATETIME DEFAULT CURRENT_TIMESTAMP,
			notes TEXT NULL,

			PRIMARY KEY (id),
			KEY idx_member (member_id),
			KEY idx_product (product_id),
			KEY idx_date (purchase_date),
			KEY idx_status (payment_status),
			KEY idx_stripe_payment (stripe_payment_intent_id)
		) $charset_collate;";

		// Event registrations table.
		$table_registrations = $wpdb->prefix . 'registrations';
		$sql_registrations   = "CREATE TABLE $table_registrations (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id BIGINT UNSIGNED NOT NULL,
			member_id BIGINT UNSIGNED NULL,
			purchase_id BIGINT UNSIGNED NOT NULL,

			-- Non-member info (if not logged in)
			guest_name VARCHAR(255) NULL,
			guest_email VARCHAR(255) NULL,

			-- Registration details
			status ENUM('confirmed', 'cancelled', 'waitlist') DEFAULT 'confirmed',
			dietary_requirements TEXT NULL,
			accessibility_needs TEXT NULL,
			additional_info TEXT NULL,

			registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,

			PRIMARY KEY (id),
			KEY idx_event (event_id),
			KEY idx_member (member_id),
			KEY idx_purchase (purchase_id),
			KEY idx_status (status)
		) $charset_collate;";

		// Options table (for Xero tokens, etc.).
		$table_options = $wpdb->prefix . 'options';
		$sql_options   = "CREATE TABLE $table_options (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			option_name VARCHAR(255) NOT NULL,
			option_value LONGTEXT NULL,
			autoload BOOLEAN DEFAULT TRUE,

			PRIMARY KEY (id),
			UNIQUE KEY uk_name (option_name)
		) $charset_collate;";

		// Zap storage table (secure configuration).
		$table_zap = $wpdb->prefix . 'zap';
		$sql_zap   = "CREATE TABLE $table_zap (
			zap_key VARCHAR(255) NOT NULL,
			zap_value LONGTEXT NULL,
			zap_lock TINYINT(1) DEFAULT 0,
			zap_tag VARCHAR(50) DEFAULT 'config',
			zap_stamp DATETIME DEFAULT CURRENT_TIMESTAMP,
			zap_pulse DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

			PRIMARY KEY (zap_key),
			KEY idx_tag (zap_tag)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql_members );
		dbDelta( $sql_products );
		dbDelta( $sql_events );
		dbDelta( $sql_purchases );
		dbDelta( $sql_registrations );
		dbDelta( $sql_options );
		dbDelta( $sql_zap );

		error_log( 'AA Customers: Database tables created.' );
	}

	/**
	 * Get members table name
	 *
	 * @return string Table name.
	 */
	public static function get_members_table_name() {
		$wpdb = AA_Customers_DB_Connection::get_connection();
		return $wpdb->prefix . 'members';
	}

	/**
	 * Get products table name
	 *
	 * @return string Table name.
	 */
	public static function get_products_table_name() {
		$wpdb = AA_Customers_DB_Connection::get_connection();
		return $wpdb->prefix . 'products';
	}

	/**
	 * Get events table name
	 *
	 * @return string Table name.
	 */
	public static function get_events_table_name() {
		$wpdb = AA_Customers_DB_Connection::get_connection();
		return $wpdb->prefix . 'events';
	}

	/**
	 * Get purchases table name
	 *
	 * @return string Table name.
	 */
	public static function get_purchases_table_name() {
		$wpdb = AA_Customers_DB_Connection::get_connection();
		return $wpdb->prefix . 'purchases';
	}

	/**
	 * Get registrations table name
	 *
	 * @return string Table name.
	 */
	public static function get_registrations_table_name() {
		$wpdb = AA_Customers_DB_Connection::get_connection();
		return $wpdb->prefix . 'registrations';
	}

	/**
	 * Get options table name
	 *
	 * @return string Table name.
	 */
	public static function get_options_table_name() {
		$wpdb = AA_Customers_DB_Connection::get_connection();
		return $wpdb->prefix . 'options';
	}

	/**
	 * Get zap table name
	 *
	 * @return string Table name.
	 */
	public static function get_zap_table_name() {
		$wpdb = AA_Customers_DB_Connection::get_connection();
		return $wpdb->prefix . 'zap';
	}
}
