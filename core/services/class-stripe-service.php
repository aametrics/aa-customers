<?php
/**
 * Stripe Service
 *
 * Handles Stripe integration:
 * - Customer creation
 * - Checkout sessions
 * - Subscription management
 * - Webhook handling
 *
 * @package AA_Customers
 * @subpackage Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Stripe_Service
 */
class AA_Customers_Stripe_Service {

	/**
	 * Stripe API key
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * Stripe publishable key
	 *
	 * @var string
	 */
	private $publishable_key;

	/**
	 * Webhook secret
	 *
	 * @var string
	 */
	private $webhook_secret;

	/**
	 * Test mode
	 *
	 * @var bool
	 */
	private $is_test_mode;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->is_test_mode = AA_Customers_Zap_Storage::get( 'stripe_mode', 'test' ) === 'test';

		if ( $this->is_test_mode ) {
			$this->secret_key      = AA_Customers_Zap_Storage::get( 'stripe_test_secret', '' );
			$this->publishable_key = AA_Customers_Zap_Storage::get( 'stripe_test_publishable', '' );
		} else {
			$this->secret_key      = AA_Customers_Zap_Storage::get( 'stripe_live_secret', '' );
			$this->publishable_key = AA_Customers_Zap_Storage::get( 'stripe_live_publishable', '' );
		}

		$this->webhook_secret = AA_Customers_Zap_Storage::get( 'stripe_webhook_secret', '' );

		// Initialize Stripe SDK if available.
		if ( $this->is_configured() ) {
			$this->init_stripe();
		}
	}

	/**
	 * Initialize Stripe SDK
	 *
	 * @return void
	 */
	private function init_stripe() {
		$autoload = AA_CUSTOMERS_PLUGIN_DIR . 'vendor/autoload.php';

		if ( file_exists( $autoload ) ) {
			require_once $autoload;
			\Stripe\Stripe::setApiKey( $this->secret_key );
		}
	}

	/**
	 * Check if Stripe is configured
	 *
	 * @return bool True if configured.
	 */
	public function is_configured() {
		return ! empty( $this->secret_key ) && ! empty( $this->publishable_key );
	}

	/**
	 * Get publishable key
	 *
	 * @return string Publishable key.
	 */
	public function get_publishable_key() {
		return $this->publishable_key;
	}

	/**
	 * Check if in test mode
	 *
	 * @return bool True if test mode.
	 */
	public function is_test_mode() {
		return $this->is_test_mode;
	}

	/**
	 * Create or get Stripe customer
	 *
	 * @param object $member Member object.
	 * @return string|WP_Error Stripe customer ID or error.
	 */
	public function get_or_create_customer( $member ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'stripe_not_configured', 'Stripe is not configured.' );
		}

		// Return existing customer ID if available.
		if ( ! empty( $member->stripe_customer_id ) ) {
			return $member->stripe_customer_id;
		}

		try {
			$customer = \Stripe\Customer::create( array(
				'email'    => $member->email,
				'name'     => $member->full_name,
				'metadata' => array(
					'member_id'    => $member->id,
					'wp_user_id'   => $member->wp_user_id,
				),
			) );

			// Save customer ID to member.
			$members_repo = new AA_Customers_Members_Repository();
			$members_repo->update( $member->id, array(
				'stripe_customer_id' => $customer->id,
			) );

			return $customer->id;

		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			error_log( 'AA Customers Stripe: Failed to create customer - ' . $e->getMessage() );
			return new WP_Error( 'stripe_error', $e->getMessage() );
		}
	}

	/**
	 * Create a checkout session
	 *
	 * @param array $args Checkout arguments.
	 * @return object|WP_Error Checkout session or error.
	 */
	public function create_checkout_session( $args ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'stripe_not_configured', 'Stripe is not configured.' );
		}

		$defaults = array(
			'customer_id'     => null,
			'customer_email'  => null,
			'price_id'        => '',
			'product_id'      => 0,
			'member_id'       => 0,
			'is_subscription' => false,
			'donation'        => 0,
			'success_url'     => home_url( '/checkout/success?session_id={CHECKOUT_SESSION_ID}' ),
			'cancel_url'      => home_url( '/checkout/cancel' ),
		);

		$args = wp_parse_args( $args, $defaults );

		try {
			$session_params = array(
				'payment_method_types' => array( 'card' ),
				'line_items'           => array(
					array(
						'price'    => $args['price_id'],
						'quantity' => 1,
					),
				),
				'mode'        => $args['is_subscription'] ? 'subscription' : 'payment',
				'success_url' => $args['success_url'],
				'cancel_url'  => $args['cancel_url'],
				'metadata'    => array(
					'product_id' => $args['product_id'],
					'member_id'  => $args['member_id'],
					'donation'   => $args['donation'],
				),
			);

			// Add customer if available.
			if ( ! empty( $args['customer_id'] ) ) {
				$session_params['customer'] = $args['customer_id'];
			} elseif ( ! empty( $args['customer_email'] ) ) {
				$session_params['customer_email'] = $args['customer_email'];
			}

			// Add donation as separate line item if specified.
			if ( $args['donation'] > 0 ) {
				$session_params['line_items'][] = array(
					'price_data' => array(
						'currency'     => 'gbp',
						'product_data' => array(
							'name' => 'Donation',
						),
						'unit_amount'  => intval( $args['donation'] * 100 ),
					),
					'quantity' => 1,
				);
			}

			$session = \Stripe\Checkout\Session::create( $session_params );

			return $session;

		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			error_log( 'AA Customers Stripe: Failed to create checkout session - ' . $e->getMessage() );
			return new WP_Error( 'stripe_error', $e->getMessage() );
		}
	}

	/**
	 * Create a billing portal session
	 *
	 * @param string $customer_id Stripe customer ID.
	 * @param string $return_url URL to return to after portal.
	 * @return object|WP_Error Portal session or error.
	 */
	public function create_portal_session( $customer_id, $return_url = '' ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'stripe_not_configured', 'Stripe is not configured.' );
		}

		if ( empty( $return_url ) ) {
			$return_url = home_url( '/member-dashboard' );
		}

		try {
			$session = \Stripe\BillingPortal\Session::create( array(
				'customer'   => $customer_id,
				'return_url' => $return_url,
			) );

			return $session;

		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			error_log( 'AA Customers Stripe: Failed to create portal session - ' . $e->getMessage() );
			return new WP_Error( 'stripe_error', $e->getMessage() );
		}
	}

	/**
	 * Cancel a subscription
	 *
	 * @param string $subscription_id Stripe subscription ID.
	 * @param bool   $immediately Cancel immediately or at period end.
	 * @return object|WP_Error Subscription or error.
	 */
	public function cancel_subscription( $subscription_id, $immediately = false ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'stripe_not_configured', 'Stripe is not configured.' );
		}

		try {
			if ( $immediately ) {
				$subscription = \Stripe\Subscription::cancel( $subscription_id );
			} else {
				$subscription = \Stripe\Subscription::update( $subscription_id, array(
					'cancel_at_period_end' => true,
				) );
			}

			return $subscription;

		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			error_log( 'AA Customers Stripe: Failed to cancel subscription - ' . $e->getMessage() );
			return new WP_Error( 'stripe_error', $e->getMessage() );
		}
	}

	/**
	 * Get subscription
	 *
	 * @param string $subscription_id Stripe subscription ID.
	 * @return object|WP_Error Subscription or error.
	 */
	public function get_subscription( $subscription_id ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'stripe_not_configured', 'Stripe is not configured.' );
		}

		try {
			return \Stripe\Subscription::retrieve( $subscription_id );

		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			error_log( 'AA Customers Stripe: Failed to get subscription - ' . $e->getMessage() );
			return new WP_Error( 'stripe_error', $e->getMessage() );
		}
	}

	/**
	 * Verify webhook signature
	 *
	 * @param string $payload Request body.
	 * @param string $sig_header Stripe signature header.
	 * @return object|WP_Error Event object or error.
	 */
	public function verify_webhook( $payload, $sig_header ) {
		if ( empty( $this->webhook_secret ) ) {
			return new WP_Error( 'webhook_not_configured', 'Webhook secret not configured.' );
		}

		try {
			$event = \Stripe\Webhook::constructEvent(
				$payload,
				$sig_header,
				$this->webhook_secret
			);

			return $event;

		} catch ( \UnexpectedValueException $e ) {
			return new WP_Error( 'invalid_payload', 'Invalid payload.' );
		} catch ( \Stripe\Exception\SignatureVerificationException $e ) {
			return new WP_Error( 'invalid_signature', 'Invalid signature.' );
		}
	}

	/**
	 * Handle webhook event
	 *
	 * @param object $event Stripe event object.
	 * @return bool True on success.
	 */
	public function handle_webhook_event( $event ) {
		error_log( 'AA Customers Stripe: Handling webhook event - ' . $event->type );

		switch ( $event->type ) {
			case 'checkout.session.completed':
				return $this->handle_checkout_completed( $event->data->object );

			case 'invoice.paid':
				return $this->handle_invoice_paid( $event->data->object );

			case 'invoice.payment_failed':
				return $this->handle_invoice_failed( $event->data->object );

			case 'customer.subscription.deleted':
				return $this->handle_subscription_deleted( $event->data->object );

			case 'customer.subscription.updated':
				return $this->handle_subscription_updated( $event->data->object );

			default:
				error_log( 'AA Customers Stripe: Unhandled event type - ' . $event->type );
				return true;
		}
	}

	/**
	 * Handle checkout.session.completed
	 *
	 * @param object $session Session object.
	 * @return bool True on success.
	 */
	private function handle_checkout_completed( $session ) {
		$metadata   = $session->metadata;
		$product_id = isset( $metadata->product_id ) ? (int) $metadata->product_id : 0;
		$member_id  = isset( $metadata->member_id ) ? (int) $metadata->member_id : 0;
		$donation   = isset( $metadata->donation ) ? (float) $metadata->donation : 0;

		if ( ! $product_id || ! $member_id ) {
			error_log( 'AA Customers Stripe: Missing metadata in checkout session.' );
			return false;
		}

		// Create purchase record.
		$purchases_repo = new AA_Customers_Purchases_Repository();
		$products_repo  = new AA_Customers_Products_Repository();
		$product        = $products_repo->get_by_id( $product_id );

		if ( ! $product ) {
			error_log( 'AA Customers Stripe: Product not found - ' . $product_id );
			return false;
		}

		$purchase_id = $purchases_repo->create( array(
			'member_id'               => $member_id,
			'product_id'              => $product_id,
			'amount'                  => $product->price,
			'donation_amount'         => $donation,
			'total_amount'            => $session->amount_total / 100,
			'currency'                => strtoupper( $session->currency ),
			'stripe_payment_intent_id' => $session->payment_intent ?? null,
			'payment_status'          => 'succeeded',
		) );

		if ( ! $purchase_id ) {
			error_log( 'AA Customers Stripe: Failed to create purchase record.' );
			return false;
		}

		// Update membership if this is a membership product.
		if ( $product->type === 'membership' ) {
			$membership_service = new AA_Customers_Membership_Service();
			$membership_service->activate_membership( $member_id, $product_id, $session->subscription ?? null );
		}

		// Create event registration if this is an event product.
		if ( $product->type === 'event' ) {
			$events_repo = new AA_Customers_Events_Repository();
			$event       = $events_repo->get_by_product_id( $product_id );

			if ( $event ) {
				$registrations_repo = new AA_Customers_Registrations_Repository();
				$registrations_repo->create( array(
					'event_id'    => $event->id,
					'member_id'   => $member_id,
					'purchase_id' => $purchase_id,
					'status'      => 'confirmed',
				) );
			}
		}

		error_log( 'AA Customers Stripe: Checkout completed - Purchase ID ' . $purchase_id );
		return true;
	}

	/**
	 * Handle invoice.paid (subscription renewal)
	 *
	 * @param object $invoice Invoice object.
	 * @return bool True on success.
	 */
	private function handle_invoice_paid( $invoice ) {
		$subscription_id = $invoice->subscription;
		$customer_id     = $invoice->customer;

		if ( empty( $subscription_id ) ) {
			return true; // Not a subscription invoice.
		}

		// Find member by Stripe customer ID.
		$members_repo = new AA_Customers_Members_Repository();
		$member       = $members_repo->get_by_stripe_customer_id( $customer_id );

		if ( ! $member ) {
			error_log( 'AA Customers Stripe: Member not found for customer - ' . $customer_id );
			return false;
		}

		// Get subscription to find product.
		$subscription = $this->get_subscription( $subscription_id );
		if ( is_wp_error( $subscription ) ) {
			return false;
		}

		// Update membership expiry.
		$membership_service = new AA_Customers_Membership_Service();
		$membership_service->extend_membership( $member->id );

		error_log( 'AA Customers Stripe: Subscription renewed for member - ' . $member->id );
		return true;
	}

	/**
	 * Handle invoice.payment_failed
	 *
	 * @param object $invoice Invoice object.
	 * @return bool True on success.
	 */
	private function handle_invoice_failed( $invoice ) {
		$customer_id = $invoice->customer;

		$members_repo = new AA_Customers_Members_Repository();
		$member       = $members_repo->get_by_stripe_customer_id( $customer_id );

		if ( ! $member ) {
			return false;
		}

		// Update member status to past_due.
		$members_repo->update( $member->id, array(
			'membership_status' => 'past_due',
		) );

		// TODO: Send payment failed email.

		error_log( 'AA Customers Stripe: Payment failed for member - ' . $member->id );
		return true;
	}

	/**
	 * Handle customer.subscription.deleted
	 *
	 * @param object $subscription Subscription object.
	 * @return bool True on success.
	 */
	private function handle_subscription_deleted( $subscription ) {
		$customer_id = $subscription->customer;

		$members_repo = new AA_Customers_Members_Repository();
		$member       = $members_repo->get_by_stripe_customer_id( $customer_id );

		if ( ! $member ) {
			return false;
		}

		// Update member status to cancelled.
		$members_repo->update( $member->id, array(
			'membership_status'      => 'cancelled',
			'stripe_subscription_id' => null,
		) );

		error_log( 'AA Customers Stripe: Subscription cancelled for member - ' . $member->id );
		return true;
	}

	/**
	 * Handle customer.subscription.updated
	 *
	 * @param object $subscription Subscription object.
	 * @return bool True on success.
	 */
	private function handle_subscription_updated( $subscription ) {
		$customer_id = $subscription->customer;

		$members_repo = new AA_Customers_Members_Repository();
		$member       = $members_repo->get_by_stripe_customer_id( $customer_id );

		if ( ! $member ) {
			return false;
		}

		// Map Stripe status to our status.
		$status_map = array(
			'active'   => 'active',
			'past_due' => 'past_due',
			'canceled' => 'cancelled',
			'unpaid'   => 'past_due',
		);

		$new_status = isset( $status_map[ $subscription->status ] ) ? $status_map[ $subscription->status ] : $subscription->status;

		$members_repo->update( $member->id, array(
			'membership_status' => $new_status,
		) );

		error_log( 'AA Customers Stripe: Subscription updated for member - ' . $member->id . ' - Status: ' . $new_status );
		return true;
	}
}
