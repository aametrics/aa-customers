<?php
/**
 * Membership Service
 *
 * Handles membership business logic:
 * - Activation and renewal
 * - Expiry checks
 * - Status management
 * - Renewal reminders
 *
 * @package AA_Customers
 * @subpackage Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Membership_Service
 */
class AA_Customers_Membership_Service {

	/**
	 * Members repository
	 *
	 * @var AA_Customers_Members_Repository
	 */
	private $members_repo;

	/**
	 * Products repository
	 *
	 * @var AA_Customers_Products_Repository
	 */
	private $products_repo;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->members_repo  = new AA_Customers_Members_Repository();
		$this->products_repo = new AA_Customers_Products_Repository();
	}

	/**
	 * Activate membership for a member
	 *
	 * @param int         $member_id Member ID.
	 * @param int         $product_id Product ID (membership type).
	 * @param string|null $subscription_id Stripe subscription ID.
	 * @return bool True on success.
	 */
	public function activate_membership( $member_id, $product_id, $subscription_id = null ) {
		$member  = $this->members_repo->get_by_id( $member_id );
		$product = $this->products_repo->get_by_id( $product_id );

		if ( ! $member || ! $product ) {
			return false;
		}

		// Calculate expiry date.
		$start_date  = date( 'Y-m-d' );
		$expiry_date = $this->calculate_expiry_date( $product );

		$update_data = array(
			'membership_status'  => 'active',
			'membership_type_id' => $product_id,
			'membership_start'   => $start_date,
			'membership_expiry'  => $expiry_date,
		);

		if ( $subscription_id ) {
			$update_data['stripe_subscription_id'] = $subscription_id;
		}

		$result = $this->members_repo->update( $member_id, $update_data );

		if ( $result ) {
			// Add member role to WordPress user.
			$user = get_user_by( 'ID', $member->wp_user_id );
			if ( $user ) {
				$user->add_role( 'member' );
			}

			error_log( sprintf(
				'AA Customers: Membership activated for member %d - Expires: %s',
				$member_id,
				$expiry_date
			) );

			// Send welcome email.
			$email_service = new AA_Customers_Email_Service();
			$email_service->send_welcome_email( $member, $product );
		}

		return $result;
	}

	/**
	 * Extend membership (renewal)
	 *
	 * @param int      $member_id Member ID.
	 * @param int|null $product_id Product ID (optional, uses current type).
	 * @return bool True on success.
	 */
	public function extend_membership( $member_id, $product_id = null ) {
		$member = $this->members_repo->get_by_id( $member_id );

		if ( ! $member ) {
			return false;
		}

		// Use current membership type if not specified.
		if ( ! $product_id ) {
			$product_id = $member->membership_type_id;
		}

		$product = $this->products_repo->get_by_id( $product_id );

		if ( ! $product ) {
			return false;
		}

		// Calculate new expiry from current expiry (or today if expired).
		$current_expiry = $member->membership_expiry;
		$base_date      = date( 'Y-m-d' );

		if ( $current_expiry && strtotime( $current_expiry ) > strtotime( $base_date ) ) {
			$base_date = $current_expiry;
		}

		$new_expiry = $this->calculate_expiry_date( $product, $base_date );

		$result = $this->members_repo->update( $member_id, array(
			'membership_status'  => 'active',
			'membership_expiry'  => $new_expiry,
			'membership_type_id' => $product_id,
		) );

		if ( $result ) {
			error_log( sprintf(
				'AA Customers: Membership extended for member %d - New expiry: %s',
				$member_id,
				$new_expiry
			) );
		}

		return $result;
	}

	/**
	 * Calculate expiry date based on product
	 *
	 * @param object      $product Product object.
	 * @param string|null $from_date Starting date (Y-m-d format).
	 * @return string Expiry date (Y-m-d format).
	 */
	private function calculate_expiry_date( $product, $from_date = null ) {
		if ( ! $from_date ) {
			$from_date = date( 'Y-m-d' );
		}

		$interval = $product->recurring_interval ?? 'year';

		switch ( $interval ) {
			case 'month':
				$expiry = date( 'Y-m-d', strtotime( $from_date . ' +1 month' ) );
				break;
			case 'year':
			default:
				$expiry = date( 'Y-m-d', strtotime( $from_date . ' +1 year' ) );
				break;
		}

		return $expiry;
	}

	/**
	 * Cancel membership
	 *
	 * @param int  $member_id Member ID.
	 * @param bool $immediately Cancel immediately or at period end.
	 * @return bool True on success.
	 */
	public function cancel_membership( $member_id, $immediately = false ) {
		$member = $this->members_repo->get_by_id( $member_id );

		if ( ! $member ) {
			return false;
		}

		// Cancel Stripe subscription if exists.
		if ( ! empty( $member->stripe_subscription_id ) ) {
			$stripe_service = new AA_Customers_Stripe_Service();
			$stripe_service->cancel_subscription( $member->stripe_subscription_id, $immediately );
		}

		$new_status = $immediately ? 'cancelled' : 'active'; // Will cancel at period end.

		$result = $this->members_repo->update( $member_id, array(
			'membership_status' => $new_status,
		) );

		if ( $result ) {
			error_log( sprintf(
				'AA Customers: Membership cancelled for member %d - Immediate: %s',
				$member_id,
				$immediately ? 'yes' : 'no'
			) );
		}

		return $result;
	}

	/**
	 * Check if member has active membership
	 *
	 * @param int $member_id Member ID.
	 * @return bool True if active.
	 */
	public function is_active( $member_id ) {
		$member = $this->members_repo->get_by_id( $member_id );

		if ( ! $member ) {
			return false;
		}

		return $member->membership_status === 'active';
	}

	/**
	 * Check if membership is expiring soon
	 *
	 * @param int $member_id Member ID.
	 * @param int $days Number of days.
	 * @return bool True if expiring within days.
	 */
	public function is_expiring_soon( $member_id, $days = 30 ) {
		$member = $this->members_repo->get_by_id( $member_id );

		if ( ! $member || $member->membership_status !== 'active' || empty( $member->membership_expiry ) ) {
			return false;
		}

		$expiry_date  = strtotime( $member->membership_expiry );
		$threshold    = strtotime( "+{$days} days" );

		return $expiry_date <= $threshold && $expiry_date >= strtotime( 'today' );
	}

	/**
	 * Check if membership is expired
	 *
	 * @param int $member_id Member ID.
	 * @return bool True if expired.
	 */
	public function is_expired( $member_id ) {
		$member = $this->members_repo->get_by_id( $member_id );

		if ( ! $member || empty( $member->membership_expiry ) ) {
			return false;
		}

		return strtotime( $member->membership_expiry ) < strtotime( 'today' );
	}

	/**
	 * Process expired memberships
	 *
	 * Updates status for any memberships that have expired.
	 * Should be run daily via cron.
	 *
	 * @return int Number of memberships updated.
	 */
	public function process_expired_memberships() {
		$expired = $this->members_repo->get_expired();
		$count   = 0;

		foreach ( $expired as $member ) {
			$result = $this->members_repo->update( $member->id, array(
				'membership_status' => 'expired',
			) );

			if ( $result ) {
				$count++;
				error_log( 'AA Customers: Membership expired for member ' . $member->id );
			}
		}

		return $count;
	}

	/**
	 * Send renewal reminders
	 *
	 * Sends reminders to members expiring within specified days.
	 * Should be run daily via cron.
	 *
	 * @param int $days Days until expiry.
	 * @return int Number of reminders sent.
	 */
	public function send_renewal_reminders( $days = 30 ) {
		$expiring      = $this->members_repo->get_expiring_soon( $days );
		$email_service = new AA_Customers_Email_Service();
		$count         = 0;

		foreach ( $expiring as $member ) {
			$days_until = ceil( ( strtotime( $member->membership_expiry ) - time() ) / 86400 );

			// Only send at specific intervals (30, 14, 7, 3, 1 days).
			if ( ! in_array( $days_until, array( 30, 14, 7, 3, 1 ), true ) ) {
				continue;
			}

			$result = $email_service->send_renewal_reminder( $member, $days_until );

			if ( $result ) {
				$count++;
			}
		}

		error_log( sprintf( 'AA Customers: Sent %d renewal reminders', $count ) );
		return $count;
	}

	/**
	 * Get membership statistics
	 *
	 * @return array Statistics array.
	 */
	public function get_statistics() {
		$counts = $this->members_repo->count_by_status();

		$expiring_30 = count( $this->members_repo->get_expiring_soon( 30 ) );
		$expiring_7  = count( $this->members_repo->get_expiring_soon( 7 ) );

		return array(
			'total'        => $counts['total'],
			'active'       => $counts['active'],
			'pending'      => $counts['pending'],
			'past_due'     => $counts['past_due'],
			'cancelled'    => $counts['cancelled'],
			'expired'      => $counts['expired'],
			'none'         => $counts['none'],
			'expiring_30'  => $expiring_30,
			'expiring_7'   => $expiring_7,
		);
	}

	/**
	 * Get member's current membership info
	 *
	 * @param int $member_id Member ID.
	 * @return array|null Membership info or null.
	 */
	public function get_membership_info( $member_id ) {
		$member = $this->members_repo->get_by_id( $member_id );

		if ( ! $member ) {
			return null;
		}

		$product = null;
		if ( $member->membership_type_id ) {
			$product = $this->products_repo->get_by_id( $member->membership_type_id );
		}

		return array(
			'status'          => $member->membership_status,
			'type_id'         => $member->membership_type_id,
			'type_name'       => $product ? $product->name : null,
			'start_date'      => $member->membership_start,
			'expiry_date'     => $member->membership_expiry,
			'subscription_id' => $member->stripe_subscription_id,
			'is_active'       => $member->membership_status === 'active',
			'is_expiring'     => $this->is_expiring_soon( $member_id ),
			'is_expired'      => $this->is_expired( $member_id ),
		);
	}
}
