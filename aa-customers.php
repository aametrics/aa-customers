<?php
/**
 * Plugin Name: AA Customers
 * Plugin URI: https://ukagp.org
 * Description: Membership management, event sales, and practitioner directory for UKAGP. Built on Project Phoenix architecture.
 * Version: 1.0.0
 * Author: Ayhan Alman
 * License: GPL v2 or later
 * Text Domain: aa-customers
 *
 * @package AA_Customers
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'AA_CUSTOMERS_VERSION', '1.0.0' );
define( 'AA_CUSTOMERS_PLUGIN_FILE', __FILE__ );
define( 'AA_CUSTOMERS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AA_CUSTOMERS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include core database layer.
require_once AA_CUSTOMERS_PLUGIN_DIR . 'core/database/class-db-connection.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'core/database/class-schema.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'core/database/class-migrator.php';

// Include core services.
require_once AA_CUSTOMERS_PLUGIN_DIR . 'core/services/class-zap-storage.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'core/services/class-email-service.php';

// Include shared admin components.
require_once AA_CUSTOMERS_PLUGIN_DIR . 'admin/shared/class-base-admin.php';

// Include admin modules.
require_once AA_CUSTOMERS_PLUGIN_DIR . 'admin/class-admin.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'admin/settings/class-settings.php';

/**
 * Main Plugin Class
 *
 * Initializes the plugin, registers activation/deactivation hooks,
 * and bootstraps all modules and components.
 *
 * @since 1.0.0
 */
class AA_Customers {

	/**
	 * Constructor
	 *
	 * Sets up hooks for plugin initialization, activation, and deactivation.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Initialize plugin components
	 *
	 * Runs on the 'init' hook to set up database, check for upgrades,
	 * and initialize all modules.
	 *
	 * @return void
	 */
	public function init() {
		// Initialize database schema (creates tables if needed).
		AA_Customers_Database::maybe_create_tables();

		// Run database migrations (automatic schema updates).
		AA_Customers_Database_Migrator::maybe_migrate();

		// Initialize admin components (only in admin).
		if ( is_admin() ) {
			new AA_Customers_Admin();
			new AA_Customers_Settings();
		}

		// Register custom member role if it doesn't exist.
		$this->register_member_role();
	}

	/**
	 * Plugin activation
	 *
	 * Creates database tables and sets default options.
	 *
	 * @return void
	 */
	public function activate() {
		// Create database tables.
		AA_Customers_Database::maybe_create_tables();

		// Set default options.
		add_option( 'aa_customers_version', AA_CUSTOMERS_VERSION );
		add_option( 'aa_customers_db_version', 0 );

		// Register member role.
		$this->register_member_role();

		// Flush rewrite rules for any custom URLs.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 *
	 * Performs cleanup tasks on plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivate() {
		// Clear any scheduled cron jobs.
		wp_clear_scheduled_hook( 'aa_customers_check_expirations' );
		wp_clear_scheduled_hook( 'aa_customers_send_reminders' );
	}

	/**
	 * Register custom member role
	 *
	 * Creates a 'member' role with minimal capabilities.
	 * Members can read but cannot access wp-admin.
	 *
	 * @return void
	 */
	private function register_member_role() {
		if ( ! get_role( 'member' ) ) {
			add_role(
				'member',
				__( 'Member', 'aa-customers' ),
				array(
					'read' => true,
				)
			);
		}
	}
}

// Initialize the plugin.
new AA_Customers();

/**
 * Redirect members away from wp-admin
 *
 * Members should use the frontend dashboard, not wp-admin.
 */
add_action( 'admin_init', function() {
	if ( current_user_can( 'member' ) && ! wp_doing_ajax() ) {
		wp_redirect( home_url( '/member-dashboard' ) );
		exit;
	}
} );

/**
 * Hide admin bar for members
 */
add_action( 'after_setup_theme', function() {
	if ( current_user_can( 'member' ) ) {
		show_admin_bar( false );
	}
} );
