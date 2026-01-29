<?php
/**
 * AJAX Handler
 *
 * Handles all frontend AJAX requests.
 *
 * @package AA_Customers
 * @subpackage Frontend
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Ajax_Handler
 */
class AA_Customers_Ajax_Handler {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Checkout.
		add_action( 'wp_ajax_aa_customers_create_checkout', array( $this, 'create_checkout_session' ) );
		add_action( 'wp_ajax_nopriv_aa_customers_create_checkout', array( $this, 'create_checkout_session' ) );

		// Billing portal.
		add_action( 'wp_ajax_aa_customers_billing_portal', array( $this, 'create_billing_portal' ) );

		// Profile update.
		add_action( 'wp_ajax_aa_customers_update_profile', array( $this, 'update_profile' ) );

		// Checkout flow: email check.
		add_action( 'wp_ajax_aa_customers_check_email', array( $this, 'check_email' ) );
		add_action( 'wp_ajax_nopriv_aa_customers_check_email', array( $this, 'check_email' ) );

		// Checkout flow: submit form and go to Stripe.
		add_action( 'wp_ajax_aa_customers_checkout_submit', array( $this, 'checkout_submit' ) );
		add_action( 'wp_ajax_nopriv_aa_customers_checkout_submit', array( $this, 'checkout_submit' ) );

		// Checkout flow: login and continue.
		add_action( 'wp_ajax_nopriv_aa_customers_login_checkout', array( $this, 'login_checkout' ) );
	}

	/**
	 * Create Stripe checkout session
	 *
	 * @return void
	 */
	public function create_checkout_session() {
		check_ajax_referer( 'aa_customers_frontend', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$donation   = isset( $_POST['donation'] ) ? floatval( $_POST['donation'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => 'Invalid product.' ) );
		}

		$products_repo = new AA_Customers_Products_Repository();
		$product = $products_repo->get_by_id( $product_id );

		if ( ! $product || $product->status !== 'active' ) {
			wp_send_json_error( array( 'message' => 'Product not available.' ) );
		}

		// Check if Stripe price is configured.
		if ( empty( $product->stripe_price_id ) ) {
			wp_send_json_error( array( 'message' => 'Stripe not configured for this product.' ) );
		}

		$stripe_service = new AA_Customers_Stripe_Service();

		if ( ! $stripe_service->is_configured() ) {
			wp_send_json_error( array( 'message' => 'Payment system not configured.' ) );
		}

		// Get or create member.
		$member_id = 0;
		$customer_id = null;
		$customer_email = null;

		if ( is_user_logged_in() ) {
			$members_repo = new AA_Customers_Members_Repository();
			$member = $members_repo->get_by_wp_user_id( get_current_user_id() );

			if ( $member ) {
				$member_id = $member->id;
				$customer_id = $stripe_service->get_or_create_customer( $member );

				if ( is_wp_error( $customer_id ) ) {
					$customer_id = null;
					$customer_email = $member->email;
				}
			} else {
				// Create member record for logged-in user.
				$member_id = $members_repo->create_from_wp_user( get_current_user_id() );
				if ( $member_id ) {
					$member = $members_repo->get_by_id( $member_id );
					$customer_email = $member->email;
				}
			}
		}

		// Build success/cancel URLs.
		$success_path = AA_Customers_Zap_Storage::get( 'checkout_success_url', '/checkout-success' );
		$success_url  = add_query_arg( 'session_id', '{CHECKOUT_SESSION_ID}', home_url( $success_path ) );
		$cancel_url   = wp_get_referer() ?: home_url();

		// Create checkout session.
		$session = $stripe_service->create_checkout_session( array(
			'customer_id'     => $customer_id,
			'customer_email'  => $customer_email,
			'price_id'        => $product->stripe_price_id,
			'product_id'      => $product_id,
			'member_id'       => $member_id,
			'is_subscription' => $product->type === 'membership' && $product->is_recurring,
			'donation'        => $donation,
			'success_url'     => $success_url,
			'cancel_url'      => $cancel_url,
		) );

		if ( is_wp_error( $session ) ) {
			wp_send_json_error( array( 'message' => $session->get_error_message() ) );
		}

		wp_send_json_success( array(
			'checkout_url' => $session->url,
		) );
	}

	/**
	 * Create Stripe billing portal session
	 *
	 * @return void
	 */
	public function create_billing_portal() {
		check_ajax_referer( 'aa_customers_frontend', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
		}

		$members_repo = new AA_Customers_Members_Repository();
		$member = $members_repo->get_by_wp_user_id( get_current_user_id() );

		if ( ! $member || empty( $member->stripe_customer_id ) ) {
			wp_send_json_error( array( 'message' => 'No billing information found.' ) );
		}

		$stripe_service = new AA_Customers_Stripe_Service();

		if ( ! $stripe_service->is_configured() ) {
			wp_send_json_error( array( 'message' => 'Payment system not configured.' ) );
		}

		$return_url = home_url( '/member-dashboard' );
		$session = $stripe_service->create_portal_session( $member->stripe_customer_id, $return_url );

		if ( is_wp_error( $session ) ) {
			wp_send_json_error( array( 'message' => $session->get_error_message() ) );
		}

		wp_send_json_success( array(
			'portal_url' => $session->url,
		) );
	}

	/**
	 * Update member profile
	 *
	 * @return void
	 */
	public function update_profile() {
		check_ajax_referer( 'aa_customers_frontend', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
		}

		$members_repo = new AA_Customers_Members_Repository();
		$member = $members_repo->get_by_wp_user_id( get_current_user_id() );

		if ( ! $member ) {
			wp_send_json_error( array( 'message' => 'Member not found.' ) );
		}

		// Allowed fields for self-update.
		$data = array();

		if ( isset( $_POST['phone'] ) ) {
			$data['phone'] = sanitize_text_field( $_POST['phone'] );
		}
		if ( isset( $_POST['address_line_1'] ) ) {
			$data['address_line_1'] = sanitize_text_field( $_POST['address_line_1'] );
		}
		if ( isset( $_POST['address_line_2'] ) ) {
			$data['address_line_2'] = sanitize_text_field( $_POST['address_line_2'] );
		}
		if ( isset( $_POST['city'] ) ) {
			$data['city'] = sanitize_text_field( $_POST['city'] );
		}
		if ( isset( $_POST['postcode'] ) ) {
			$data['postcode'] = sanitize_text_field( $_POST['postcode'] );
		}
		if ( isset( $_POST['country'] ) ) {
			$data['country'] = sanitize_text_field( $_POST['country'] );
		}

		// Directory fields.
		if ( isset( $_POST['show_in_directory'] ) ) {
			$data['show_in_directory'] = $_POST['show_in_directory'] === 'true' ? 1 : 0;
		}
		if ( isset( $_POST['practitioner_bio'] ) ) {
			$data['practitioner_bio'] = wp_kses_post( $_POST['practitioner_bio'] );
		}
		if ( isset( $_POST['practitioner_specialties'] ) ) {
			$data['practitioner_specialties'] = sanitize_text_field( $_POST['practitioner_specialties'] );
		}
		if ( isset( $_POST['practitioner_location'] ) ) {
			$data['practitioner_location'] = sanitize_text_field( $_POST['practitioner_location'] );
		}
		if ( isset( $_POST['practitioner_website'] ) ) {
			$data['practitioner_website'] = esc_url_raw( $_POST['practitioner_website'] );
		}

		if ( empty( $data ) ) {
			wp_send_json_error( array( 'message' => 'No data to update.' ) );
		}

		$result = $members_repo->update( $member->id, $data );

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Profile updated successfully.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to update profile.' ) );
		}
	}

	/**
	 * Check if email exists in the system
	 *
	 * Returns login form if exists, registration form if not.
	 *
	 * @return void
	 */
	public function check_email() {
		check_ajax_referer( 'aa_checkout_nonce', '_wpnonce' );

		$email      = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
		}

		// Check if user exists.
		$user = get_user_by( 'email', $email );

		if ( $user ) {
			// User exists - show login form.
			ob_start();
			?>
			<div class="aa-existing-account">
				<h3>Welcome back!</h3>
				<p>We found an account with this email. Please log in to continue.</p>

				<form id="aa-login-form" class="aa-form">
					<?php wp_nonce_field( 'aa_login_nonce', '_wpnonce' ); ?>
					<input type="hidden" name="email" value="<?php echo esc_attr( $email ); ?>">
					<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">

					<div class="aa-form-row">
						<label for="login_password">Password</label>
						<input type="password" id="login_password" name="password" required>
					</div>

					<div class="aa-form-actions">
						<button type="submit" class="aa-button">Log In & Continue</button>
					</div>

					<p class="aa-form-link">
						<a href="<?php echo esc_url( wp_lostpassword_url( get_permalink() ) ); ?>">Forgot password?</a>
					</p>
				</form>
			</div>

			<script>
			jQuery(function($) {
				$('#aa-login-form').on('submit', function(e) {
					e.preventDefault();
					var form = $(this);
					var btn = form.find('button[type="submit"]');

					btn.prop('disabled', true).text('Logging in...');

					$.post(aa_customers.ajax_url, {
						action: 'aa_customers_login_checkout',
						email: form.find('input[name="email"]').val(),
						password: form.find('input[name="password"]').val(),
						product_id: form.find('input[name="product_id"]').val(),
						_wpnonce: form.find('input[name="_wpnonce"]').val()
					}, function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || 'Login failed.');
							btn.prop('disabled', false).text('Log In & Continue');
						}
					});
				});
			});
			</script>
			<?php
			$html = ob_get_clean();

			wp_send_json_success( array(
				'exists' => true,
				'html'   => $html,
			) );
		} else {
			// New user - show registration form.
			$forms_repo = new AA_Customers_Forms_Repository();
			$form       = $forms_repo->get_for_product( $product_id );
			$fields     = $form ? $forms_repo->get_fields( $form->id ) : array();

			ob_start();
			?>
			<div class="aa-new-account">
				<h3>Create your account</h3>
				<p>Complete the form below to continue with your purchase.</p>

				<form id="aa-register-checkout-form" class="aa-form">
					<?php wp_nonce_field( 'aa_checkout_submit', '_wpnonce' ); ?>
					<input type="hidden" name="email" value="<?php echo esc_attr( $email ); ?>">
					<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
					<input type="hidden" name="form_id" value="<?php echo esc_attr( $form ? $form->id : 0 ); ?>">
					<input type="hidden" name="is_new_user" value="1">

					<div class="aa-form-row">
						<label for="reg_full_name">Full Name <span class="required">*</span></label>
						<input type="text" id="reg_full_name" name="full_name" required>
					</div>

					<div class="aa-form-row">
						<label for="reg_email">Email</label>
						<input type="email" id="reg_email" value="<?php echo esc_attr( $email ); ?>" disabled>
					</div>

					<div class="aa-form-row">
						<label for="reg_password">Create Password <span class="required">*</span></label>
						<input type="password" id="reg_password" name="password" required minlength="8">
						<p class="aa-field-help">Minimum 8 characters.</p>
					</div>

					<?php
					// Render additional form fields.
					foreach ( $fields as $field ) {
						// Skip email and full_name as they're already shown.
						if ( in_array( $field->target_column, array( 'email', 'full_name' ), true ) ) {
							continue;
						}
						$this->render_checkout_field( $field );
					}
					?>

					<div class="aa-form-actions">
						<button type="submit" class="aa-button">Continue to Payment</button>
					</div>
				</form>
			</div>

			<script>
			jQuery(function($) {
				$('#aa-register-checkout-form').on('submit', function(e) {
					e.preventDefault();
					var form = $(this);
					var btn = form.find('button[type="submit"]');

					btn.prop('disabled', true).text('Processing...');

					$.post(aa_customers.ajax_url, form.serialize() + '&action=aa_customers_checkout_submit', function(response) {
						if (response.success && response.data.checkout_url) {
							window.location.href = response.data.checkout_url;
						} else {
							alert(response.data.message || 'An error occurred.');
							btn.prop('disabled', false).text('Continue to Payment');
						}
					}).fail(function() {
						alert('Connection error. Please try again.');
						btn.prop('disabled', false).text('Continue to Payment');
					});
				});
			});
			</script>
			<?php
			$html = ob_get_clean();

			wp_send_json_success( array(
				'exists' => false,
				'html'   => $html,
			) );
		}
	}

	/**
	 * Handle checkout form submission
	 *
	 * Creates member (pending status) and redirects to Stripe.
	 *
	 * @return void
	 */
	public function checkout_submit() {
		check_ajax_referer( 'aa_checkout_submit', '_wpnonce' );

		$product_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$is_new_user = isset( $_POST['is_new_user'] ) && $_POST['is_new_user'] === '1';
		$donation    = isset( $_POST['donation'] ) ? floatval( $_POST['donation'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => 'Invalid product.' ) );
		}

		$products_repo = new AA_Customers_Products_Repository();
		$product       = $products_repo->get_by_id( $product_id );

		if ( ! $product || $product->status !== 'active' ) {
			wp_send_json_error( array( 'message' => 'Product not available.' ) );
		}

		if ( empty( $product->stripe_price_id ) ) {
			wp_send_json_error( array( 'message' => 'Payment not configured for this product.' ) );
		}

		$members_repo = new AA_Customers_Members_Repository();
		$member       = null;
		$user_id      = 0;

		// Handle new user registration.
		if ( $is_new_user ) {
			$email     = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
			$full_name = isset( $_POST['full_name'] ) ? sanitize_text_field( $_POST['full_name'] ) : '';
			$password  = isset( $_POST['password'] ) ? $_POST['password'] : '';

			if ( ! is_email( $email ) ) {
				wp_send_json_error( array( 'message' => 'Invalid email address.' ) );
			}

			if ( empty( $full_name ) ) {
				wp_send_json_error( array( 'message' => 'Full name is required.' ) );
			}

			if ( strlen( $password ) < 8 ) {
				wp_send_json_error( array( 'message' => 'Password must be at least 8 characters.' ) );
			}

			// Check if email already exists.
			if ( email_exists( $email ) ) {
				wp_send_json_error( array( 'message' => 'An account with this email already exists.' ) );
			}

			// Create WordPress user.
			$username = sanitize_user( explode( '@', $email )[0], true );
			$username = $this->get_unique_username( $username );

			$user_id = wp_create_user( $username, $password, $email );

			if ( is_wp_error( $user_id ) ) {
				wp_send_json_error( array( 'message' => 'Failed to create account: ' . $user_id->get_error_message() ) );
			}

			// Set user display name.
			wp_update_user( array(
				'ID'           => $user_id,
				'display_name' => $full_name,
			) );

			// Add member role.
			$user = new WP_User( $user_id );
			$user->add_role( 'member' );

			// Log in the user.
			wp_set_auth_cookie( $user_id );
			wp_set_current_user( $user_id );

		} else {
			// Existing user.
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( array( 'message' => 'Please log in to continue.' ) );
			}

			$user_id = get_current_user_id();
		}

		// Get or create member.
		$member = $members_repo->get_by_wp_user_id( $user_id );

		$member_data = array(
			'full_name'         => isset( $_POST['full_name'] ) ? sanitize_text_field( $_POST['full_name'] ) : '',
			'email'             => isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : wp_get_current_user()->user_email,
			'membership_status' => 'pending',
		);

		// Collect additional fields.
		if ( isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ) {
			foreach ( $_POST['fields'] as $column => $value ) {
				$column = sanitize_key( $column );
				// Only allow known columns.
				$allowed = array(
					'phone', 'address_line_1', 'address_line_2', 'city', 'postcode', 'country',
					'practitioner_bio', 'practitioner_specialties', 'practitioner_location', 'practitioner_website',
				);
				if ( in_array( $column, $allowed, true ) ) {
					$member_data[ $column ] = sanitize_text_field( $value );
				}
			}
		}

		if ( $member ) {
			// Update existing member.
			$members_repo->update( $member->id, $member_data );
			$member_id = $member->id;
		} else {
			// Create new member.
			$member_data['wp_user_id'] = $user_id;
			$member_id = $members_repo->create( $member_data );
		}

		if ( ! $member_id ) {
			wp_send_json_error( array( 'message' => 'Failed to create member profile.' ) );
		}

		// Create Stripe checkout session.
		$stripe_service = new AA_Customers_Stripe_Service();

		if ( ! $stripe_service->is_configured() ) {
			wp_send_json_error( array( 'message' => 'Payment system not configured.' ) );
		}

		// Get updated member.
		$member = $members_repo->get_by_id( $member_id );

		$customer_id = $stripe_service->get_or_create_customer( $member );

		// Get success URL from settings.
		$success_url = AA_Customers_Zap_Storage::get( 'checkout_success_url', '/checkout-success' );

		// Ensure it's a full URL.
		if ( strpos( $success_url, 'http' ) !== 0 ) {
			$success_url = home_url( $success_url );
		}

		$cancel_url = get_permalink();

		$checkout_url = $stripe_service->create_checkout_session( array(
			'customer_id'     => $customer_id,
			'price_id'        => $product->stripe_price_id,
			'product_name'    => $product->name,
			'product_id'      => $product->id,
			'member_id'       => $member_id,
			'is_subscription' => $product->is_recurring,
			'success_url'     => $success_url . '?session_id={CHECKOUT_SESSION_ID}',
			'cancel_url'      => $cancel_url,
			'donation'        => $donation,
		) );

		if ( ! $checkout_url ) {
			wp_send_json_error( array( 'message' => 'Failed to create checkout session.' ) );
		}

		wp_send_json_success( array( 'checkout_url' => $checkout_url ) );
	}

	/**
	 * Get unique username
	 *
	 * @param string $base Base username.
	 * @return string Unique username.
	 */
	private function get_unique_username( $base ) {
		$username = $base;
		$counter  = 1;

		while ( username_exists( $username ) ) {
			$username = $base . $counter;
			$counter++;
		}

		return $username;
	}

	/**
	 * Login during checkout and continue
	 *
	 * @return void
	 */
	public function login_checkout() {
		check_ajax_referer( 'aa_login_nonce', '_wpnonce' );

		$email    = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$password = isset( $_POST['password'] ) ? $_POST['password'] : '';

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Invalid email address.' ) );
		}

		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			wp_send_json_error( array( 'message' => 'No account found with that email.' ) );
		}

		// Authenticate.
		$auth = wp_authenticate( $user->user_login, $password );

		if ( is_wp_error( $auth ) ) {
			wp_send_json_error( array( 'message' => 'Incorrect password. Please try again.' ) );
		}

		// Log in.
		wp_set_auth_cookie( $user->ID );
		wp_set_current_user( $user->ID );

		wp_send_json_success( array( 'message' => 'Logged in successfully.' ) );
	}

	/**
	 * Render a checkout field
	 *
	 * @param object $field Field object.
	 * @return void
	 */
	private function render_checkout_field( $field ) {
		$column        = $field->target_column;
		$required      = $field->required ? 'required' : '';
		$required_mark = $field->required ? '<span class="required">*</span>' : '';

		$options = array();
		if ( ! empty( $field->options_json ) ) {
			$lines = explode( "\n", $field->options_json );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( ! empty( $line ) ) {
					$options[] = $line;
				}
			}
		}
		?>
		<div class="aa-form-row">
			<label for="field_<?php echo esc_attr( $field->id ); ?>">
				<?php echo esc_html( $field->label ); ?> <?php echo $required_mark; ?>
			</label>

			<?php
			switch ( $field->display_type ) {
				case 'textarea':
					echo '<textarea id="field_' . esc_attr( $field->id ) . '" name="fields[' . esc_attr( $column ) . ']" placeholder="' . esc_attr( $field->placeholder ) . '" ' . $required . '></textarea>';
					break;

				case 'dropdown':
					echo '<select id="field_' . esc_attr( $field->id ) . '" name="fields[' . esc_attr( $column ) . ']" ' . $required . '>';
					echo '<option value="">— Select —</option>';
					foreach ( $options as $opt ) {
						echo '<option value="' . esc_attr( $opt ) . '">' . esc_html( $opt ) . '</option>';
					}
					echo '</select>';
					break;

				case 'checkbox':
					echo '<label class="aa-checkbox-label"><input type="checkbox" id="field_' . esc_attr( $field->id ) . '" name="fields[' . esc_attr( $column ) . ']" value="1"> ' . esc_html( $field->placeholder ?: 'Yes' ) . '</label>';
					break;

				case 'date':
					echo '<input type="date" id="field_' . esc_attr( $field->id ) . '" name="fields[' . esc_attr( $column ) . ']" ' . $required . '>';
					break;

				case 'email':
					echo '<input type="email" id="field_' . esc_attr( $field->id ) . '" name="fields[' . esc_attr( $column ) . ']" placeholder="' . esc_attr( $field->placeholder ) . '" ' . $required . '>';
					break;

				case 'tel':
					echo '<input type="tel" id="field_' . esc_attr( $field->id ) . '" name="fields[' . esc_attr( $column ) . ']" placeholder="' . esc_attr( $field->placeholder ) . '" ' . $required . '>';
					break;

				default:
					echo '<input type="text" id="field_' . esc_attr( $field->id ) . '" name="fields[' . esc_attr( $column ) . ']" placeholder="' . esc_attr( $field->placeholder ) . '" ' . $required . '>';
					break;
			}
			?>

			<?php if ( $field->help_text ) : ?>
				<p class="aa-field-help"><?php echo esc_html( $field->help_text ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
