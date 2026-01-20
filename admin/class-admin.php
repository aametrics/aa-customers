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
	 * Members admin
	 *
	 * @var AA_Customers_Members_Admin
	 */
	private $members_admin;

	/**
	 * Products admin
	 *
	 * @var AA_Customers_Products_Admin
	 */
	private $products_admin;

	/**
	 * Events admin
	 *
	 * @var AA_Customers_Events_Admin
	 */
	private $events_admin;

	/**
	 * Purchases admin
	 *
	 * @var AA_Customers_Purchases_Admin
	 */
	private $purchases_admin;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Initialize sub-admin classes.
		$this->members_admin   = new AA_Customers_Members_Admin();
		$this->products_admin  = new AA_Customers_Products_Admin();
		$this->events_admin    = new AA_Customers_Events_Admin();
		$this->purchases_admin = new AA_Customers_Purchases_Admin();
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
				<?php
				// Get real statistics.
				$membership_service = new AA_Customers_Membership_Service();
				$stats = $membership_service->get_statistics();

				$purchases_repo = new AA_Customers_Purchases_Repository();
				$monthly_revenue = $purchases_repo->get_monthly_revenue();

				$events_repo = new AA_Customers_Events_Repository();
				$upcoming_events = $events_repo->get_upcoming_with_products( 5 );
				?>

				<div class="aa-customers-dashboard-grid">

					<div class="aa-customers-card">
						<h2><?php esc_html_e( 'Members', 'aa-customers' ); ?></h2>
						<p class="aa-customers-stat"><?php echo esc_html( $stats['total'] ); ?></p>
						<p><?php esc_html_e( 'Total members', 'aa-customers' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-members' ) ); ?>" class="button">
							<?php esc_html_e( 'View Members', 'aa-customers' ); ?>
						</a>
					</div>

					<div class="aa-customers-card">
						<h2><?php esc_html_e( 'Active Memberships', 'aa-customers' ); ?></h2>
						<p class="aa-customers-stat"><?php echo esc_html( $stats['active'] ); ?></p>
						<p><?php esc_html_e( 'Currently active', 'aa-customers' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-members&status=active' ) ); ?>" class="button">
							<?php esc_html_e( 'View Active', 'aa-customers' ); ?>
						</a>
					</div>

					<div class="aa-customers-card <?php echo $stats['expiring_30'] > 0 ? 'card-warning' : ''; ?>">
						<h2><?php esc_html_e( 'Expiring Soon', 'aa-customers' ); ?></h2>
						<p class="aa-customers-stat"><?php echo esc_html( $stats['expiring_30'] ); ?></p>
						<p><?php esc_html_e( 'Next 30 days', 'aa-customers' ); ?></p>
						<?php if ( $stats['expiring_7'] > 0 ) : ?>
							<p class="description"><?php echo esc_html( $stats['expiring_7'] ); ?> expiring within 7 days</p>
						<?php endif; ?>
					</div>

					<div class="aa-customers-card">
						<h2><?php esc_html_e( 'Revenue', 'aa-customers' ); ?></h2>
						<p class="aa-customers-stat">£<?php echo esc_html( number_format( $monthly_revenue, 0 ) ); ?></p>
						<p><?php echo esc_html( date( 'F Y' ) ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-purchases' ) ); ?>" class="button">
							<?php esc_html_e( 'View Purchases', 'aa-customers' ); ?>
						</a>
					</div>

				</div>

				<?php if ( ! empty( $upcoming_events ) ) : ?>
					<div class="aa-customers-upcoming-events">
						<h2><?php esc_html_e( 'Upcoming Events', 'aa-customers' ); ?></h2>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th>Event</th>
									<th>Date</th>
									<th>Registrations</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $upcoming_events as $event ) : ?>
									<?php $reg_count = $events_repo->get_registration_count( $event->id ); ?>
									<tr>
										<td>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-events&action=edit&id=' . $event->id ) ); ?>">
												<?php echo esc_html( $event->name ); ?>
											</a>
										</td>
										<td><?php echo esc_html( date( 'j M Y', strtotime( $event->event_date ) ) ); ?></td>
										<td>
											<?php echo esc_html( $reg_count ); ?>
											<?php if ( $event->capacity ) : ?>
												/ <?php echo esc_html( $event->capacity ); ?>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-events' ) ); ?>" class="button">
								<?php esc_html_e( 'View All Events', 'aa-customers' ); ?>
							</a>
						</p>
					</div>
				<?php endif; ?>

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
	 * Render members page
	 *
	 * @return void
	 */
	public function render_members() {
		if ( ! $this->check_capability() ) {
			wp_die( __( 'Unauthorized access', 'aa-customers' ) );
		}

		$this->members_admin->render();
	}

	/**
	 * Render products page
	 *
	 * @return void
	 */
	public function render_products() {
		if ( ! $this->check_capability() ) {
			wp_die( __( 'Unauthorized access', 'aa-customers' ) );
		}

		$this->products_admin->render();
	}

	/**
	 * Render events page
	 *
	 * @return void
	 */
	public function render_events() {
		if ( ! $this->check_capability() ) {
			wp_die( __( 'Unauthorized access', 'aa-customers' ) );
		}

		$this->events_admin->render();
	}

	/**
	 * Render purchases page
	 *
	 * @return void
	 */
	public function render_purchases() {
		if ( ! $this->check_capability() ) {
			wp_die( __( 'Unauthorized access', 'aa-customers' ) );
		}

		$this->purchases_admin->render();
	}
}
