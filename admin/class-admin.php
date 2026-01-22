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
	 * Force create database tables with detailed logging
	 *
	 * @return array Result with success status and log.
	 */
	private function force_create_tables() {
		$log = array();
		$success = false;

		$log[] = 'Starting table creation...';
		$log[] = 'AA_CRM_DB_NAME: ' . ( defined( 'AA_CRM_DB_NAME' ) ? AA_CRM_DB_NAME : 'NOT DEFINED' );
		$log[] = 'AA_CRM_DB_USER: ' . ( defined( 'AA_CRM_DB_USER' ) ? AA_CRM_DB_USER : 'NOT DEFINED' );
		$log[] = 'AA_CRM_DB_HOST: ' . ( defined( 'AA_CRM_DB_HOST' ) ? AA_CRM_DB_HOST : 'NOT DEFINED' );
		$log[] = 'AA_CRM_DB_PREFIX: ' . ( defined( 'AA_CRM_DB_PREFIX' ) ? AA_CRM_DB_PREFIX : 'NOT DEFINED (default: crm_)' );

		// Check WordPress database name.
		global $wpdb;
		$log[] = 'WordPress DB: ' . $wpdb->dbname;

		if ( defined( 'AA_CRM_DB_NAME' ) && AA_CRM_DB_NAME === $wpdb->dbname ) {
			$log[] = 'ERROR: CRM database name matches WordPress database! Aborting for safety.';
			return array( 'success' => false, 'log' => implode( "\n", $log ) );
		}

		// Get connection.
		AA_Customers_DB_Connection::reset_connection();
		$db = AA_Customers_DB_Connection::get_connection();

		$log[] = 'Connected to: ' . $db->dbname;
		$log[] = 'Using prefix: ' . $db->prefix;

		// Verify connection.
		$test = $db->get_var( 'SELECT 1' );
		if ( 1 !== (int) $test ) {
			$log[] = 'ERROR: Connection test failed!';
			return array( 'success' => false, 'log' => implode( "\n", $log ) );
		}
		$log[] = 'Connection test: OK';

		// Get charset.
		$charset_collate = $db->get_charset_collate();
		$log[] = 'Charset: ' . $charset_collate;

		// Create members table.
		$table_name = $db->prefix . 'members';
		$log[] = 'Creating table: ' . $table_name;

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			wp_user_id BIGINT UNSIGNED NOT NULL,
			stripe_customer_id VARCHAR(255) NULL,
			full_name VARCHAR(255) NOT NULL,
			email VARCHAR(255) NOT NULL,
			phone VARCHAR(50) NULL,
			address_line1 VARCHAR(255) NULL,
			address_line2 VARCHAR(255) NULL,
			city VARCHAR(100) NULL,
			postcode VARCHAR(20) NULL,
			country VARCHAR(100) DEFAULT 'United Kingdom',
			membership_type VARCHAR(50) NULL,
			membership_status VARCHAR(20) DEFAULT 'pending',
			membership_start DATE NULL,
			membership_expiry DATE NULL,
			is_student TINYINT(1) DEFAULT 0,
			qualifications TEXT NULL,
			accreditation_level VARCHAR(100) NULL,
			practice_areas TEXT NULL,
			insurance_provider VARCHAR(255) NULL,
			insurance_expiry DATE NULL,
			show_in_directory TINYINT(1) DEFAULT 0,
			directory_bio TEXT NULL,
			directory_photo VARCHAR(500) NULL,
			website VARCHAR(255) NULL,
			notes TEXT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY wp_user_id (wp_user_id),
			KEY membership_status (membership_status),
			KEY membership_expiry (membership_expiry),
			KEY show_in_directory (show_in_directory)
		) $charset_collate;";

		$result = $db->query( $sql );
		if ( false === $result ) {
			$log[] = 'ERROR creating members table: ' . $db->last_error;
		} else {
			$log[] = 'Members table: OK';
		}

		// Create products table.
		$table_name = $db->prefix . 'products';
		$log[] = 'Creating table: ' . $table_name;

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			description TEXT NULL,
			type VARCHAR(50) NOT NULL,
			price_member DECIMAL(10,2) DEFAULT 0.00,
			price_non_member DECIMAL(10,2) DEFAULT 0.00,
			stripe_price_id VARCHAR(255) NULL,
			stripe_price_id_non_member VARCHAR(255) NULL,
			is_active TINYINT(1) DEFAULT 1,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY type (type),
			KEY is_active (is_active)
		) $charset_collate;";

		$result = $db->query( $sql );
		if ( false === $result ) {
			$log[] = 'ERROR creating products table: ' . $db->last_error;
		} else {
			$log[] = 'Products table: OK';
		}

		// Create events table.
		$table_name = $db->prefix . 'events';
		$log[] = 'Creating table: ' . $table_name;

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			event_date DATE NOT NULL,
			event_time TIME NULL,
			end_date DATE NULL,
			end_time TIME NULL,
			location VARCHAR(255) NULL,
			address TEXT NULL,
			capacity INT UNSIGNED NULL,
			registration_deadline DATE NULL,
			is_online TINYINT(1) DEFAULT 0,
			online_url VARCHAR(500) NULL,
			status VARCHAR(20) DEFAULT 'upcoming',
			notes TEXT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY product_id (product_id),
			KEY event_date (event_date),
			KEY status (status)
		) $charset_collate;";

		$result = $db->query( $sql );
		if ( false === $result ) {
			$log[] = 'ERROR creating events table: ' . $db->last_error;
		} else {
			$log[] = 'Events table: OK';
		}

		// Create purchases table.
		$table_name = $db->prefix . 'purchases';
		$log[] = 'Creating table: ' . $table_name;

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT UNSIGNED NULL,
			product_id BIGINT UNSIGNED NULL,
			event_id BIGINT UNSIGNED NULL,
			stripe_payment_intent VARCHAR(255) NULL,
			stripe_invoice_id VARCHAR(255) NULL,
			amount DECIMAL(10,2) NOT NULL,
			currency VARCHAR(3) DEFAULT 'GBP',
			payment_status VARCHAR(20) DEFAULT 'pending',
			payment_method VARCHAR(50) NULL,
			is_donation TINYINT(1) DEFAULT 0,
			donation_type VARCHAR(100) NULL,
			purchaser_name VARCHAR(255) NULL,
			purchaser_email VARCHAR(255) NULL,
			notes TEXT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY member_id (member_id),
			KEY product_id (product_id),
			KEY payment_status (payment_status),
			KEY created_at (created_at)
		) $charset_collate;";

		$result = $db->query( $sql );
		if ( false === $result ) {
			$log[] = 'ERROR creating purchases table: ' . $db->last_error;
		} else {
			$log[] = 'Purchases table: OK';
		}

		// Create registrations table.
		$table_name = $db->prefix . 'registrations';
		$log[] = 'Creating table: ' . $table_name;

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id BIGINT UNSIGNED NOT NULL,
			member_id BIGINT UNSIGNED NULL,
			purchase_id BIGINT UNSIGNED NULL,
			attendee_name VARCHAR(255) NOT NULL,
			attendee_email VARCHAR(255) NOT NULL,
			status VARCHAR(20) DEFAULT 'confirmed',
			dietary_requirements TEXT NULL,
			accessibility_needs TEXT NULL,
			notes TEXT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY event_id (event_id),
			KEY member_id (member_id),
			KEY status (status)
		) $charset_collate;";

		$result = $db->query( $sql );
		if ( false === $result ) {
			$log[] = 'ERROR creating registrations table: ' . $db->last_error;
		} else {
			$log[] = 'Registrations table: OK';
		}

		// Create options table.
		$table_name = $db->prefix . 'options';
		$log[] = 'Creating table: ' . $table_name;

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			option_name VARCHAR(255) NOT NULL,
			option_value LONGTEXT NULL,
			autoload VARCHAR(20) DEFAULT 'yes',
			PRIMARY KEY (id),
			UNIQUE KEY option_name (option_name)
		) $charset_collate;";

		$result = $db->query( $sql );
		if ( false === $result ) {
			$log[] = 'ERROR creating options table: ' . $db->last_error;
		} else {
			$log[] = 'Options table: OK';
		}

		// Create zap table (settings storage).
		$table_name = $db->prefix . 'zap';
		$log[] = 'Creating table: ' . $table_name;

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			zap_key VARCHAR(255) NOT NULL,
			zap_value LONGTEXT NULL,
			zap_lock TINYINT(1) DEFAULT 0,
			zap_tag VARCHAR(50) DEFAULT 'config',
			zap_stamp DATETIME DEFAULT CURRENT_TIMESTAMP,
			zap_pulse DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (zap_key),
			KEY idx_tag (zap_tag)
		) $charset_collate;";

		$result = $db->query( $sql );
		if ( false === $result ) {
			$log[] = 'ERROR creating zap table: ' . $db->last_error;
		} else {
			$log[] = 'Zap table: OK';
		}

		// Verify tables were created.
		$check_table = $db->prefix . 'members';
		$exists = $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $check_table ) );
		if ( $exists ) {
			$log[] = '';
			$log[] = 'SUCCESS: All tables created!';
			$success = true;
		} else {
			$log[] = '';
			$log[] = 'FAILED: Tables were not created. Check database permissions.';
		}

		return array( 'success' => $success, 'log' => implode( "\n", $log ) );
	}

	/**
	 * Get database connection status for diagnostics
	 *
	 * @return array Status info.
	 */
	private function get_database_status() {
		$status = array(
			'connected'      => false,
			'tables_exist'   => false,
			'missing_tables' => array(),
			'error'          => '',
		);

		if ( ! AA_Customers_DB_Connection::is_separate_database() ) {
			$status['error'] = 'Database constants not defined';
			return $status;
		}

		try {
			$db = AA_Customers_DB_Connection::get_connection();

			// Test connection.
			$test = $db->get_var( 'SELECT 1' );
			if ( 1 === (int) $test ) {
				$status['connected'] = true;

				// Check all required tables.
				$required_tables = array( 'members', 'products', 'events', 'purchases', 'registrations', 'options', 'zap' );
				$missing = array();

				foreach ( $required_tables as $table ) {
					$table_name = $db->prefix . $table;
					$exists = $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
					if ( empty( $exists ) ) {
						$missing[] = $table;
					}
				}

				$status['missing_tables'] = $missing;
				$status['tables_exist'] = empty( $missing );
			} else {
				$status['error'] = 'Connection test failed';
			}
		} catch ( Exception $e ) {
			$status['error'] = $e->getMessage();
		}

		return $status;
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

		// Handle manual table creation request.
		$creation_result = null;
		if ( $db_configured && isset( $_GET['create_tables'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'aa_create_tables' ) ) {
			$creation_result = $this->force_create_tables();
		}

		// Get diagnostic info.
		$db_status = $this->get_database_status();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AA Customers Dashboard', 'aa-customers' ); ?></h1>

			<?php if ( $creation_result ) : ?>
				<div class="notice notice-<?php echo $creation_result['success'] ? 'success' : 'error'; ?>" style="padding: 15px;">
					<strong>Table Creation Result:</strong>
					<pre style="background: #f5f5f5; padding: 10px; margin-top: 10px; overflow-x: auto;"><?php echo esc_html( $creation_result['log'] ); ?></pre>
				</div>
			<?php endif; ?>

			<!-- Database Status -->
			<div class="notice notice-<?php echo $db_status['connected'] && $db_status['tables_exist'] ? 'success' : 'warning'; ?>" style="padding: 15px;">
				<strong>Database Status:</strong>
				<ul style="margin: 10px 0 0 20px;">
					<li>Constants Defined: <?php echo $db_configured ? '✓ Yes' : '✗ No'; ?></li>
					<?php if ( $db_configured ) : ?>
						<li>Database Name: <?php echo esc_html( defined( 'AA_CRM_DB_NAME' ) ? AA_CRM_DB_NAME : 'Not set' ); ?></li>
						<li>Table Prefix: <?php echo esc_html( defined( 'AA_CRM_DB_PREFIX' ) ? AA_CRM_DB_PREFIX : 'crm_' ); ?></li>
						<li>Connection: <?php echo $db_status['connected'] ? '✓ Connected' : '✗ Failed'; ?></li>
						<li>All Tables Exist: <?php echo $db_status['tables_exist'] ? '✓ Yes' : '✗ No'; ?></li>
						<?php if ( ! empty( $db_status['missing_tables'] ) ) : ?>
							<li style="color: orange;">Missing Tables: <?php echo esc_html( implode( ', ', $db_status['missing_tables'] ) ); ?></li>
						<?php endif; ?>
						<?php if ( ! empty( $db_status['error'] ) ) : ?>
							<li style="color: red;">Error: <?php echo esc_html( $db_status['error'] ); ?></li>
						<?php endif; ?>
						<?php if ( ! $db_status['tables_exist'] || ! empty( $db_status['missing_tables'] ) ) : ?>
							<li style="margin-top: 10px;">
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=aa-customers&create_tables=1' ), 'aa_create_tables' ) ); ?>" class="button button-primary">
									Create Missing Tables
								</a>
							</li>
						<?php endif; ?>
					<?php endif; ?>
				</ul>
			</div>

			<?php if ( ! $db_configured ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Database not configured.', 'aa-customers' ); ?></strong>
						<?php esc_html_e( 'Add the following constants to wp-config.php:', 'aa-customers' ); ?>
					</p>
					<pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">
define('AA_CRM_DB_HOST', 'localhost');
define('AA_CRM_DB_USER', 'your_username');
define('AA_CRM_DB_PASSWORD', 'your_password');
define('AA_CRM_DB_NAME', 'ukagp_crm');
define('AA_CRM_DB_CHARSET', 'utf8mb4');
define('AA_CRM_DB_PREFIX', 'crm_');
define('AA_CRM_ENCRYPTION_KEY', 'your_encryption_key');</pre>
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
