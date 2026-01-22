<?php
/**
 * Settings Admin Class
 *
 * Handles plugin settings including Stripe and Xero API configuration.
 *
 * @package AA_Customers
 * @subpackage Admin
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Settings
 */
class AA_Customers_Settings extends AA_Customers_Base_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_aa_customers_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_xero_callback' ) );
		add_action( 'admin_post_aa_customers_xero_connect', array( $this, 'handle_xero_connect' ) );
		add_action( 'admin_post_aa_customers_xero_disconnect', array( $this, 'handle_xero_disconnect' ) );
	}

	/**
	 * Register settings submenu
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'aa-customers',
			__( 'Settings', 'aa-customers' ),
			__( 'Settings', 'aa-customers' ),
			'manage_options',
			'aa-customers-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public function render_settings() {
		if ( ! $this->check_capability() ) {
			wp_die( __( 'Unauthorized access', 'aa-customers' ) );
		}

		$current_tab = $this->get_param( 'tab', 'general' );

		// Get current settings.
		$settings = array(
			// General.
			'email_from_name'       => AA_Customers_Zap_Storage::get( 'email_from_name', get_bloginfo( 'name' ) ),
			'email_from_address'    => AA_Customers_Zap_Storage::get( 'email_from_address', get_option( 'admin_email' ) ),
			'email_bcc_enabled'     => AA_Customers_Zap_Storage::get( 'email_bcc_enabled', false ),
			'email_bcc_address'     => AA_Customers_Zap_Storage::get( 'email_bcc_address', '' ),
			'checkout_success_url'  => AA_Customers_Zap_Storage::get( 'checkout_success_url', '/checkout-success' ),
			'member_dashboard_url'  => AA_Customers_Zap_Storage::get( 'member_dashboard_url', '/member-dashboard' ),

			// Stripe.
			'stripe_mode'              => AA_Customers_Zap_Storage::get( 'stripe_mode', 'test' ),
			'stripe_test_publishable'  => AA_Customers_Zap_Storage::get( 'stripe_test_publishable', '' ),
			'stripe_test_secret'       => AA_Customers_Zap_Storage::get( 'stripe_test_secret', '' ),
			'stripe_live_publishable'  => AA_Customers_Zap_Storage::get( 'stripe_live_publishable', '' ),
			'stripe_live_secret'       => AA_Customers_Zap_Storage::get( 'stripe_live_secret', '' ),
			'stripe_webhook_secret'    => AA_Customers_Zap_Storage::get( 'stripe_webhook_secret', '' ),

			// Xero.
			'xero_client_id'     => AA_Customers_Zap_Storage::get( 'xero_client_id', '' ),
			'xero_client_secret' => AA_Customers_Zap_Storage::get( 'xero_client_secret', '' ),
			'xero_tenant_id'     => AA_Customers_Zap_Storage::get( 'xero_tenant_id', '' ),
		);

		// Success/error messages.
		$this->display_admin_notices(
			array( 'saved' => __( 'Settings saved successfully.', 'aa-customers' ) ),
			array( 'save_failed' => __( 'Failed to save settings.', 'aa-customers' ) )
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AA Customers Settings', 'aa-customers' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-settings&tab=general' ) ); ?>"
				   class="nav-tab <?php echo 'general' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'aa-customers' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-settings&tab=stripe' ) ); ?>"
				   class="nav-tab <?php echo 'stripe' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Stripe', 'aa-customers' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-settings&tab=xero' ) ); ?>"
				   class="nav-tab <?php echo 'xero' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Xero', 'aa-customers' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-settings&tab=emails' ) ); ?>"
				   class="nav-tab <?php echo 'emails' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Email Templates', 'aa-customers' ); ?>
				</a>
			</h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="aa_customers_save_settings">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>">
				<?php wp_nonce_field( 'aa_customers_settings', 'aa_customers_nonce' ); ?>

				<?php
				switch ( $current_tab ) {
					case 'stripe':
						$this->render_stripe_tab( $settings );
						break;
					case 'xero':
						$this->render_xero_tab( $settings );
						break;
					case 'emails':
						$this->render_emails_tab();
						break;
					default:
						$this->render_general_tab( $settings );
						break;
				}
				?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render general settings tab
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_general_tab( $settings ) {
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="email_from_name"><?php esc_html_e( 'Email From Name', 'aa-customers' ); ?></label>
				</th>
				<td>
					<input type="text" name="email_from_name" id="email_from_name"
						   value="<?php echo esc_attr( $settings['email_from_name'] ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="email_from_address"><?php esc_html_e( 'Email From Address', 'aa-customers' ); ?></label>
				</th>
				<td>
					<input type="email" name="email_from_address" id="email_from_address"
						   value="<?php echo esc_attr( $settings['email_from_address'] ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="email_bcc_enabled"><?php esc_html_e( 'BCC Admin', 'aa-customers' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="email_bcc_enabled" id="email_bcc_enabled" value="1"
							   <?php checked( $settings['email_bcc_enabled'] ); ?>>
						<?php esc_html_e( 'Send a copy of all emails to admin', 'aa-customers' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="email_bcc_address"><?php esc_html_e( 'BCC Address', 'aa-customers' ); ?></label>
				</th>
				<td>
					<input type="email" name="email_bcc_address" id="email_bcc_address"
						   value="<?php echo esc_attr( $settings['email_bcc_address'] ); ?>" class="regular-text">
				</td>
			</tr>

			<tr>
				<th colspan="2"><h3><?php esc_html_e( 'Page URLs', 'aa-customers' ); ?></h3></th>
			</tr>
			<tr>
				<th scope="row">
					<label for="checkout_success_url"><?php esc_html_e( 'Checkout Success Page', 'aa-customers' ); ?></label>
				</th>
				<td>
					<input type="text" name="checkout_success_url" id="checkout_success_url"
						   value="<?php echo esc_attr( $settings['checkout_success_url'] ); ?>" class="regular-text"
						   placeholder="/checkout-success">
					<p class="description">
						<?php esc_html_e( 'Path to the checkout success page (relative to site root, e.g., /checkout-success)', 'aa-customers' ); ?>
						<br><strong><?php esc_html_e( 'Full URL:', 'aa-customers' ); ?></strong> 
						<code><?php echo esc_url( home_url( $settings['checkout_success_url'] ) ); ?></code>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="member_dashboard_url"><?php esc_html_e( 'Member Dashboard Page', 'aa-customers' ); ?></label>
				</th>
				<td>
					<input type="text" name="member_dashboard_url" id="member_dashboard_url"
						   value="<?php echo esc_attr( $settings['member_dashboard_url'] ); ?>" class="regular-text"
						   placeholder="/member-dashboard">
					<p class="description">
						<?php esc_html_e( 'Path to the member dashboard page (relative to site root)', 'aa-customers' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render Stripe settings tab
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_stripe_tab( $settings ) {
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Mode', 'aa-customers' ); ?></th>
				<td>
					<label>
						<input type="radio" name="stripe_mode" value="test"
							   <?php checked( $settings['stripe_mode'], 'test' ); ?>>
						<?php esc_html_e( 'Test Mode', 'aa-customers' ); ?>
					</label>
					<br>
					<label>
						<input type="radio" name="stripe_mode" value="live"
							   <?php checked( $settings['stripe_mode'], 'live' ); ?>>
						<?php esc_html_e( 'Live Mode', 'aa-customers' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th colspan="2"><h3><?php esc_html_e( 'Test Keys', 'aa-customers' ); ?></h3></th>
			</tr>
			<tr>
				<th scope="row">
					<label for="stripe_test_publishable"><?php esc_html_e( 'Publishable Key', 'aa-customers' ); ?></label>
				</th>
				<td>
					<input type="text" name="stripe_test_publishable" id="stripe_test_publishable"
						   value="<?php echo esc_attr( $settings['stripe_test_publishable'] ); ?>" class="large-text"
						   placeholder="pk_test_...">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="stripe_test_secret"><?php esc_html_e( 'Secret Key', 'aa-customers' ); ?></label>
				</th>
				<td>
					<input type="password" name="stripe_test_secret" id="stripe_test_secret"
						   value="<?php echo esc_attr( $settings['stripe_test_secret'] ); ?>" class="large-text"
						   placeholder="sk_test_...">
				</td>
			</tr>

			<tr>
				<th colspan="2"><h3><?php esc_html_e( 'Live Keys', 'aa-customers' ); ?></h3></th>
			</tr>
			<tr>
				<th scope="row">
					<label for="stripe_live_publishable"><?php esc_html_e( 'Publishable Key', 'aa-customers' ); ?></label>
				</th>
				<td>
					<input type="text" name="stripe_live_publishable" id="stripe_live_publishable"
						   value="<?php echo esc_attr( $settings['stripe_live_publishable'] ); ?>" class="large-text"
						   placeholder="pk_live_...">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="stripe_live_secret"><?php esc_html_e( 'Secret Key', 'aa-customers' ); ?></label>
				</th>
				<td>
					<input type="password" name="stripe_live_secret" id="stripe_live_secret"
						   value="<?php echo esc_attr( $settings['stripe_live_secret'] ); ?>" class="large-text"
						   placeholder="sk_live_...">
				</td>
			</tr>

			<tr>
				<th colspan="2"><h3><?php esc_html_e( 'Webhook', 'aa-customers' ); ?></h3></th>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Webhook URL', 'aa-customers' ); ?></th>
				<td>
					<code><?php echo esc_url( home_url( '/wp-json/aa-customers/v1/stripe-webhook' ) ); ?></code>
					<p class="description"><?php esc_html_e( 'Add this URL to your Stripe webhook settings.', 'aa-customers' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="stripe_webhook_secret"><?php esc_html_e( 'Webhook Secret', 'aa-customers' ); ?></label>
				</th>
				<td>
					<input type="password" name="stripe_webhook_secret" id="stripe_webhook_secret"
						   value="<?php echo esc_attr( $settings['stripe_webhook_secret'] ); ?>" class="large-text"
						   placeholder="whsec_...">
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render Xero settings tab
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_xero_tab( $settings ) {
		$xero_service   = new AA_Customers_Xero_Service();
		$xero_connected = $xero_service->is_connected();
		$tenant_name    = AA_Customers_Zap_Storage::get( 'xero_tenant_name', '' );

		// Display OAuth callback messages.
		if ( isset( $_GET['xero_success'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Successfully connected to Xero!', 'aa-customers' ) . '</p></div>';
		}
		if ( isset( $_GET['xero_error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( urldecode( $_GET['xero_error'] ) ) . '</p></div>';
		}
		if ( isset( $_GET['xero_disconnected'] ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Disconnected from Xero.', 'aa-customers' ) . '</p></div>';
		}

		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Connection Status', 'aa-customers' ); ?></th>
				<td>
					<?php if ( $xero_connected ) : ?>
						<span style="color: green; font-weight: bold;">● <?php esc_html_e( 'Connected', 'aa-customers' ); ?></span>
						<?php if ( $tenant_name ) : ?>
							<br><small><?php echo esc_html( sprintf( __( 'Organization: %s', 'aa-customers' ), $tenant_name ) ); ?></small>
						<?php endif; ?>
					<?php else : ?>
						<span style="color: orange;">○ <?php esc_html_e( 'Not Connected', 'aa-customers' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="xero_client_id"><?php esc_html_e( 'Client ID', 'aa-customers' ); ?></label>
				</th>
				<td>
					<input type="text" name="xero_client_id" id="xero_client_id"
						   value="<?php echo esc_attr( $settings['xero_client_id'] ); ?>" class="large-text"
						   <?php echo $xero_connected ? 'readonly' : ''; ?>>
					<p class="description"><?php esc_html_e( 'From your Xero app at developer.xero.com', 'aa-customers' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="xero_client_secret"><?php esc_html_e( 'Client Secret', 'aa-customers' ); ?></label>
				</th>
				<td>
					<input type="password" name="xero_client_secret" id="xero_client_secret"
						   value="<?php echo esc_attr( $settings['xero_client_secret'] ); ?>" class="large-text"
						   <?php echo $xero_connected ? 'readonly' : ''; ?>>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Redirect URI', 'aa-customers' ); ?></th>
				<td>
					<code><?php echo esc_url( admin_url( 'admin.php?page=aa-customers-settings&tab=xero&xero_callback=1' ) ); ?></code>
					<p class="description"><?php esc_html_e( 'Add this exact URL to your Xero app\'s redirect URIs.', 'aa-customers' ); ?></p>
				</td>
			</tr>

			<?php if ( $xero_connected ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Xero Account Settings', 'aa-customers' ); ?></th>
				<td>
					<label for="xero_sales_account"><?php esc_html_e( 'Sales Account Code:', 'aa-customers' ); ?></label>
					<input type="text" name="xero_sales_account" id="xero_sales_account"
						   value="<?php echo esc_attr( AA_Customers_Zap_Storage::get( 'xero_sales_account', '200' ) ); ?>" 
						   class="small-text" placeholder="200">
					<br><br>
					<label for="xero_stripe_account_code"><?php esc_html_e( 'Stripe Bank Account Code:', 'aa-customers' ); ?></label>
					<input type="text" name="xero_stripe_account_code" id="xero_stripe_account_code"
						   value="<?php echo esc_attr( AA_Customers_Zap_Storage::get( 'xero_stripe_account_code', '090' ) ); ?>" 
						   class="small-text" placeholder="090">
					<p class="description"><?php esc_html_e( 'The Xero account codes for sales revenue and your Stripe bank account.', 'aa-customers' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Disconnect', 'aa-customers' ); ?></th>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=aa_customers_xero_disconnect&_wpnonce=' . wp_create_nonce( 'xero_disconnect' ) ) ); ?>" 
					   class="button button-secondary"
					   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to disconnect from Xero?', 'aa-customers' ); ?>');">
						<?php esc_html_e( 'Disconnect from Xero', 'aa-customers' ); ?>
					</a>
				</td>
			</tr>
			<?php elseif ( ! empty( $settings['xero_client_id'] ) && ! empty( $settings['xero_client_secret'] ) ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Connect', 'aa-customers' ); ?></th>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=aa_customers_xero_connect&_wpnonce=' . wp_create_nonce( 'xero_connect' ) ) ); ?>" 
					   class="button button-primary">
						<?php esc_html_e( 'Connect to Xero', 'aa-customers' ); ?>
					</a>
					<p class="description"><?php esc_html_e( 'You will be redirected to Xero to authorize this connection.', 'aa-customers' ); ?></p>
				</td>
			</tr>
			<?php else : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Setup Instructions', 'aa-customers' ); ?></th>
				<td>
					<ol>
						<li><?php esc_html_e( 'Go to developer.xero.com and create a new app', 'aa-customers' ); ?></li>
						<li><?php esc_html_e( 'Choose "Web app" as the integration type', 'aa-customers' ); ?></li>
						<li><?php esc_html_e( 'Copy the Redirect URI above into your Xero app settings', 'aa-customers' ); ?></li>
						<li><?php esc_html_e( 'Copy your Client ID and Client Secret here', 'aa-customers' ); ?></li>
						<li><?php esc_html_e( 'Save settings, then click Connect to Xero', 'aa-customers' ); ?></li>
					</ol>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * Handle Xero connect button
	 */
	public function handle_xero_connect() {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'xero_connect' ) ) {
			wp_die( 'Security check failed' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$xero_service = new AA_Customers_Xero_Service();
		$auth_url     = $xero_service->get_authorization_url();

		wp_redirect( $auth_url );
		exit;
	}

	/**
	 * Handle Xero OAuth callback
	 */
	public function handle_xero_callback() {
		// Only process on the settings page with xero_callback parameter.
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'aa-customers-settings' ) {
			return;
		}
		if ( ! isset( $_GET['xero_callback'] ) ) {
			return;
		}
		if ( ! isset( $_GET['code'] ) ) {
			return;
		}

		$code  = sanitize_text_field( $_GET['code'] );
		$state = sanitize_text_field( $_GET['state'] ?? '' );

		$xero_service = new AA_Customers_Xero_Service();
		$result       = $xero_service->handle_callback( $code, $state );

		$redirect_url = admin_url( 'admin.php?page=aa-customers-settings&tab=xero' );

		if ( is_wp_error( $result ) ) {
			$redirect_url = add_query_arg( 'xero_error', urlencode( $result->get_error_message() ), $redirect_url );
		} else {
			$redirect_url = add_query_arg( 'xero_success', '1', $redirect_url );
		}

		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle Xero disconnect
	 */
	public function handle_xero_disconnect() {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'xero_disconnect' ) ) {
			wp_die( 'Security check failed' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$xero_service = new AA_Customers_Xero_Service();
		$xero_service->disconnect();

		wp_redirect( admin_url( 'admin.php?page=aa-customers-settings&tab=xero&xero_disconnected=1' ) );
		exit;
	}

	/**
	 * Render email templates tab
	 *
	 * @return void
	 */
	private function render_emails_tab() {
		?>
		<p><?php esc_html_e( 'Email template customization coming soon.', 'aa-customers' ); ?></p>
		<p><?php esc_html_e( 'Available variables:', 'aa-customers' ); ?></p>
		<ul>
			<li><code>{{member_name}}</code> - <?php esc_html_e( 'Member\'s full name', 'aa-customers' ); ?></li>
			<li><code>{{membership_type}}</code> - <?php esc_html_e( 'Membership type name', 'aa-customers' ); ?></li>
			<li><code>{{expiry_date}}</code> - <?php esc_html_e( 'Membership expiry date', 'aa-customers' ); ?></li>
			<li><code>{{renewal_link}}</code> - <?php esc_html_e( 'Link to renew', 'aa-customers' ); ?></li>
			<li><code>{{portal_link}}</code> - <?php esc_html_e( 'Member dashboard link', 'aa-customers' ); ?></li>
		</ul>
		<?php
	}

	/**
	 * Handle settings save
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		$this->verify_nonce( 'aa_customers_nonce', 'aa_customers_settings' );

		if ( ! $this->check_capability() ) {
			wp_die( __( 'Unauthorized access', 'aa-customers' ) );
		}

		$tab = $this->post_param( 'tab', 'general' );

		switch ( $tab ) {
			case 'stripe':
				AA_Customers_Zap_Storage::set( 'stripe_mode', $this->post_param( 'stripe_mode', 'test' ), 'config' );
				AA_Customers_Zap_Storage::set( 'stripe_test_publishable', $this->post_param( 'stripe_test_publishable' ), 'sensitive' );
				AA_Customers_Zap_Storage::set( 'stripe_test_secret', $this->post_param( 'stripe_test_secret' ), 'sensitive' );
				AA_Customers_Zap_Storage::set( 'stripe_live_publishable', $this->post_param( 'stripe_live_publishable' ), 'sensitive' );
				AA_Customers_Zap_Storage::set( 'stripe_live_secret', $this->post_param( 'stripe_live_secret' ), 'sensitive' );
				AA_Customers_Zap_Storage::set( 'stripe_webhook_secret', $this->post_param( 'stripe_webhook_secret' ), 'sensitive' );
				break;

			case 'xero':
				AA_Customers_Zap_Storage::set( 'xero_client_id', $this->post_param( 'xero_client_id' ), 'sensitive' );
				AA_Customers_Zap_Storage::set( 'xero_client_secret', $this->post_param( 'xero_client_secret' ), 'sensitive' );
				AA_Customers_Zap_Storage::set( 'xero_sales_account', $this->post_param( 'xero_sales_account', '200' ), 'config' );
				AA_Customers_Zap_Storage::set( 'xero_stripe_account_code', $this->post_param( 'xero_stripe_account_code', '090' ), 'config' );
				break;

			default:
				AA_Customers_Zap_Storage::set( 'email_from_name', $this->post_param( 'email_from_name' ), 'config' );
				AA_Customers_Zap_Storage::set( 'email_from_address', $this->post_param( 'email_from_address' ), 'config' );
				AA_Customers_Zap_Storage::set( 'email_bcc_enabled', (bool) $this->post_param( 'email_bcc_enabled' ), 'config' );
				AA_Customers_Zap_Storage::set( 'email_bcc_address', $this->post_param( 'email_bcc_address' ), 'config' );
				AA_Customers_Zap_Storage::set( 'checkout_success_url', $this->post_param( 'checkout_success_url', '/checkout-success' ), 'config' );
				AA_Customers_Zap_Storage::set( 'member_dashboard_url', $this->post_param( 'member_dashboard_url', '/member-dashboard' ), 'config' );
				break;
		}

		$this->redirect_with_message( 'aa-customers-settings', 'saved', array( 'tab' => $tab ) );
	}
}
