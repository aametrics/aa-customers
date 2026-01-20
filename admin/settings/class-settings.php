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
			'email_from_name'    => AA_Customers_Zap_Storage::get( 'email_from_name', get_bloginfo( 'name' ) ),
			'email_from_address' => AA_Customers_Zap_Storage::get( 'email_from_address', get_option( 'admin_email' ) ),
			'email_bcc_enabled'  => AA_Customers_Zap_Storage::get( 'email_bcc_enabled', false ),
			'email_bcc_address'  => AA_Customers_Zap_Storage::get( 'email_bcc_address', '' ),

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
		$xero_connected = ! empty( AA_Customers_Zap_Storage::get( 'xero_access_token' ) );

		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Connection Status', 'aa-customers' ); ?></th>
				<td>
					<?php if ( $xero_connected ) : ?>
						<span style="color: green;">● <?php esc_html_e( 'Connected', 'aa-customers' ); ?></span>
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
						   value="<?php echo esc_attr( $settings['xero_client_id'] ); ?>" class="large-text">
					<p class="description"><?php esc_html_e( 'From your Xero app at developer.xero.com', 'aa-customers' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="xero_client_secret"><?php esc_html_e( 'Client Secret', 'aa-customers' ); ?></label>
				</th>
				<td>
					<input type="password" name="xero_client_secret" id="xero_client_secret"
						   value="<?php echo esc_attr( $settings['xero_client_secret'] ); ?>" class="large-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Redirect URI', 'aa-customers' ); ?></th>
				<td>
					<code><?php echo esc_url( admin_url( 'admin.php?page=aa-customers-settings&tab=xero&xero_callback=1' ) ); ?></code>
					<p class="description"><?php esc_html_e( 'Add this to your Xero app\'s redirect URIs.', 'aa-customers' ); ?></p>
				</td>
			</tr>
			<?php if ( ! empty( $settings['xero_client_id'] ) && ! empty( $settings['xero_client_secret'] ) && ! $xero_connected ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Connect', 'aa-customers' ); ?></th>
				<td>
					<a href="#" class="button button-primary"><?php esc_html_e( 'Connect to Xero', 'aa-customers' ); ?></a>
					<p class="description"><?php esc_html_e( 'OAuth2 connection will be implemented.', 'aa-customers' ); ?></p>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
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
				break;

			default:
				AA_Customers_Zap_Storage::set( 'email_from_name', $this->post_param( 'email_from_name' ), 'config' );
				AA_Customers_Zap_Storage::set( 'email_from_address', $this->post_param( 'email_from_address' ), 'config' );
				AA_Customers_Zap_Storage::set( 'email_bcc_enabled', (bool) $this->post_param( 'email_bcc_enabled' ), 'config' );
				AA_Customers_Zap_Storage::set( 'email_bcc_address', $this->post_param( 'email_bcc_address' ), 'config' );
				break;
		}

		$this->redirect_with_message( 'aa-customers-settings', 'saved', array( 'tab' => $tab ) );
	}
}
