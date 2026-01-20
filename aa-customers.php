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

// Include core repositories.
require_once AA_CUSTOMERS_PLUGIN_DIR . 'core/repositories/class-members-repository.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'core/repositories/class-products-repository.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'core/repositories/class-purchases-repository.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'core/repositories/class-events-repository.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'core/repositories/class-registrations-repository.php';

// Include core services.
require_once AA_CUSTOMERS_PLUGIN_DIR . 'core/services/class-zap-storage.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'core/services/class-email-service.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'core/services/class-pdf-service.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'core/services/class-stripe-service.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'core/services/class-membership-service.php';

// Include shared admin components.
require_once AA_CUSTOMERS_PLUGIN_DIR . 'admin/shared/class-base-admin.php';

// Include admin modules.
require_once AA_CUSTOMERS_PLUGIN_DIR . 'admin/members/class-members-admin.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'admin/products/class-products-admin.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'admin/events/class-events-admin.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'admin/purchases/class-purchases-admin.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'admin/class-admin.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'admin/settings/class-settings.php';

// Include frontend modules.
require_once AA_CUSTOMERS_PLUGIN_DIR . 'frontend/class-shortcodes.php';
require_once AA_CUSTOMERS_PLUGIN_DIR . 'frontend/class-ajax-handler.php';

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
		add_action( 'init', array( $this, 'setup_cron' ) );

		// Cron handlers.
		add_action( 'aa_customers_check_expirations', array( $this, 'cron_check_expirations' ) );
		add_action( 'aa_customers_send_reminders', array( $this, 'cron_send_reminders' ) );

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

		// Initialize frontend components.
		new AA_Customers_Shortcodes();
		new AA_Customers_Ajax_Handler();

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
	 * Setup cron jobs
	 *
	 * Schedules daily membership checks.
	 *
	 * @return void
	 */
	public function setup_cron() {
		// Schedule daily expiration check.
		if ( ! wp_next_scheduled( 'aa_customers_check_expirations' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 6:00am' ), 'daily', 'aa_customers_check_expirations' );
		}

		// Schedule daily renewal reminders.
		if ( ! wp_next_scheduled( 'aa_customers_send_reminders' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 8:00am' ), 'daily', 'aa_customers_send_reminders' );
		}
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

/**
 * Cron: Check expirations
 */
add_action( 'aa_customers_check_expirations', function() {
	if ( ! AA_Customers_DB_Connection::is_separate_database() ) {
		return;
	}
	$membership_service = new AA_Customers_Membership_Service();
	$count = $membership_service->process_expired_memberships();
	error_log( 'AA Customers Cron: Processed ' . $count . ' expired memberships.' );
} );

/**
 * Cron: Send renewal reminders
 */
add_action( 'aa_customers_send_reminders', function() {
	if ( ! AA_Customers_DB_Connection::is_separate_database() ) {
		return;
	}
	$membership_service = new AA_Customers_Membership_Service();
	$count = $membership_service->send_renewal_reminders( 30 );
	error_log( 'AA Customers Cron: Sent ' . $count . ' renewal reminders.' );
} );

/**
 * Register REST API routes for Stripe webhooks
 */
add_action( 'rest_api_init', function() {
	register_rest_route( 'aa-customers/v1', '/stripe-webhook', array(
		'methods'             => 'POST',
		'callback'            => 'aa_customers_handle_stripe_webhook',
		'permission_callback' => '__return_true',
	) );
} );

/**
 * Handle Stripe webhook
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response.
 */
function aa_customers_handle_stripe_webhook( $request ) {
	$payload    = $request->get_body();
	$sig_header = $request->get_header( 'stripe-signature' );

	$stripe_service = new AA_Customers_Stripe_Service();

	$event = $stripe_service->verify_webhook( $payload, $sig_header );

	if ( is_wp_error( $event ) ) {
		error_log( 'AA Customers Stripe Webhook: ' . $event->get_error_message() );
		return new WP_REST_Response( array( 'error' => $event->get_error_message() ), 400 );
	}

	$result = $stripe_service->handle_webhook_event( $event );

	return new WP_REST_Response( array( 'received' => true ), 200 );
}
