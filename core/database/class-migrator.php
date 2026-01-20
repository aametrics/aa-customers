<?php
/**
 * Database Migrator for AA Customers
 *
 * Handles incremental database migrations.
 * Each migration is a versioned method that modifies the schema.
 *
 * @package AA_Customers
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database Migrator class
 */
class AA_Customers_Database_Migrator {

	/**
	 * Current database version
	 *
	 * Increment this when adding new migrations.
	 *
	 * @var int
	 */
	const CURRENT_VERSION = 1;

	/**
	 * Maybe run migrations
	 *
	 * Checks current version and runs any pending migrations.
	 *
	 * @return void
	 */
	public static function maybe_migrate() {
		$current_version = (int) get_option( 'aa_customers_db_version', 0 );

		if ( $current_version >= self::CURRENT_VERSION ) {
			return;
		}

		// Safety check: Ensure we have DB connection.
		if ( ! AA_Customers_DB_Connection::is_separate_database() ) {
			return;
		}

		error_log( sprintf(
			'AA Customers: Running migrations from v%d to v%d',
			$current_version,
			self::CURRENT_VERSION
		) );

		// Run each migration in sequence.
		for ( $version = $current_version + 1; $version <= self::CURRENT_VERSION; $version++ ) {
			$method = 'migrate_to_v' . $version;

			if ( method_exists( __CLASS__, $method ) ) {
				error_log( "AA Customers: Running migration: $method" );

				try {
					self::$method();
					update_option( 'aa_customers_db_version', $version );
					error_log( "AA Customers: Migration $method completed." );
				} catch ( Exception $e ) {
					error_log( "AA Customers: Migration $method FAILED: " . $e->getMessage() );
					break;
				}
			}
		}
	}

	/**
	 * Migration v1: Initial schema
	 *
	 * Creates the initial database schema.
	 * Tables are created by class-schema.php, this just marks v1 complete.
	 *
	 * @return void
	 */
	private static function migrate_to_v1() {
		// Initial schema is created by AA_Customers_Database::maybe_create_tables()
		// This migration just marks v1 as complete.
		error_log( 'AA Customers: v1 - Initial schema marked complete.' );
	}

	// =========================================================================
	// FUTURE MIGRATIONS
	// =========================================================================
	//
	// Add new migrations as needed. Each migration should be a private static
	// method named migrate_to_vN where N is the version number.
	//
	// Example:
	//
	// private static function migrate_to_v2() {
	//     $wpdb = AA_Customers_DB_Connection::get_connection();
	//     $table = AA_Customers_Database::get_members_table_name();
	//
	//     // Add new column
	//     $wpdb->query( "ALTER TABLE {$table} ADD COLUMN new_field VARCHAR(255) NULL" );
	// }
	//
	// Don't forget to increment CURRENT_VERSION constant!
	// =========================================================================

	/**
	 * Get current database version
	 *
	 * @return int Current version.
	 */
	public static function get_current_version() {
		return (int) get_option( 'aa_customers_db_version', 0 );
	}

	/**
	 * Get target database version
	 *
	 * @return int Target version.
	 */
	public static function get_target_version() {
		return self::CURRENT_VERSION;
	}

	/**
	 * Check if migrations are pending
	 *
	 * @return bool True if migrations are pending.
	 */
	public static function has_pending_migrations() {
		return self::get_current_version() < self::CURRENT_VERSION;
	}
}
