<?php
/**
 * Registrations Repository
 *
 * Database CRUD operations for event registrations.
 *
 * @package AA_Customers
 * @subpackage Repositories
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Registrations_Repository
 */
class AA_Customers_Registrations_Repository {

	/**
	 * Get all registrations
	 *
	 * @param array $args Query arguments.
	 * @return array Array of registration objects.
	 */
	public function get_all( $args = array() ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_registrations_table_name();

		$defaults = array(
			'event_id'  => 0,
			'member_id' => 0,
			'status'    => '',
			'orderby'   => 'registered_at',
			'order'     => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( $args['event_id'] > 0 ) {
			$where[]  = 'event_id = %d';
			$values[] = $args['event_id'];
		}

		if ( $args['member_id'] > 0 ) {
			$where[]  = 'member_id = %d';
			$values[] = $args['member_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );

		$allowed_orderby = array( 'registered_at', 'status' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'registered_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order}";

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get registrations for an event with member/guest info
	 *
	 * @param int $event_id Event ID.
	 * @return array Array of registration objects.
	 */
	public function get_for_event_with_details( $event_id ) {
		$wpdb                = AA_Customers_DB_Connection::get_connection();
		$registrations_table = AA_Customers_Database::get_registrations_table_name();
		$members_table       = AA_Customers_Database::get_members_table_name();

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT r.*, 
				COALESCE(m.full_name, r.guest_name) as attendee_name,
				COALESCE(m.email, r.guest_email) as attendee_email
			FROM {$registrations_table} r
			LEFT JOIN {$members_table} m ON r.member_id = m.id
			WHERE r.event_id = %d
			ORDER BY r.registered_at DESC",
			$event_id
		) );
	}

	/**
	 * Get registration by ID
	 *
	 * @param int $id Registration ID.
	 * @return object|null Registration object or null.
	 */
	public function get_by_id( $id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_registrations_table_name();

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		) );
	}

	/**
	 * Get registration by purchase ID
	 *
	 * @param int $purchase_id Purchase ID.
	 * @return object|null Registration object or null.
	 */
	public function get_by_purchase_id( $purchase_id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_registrations_table_name();

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE purchase_id = %d",
			$purchase_id
		) );
	}

	/**
	 * Check if member is registered for event
	 *
	 * @param int $event_id Event ID.
	 * @param int $member_id Member ID.
	 * @return bool True if registered.
	 */
	public function is_member_registered( $event_id, $member_id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_registrations_table_name();

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} 
			WHERE event_id = %d AND member_id = %d AND status != 'cancelled'",
			$event_id,
			$member_id
		) );

		return (int) $count > 0;
	}

	/**
	 * Get registrations for a member
	 *
	 * @param int $member_id Member ID.
	 * @return array Array of registration objects.
	 */
	public function get_by_member( $member_id ) {
		return $this->get_all( array( 'member_id' => $member_id ) );
	}

	/**
	 * Create a new registration
	 *
	 * @param array $data Registration data.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function create( $data ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_registrations_table_name();

		// Required fields.
		if ( empty( $data['event_id'] ) || empty( $data['purchase_id'] ) ) {
			return false;
		}

		// Must have either member_id or guest info.
		if ( empty( $data['member_id'] ) && ( empty( $data['guest_name'] ) || empty( $data['guest_email'] ) ) ) {
			return false;
		}

		$insert_data = array(
			'event_id'              => absint( $data['event_id'] ),
			'member_id'             => isset( $data['member_id'] ) ? absint( $data['member_id'] ) : null,
			'purchase_id'           => absint( $data['purchase_id'] ),
			'guest_name'            => isset( $data['guest_name'] ) ? sanitize_text_field( $data['guest_name'] ) : null,
			'guest_email'           => isset( $data['guest_email'] ) ? sanitize_email( $data['guest_email'] ) : null,
			'status'                => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'confirmed',
			'dietary_requirements'  => isset( $data['dietary_requirements'] ) ? sanitize_textarea_field( $data['dietary_requirements'] ) : null,
			'accessibility_needs'   => isset( $data['accessibility_needs'] ) ? sanitize_textarea_field( $data['accessibility_needs'] ) : null,
			'additional_info'       => isset( $data['additional_info'] ) ? sanitize_textarea_field( $data['additional_info'] ) : null,
		);

		$format = array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

		$result = $wpdb->insert( $table, $insert_data, $format );

		if ( false === $result ) {
			error_log( 'AA Customers: Failed to create registration - ' . $wpdb->last_error );
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update a registration
	 *
	 * @param int   $id Registration ID.
	 * @param array $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( $id, $data ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_registrations_table_name();

		if ( ! $this->get_by_id( $id ) ) {
			return false;
		}

		$update_data   = array();
		$update_format = array();

		$allowed_fields = array(
			'status'               => '%s',
			'dietary_requirements' => '%s',
			'accessibility_needs'  => '%s',
			'additional_info'      => '%s',
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
	 * Cancel a registration
	 *
	 * @param int $id Registration ID.
	 * @return bool True on success.
	 */
	public function cancel( $id ) {
		return $this->update( $id, array( 'status' => 'cancelled' ) );
	}

	/**
	 * Move to waitlist
	 *
	 * @param int $id Registration ID.
	 * @return bool True on success.
	 */
	public function move_to_waitlist( $id ) {
		return $this->update( $id, array( 'status' => 'waitlist' ) );
	}

	/**
	 * Confirm from waitlist
	 *
	 * @param int $id Registration ID.
	 * @return bool True on success.
	 */
	public function confirm_from_waitlist( $id ) {
		return $this->update( $id, array( 'status' => 'confirmed' ) );
	}

	/**
	 * Get waitlist for an event
	 *
	 * @param int $event_id Event ID.
	 * @return array Array of registration objects.
	 */
	public function get_waitlist( $event_id ) {
		return $this->get_all( array(
			'event_id' => $event_id,
			'status'   => 'waitlist',
			'orderby'  => 'registered_at',
			'order'    => 'ASC',
		) );
	}

	/**
	 * Count registrations by status for an event
	 *
	 * @param int $event_id Event ID.
	 * @return array Associative array of status => count.
	 */
	public function count_by_status_for_event( $event_id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_registrations_table_name();

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT status, COUNT(*) as count FROM {$table} WHERE event_id = %d GROUP BY status",
			$event_id
		) );

		$counts = array(
			'confirmed' => 0,
			'cancelled' => 0,
			'waitlist'  => 0,
		);

		foreach ( $results as $row ) {
			$counts[ $row->status ] = (int) $row->count;
		}

		return $counts;
	}
}
