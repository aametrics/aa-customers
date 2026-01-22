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
}
