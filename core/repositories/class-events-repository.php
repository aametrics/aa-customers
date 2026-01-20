<?php
/**
 * Events Repository
 *
 * Database CRUD operations for events (metadata for event products).
 *
 * @package AA_Customers
 * @subpackage Repositories
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Events_Repository
 */
class AA_Customers_Events_Repository {

	/**
	 * Get all events
	 *
	 * @param array $args Query arguments.
	 * @return array Array of event objects.
	 */
	public function get_all( $args = array() ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_events_table_name();

		$defaults = array(
			'upcoming' => false,
			'past'     => false,
			'orderby'  => 'event_date',
			'order'    => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( $args['upcoming'] ) {
			$where[] = 'event_date >= CURDATE()';
		}

		if ( $args['past'] ) {
			$where[] = 'event_date < CURDATE()';
		}

		$where_clause = implode( ' AND ', $where );

		$allowed_orderby = array( 'event_date', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'event_date';
		$order           = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order}";

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get upcoming events with product info
	 *
	 * @param int $limit Maximum number of events.
	 * @return array Array of event objects with product data.
	 */
	public function get_upcoming_with_products( $limit = 10 ) {
		$wpdb         = AA_Customers_DB_Connection::get_connection();
		$events_table = AA_Customers_Database::get_events_table_name();
		$products_table = AA_Customers_Database::get_products_table_name();

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT e.*, p.name, p.slug, p.description, p.price, p.member_price, p.status as product_status
			FROM {$events_table} e
			INNER JOIN {$products_table} p ON e.product_id = p.id
			WHERE e.event_date >= CURDATE()
			AND p.status = 'active'
			ORDER BY e.event_date ASC
			LIMIT %d",
			$limit
		) );
	}

	/**
	 * Get event by ID
	 *
	 * @param int $id Event ID.
	 * @return object|null Event object or null.
	 */
	public function get_by_id( $id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_events_table_name();

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		) );
	}

	/**
	 * Get event by product ID
	 *
	 * @param int $product_id Product ID.
	 * @return object|null Event object or null.
	 */
	public function get_by_product_id( $product_id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_events_table_name();

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE product_id = %d",
			$product_id
		) );
	}

	/**
	 * Get event with product info
	 *
	 * @param int $id Event ID.
	 * @return object|null Event object with product data or null.
	 */
	public function get_with_product( $id ) {
		$wpdb         = AA_Customers_DB_Connection::get_connection();
		$events_table = AA_Customers_Database::get_events_table_name();
		$products_table = AA_Customers_Database::get_products_table_name();

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT e.*, p.name, p.slug, p.description, p.price, p.member_price, p.stripe_price_id, p.status as product_status
			FROM {$events_table} e
			INNER JOIN {$products_table} p ON e.product_id = p.id
			WHERE e.id = %d",
			$id
		) );
	}

	/**
	 * Create a new event
	 *
	 * @param array $data Event data.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function create( $data ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_events_table_name();

		// Required fields.
		if ( empty( $data['product_id'] ) || empty( $data['event_date'] ) ) {
			return false;
		}

		$insert_data = array(
			'product_id'            => absint( $data['product_id'] ),
			'event_date'            => sanitize_text_field( $data['event_date'] ),
			'event_time'            => isset( $data['event_time'] ) ? sanitize_text_field( $data['event_time'] ) : null,
			'end_date'              => isset( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : null,
			'end_time'              => isset( $data['end_time'] ) ? sanitize_text_field( $data['end_time'] ) : null,
			'location'              => isset( $data['location'] ) ? sanitize_text_field( $data['location'] ) : null,
			'location_details'      => isset( $data['location_details'] ) ? wp_kses_post( $data['location_details'] ) : null,
			'capacity'              => isset( $data['capacity'] ) ? absint( $data['capacity'] ) : null,
			'registration_deadline' => isset( $data['registration_deadline'] ) ? sanitize_text_field( $data['registration_deadline'] ) : null,
			'additional_info'       => isset( $data['additional_info'] ) ? wp_kses_post( $data['additional_info'] ) : null,
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' );

		$result = $wpdb->insert( $table, $insert_data, $format );

		if ( false === $result ) {
			error_log( 'AA Customers: Failed to create event - ' . $wpdb->last_error );
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update an event
	 *
	 * @param int   $id Event ID.
	 * @param array $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( $id, $data ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_events_table_name();

		if ( ! $this->get_by_id( $id ) ) {
			return false;
		}

		$update_data   = array();
		$update_format = array();

		$allowed_fields = array(
			'event_date'            => '%s',
			'event_time'            => '%s',
			'end_date'              => '%s',
			'end_time'              => '%s',
			'location'              => '%s',
			'location_details'      => '%s',
			'capacity'              => '%d',
			'registration_deadline' => '%s',
			'additional_info'       => '%s',
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
	 * Delete an event
	 *
	 * @param int $id Event ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_events_table_name();

		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Get registration count for an event
	 *
	 * @param int $event_id Event ID.
	 * @return int Number of registrations.
	 */
	public function get_registration_count( $event_id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_registrations_table_name();

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND status = 'confirmed'",
			$event_id
		) );

		return (int) $count;
	}

	/**
	 * Check if event has capacity
	 *
	 * @param int $event_id Event ID.
	 * @return bool True if has capacity or no limit.
	 */
	public function has_capacity( $event_id ) {
		$event = $this->get_by_id( $event_id );

		if ( ! $event || empty( $event->capacity ) ) {
			return true; // No capacity limit.
		}

		$registered = $this->get_registration_count( $event_id );

		return $registered < $event->capacity;
	}

	/**
	 * Check if registration is open for an event
	 *
	 * @param int $event_id Event ID.
	 * @return bool True if registration is open.
	 */
	public function is_registration_open( $event_id ) {
		$event = $this->get_by_id( $event_id );

		if ( ! $event ) {
			return false;
		}

		// Check if event date has passed.
		if ( strtotime( $event->event_date ) < strtotime( 'today' ) ) {
			return false;
		}

		// Check registration deadline.
		if ( ! empty( $event->registration_deadline ) ) {
			if ( strtotime( $event->registration_deadline ) < strtotime( 'today' ) ) {
				return false;
			}
		}

		// Check capacity.
		return $this->has_capacity( $event_id );
	}
}
