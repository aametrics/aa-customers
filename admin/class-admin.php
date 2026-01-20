<?php
/**
 * Main Admin Class
 *
 * Handles admin menu registration and main dashboard.
 *
 * @package AA_Customers
 * @subpackage Admin
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Admin
 */
class AA_Customers_Admin extends AA_Customers_Base_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register admin menu
	 *
	 * @return void
	 */
	public function register_menu() {
		// Main menu.
		add_menu_page(
			__( 'AA Customers', 'aa-customers' ),
			__( 'AA Customers', 'aa-customers' ),
			'manage_options',
			'aa-customers',
			array( $this, 'render_dashboard' ),
			'dashicons-groups',
			30
		);

		// Dashboard submenu (same as main).
		add_submenu_page(
			'aa-customers',
			__( 'Dashboard', 'aa-customers' ),
			__( 'Dashboard', 'aa-customers' ),
			'manage_options',
			'aa-customers',
			array( $this, 'render_dashboard' )
		);

		// Members submenu.
		add_submenu_page(
			'aa-customers',
			__( 'Members', 'aa-customers' ),
			__( 'Members', 'aa-customers' ),
			'manage_options',
			'aa-customers-members',
			array( $this, 'render_members' )
		);

		// Products submenu.
		add_submenu_page(
			'aa-customers',
			__( 'Products', 'aa-customers' ),
			__( 'Products', 'aa-customers' ),
			'manage_options',
			'aa-customers-products',
			array( $this, 'render_products' )
		);

		// Events submenu.
		add_submenu_page(
			'aa-customers',
			__( 'Events', 'aa-customers' ),
			__( 'Events', 'aa-customers' ),
			'manage_options',
			'aa-customers-events',
			array( $this, 'render_events' )
		);

		// Purchases submenu.
		add_submenu_page(
			'aa-customers',
			__( 'Purchases', 'aa-customers' ),
			__( 'Purchases', 'aa-customers' ),
			'manage_options',
			'aa-customers-purchases',
			array( $this, 'render_purchases' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		// Only load on our pages.
		if ( strpos( $hook, 'aa-customers' ) === false ) {
			return;
		}

		// Admin CSS.
		wp_enqueue_style(
			'aa-customers-admin',
			AA_CUSTOMERS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			AA_CUSTOMERS_VERSION
		);

		// Admin JS.
		wp_enqueue_script(
			'aa-customers-admin',
			AA_CUSTOMERS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			AA_CUSTOMERS_VERSION,
			true
		);

		wp_localize_script( 'aa-customers-admin', 'aaCustomers', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'aa_customers_ajax' ),
		) );
	}

	/**
	 * Render dashboard page
	 *
	 * @return void
	 */
	public function render_dashboard() {
		if ( ! $this->check_capability() ) {
			wp_die( __( 'Unauthorized access', 'aa-customers' ) );
		}

		// Check if database is configured.
		$db_configured = AA_Customers_DB_Connection::is_separate_database();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AA Customers Dashboard', 'aa-customers' ); ?></h1>

			<?php if ( ! $db_configured ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Database not configured.', 'aa-customers' ); ?></strong>
						<?php esc_html_e( 'Add the following constants to wp-config.php:', 'aa-customers' ); ?>
					</p>
					<pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">
define('AA_CUSTOMER_DB_HOST', 'localhost');
define('AA_CUSTOMER_DB_USER', 'your_username');
define('AA_CUSTOMER_DB_PASS', 'your_password');
define('AA_CUSTOMER_DB_NAME', 'aa_customer');
define('AA_CUSTOMER_DB_PREFIX', 'aac_');</pre>
				</div>
			<?php else : ?>
				<div class="notice notice-success">
					<p><?php esc_html_e( 'Database connected successfully.', 'aa-customers' ); ?></p>
				</div>

				<div class="aa-customers-dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">

					<div class="aa-customers-card" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 4px;">
						<h2 style="margin-top: 0;"><?php esc_html_e( 'Members', 'aa-customers' ); ?></h2>
						<p class="aa-customers-stat" style="font-size: 36px; font-weight: bold; margin: 20px 0;">—</p>
						<p><?php esc_html_e( 'Total members', 'aa-customers' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-members' ) ); ?>" class="button">
							<?php esc_html_e( 'View Members', 'aa-customers' ); ?>
						</a>
					</div>

					<div class="aa-customers-card" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 4px;">
						<h2 style="margin-top: 0;"><?php esc_html_e( 'Active Memberships', 'aa-customers' ); ?></h2>
						<p class="aa-customers-stat" style="font-size: 36px; font-weight: bold; margin: 20px 0;">—</p>
						<p><?php esc_html_e( 'Currently active', 'aa-customers' ); ?></p>
					</div>

					<div class="aa-customers-card" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 4px;">
						<h2 style="margin-top: 0;"><?php esc_html_e( 'Expiring Soon', 'aa-customers' ); ?></h2>
						<p class="aa-customers-stat" style="font-size: 36px; font-weight: bold; margin: 20px 0;">—</p>
						<p><?php esc_html_e( 'Next 30 days', 'aa-customers' ); ?></p>
					</div>

					<div class="aa-customers-card" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 4px;">
						<h2 style="margin-top: 0;"><?php esc_html_e( 'Revenue', 'aa-customers' ); ?></h2>
						<p class="aa-customers-stat" style="font-size: 36px; font-weight: bold; margin: 20px 0;">£—</p>
						<p><?php esc_html_e( 'This month', 'aa-customers' ); ?></p>
					</div>

				</div>

				<div style="margin-top: 30px;">
					<h2><?php esc_html_e( 'Quick Actions', 'aa-customers' ); ?></h2>
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-members&action=add' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Add Member', 'aa-customers' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-products' ) ); ?>" class="button">
							<?php esc_html_e( 'Manage Products', 'aa-customers' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-settings' ) ); ?>" class="button">
							<?php esc_html_e( 'Settings', 'aa-customers' ); ?>
						</a>
					</p>
				</div>

			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Render members page (placeholder)
	 *
	 * @return void
	 */
	public function render_members() {
		if ( ! $this->check_capability() ) {
			wp_die( __( 'Unauthorized access', 'aa-customers' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Members', 'aa-customers' ); ?></h1>
			<p><?php esc_html_e( 'Member management coming soon.', 'aa-customers' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render products page (placeholder)
	 *
	 * @return void
	 */
	public function render_products() {
		if ( ! $this->check_capability() ) {
			wp_die( __( 'Unauthorized access', 'aa-customers' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Products', 'aa-customers' ); ?></h1>
			<p><?php esc_html_e( 'Product management coming soon.', 'aa-customers' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render events page (placeholder)
	 *
	 * @return void
	 */
	public function render_events() {
		if ( ! $this->check_capability() ) {
			wp_die( __( 'Unauthorized access', 'aa-customers' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Events', 'aa-customers' ); ?></h1>
			<p><?php esc_html_e( 'Event management coming soon.', 'aa-customers' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render purchases page (placeholder)
	 *
	 * @return void
	 */
	public function render_purchases() {
		if ( ! $this->check_capability() ) {
			wp_die( __( 'Unauthorized access', 'aa-customers' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Purchases', 'aa-customers' ); ?></h1>
			<p><?php esc_html_e( 'Purchase history coming soon.', 'aa-customers' ); ?></p>
		</div>
		<?php
	}
}
