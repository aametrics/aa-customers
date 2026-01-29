<?php
/**
 * Forms Repository
 *
 * Handles CRUD operations for data collection forms.
 *
 * @package AA_Customers
 * @subpackage Repositories
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Forms_Repository
 */
class AA_Customers_Forms_Repository {

	/**
	 * Database connection
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Forms table name
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Form fields table name
	 *
	 * @var string
	 */
	private $fields_table;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->wpdb         = AA_Customers_DB_Connection::get_connection();
		$this->table        = AA_Customers_Database::get_forms_table_name();
		$this->fields_table = AA_Customers_Database::get_form_fields_table_name();
	}

	/**
	 * Get all forms
	 *
	 * @param array $args Query arguments.
	 * @return array Array of form objects.
	 */
	public function get_all( $args = array() ) {
		$defaults = array(
			'status'     => '',
			'form_type'  => '',
			'product_id' => null,
			'limit'      => 50,
			'offset'     => 0,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['form_type'] ) ) {
			$where[]  = 'form_type = %s';
			$values[] = $args['form_type'];
		}

		if ( null !== $args['product_id'] ) {
			if ( empty( $args['product_id'] ) ) {
				$where[] = 'product_id IS NULL';
			} else {
				$where[]  = 'product_id = %d';
				$values[] = $args['product_id'];
			}
		}

		$where_clause = implode( ' AND ', $where );

		$allowed_orderby = array( 'id', 'name', 'created_at', 'updated_at', 'status' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'name';
		$order           = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$sql = "SELECT * FROM {$this->table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		if ( count( $values ) > 2 ) {
			$results = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $values ) );
		} else {
			$results = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $args['limit'], $args['offset'] ) );
		}

		return $results ?: array();
	}

	/**
	 * Get form by ID
	 *
	 * @param int $id Form ID.
	 * @return object|null Form object or null.
	 */
	public function get_by_id( $id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id )
		);
	}

	/**
	 * Get form by slug
	 *
	 * @param string $slug Form slug.
	 * @return object|null Form object or null.
	 */
	public function get_by_slug( $slug ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE slug = %s", $slug )
		);
	}

	/**
	 * Get form for a product
	 *
	 * @param int $product_id Product ID.
	 * @return object|null Form object or null.
	 */
	public function get_for_product( $product_id ) {
		// First try to get product-specific form.
		$form = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE product_id = %d AND status = 'active' ORDER BY version DESC LIMIT 1",
				$product_id
			)
		);

		if ( $form ) {
			return $form;
		}

		// Fall back to default form for the product type.
		$products_table = AA_Customers_Database::get_products_table_name();
		$product        = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT type FROM {$products_table} WHERE id = %d", $product_id )
		);

		if ( $product ) {
			return $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE product_id IS NULL AND form_type = %s AND status = 'active' ORDER BY version DESC LIMIT 1",
					$product->type
				)
			);
		}

		return null;
	}

	/**
	 * Create a form
	 *
	 * @param array $data Form data.
	 * @return int|false Form ID or false on failure.
	 */
	public function create( $data ) {
		$defaults = array(
			'name'        => '',
			'slug'        => '',
			'description' => '',
			'product_id'  => null,
			'form_type'   => 'general',
			'status'      => 'draft',
			'version'     => 1,
		);

		$data = wp_parse_args( $data, $defaults );

		// Generate slug if not provided.
		if ( empty( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		}

		// Ensure unique slug.
		$data['slug'] = $this->get_unique_slug( $data['slug'] );

		$result = $this->wpdb->insert(
			$this->table,
			array(
				'name'        => $data['name'],
				'slug'        => $data['slug'],
				'description' => $data['description'],
				'product_id'  => $data['product_id'] ?: null,
				'form_type'   => $data['form_type'],
				'status'      => $data['status'],
				'version'     => $data['version'],
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%d' )
		);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Update a form
	 *
	 * @param int   $id   Form ID.
	 * @param array $data Form data.
	 * @return bool True on success.
	 */
	public function update( $id, $data ) {
		$allowed = array( 'name', 'slug', 'description', 'product_id', 'form_type', 'status', 'version' );
		$update  = array();
		$formats = array();

		foreach ( $allowed as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$update[ $field ] = $data[ $field ];
				$formats[]        = in_array( $field, array( 'product_id', 'version' ), true ) ? '%d' : '%s';
			}
		}

		if ( empty( $update ) ) {
			return false;
		}

		return (bool) $this->wpdb->update( $this->table, $update, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Delete a form
	 *
	 * @param int $id Form ID.
	 * @return bool True on success.
	 */
	public function delete( $id ) {
		// Delete fields first.
		$this->wpdb->delete( $this->fields_table, array( 'form_id' => $id ), array( '%d' ) );

		// Delete form.
		return (bool) $this->wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Get unique slug
	 *
	 * @param string $slug  Base slug.
	 * @param int    $exclude_id Optional ID to exclude.
	 * @return string Unique slug.
	 */
	private function get_unique_slug( $slug, $exclude_id = 0 ) {
		$original = $slug;
		$counter  = 1;

		while ( true ) {
			$existing = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT id FROM {$this->table} WHERE slug = %s AND id != %d",
					$slug,
					$exclude_id
				)
			);

			if ( ! $existing ) {
				break;
			}

			$slug = $original . '-' . $counter;
			$counter++;
		}

		return $slug;
	}

	/**
	 * Get form fields
	 *
	 * @param int $form_id Form ID.
	 * @return array Array of field objects.
	 */
	public function get_fields( $form_id ) {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->fields_table} WHERE form_id = %d ORDER BY field_order ASC",
				$form_id
			)
		) ?: array();
	}

	/**
	 * Get field by ID
	 *
	 * @param int $id Field ID.
	 * @return object|null Field object or null.
	 */
	public function get_field_by_id( $id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->fields_table} WHERE id = %d", $id )
		);
	}

	/**
	 * Add a field to a form
	 *
	 * @param int   $form_id Form ID.
	 * @param array $data    Field data.
	 * @return int|false Field ID or false on failure.
	 */
	public function add_field( $form_id, $data ) {
		$defaults = array(
			'field_order'      => 0,
			'target_table'     => 'members',
			'target_column'    => '',
			'label'            => '',
			'display_type'     => 'text',
			'placeholder'      => '',
			'help_text'        => '',
			'required'         => false,
			'options_json'     => null,
			'validation_rules' => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// Get next field order if not specified.
		if ( empty( $data['field_order'] ) ) {
			$max_order = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT MAX(field_order) FROM {$this->fields_table} WHERE form_id = %d",
					$form_id
				)
			);
			$data['field_order'] = ( $max_order ?: 0 ) + 1;
		}

		$result = $this->wpdb->insert(
			$this->fields_table,
			array(
				'form_id'          => $form_id,
				'field_order'      => $data['field_order'],
				'target_table'     => $data['target_table'],
				'target_column'    => $data['target_column'],
				'label'            => $data['label'],
				'display_type'     => $data['display_type'],
				'placeholder'      => $data['placeholder'],
				'help_text'        => $data['help_text'],
				'required'         => $data['required'] ? 1 : 0,
				'options_json'     => $data['options_json'],
				'validation_rules' => $data['validation_rules'],
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Update a field
	 *
	 * @param int   $id   Field ID.
	 * @param array $data Field data.
	 * @return bool True on success.
	 */
	public function update_field( $id, $data ) {
		$allowed = array(
			'field_order', 'target_table', 'target_column', 'label',
			'display_type', 'placeholder', 'help_text', 'required',
			'options_json', 'validation_rules',
		);

		$update  = array();
		$formats = array();

		foreach ( $allowed as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$update[ $field ] = $data[ $field ];
				if ( in_array( $field, array( 'field_order', 'required' ), true ) ) {
					$formats[] = '%d';
				} else {
					$formats[] = '%s';
				}
			}
		}

		if ( empty( $update ) ) {
			return false;
		}

		return (bool) $this->wpdb->update( $this->fields_table, $update, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Delete a field
	 *
	 * @param int $id Field ID.
	 * @return bool True on success.
	 */
	public function delete_field( $id ) {
		return (bool) $this->wpdb->delete( $this->fields_table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Reorder fields
	 *
	 * @param int   $form_id   Form ID.
	 * @param array $field_ids Ordered array of field IDs.
	 * @return bool True on success.
	 */
	public function reorder_fields( $form_id, $field_ids ) {
		foreach ( $field_ids as $order => $field_id ) {
			$this->wpdb->update(
				$this->fields_table,
				array( 'field_order' => $order + 1 ),
				array( 'id' => $field_id, 'form_id' => $form_id ),
				array( '%d' ),
				array( '%d', '%d' )
			);
		}

		return true;
	}

	/**
	 * Duplicate a form (for versioning)
	 *
	 * @param int    $id       Form ID to duplicate.
	 * @param string $new_name Optional new name.
	 * @return int|false New form ID or false on failure.
	 */
	public function duplicate( $id, $new_name = '' ) {
		$form = $this->get_by_id( $id );

		if ( ! $form ) {
			return false;
		}

		$new_form_id = $this->create(
			array(
				'name'        => $new_name ?: $form->name . ' (Copy)',
				'slug'        => '',
				'description' => $form->description,
				'product_id'  => $form->product_id,
				'form_type'   => $form->form_type,
				'status'      => 'draft',
				'version'     => $form->version + 1,
			)
		);

		if ( ! $new_form_id ) {
			return false;
		}

		// Copy fields.
		$fields = $this->get_fields( $id );

		foreach ( $fields as $field ) {
			$this->add_field(
				$new_form_id,
				array(
					'field_order'      => $field->field_order,
					'target_table'     => $field->target_table,
					'target_column'    => $field->target_column,
					'label'            => $field->label,
					'display_type'     => $field->display_type,
					'placeholder'      => $field->placeholder,
					'help_text'        => $field->help_text,
					'required'         => $field->required,
					'options_json'     => $field->options_json,
					'validation_rules' => $field->validation_rules,
				)
			);
		}

		return $new_form_id;
	}

	/**
	 * Count forms by status
	 *
	 * @return array Status counts.
	 */
	public function count_by_status() {
		$results = $this->wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$this->table} GROUP BY status"
		);

		$counts = array(
			'active' => 0,
			'draft'  => 0,
		);

		foreach ( $results as $row ) {
			$counts[ $row->status ] = (int) $row->count;
		}

		return $counts;
	}

	/**
	 * Get available target columns for a table
	 *
	 * @param string $table_name Table name (without prefix).
	 * @return array Array of column info.
	 */
	public function get_available_columns( $table_name = 'members' ) {
		$full_table = $this->wpdb->prefix . $table_name;

		$columns = $this->wpdb->get_results( "DESCRIBE {$full_table}" );

		$available = array();

		// Exclude system columns that shouldn't be in forms.
		$excluded = array(
			'id', 'wp_user_id', 'stripe_customer_id', 'stripe_subscription_id',
			'xero_contact_id', 'created_at', 'updated_at',
		);

		foreach ( $columns as $col ) {
			if ( in_array( $col->Field, $excluded, true ) ) {
				continue;
			}

			$type = $this->map_db_type_to_display( $col->Type );

			$available[] = array(
				'column'       => $col->Field,
				'type'         => $col->Type,
				'display_type' => $type['display'],
				'options'      => $type['options'],
				'nullable'     => $col->Null === 'YES',
				'default'      => $col->Default,
			);
		}

		return $available;
	}

	/**
	 * Map database column type to form display type
	 *
	 * @param string $db_type Database column type.
	 * @return array Display type and options.
	 */
	private function map_db_type_to_display( $db_type ) {
		$type    = strtolower( $db_type );
		$options = array();

		// Check for ENUM.
		if ( strpos( $type, 'enum' ) === 0 ) {
			preg_match( "/enum\('(.*)'\)/", $type, $matches );
			if ( ! empty( $matches[1] ) ) {
				$options = explode( "','", $matches[1] );
			}
			return array( 'display' => 'dropdown', 'options' => $options );
		}

		// Check for BOOLEAN / TINYINT(1).
		if ( strpos( $type, 'tinyint(1)' ) !== false || $type === 'boolean' ) {
			return array( 'display' => 'checkbox', 'options' => array() );
		}

		// Check for TEXT.
		if ( strpos( $type, 'text' ) !== false ) {
			return array( 'display' => 'textarea', 'options' => array() );
		}

		// Check for DATE.
		if ( strpos( $type, 'date' ) !== false ) {
			return array( 'display' => 'date', 'options' => array() );
		}

		// Check for INT / DECIMAL.
		if ( strpos( $type, 'int' ) !== false || strpos( $type, 'decimal' ) !== false ) {
			return array( 'display' => 'number', 'options' => array() );
		}

		// Default to text.
		return array( 'display' => 'text', 'options' => array() );
	}
}
