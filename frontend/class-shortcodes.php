<?php
/**
 * Shortcodes Handler
 *
 * Registers and renders all frontend shortcodes.
 *
 * @package AA_Customers
 * @subpackage Frontend
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Shortcodes
 */
class AA_Customers_Shortcodes {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register all shortcodes
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		add_shortcode( 'aa_member_dashboard', array( $this, 'render_member_dashboard' ) );
		add_shortcode( 'aa_practitioner_directory', array( $this, 'render_practitioner_directory' ) );
		add_shortcode( 'aa_join', array( $this, 'render_join_page' ) );
		add_shortcode( 'aa_events', array( $this, 'render_events_list' ) );
		add_shortcode( 'aa_event', array( $this, 'render_single_event' ) );
		add_shortcode( 'aa_checkout_success', array( $this, 'render_checkout_success' ) );
	}

	/**
	 * Enqueue frontend assets
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		// Only load on pages with our shortcodes.
		global $post;

		if ( ! $post ) {
			return;
		}

		$shortcodes = array(
			'aa_member_dashboard',
			'aa_practitioner_directory',
			'aa_join',
			'aa_events',
			'aa_event',
			'aa_checkout_success',
		);

		$has_shortcode = false;
		foreach ( $shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				$has_shortcode = true;
				break;
			}
		}

		if ( ! $has_shortcode ) {
			return;
		}

		wp_enqueue_style(
			'aa-customers-frontend',
			AA_CUSTOMERS_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			AA_CUSTOMERS_VERSION
		);

		wp_enqueue_script(
			'aa-customers-frontend',
			AA_CUSTOMERS_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			AA_CUSTOMERS_VERSION,
			true
		);

		wp_localize_script( 'aa-customers-frontend', 'aaCustomersFrontend', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'aa_customers_frontend' ),
		) );
	}

	/**
	 * Render member dashboard
	 *
	 * Shortcode: [aa_member_dashboard]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_member_dashboard( $atts ) {
		// Must be logged in.
		if ( ! is_user_logged_in() ) {
			return $this->render_login_required();
		}

		$user_id = get_current_user_id();

		// Get member record.
		$members_repo = new AA_Customers_Members_Repository();
		$member = $members_repo->get_by_wp_user_id( $user_id );

		if ( ! $member ) {
			return $this->render_not_a_member();
		}

		// Get membership info.
		$membership_service = new AA_Customers_Membership_Service();
		$membership_info = $membership_service->get_membership_info( $member->id );

		// Get purchases.
		$purchases_repo = new AA_Customers_Purchases_Repository();
		$purchases = $purchases_repo->get_by_member( $member->id );

		// Get registrations.
		$registrations_repo = new AA_Customers_Registrations_Repository();
		$registrations = $registrations_repo->get_by_member( $member->id );

		ob_start();
		?>
		<div class="aa-member-dashboard">
			<h2>Welcome, <?php echo esc_html( $member->full_name ); ?></h2>

			<div class="aa-dashboard-grid">
				<!-- Membership Card -->
				<div class="aa-dashboard-card">
					<h3>Your Membership</h3>
					<?php if ( $membership_info['is_active'] ) : ?>
						<div class="aa-membership-status status-active">
							<span class="status-badge">Active</span>
							<p><strong>Type:</strong> <?php echo esc_html( $membership_info['type_name'] ?: 'Member' ); ?></p>
							<p><strong>Expires:</strong> <?php echo esc_html( date( 'j F Y', strtotime( $membership_info['expiry_date'] ) ) ); ?></p>
							<?php if ( $membership_info['is_expiring'] ) : ?>
								<p class="aa-warning">Your membership expires soon. <a href="#renew">Renew now</a></p>
							<?php endif; ?>
						</div>
					<?php elseif ( $membership_info['status'] === 'expired' ) : ?>
						<div class="aa-membership-status status-expired">
							<span class="status-badge">Expired</span>
							<p>Your membership expired on <?php echo esc_html( date( 'j F Y', strtotime( $membership_info['expiry_date'] ) ) ); ?></p>
							<a href="<?php echo esc_url( home_url( '/join' ) ); ?>" class="aa-button">Renew Membership</a>
						</div>
					<?php else : ?>
						<div class="aa-membership-status status-none">
							<p>You don't have an active membership.</p>
							<a href="<?php echo esc_url( home_url( '/join' ) ); ?>" class="aa-button">Join Now</a>
						</div>
					<?php endif; ?>

					<?php if ( $member->stripe_customer_id && $membership_info['subscription_id'] ) : ?>
						<p class="aa-manage-billing">
							<a href="#" class="aa-billing-portal-link" data-member-id="<?php echo esc_attr( $member->id ); ?>">Manage Billing</a>
						</p>
					<?php endif; ?>
				</div>

				<!-- Profile Card -->
				<div class="aa-dashboard-card">
					<h3>Your Profile</h3>
					<p><strong>Email:</strong> <?php echo esc_html( $member->email ); ?></p>
					<p><strong>Phone:</strong> <?php echo esc_html( $member->phone ?: 'Not set' ); ?></p>
					<?php if ( $member->show_in_directory ) : ?>
						<p class="aa-directory-status"><span class="dashicons dashicons-yes"></span> Visible in directory</p>
					<?php endif; ?>
					<a href="#" class="aa-button aa-button-secondary aa-edit-profile-link">Edit Profile</a>
				</div>
			</div>

			<!-- Purchase History -->
			<?php if ( ! empty( $purchases ) ) : ?>
				<div class="aa-dashboard-section">
					<h3>Purchase History</h3>
					<table class="aa-table">
						<thead>
							<tr>
								<th>Date</th>
								<th>Product</th>
								<th>Amount</th>
								<th>Status</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$products_repo = new AA_Customers_Products_Repository();
							foreach ( array_slice( $purchases, 0, 10 ) as $purchase ) :
								$product = $products_repo->get_by_id( $purchase->product_id );
							?>
								<tr>
									<td><?php echo esc_html( date( 'j M Y', strtotime( $purchase->purchase_date ) ) ); ?></td>
									<td><?php echo esc_html( $product ? $product->name : 'Unknown' ); ?></td>
									<td>£<?php echo esc_html( number_format( $purchase->total_amount, 2 ) ); ?></td>
									<td><span class="aa-status-badge aa-status-<?php echo esc_attr( $purchase->payment_status ); ?>"><?php echo esc_html( ucfirst( $purchase->payment_status ) ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<!-- Event Registrations -->
			<?php if ( ! empty( $registrations ) ) : ?>
				<div class="aa-dashboard-section">
					<h3>Your Event Registrations</h3>
					<table class="aa-table">
						<thead>
							<tr>
								<th>Event</th>
								<th>Date</th>
								<th>Status</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$events_repo = new AA_Customers_Events_Repository();
							foreach ( $registrations as $reg ) :
								$event = $events_repo->get_with_product( $reg->event_id );
								if ( ! $event ) continue;
							?>
								<tr>
									<td><?php echo esc_html( $event->name ); ?></td>
									<td><?php echo esc_html( date( 'j M Y', strtotime( $event->event_date ) ) ); ?></td>
									<td><span class="aa-status-badge aa-status-<?php echo esc_attr( $reg->status ); ?>"><?php echo esc_html( ucfirst( $reg->status ) ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render practitioner directory
	 *
	 * Shortcode: [aa_practitioner_directory]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_practitioner_directory( $atts ) {
		$atts = shortcode_atts( array(
			'show_search' => 'yes',
		), $atts );

		$members_repo = new AA_Customers_Members_Repository();

		// Get filter values.
		$search   = isset( $_GET['aa_search'] ) ? sanitize_text_field( $_GET['aa_search'] ) : '';
		$location = isset( $_GET['aa_location'] ) ? sanitize_text_field( $_GET['aa_location'] ) : '';
		$specialty = isset( $_GET['aa_specialty'] ) ? sanitize_text_field( $_GET['aa_specialty'] ) : '';

		$practitioners = $members_repo->get_directory_members( array(
			'search'    => $search,
			'location'  => $location,
			'specialty' => $specialty,
		) );

		ob_start();
		?>
		<div class="aa-practitioner-directory">
			<?php if ( $atts['show_search'] === 'yes' ) : ?>
				<form class="aa-directory-search" method="get">
					<div class="aa-search-row">
						<input type="text" name="aa_search" placeholder="Search by name..." value="<?php echo esc_attr( $search ); ?>">
						<input type="text" name="aa_location" placeholder="Location..." value="<?php echo esc_attr( $location ); ?>">
						<input type="text" name="aa_specialty" placeholder="Specialty..." value="<?php echo esc_attr( $specialty ); ?>">
						<button type="submit" class="aa-button">Search</button>
						<?php if ( $search || $location || $specialty ) : ?>
							<a href="<?php echo esc_url( get_permalink() ); ?>" class="aa-button aa-button-secondary">Clear</a>
						<?php endif; ?>
					</div>
				</form>
			<?php endif; ?>

			<?php if ( empty( $practitioners ) ) : ?>
				<p class="aa-no-results">No practitioners found matching your criteria.</p>
			<?php else : ?>
				<div class="aa-directory-grid">
					<?php foreach ( $practitioners as $practitioner ) : ?>
						<div class="aa-practitioner-card">
							<?php if ( $practitioner->practitioner_photo_url ) : ?>
								<div class="aa-practitioner-photo">
									<img src="<?php echo esc_url( $practitioner->practitioner_photo_url ); ?>" alt="<?php echo esc_attr( $practitioner->full_name ); ?>">
								</div>
							<?php endif; ?>
							<div class="aa-practitioner-info">
								<h3><?php echo esc_html( $practitioner->full_name ); ?></h3>
								<?php if ( $practitioner->practitioner_location ) : ?>
									<p class="aa-location"><span class="dashicons dashicons-location"></span> <?php echo esc_html( $practitioner->practitioner_location ); ?></p>
								<?php endif; ?>
								<?php if ( $practitioner->practitioner_specialties ) : ?>
									<p class="aa-specialties">
										<?php
										$specs = array_map( 'trim', explode( ',', $practitioner->practitioner_specialties ) );
										foreach ( $specs as $spec ) {
											echo '<span class="aa-specialty-tag">' . esc_html( $spec ) . '</span>';
										}
										?>
									</p>
								<?php endif; ?>
								<?php if ( $practitioner->practitioner_bio ) : ?>
									<p class="aa-bio"><?php echo esc_html( wp_trim_words( $practitioner->practitioner_bio, 30 ) ); ?></p>
								<?php endif; ?>
								<?php if ( $practitioner->practitioner_website ) : ?>
									<a href="<?php echo esc_url( $practitioner->practitioner_website ); ?>" class="aa-website-link" target="_blank" rel="noopener">Visit Website</a>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="aa-directory-count"><?php echo count( $practitioners ); ?> practitioner<?php echo count( $practitioners ) !== 1 ? 's' : ''; ?> found</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render join/membership page
	 *
	 * Shortcode: [aa_join]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_join_page( $atts ) {
		$products_repo = new AA_Customers_Products_Repository();
		$memberships = $products_repo->get_active_memberships();

		// Check if user is already logged in and has membership.
		$current_member = null;
		$current_membership = null;
		if ( is_user_logged_in() ) {
			$members_repo = new AA_Customers_Members_Repository();
			$current_member = $members_repo->get_by_wp_user_id( get_current_user_id() );
			if ( $current_member ) {
				$membership_service = new AA_Customers_Membership_Service();
				$current_membership = $membership_service->get_membership_info( $current_member->id );
			}
		}

		ob_start();
		?>
		<div class="aa-join-page">
			<h2>Membership Options</h2>

			<?php if ( $current_membership && $current_membership['is_active'] ) : ?>
				<div class="aa-notice aa-notice-info">
					<p>You already have an active membership (<?php echo esc_html( $current_membership['type_name'] ); ?>) expiring on <?php echo esc_html( date( 'j F Y', strtotime( $current_membership['expiry_date'] ) ) ); ?>.</p>
				</div>
			<?php endif; ?>

			<?php if ( empty( $memberships ) ) : ?>
				<p>No membership options are currently available. Please check back later.</p>
			<?php else : ?>
				<div class="aa-membership-options">
					<?php foreach ( $memberships as $membership ) : ?>
						<div class="aa-membership-card">
							<h3><?php echo esc_html( $membership->name ); ?></h3>
							<div class="aa-price">
								<span class="aa-amount">£<?php echo esc_html( number_format( $membership->price, 2 ) ); ?></span>
								<?php if ( $membership->is_recurring ) : ?>
									<span class="aa-period">/ <?php echo esc_html( $membership->recurring_interval ); ?></span>
								<?php endif; ?>
							</div>
							<?php if ( $membership->description ) : ?>
								<div class="aa-description"><?php echo wp_kses_post( $membership->description ); ?></div>
							<?php endif; ?>
							<a href="#" class="aa-button aa-checkout-btn" data-product-id="<?php echo esc_attr( $membership->id ); ?>">
								<?php echo $current_membership && $current_membership['is_active'] ? 'Upgrade' : 'Join Now'; ?>
							</a>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! is_user_logged_in() ) : ?>
				<div class="aa-login-notice">
					<p>Already a member? <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Log in</a> to manage your membership.</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render events list
	 *
	 * Shortcode: [aa_events]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_events_list( $atts ) {
		$atts = shortcode_atts( array(
			'limit' => 10,
		), $atts );

		$events_repo = new AA_Customers_Events_Repository();
		$events = $events_repo->get_upcoming_with_products( intval( $atts['limit'] ) );

		// Check if user is a member for pricing.
		$is_member = false;
		if ( is_user_logged_in() ) {
			$members_repo = new AA_Customers_Members_Repository();
			$member = $members_repo->get_by_wp_user_id( get_current_user_id() );
			if ( $member ) {
				$membership_service = new AA_Customers_Membership_Service();
				$is_member = $membership_service->is_active( $member->id );
			}
		}

		ob_start();
		?>
		<div class="aa-events-list">
			<?php if ( empty( $events ) ) : ?>
				<p class="aa-no-events">No upcoming events at this time. Check back soon!</p>
			<?php else : ?>
				<div class="aa-events-grid">
					<?php foreach ( $events as $event ) : ?>
						<?php
						$reg_count = $events_repo->get_registration_count( $event->id );
						$has_capacity = empty( $event->capacity ) || $reg_count < $event->capacity;
						$is_open = $events_repo->is_registration_open( $event->id );
						$price = $is_member && $event->member_price !== null ? $event->member_price : $event->price;
						?>
						<div class="aa-event-card">
							<div class="aa-event-date">
								<span class="aa-day"><?php echo esc_html( date( 'j', strtotime( $event->event_date ) ) ); ?></span>
								<span class="aa-month"><?php echo esc_html( date( 'M', strtotime( $event->event_date ) ) ); ?></span>
								<span class="aa-year"><?php echo esc_html( date( 'Y', strtotime( $event->event_date ) ) ); ?></span>
							</div>
							<div class="aa-event-details">
								<h3><?php echo esc_html( $event->name ); ?></h3>
								<?php if ( $event->event_time ) : ?>
									<p class="aa-time"><span class="dashicons dashicons-clock"></span> <?php echo esc_html( date( 'g:ia', strtotime( $event->event_time ) ) ); ?></p>
								<?php endif; ?>
								<?php if ( $event->location ) : ?>
									<p class="aa-location"><span class="dashicons dashicons-location"></span> <?php echo esc_html( $event->location ); ?></p>
								<?php endif; ?>
								<?php if ( $event->description ) : ?>
									<p class="aa-description"><?php echo esc_html( wp_trim_words( $event->description, 20 ) ); ?></p>
								<?php endif; ?>
								<div class="aa-event-footer">
									<span class="aa-price">
										<?php if ( $price > 0 ) : ?>
											£<?php echo esc_html( number_format( $price, 2 ) ); ?>
											<?php if ( $is_member && $event->member_price !== null && $event->member_price < $event->price ) : ?>
												<span class="aa-member-discount">(Member price)</span>
											<?php endif; ?>
										<?php else : ?>
											Free
										<?php endif; ?>
									</span>
									<?php if ( $is_open && $has_capacity ) : ?>
										<a href="#" class="aa-button aa-checkout-btn" data-product-id="<?php echo esc_attr( $event->product_id ); ?>">Register</a>
									<?php elseif ( ! $has_capacity ) : ?>
										<span class="aa-sold-out">Sold Out</span>
									<?php else : ?>
										<span class="aa-closed">Registration Closed</span>
									<?php endif; ?>
								</div>
								<?php if ( $event->capacity ) : ?>
									<p class="aa-spots-left">
										<?php
										$spots_left = $event->capacity - $reg_count;
										if ( $spots_left > 0 && $spots_left <= 5 ) {
											echo '<span class="aa-low-spots">Only ' . $spots_left . ' spot' . ( $spots_left !== 1 ? 's' : '' ) . ' left!</span>';
										}
										?>
									</p>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render single event
	 *
	 * Shortcode: [aa_event id="123"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_single_event( $atts ) {
		$atts = shortcode_atts( array(
			'id' => 0,
		), $atts );

		if ( ! $atts['id'] ) {
			return '<p>Event ID not specified.</p>';
		}

		$events_repo = new AA_Customers_Events_Repository();
		$event = $events_repo->get_with_product( intval( $atts['id'] ) );

		if ( ! $event ) {
			return '<p>Event not found.</p>';
		}

		// Check if user is a member for pricing.
		$is_member = false;
		if ( is_user_logged_in() ) {
			$members_repo = new AA_Customers_Members_Repository();
			$member = $members_repo->get_by_wp_user_id( get_current_user_id() );
			if ( $member ) {
				$membership_service = new AA_Customers_Membership_Service();
				$is_member = $membership_service->is_active( $member->id );
			}
		}

		$reg_count = $events_repo->get_registration_count( $event->id );
		$has_capacity = empty( $event->capacity ) || $reg_count < $event->capacity;
		$is_open = $events_repo->is_registration_open( $event->id );
		$price = $is_member && $event->member_price !== null ? $event->member_price : $event->price;

		ob_start();
		?>
		<div class="aa-single-event">
			<h2><?php echo esc_html( $event->name ); ?></h2>

			<div class="aa-event-meta">
				<p><strong>Date:</strong> <?php echo esc_html( date( 'l j F Y', strtotime( $event->event_date ) ) ); ?></p>
				<?php if ( $event->event_time ) : ?>
					<p><strong>Time:</strong> <?php echo esc_html( date( 'g:ia', strtotime( $event->event_time ) ) ); ?>
						<?php if ( $event->end_time ) : ?>
							- <?php echo esc_html( date( 'g:ia', strtotime( $event->end_time ) ) ); ?>
						<?php endif; ?>
					</p>
				<?php endif; ?>
				<?php if ( $event->location ) : ?>
					<p><strong>Location:</strong> <?php echo esc_html( $event->location ); ?></p>
				<?php endif; ?>
				<?php if ( $event->location_details ) : ?>
					<div class="aa-location-details"><?php echo wp_kses_post( $event->location_details ); ?></div>
				<?php endif; ?>
			</div>

			<?php if ( $event->description ) : ?>
				<div class="aa-event-description">
					<?php echo wp_kses_post( $event->description ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $event->additional_info ) : ?>
				<div class="aa-additional-info">
					<h4>Additional Information</h4>
					<?php echo wp_kses_post( $event->additional_info ); ?>
				</div>
			<?php endif; ?>

			<div class="aa-event-registration">
				<div class="aa-price-box">
					<span class="aa-label">Price:</span>
					<span class="aa-price">£<?php echo esc_html( number_format( $price, 2 ) ); ?></span>
					<?php if ( $is_member && $event->member_price !== null && $event->member_price < $event->price ) : ?>
						<span class="aa-member-discount">(Member price - you save £<?php echo esc_html( number_format( $event->price - $event->member_price, 2 ) ); ?>)</span>
					<?php elseif ( ! $is_member && $event->member_price !== null ) : ?>
						<span class="aa-non-member-note">Members pay only £<?php echo esc_html( number_format( $event->member_price, 2 ) ); ?></span>
					<?php endif; ?>
				</div>

				<?php if ( $is_open && $has_capacity ) : ?>
					<a href="#" class="aa-button aa-button-large aa-checkout-btn" data-product-id="<?php echo esc_attr( $event->product_id ); ?>">Register Now</a>
				<?php elseif ( ! $has_capacity ) : ?>
					<p class="aa-sold-out-notice">This event is sold out.</p>
				<?php else : ?>
					<p class="aa-closed-notice">Registration for this event has closed.</p>
				<?php endif; ?>

				<?php if ( $event->capacity ) : ?>
					<p class="aa-capacity"><?php echo esc_html( $reg_count ); ?> / <?php echo esc_html( $event->capacity ); ?> registered</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render checkout success page
	 *
	 * Shortcode: [aa_checkout_success]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_checkout_success( $atts ) {
		$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( $_GET['session_id'] ) : '';

		ob_start();
		?>
		<div class="aa-checkout-success">
			<div class="aa-success-icon">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<h2>Thank You!</h2>
			<p>Your payment was successful and your order has been confirmed.</p>
			<p>You should receive a confirmation email shortly.</p>
			<div class="aa-success-actions">
				<a href="<?php echo esc_url( home_url( '/member-dashboard' ) ); ?>" class="aa-button">Go to Dashboard</a>
				<a href="<?php echo esc_url( home_url() ); ?>" class="aa-button aa-button-secondary">Return to Home</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render login required message
	 *
	 * @return string HTML output.
	 */
	private function render_login_required() {
		ob_start();
		?>
		<div class="aa-login-required">
			<h2>Login Required</h2>
			<p>You need to be logged in to access this page.</p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="aa-button">Log In</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render not a member message
	 *
	 * @return string HTML output.
	 */
	private function render_not_a_member() {
		ob_start();
		?>
		<div class="aa-not-member">
			<h2>Become a Member</h2>
			<p>You're logged in but don't have a member profile yet.</p>
			<a href="<?php echo esc_url( home_url( '/join' ) ); ?>" class="aa-button">Join Now</a>
		</div>
		<?php
		return ob_get_clean();
	}
}
