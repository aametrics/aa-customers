<?php
/**
 * Purchases Repository
 *
 * Database CRUD operations for purchases (transactions).
 *
 * @package AA_Customers
 * @subpackage Repositories
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Purchases_Repository
 */
class AA_Customers_Purchases_Repository {

	/**
	 * Get all purchases
	 *
	 * @param array $args Query arguments.
	 * @return array Array of purchase objects.
	 */
	public function get_all( $args = array() ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_purchases_table_name();

		$defaults = array(
			'member_id'      => 0,
			'product_id'     => 0,
			'payment_status' => '',
			'orderby'        => 'purchase_date',
			'order'          => 'DESC',
			'limit'          => 0,
			'offset'         => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( $args['member_id'] > 0 ) {
			$where[]  = 'member_id = %d';
			$values[] = $args['member_id'];
		}

		if ( $args['product_id'] > 0 ) {
			$where[]  = 'product_id = %d';
			$values[] = $args['product_id'];
		}

		if ( ! empty( $args['payment_status'] ) ) {
			$where[]  = 'payment_status = %s';
			$values[] = $args['payment_status'];
		}

		$where_clause = implode( ' AND ', $where );

		$allowed_orderby = array( 'purchase_date', 'total_amount', 'payment_status' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'purchase_date';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order}";

		if ( $args['limit'] > 0 ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$values[] = $args['limit'];
			$values[] = $args['offset'];
		}

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get purchase by ID
	 *
	 * @param int $id Purchase ID.
	 * @return object|null Purchase object or null.
	 */
	public function get_by_id( $id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_purchases_table_name();

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		) );
	}

	/**
	 * Get purchase by Stripe payment intent ID
	 *
	 * @param string $payment_intent_id Stripe payment intent ID.
	 * @return object|null Purchase object or null.
	 */
	public function get_by_stripe_payment_intent( $payment_intent_id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_purchases_table_name();

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE stripe_payment_intent_id = %s",
			$payment_intent_id
		) );
	}

	/**
	 * Get purchases for a member
	 *
	 * @param int $member_id Member ID.
	 * @return array Array of purchase objects.
	 */
	public function get_by_member( $member_id ) {
		return $this->get_all( array( 'member_id' => $member_id ) );
	}

	/**
	 * Create a new purchase
	 *
	 * @param array $data Purchase data.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function create( $data ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_purchases_table_name();

		// Required fields.
		if ( empty( $data['member_id'] ) || empty( $data['product_id'] ) || ! isset( $data['amount'] ) ) {
			return false;
		}

		$donation_amount = isset( $data['donation_amount'] ) ? floatval( $data['donation_amount'] ) : 0;
		$amount          = floatval( $data['amount'] );
		$total_amount    = isset( $data['total_amount'] ) ? floatval( $data['total_amount'] ) : ( $amount + $donation_amount );

		$insert_data = array(
			'member_id'               => absint( $data['member_id'] ),
			'product_id'              => absint( $data['product_id'] ),
			'amount'                  => $amount,
			'donation_amount'         => $donation_amount,
			'total_amount'            => $total_amount,
			'currency'                => isset( $data['currency'] ) ? strtoupper( sanitize_text_field( $data['currency'] ) ) : 'GBP',
			'stripe_payment_intent_id' => isset( $data['stripe_payment_intent_id'] ) ? sanitize_text_field( $data['stripe_payment_intent_id'] ) : null,
			'stripe_invoice_id'       => isset( $data['stripe_invoice_id'] ) ? sanitize_text_field( $data['stripe_invoice_id'] ) : null,
			'payment_status'          => isset( $data['payment_status'] ) ? sanitize_text_field( $data['payment_status'] ) : 'pending',
			'xero_invoice_id'         => isset( $data['xero_invoice_id'] ) ? sanitize_text_field( $data['xero_invoice_id'] ) : null,
			'is_renewal'              => isset( $data['is_renewal'] ) ? (bool) $data['is_renewal'] : false,
			'notes'                   => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
		);

		$format = array( '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );

		$result = $wpdb->insert( $table, $insert_data, $format );

		if ( false === $result ) {
			error_log( 'AA Customers: Failed to create purchase - ' . $wpdb->last_error );
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update a purchase
	 *
	 * @param int   $id Purchase ID.
	 * @param array $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( $id, $data ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_purchases_table_name();

		if ( ! $this->get_by_id( $id ) ) {
			return false;
		}

		$update_data   = array();
		$update_format = array();

		$allowed_fields = array(
			'payment_status'           => '%s',
			'stripe_payment_intent_id' => '%s',
			'stripe_invoice_id'        => '%s',
			'xero_invoice_id'          => '%s',
			'notes'                    => '%s',
		);

		foreach ( $allowed_fields as $field => $format ) {
			if ( isset( $data[ $field ] ) ) {
				$update_data[ $field ] = $data[ $field ];
				$update_format[]       = $format;
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $id ),
			$update_format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Update payment status
	 *
	 * @param int    $id Purchase ID.
	 * @param string $status New status.
	 * @return bool True on success.
	 */
	public function update_status( $id, $status ) {
		return $this->update( $id, array( 'payment_status' => $status ) );
	}

	/**
	 * Get revenue for a period
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date End date (Y-m-d).
	 * @return float Total revenue.
	 */
	public function get_revenue( $start_date, $end_date ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_purchases_table_name();

		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(total_amount) FROM {$table} 
			WHERE payment_status = 'succeeded' 
			AND DATE(purchase_date) >= %s 
			AND DATE(purchase_date) <= %s",
			$start_date,
			$end_date
		) );

		return (float) $result;
	}

	/**
	 * Get revenue for current month
	 *
	 * @return float Total revenue.
	 */
	public function get_monthly_revenue() {
		$start = date( 'Y-m-01' );
		$end   = date( 'Y-m-t' );

		return $this->get_revenue( $start, $end );
	}

	/**
	 * Get donation total for a period
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date End date (Y-m-d).
	 * @return float Total donations.
	 */
	public function get_donations( $start_date, $end_date ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_purchases_table_name();

		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(donation_amount) FROM {$table} 
			WHERE payment_status = 'succeeded' 
			AND DATE(purchase_date) >= %s 
			AND DATE(purchase_date) <= %s",
			$start_date,
			$end_date
		) );

		return (float) $result;
	}

	/**
	 * Count purchases by status
	 *
	 * @return array Associative array of status => count.
	 */
	public function count_by_status() {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_purchases_table_name();

		$results = $wpdb->get_results(
			"SELECT payment_status, COUNT(*) as count FROM {$table} GROUP BY payment_status"
		);

		$counts = array(
			'pending'   => 0,
			'succeeded' => 0,
			'failed'    => 0,
			'refunded'  => 0,
		);

		foreach ( $results as $row ) {
			$counts[ $row->payment_status ] = (int) $row->count;
		}

		return $counts;
	}
}
