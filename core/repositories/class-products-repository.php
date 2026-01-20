<?php
/**
 * Products Repository
 *
 * Database CRUD operations for products (memberships and events).
 *
 * @package AA_Customers
 * @subpackage Repositories
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Products_Repository
 */
class AA_Customers_Products_Repository {

	/**
	 * Get all products
	 *
	 * @param array $args Query arguments.
	 * @return array Array of product objects.
	 */
	public function get_all( $args = array() ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_products_table_name();

		$defaults = array(
			'type'    => '',
			'status'  => '',
			'orderby' => 'name',
			'order'   => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		// Filter by type.
		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'type = %s';
			$values[] = $args['type'];
		}

		// Filter by status.
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );

		$allowed_orderby = array( 'name', 'price', 'type', 'status', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'name';
		$order           = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order}";

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get product by ID
	 *
	 * @param int $id Product ID.
	 * @return object|null Product object or null.
	 */
	public function get_by_id( $id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_products_table_name();

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		) );
	}

	/**
	 * Get product by slug
	 *
	 * @param string $slug Product slug.
	 * @return object|null Product object or null.
	 */
	public function get_by_slug( $slug ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_products_table_name();

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE slug = %s",
			$slug
		) );
	}

	/**
	 * Get product by Stripe product ID
	 *
	 * @param string $stripe_product_id Stripe product ID.
	 * @return object|null Product object or null.
	 */
	public function get_by_stripe_product_id( $stripe_product_id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_products_table_name();

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE stripe_product_id = %s",
			$stripe_product_id
		) );
	}

	/**
	 * Get active membership products
	 *
	 * @return array Array of product objects.
	 */
	public function get_active_memberships() {
		return $this->get_all( array(
			'type'   => 'membership',
			'status' => 'active',
		) );
	}

	/**
	 * Get active event products
	 *
	 * @return array Array of product objects.
	 */
	public function get_active_events() {
		return $this->get_all( array(
			'type'   => 'event',
			'status' => 'active',
		) );
	}

	/**
	 * Create a new product
	 *
	 * @param array $data Product data.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function create( $data ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_products_table_name();

		// Required fields.
		if ( empty( $data['name'] ) || empty( $data['type'] ) || ! isset( $data['price'] ) ) {
			return false;
		}

		// Generate slug if not provided.
		$slug = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $data['name'] );

		// Ensure slug is unique.
		$slug = $this->make_unique_slug( $slug );

		$insert_data = array(
			'type'               => sanitize_text_field( $data['type'] ),
			'name'               => sanitize_text_field( $data['name'] ),
			'slug'               => $slug,
			'description'        => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
			'price'              => floatval( $data['price'] ),
			'member_price'       => isset( $data['member_price'] ) ? floatval( $data['member_price'] ) : null,
			'stripe_product_id'  => isset( $data['stripe_product_id'] ) ? sanitize_text_field( $data['stripe_product_id'] ) : null,
			'stripe_price_id'    => isset( $data['stripe_price_id'] ) ? sanitize_text_field( $data['stripe_price_id'] ) : null,
			'is_recurring'       => isset( $data['is_recurring'] ) ? (bool) $data['is_recurring'] : false,
			'recurring_interval' => isset( $data['recurring_interval'] ) ? sanitize_text_field( $data['recurring_interval'] ) : 'year',
			'allow_donation'     => isset( $data['allow_donation'] ) ? (bool) $data['allow_donation'] : true,
			'status'             => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'draft',
		);

		$format = array( '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%d', '%s', '%d', '%s' );

		$result = $wpdb->insert( $table, $insert_data, $format );

		if ( false === $result ) {
			error_log( 'AA Customers: Failed to create product - ' . $wpdb->last_error );
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update a product
	 *
	 * @param int   $id Product ID.
	 * @param array $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( $id, $data ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_products_table_name();

		if ( ! $this->get_by_id( $id ) ) {
			return false;
		}

		$update_data   = array();
		$update_format = array();

		$allowed_fields = array(
			'name'               => '%s',
			'slug'               => '%s',
			'description'        => '%s',
			'price'              => '%f',
			'member_price'       => '%f',
			'stripe_product_id'  => '%s',
			'stripe_price_id'    => '%s',
			'is_recurring'       => '%d',
			'recurring_interval' => '%s',
			'allow_donation'     => '%d',
			'status'             => '%s',
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
	 * Delete a product
	 *
	 * @param int $id Product ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $id ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = AA_Customers_Database::get_products_table_name();

		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Make a slug unique
	 *
	 * @param string $slug Base slug.
	 * @param int    $exclude_id ID to exclude from check.
	 * @return string Unique slug.
	 */
	private function make_unique_slug( $slug, $exclude_id = 0 ) {
		$wpdb          = AA_Customers_DB_Connection::get_connection();
		$table         = AA_Customers_Database::get_products_table_name();
		$original_slug = $slug;
		$counter       = 1;

		while ( true ) {
			$sql = $wpdb->prepare(
				"SELECT id FROM {$table} WHERE slug = %s",
				$slug
			);

			if ( $exclude_id > 0 ) {
				$sql .= $wpdb->prepare( " AND id != %d", $exclude_id );
			}

			$exists = $wpdb->get_var( $sql );

			if ( ! $exists ) {
				break;
			}

			$slug = $original_slug . '-' . $counter;
			$counter++;
		}

		return $slug;
	}

	/**
	 * Get price for a product (considering member pricing)
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $is_member Whether the purchaser is a member.
	 * @return float Price.
	 */
	public function get_price( $product_id, $is_member = false ) {
		$product = $this->get_by_id( $product_id );

		if ( ! $product ) {
			return 0;
		}

		if ( $is_member && $product->member_price !== null ) {
			return (float) $product->member_price;
		}

		return (float) $product->price;
	}
}
