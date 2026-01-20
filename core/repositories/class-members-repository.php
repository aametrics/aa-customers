<?php
/**
 * Members Repository
 *
 * Database CRUD operations for members.
 * Members are linked to WordPress users via wp_user_id.
 *
 * @package AA_Customers
 * @subpackage Repositories
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Members_Repository
 */
class AA_Customers_Members_Repository {

	/**
	 * Get all members
	 *
	 * @param array $args Query arguments.
	 * @return array Array of member objects.
	 */
	public function get_all( $args = array() ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_members_table_name();

		$defaults = array(
			'status'   => '',
			'orderby'  => 'full_name',
			'order'    => 'ASC',
			'limit'    => 0,
			'offset'   => 0,
			'search'   => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		// Filter by status.
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'membership_status = %s';
			$values[] = $args['status'];
		}

		// Search by name or email.
		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(full_name LIKE %s OR email LIKE %s)';
			$search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $search;
			$values[] = $search;
		}

		$where_clause = implode( ' AND ', $where );

		// Build ORDER BY.
		$allowed_orderby = array( 'full_name', 'email', 'membership_status', 'membership_expiry', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'full_name';
		$order           = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order}";

		// Add LIMIT.
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
	 * Get member by ID
	 *
	 * @param int $id Member ID.
	 * @return object|null Member object or null.
	 */
	public function get_by_id( $id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_members_table_name();

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		) );
	}

	/**
	 * Get member by WordPress user ID
	 *
	 * @param int $wp_user_id WordPress user ID.
	 * @return object|null Member object or null.
	 */
	public function get_by_wp_user_id( $wp_user_id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_members_table_name();

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE wp_user_id = %d",
			$wp_user_id
		) );
	}

	/**
	 * Get member by email
	 *
	 * @param string $email Email address.
	 * @return object|null Member object or null.
	 */
	public function get_by_email( $email ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_members_table_name();

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE email = %s",
			$email
		) );
	}

	/**
	 * Get member by Stripe customer ID
	 *
	 * @param string $stripe_customer_id Stripe customer ID.
	 * @return object|null Member object or null.
	 */
	public function get_by_stripe_customer_id( $stripe_customer_id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_members_table_name();

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE stripe_customer_id = %s",
			$stripe_customer_id
		) );
	}

	/**
	 * Create a new member
	 *
	 * @param array $data Member data.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function create( $data ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_members_table_name();

		// Required fields.
		if ( empty( $data['wp_user_id'] ) || empty( $data['email'] ) || empty( $data['full_name'] ) ) {
			return false;
		}

		// Check for duplicate wp_user_id.
		if ( $this->get_by_wp_user_id( $data['wp_user_id'] ) ) {
			return false;
		}

		// Check for duplicate email.
		if ( $this->get_by_email( $data['email'] ) ) {
			return false;
		}

		$insert_data = array(
			'wp_user_id'            => absint( $data['wp_user_id'] ),
			'full_name'             => sanitize_text_field( $data['full_name'] ),
			'email'                 => sanitize_email( $data['email'] ),
			'phone'                 => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : null,
			'address_line_1'        => isset( $data['address_line_1'] ) ? sanitize_text_field( $data['address_line_1'] ) : null,
			'address_line_2'        => isset( $data['address_line_2'] ) ? sanitize_text_field( $data['address_line_2'] ) : null,
			'city'                  => isset( $data['city'] ) ? sanitize_text_field( $data['city'] ) : null,
			'postcode'              => isset( $data['postcode'] ) ? sanitize_text_field( $data['postcode'] ) : null,
			'country'               => isset( $data['country'] ) ? sanitize_text_field( $data['country'] ) : 'United Kingdom',
			'membership_status'     => isset( $data['membership_status'] ) ? sanitize_text_field( $data['membership_status'] ) : 'none',
			'stripe_customer_id'    => isset( $data['stripe_customer_id'] ) ? sanitize_text_field( $data['stripe_customer_id'] ) : null,
			'show_in_directory'     => isset( $data['show_in_directory'] ) ? (bool) $data['show_in_directory'] : false,
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

		$result = $wpdb->insert( $table, $insert_data, $format );

		if ( false === $result ) {
			error_log( 'AA Customers: Failed to create member - ' . $wpdb->last_error );
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update a member
	 *
	 * @param int   $id Member ID.
	 * @param array $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( $id, $data ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_members_table_name();

		// Check member exists.
		if ( ! $this->get_by_id( $id ) ) {
			return false;
		}

		$update_data   = array();
		$update_format = array();

		// Allowed fields for update.
		$allowed_fields = array(
			'full_name'              => '%s',
			'email'                  => '%s',
			'phone'                  => '%s',
			'address_line_1'         => '%s',
			'address_line_2'         => '%s',
			'city'                   => '%s',
			'postcode'               => '%s',
			'country'                => '%s',
			'membership_status'      => '%s',
			'membership_type_id'     => '%d',
			'membership_start'       => '%s',
			'membership_expiry'      => '%s',
			'stripe_customer_id'     => '%s',
			'stripe_subscription_id' => '%s',
			'show_in_directory'      => '%d',
			'practitioner_bio'       => '%s',
			'practitioner_specialties' => '%s',
			'practitioner_location'  => '%s',
			'practitioner_website'   => '%s',
			'practitioner_photo_url' => '%s',
			'xero_contact_id'        => '%s',
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
	 * Delete a member
	 *
	 * @param int $id Member ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_members_table_name();

		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Get members expiring soon
	 *
	 * @param int $days Number of days until expiry.
	 * @return array Array of member objects.
	 */
	public function get_expiring_soon( $days = 30 ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_members_table_name();

		$future_date = date( 'Y-m-d', strtotime( "+{$days} days" ) );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} 
			WHERE membership_status = 'active' 
			AND membership_expiry IS NOT NULL 
			AND membership_expiry <= %s 
			AND membership_expiry >= CURDATE()
			ORDER BY membership_expiry ASC",
			$future_date
		) );
	}

	/**
	 * Get expired members
	 *
	 * @return array Array of member objects.
	 */
	public function get_expired() {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_members_table_name();

		return $wpdb->get_results(
			"SELECT * FROM {$table} 
			WHERE membership_status = 'active' 
			AND membership_expiry IS NOT NULL 
			AND membership_expiry < CURDATE()
			ORDER BY membership_expiry DESC"
		);
	}

	/**
	 * Get directory members (active and visible)
	 *
	 * @param array $args Query arguments.
	 * @return array Array of member objects.
	 */
	public function get_directory_members( $args = array() ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_members_table_name();

		$defaults = array(
			'specialty' => '',
			'location'  => '',
			'search'    => '',
			'orderby'   => 'full_name',
			'order'     => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( "membership_status = 'active'", "show_in_directory = 1" );
		$values = array();

		// Filter by specialty.
		if ( ! empty( $args['specialty'] ) ) {
			$where[]  = 'practitioner_specialties LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['specialty'] ) . '%';
		}

		// Filter by location.
		if ( ! empty( $args['location'] ) ) {
			$where[]  = 'practitioner_location LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['location'] ) . '%';
		}

		// Search by name.
		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'full_name LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$where_clause = implode( ' AND ', $where );
		$order        = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY full_name {$order}";

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Count members by status
	 *
	 * @return array Associative array of status => count.
	 */
	public function count_by_status() {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_members_table_name();

		$results = $wpdb->get_results(
			"SELECT membership_status, COUNT(*) as count FROM {$table} GROUP BY membership_status"
		);

		$counts = array(
			'active'    => 0,
			'pending'   => 0,
			'past_due'  => 0,
			'cancelled' => 0,
			'expired'   => 0,
			'none'      => 0,
		);

		foreach ( $results as $row ) {
			$counts[ $row->membership_status ] = (int) $row->count;
		}

		$counts['total'] = array_sum( $counts );

		return $counts;
	}

	/**
	 * Create member from WordPress user
	 *
	 * @param int   $wp_user_id WordPress user ID.
	 * @param array $extra_data Additional member data.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function create_from_wp_user( $wp_user_id, $extra_data = array() ) {
		$user = get_user_by( 'ID', $wp_user_id );

		if ( ! $user ) {
			return false;
		}

		$data = array_merge( array(
			'wp_user_id' => $wp_user_id,
			'email'      => $user->user_email,
			'full_name'  => $user->display_name ?: $user->user_login,
		), $extra_data );

		return $this->create( $data );
	}
}
