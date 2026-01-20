<?php
/**
 * Zap Storage Service
 *
 * Manages plugin configuration in custom database storage.
 * Named 'zap' for low-profile database presence.
 *
 * Note: Unlike aa-planner, aa-customers does NOT use field-level encryption
 * for member data. API keys are stored plaintext in the database but the
 * database itself should be protected at the hosting level.
 *
 * Storage Strategy:
 * - API keys (Stripe, Xero) = stored plaintext, marked as 'sensitive'
 * - Config data (settings) = plaintext
 * - Xero OAuth tokens = plaintext (refresh as needed)
 *
 * @package AA_Customers
 * @subpackage Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Zap_Storage
 */
class AA_Customers_Zap_Storage {

	/**
	 * Get configuration value
	 *
	 * @param string $key Configuration key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed Configuration value.
	 */
	public static function get( $key, $default = null ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = $wpdb->prefix . 'zap';

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT zap_value FROM {$table} WHERE zap_key = %s",
			$key
		) );

		if ( ! $row ) {
			return $default;
		}

		return maybe_unserialize( $row->zap_value );
	}

	/**
	 * Set configuration value
	 *
	 * @param string $key Configuration key.
	 * @param mixed  $value Configuration value.
	 * @param string $tag Category tag (sensitive/config/style).
	 * @return bool True on success.
	 */
	public static function set( $key, $value, $tag = 'config' ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = $wpdb->prefix . 'zap';

		// Serialize complex types.
		$stored_value = is_array( $value ) || is_object( $value )
			? serialize( $value )
			: $value;

		// Mark sensitive data.
		$lock = in_array( $tag, array( 'sensitive' ), true ) ? 1 : 0;

		// Insert or update.
		$result = $wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (zap_key, zap_value, zap_lock, zap_tag, zap_stamp, zap_pulse)
			 VALUES (%s, %s, %d, %s, NOW(), NOW())
			 ON DUPLICATE KEY UPDATE
				zap_value = VALUES(zap_value),
				zap_lock = VALUES(zap_lock),
				zap_tag = VALUES(zap_tag),
				zap_pulse = NOW()",
			$key,
			$stored_value,
			$lock,
			$tag
		) );

		return false !== $result;
	}

	/**
	 * Delete configuration value
	 *
	 * @param string $key Configuration key.
	 * @return bool True on success.
	 */
	public static function delete( $key ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = $wpdb->prefix . 'zap';

		$result = $wpdb->delete( $table, array( 'zap_key' => $key ), array( '%s' ) );
		return false !== $result;
	}

	/**
	 * Get all values in a category
	 *
	 * @param string $tag Category tag.
	 * @return array Associative array of key => value.
	 */
	public static function get_tag( $tag ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = $wpdb->prefix . 'zap';

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT zap_key, zap_value FROM {$table} WHERE zap_tag = %s",
			$tag
		) );

		if ( ! $rows ) {
			return array();
		}

		$results = array();
		foreach ( $rows as $row ) {
			$results[ $row->zap_key ] = maybe_unserialize( $row->zap_value );
		}

		return $results;
	}

	/**
	 * Get multiple values by keys
	 *
	 * @param array $keys Array of configuration keys.
	 * @return array Associative array of key => value.
	 */
	public static function get_many( $keys ) {
		if ( empty( $keys ) ) {
			return array();
		}

		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = $wpdb->prefix . 'zap';

		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT zap_key, zap_value FROM {$table} WHERE zap_key IN ($placeholders)",
			...$keys
		) );

		if ( ! $rows ) {
			return array();
		}

		$results = array();
		foreach ( $rows as $row ) {
			$results[ $row->zap_key ] = maybe_unserialize( $row->zap_value );
		}

		return $results;
	}

	/**
	 * Check if a key exists
	 *
	 * @param string $key Configuration key.
	 * @return bool True if exists.
	 */
	public static function exists( $key ) {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = $wpdb->prefix . 'zap';

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE zap_key = %s",
			$key
		) );

		return (int) $count > 0;
	}

	/**
	 * Get all configuration keys (useful for debugging)
	 *
	 * @return array Array of configuration keys grouped by tag.
	 */
	public static function get_all_keys() {
		$wpdb  = AA_Customers_DB_Connection::get_connection();
		$table = $wpdb->prefix . 'zap';

		$rows = $wpdb->get_results(
			"SELECT zap_key, zap_tag, zap_lock FROM {$table} ORDER BY zap_tag, zap_key",
			ARRAY_A
		);

		$grouped = array();
		foreach ( $rows as $row ) {
			$tag = $row['zap_tag'];
			if ( ! isset( $grouped[ $tag ] ) ) {
				$grouped[ $tag ] = array();
			}
			$grouped[ $tag ][] = array(
				'key'    => $row['zap_key'],
				'locked' => (bool) $row['zap_lock'],
			);
		}

		return $grouped;
	}
}
