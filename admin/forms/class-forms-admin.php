<?php
/**
 * Forms Admin
 *
 * Handles the Data Collection Forms admin interface:
 * - List all forms
 * - Add/Edit forms
 * - Manage form fields
 *
 * @package AA_Customers
 * @subpackage Admin
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Forms_Admin
 */
class AA_Customers_Forms_Admin extends AA_Customers_Base_Admin {

	/**
	 * Forms repository
	 *
	 * @var AA_Customers_Forms_Repository
	 */
	private $repository;

	/**
	 * Products repository
	 *
	 * @var AA_Customers_Products_Repository
	 */
	private $products_repository;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->repository          = new AA_Customers_Forms_Repository();
		$this->products_repository = new AA_Customers_Products_Repository();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'wp_ajax_aa_customers_save_field_order', array( $this, 'ajax_save_field_order' ) );
		add_action( 'wp_ajax_aa_customers_get_column_info', array( $this, 'ajax_get_column_info' ) );
	}

	/**
	 * Handle form submissions and actions
	 *
	 * @return void
	 */
	public function handle_actions() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'aa-customers-forms' ) {
			return;
		}

		if ( isset( $_POST['aa_customers_save_form'] ) ) {
			$this->handle_save_form();
		}

		if ( isset( $_POST['aa_customers_save_field'] ) ) {
			$this->handle_save_field();
		}

		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
			$this->handle_delete_form();
		}

		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_field' && isset( $_GET['field_id'] ) ) {
			$this->handle_delete_field();
		}

		if ( isset( $_GET['action'] ) && $_GET['action'] === 'duplicate' && isset( $_GET['id'] ) ) {
			$this->handle_duplicate_form();
		}
	}

	/**
	 * Render forms page (router)
	 *
	 * @return void
	 */
	public function render() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

		switch ( $action ) {
			case 'add':
				$this->render_form_editor();
				break;
			case 'edit':
				$this->render_form_editor();
				break;
			case 'fields':
				$this->render_field_manager();
				break;
			case 'add_field':
				$this->render_field_editor();
				break;
			case 'edit_field':
				$this->render_field_editor();
				break;
			default:
				$this->render_list();
				break;
		}
	}

	/**
	 * Render forms list
	 *
	 * @return void
	 */
	private function render_list() {
		$forms  = $this->repository->get_all();
		$counts = $this->repository->count_by_status();
		$total  = $counts['active'] + $counts['draft'];
		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'Data Collection Forms', 'aa-customers' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-forms&action=add' ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Add New Form', 'aa-customers' ); ?>
				</a>
			</h1>

			<?php $this->render_admin_notices(); ?>

			<p class="description"><?php esc_html_e( 'Create forms to collect data during the checkout process. Each form can be linked to a product or used as a default.', 'aa-customers' ); ?></p>

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-forms' ) ); ?>" class="current">All <span class="count">(<?php echo esc_html( $total ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-forms&status=active' ) ); ?>">Active <span class="count">(<?php echo esc_html( $counts['active'] ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-forms&status=draft' ) ); ?>">Draft <span class="count">(<?php echo esc_html( $counts['draft'] ); ?>)</span></a></li>
			</ul>

			<table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-name">Name</th>
						<th scope="col" class="manage-column column-type">Type</th>
						<th scope="col" class="manage-column column-product">Linked Product</th>
						<th scope="col" class="manage-column column-fields">Fields</th>
						<th scope="col" class="manage-column column-status">Status</th>
						<th scope="col" class="manage-column column-actions">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $forms ) ) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e( 'No forms found. Create your first form to get started.', 'aa-customers' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $forms as $form ) : ?>
							<?php
							$fields  = $this->repository->get_fields( $form->id );
							$product = $form->product_id ? $this->products_repository->get_by_id( $form->product_id ) : null;
							?>
							<tr>
								<td class="column-name">
									<strong>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-forms&action=edit&id=' . $form->id ) ); ?>">
											<?php echo esc_html( $form->name ); ?>
										</a>
									</strong>
									<div class="row-actions">
										<span class="edit">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-forms&action=edit&id=' . $form->id ) ); ?>">Edit</a> |
										</span>
										<span class="fields">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-forms&action=fields&id=' . $form->id ) ); ?>">Manage Fields</a> |
										</span>
										<span class="duplicate">
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=aa-customers-forms&action=duplicate&id=' . $form->id ), 'duplicate_form_' . $form->id ) ); ?>">Duplicate</a> |
										</span>
										<span class="delete">
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=aa-customers-forms&action=delete&id=' . $form->id ), 'delete_form_' . $form->id ) ); ?>" onclick="return confirm('Are you sure?')">Delete</a>
										</span>
									</div>
								</td>
								<td class="column-type">
									<?php echo esc_html( ucfirst( $form->form_type ) ); ?>
								</td>
								<td class="column-product">
									<?php if ( $product ) : ?>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-products&action=edit&id=' . $product->id ) ); ?>">
											<?php echo esc_html( $product->name ); ?>
										</a>
									<?php else : ?>
										<em>Default (all <?php echo esc_html( $form->form_type ); ?>s)</em>
									<?php endif; ?>
								</td>
								<td class="column-fields">
									<?php echo esc_html( count( $fields ) ); ?> fields
								</td>
								<td class="column-status">
									<?php echo $this->get_status_badge( $form->status ); ?>
								</td>
								<td class="column-actions">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-forms&action=fields&id=' . $form->id ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Manage Fields', 'aa-customers' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render form editor (add/edit)
	 *
	 * @return void
	 */
	private function render_form_editor() {
		$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$form = $id ? $this->repository->get_by_id( $id ) : null;

		$products = $this->products_repository->get_all( array( 'status' => 'active' ) );
		?>
		<div class="wrap">
			<h1><?php echo $form ? esc_html__( 'Edit Form', 'aa-customers' ) : esc_html__( 'Add New Form', 'aa-customers' ); ?></h1>

			<?php $this->render_admin_notices(); ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'aa_customers_form_nonce', '_wpnonce' ); ?>
				<input type="hidden" name="form_id" value="<?php echo esc_attr( $id ); ?>">

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="name"><?php esc_html_e( 'Form Name', 'aa-customers' ); ?> <span class="required">*</span></label>
						</th>
						<td>
							<input type="text" name="name" id="name" class="regular-text" required
								   value="<?php echo esc_attr( $form->name ?? '' ); ?>">
							<p class="description"><?php esc_html_e( 'Internal name for this form.', 'aa-customers' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="description"><?php esc_html_e( 'Description', 'aa-customers' ); ?></label>
						</th>
						<td>
							<textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea( $form->description ?? '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Optional description shown to users above the form.', 'aa-customers' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="form_type"><?php esc_html_e( 'Form Type', 'aa-customers' ); ?></label>
						</th>
						<td>
							<select name="form_type" id="form_type">
								<option value="membership" <?php selected( $form->form_type ?? '', 'membership' ); ?>><?php esc_html_e( 'Membership', 'aa-customers' ); ?></option>
								<option value="event" <?php selected( $form->form_type ?? '', 'event' ); ?>><?php esc_html_e( 'Event', 'aa-customers' ); ?></option>
								<option value="general" <?php selected( $form->form_type ?? 'general', 'general' ); ?>><?php esc_html_e( 'General', 'aa-customers' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'What type of product is this form for?', 'aa-customers' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="product_id"><?php esc_html_e( 'Linked Product', 'aa-customers' ); ?></label>
						</th>
						<td>
							<select name="product_id" id="product_id">
								<option value=""><?php esc_html_e( '— Default (use for all products of this type)', 'aa-customers' ); ?></option>
								<?php foreach ( $products as $product ) : ?>
									<option value="<?php echo esc_attr( $product->id ); ?>" <?php selected( $form->product_id ?? '', $product->id ); ?>>
										<?php echo esc_html( $product->name ); ?> (<?php echo esc_html( ucfirst( $product->type ) ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Optionally link this form to a specific product.', 'aa-customers' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="status"><?php esc_html_e( 'Status', 'aa-customers' ); ?></label>
						</th>
						<td>
							<select name="status" id="status">
								<option value="draft" <?php selected( $form->status ?? 'draft', 'draft' ); ?>><?php esc_html_e( 'Draft', 'aa-customers' ); ?></option>
								<option value="active" <?php selected( $form->status ?? '', 'active' ); ?>><?php esc_html_e( 'Active', 'aa-customers' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="aa_customers_save_form" class="button button-primary" value="<?php esc_attr_e( 'Save Form', 'aa-customers' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-forms' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'aa-customers' ); ?></a>
					<?php if ( $form ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-forms&action=fields&id=' . $form->id ) ); ?>" class="button button-secondary" style="margin-left:20px;">
							<?php esc_html_e( 'Manage Fields →', 'aa-customers' ); ?>
						</a>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render field manager for a form
	 *
	 * @return void
	 */
	private function render_field_manager() {
		$form_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$form    = $this->repository->get_by_id( $form_id );

		if ( ! $form ) {
			wp_die( 'Form not found.' );
		}

		$fields           = $this->repository->get_fields( $form_id );
		$available_columns = $this->repository->get_available_columns( 'members' );
		?>
		<div class="wrap">
			<h1>
				<?php printf( esc_html__( 'Manage Fields: %s', 'aa-customers' ), esc_html( $form->name ) ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-forms&action=add_field&form_id=' . $form_id ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Add Field', 'aa-customers' ); ?>
				</a>
			</h1>

			<?php $this->render_admin_notices(); ?>

			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-forms&action=edit&id=' . $form_id ) ); ?>">← Back to Form Settings</a>
			</p>

			<?php if ( empty( $fields ) ) : ?>
				<div class="notice notice-info">
					<p><?php esc_html_e( 'This form has no fields yet. Click "Add Field" to start building your form.', 'aa-customers' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped" id="form-fields-table">
					<thead>
						<tr>
							<th style="width:30px;"></th>
							<th>Label</th>
							<th>Maps To</th>
							<th>Type</th>
							<th>Required</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody id="sortable-fields">
						<?php foreach ( $fields as $field ) : ?>
							<tr data-field-id="<?php echo esc_attr( $field->id ); ?>">
								<td class="drag-handle" style="cursor:move;">☰</td>
								<td>
									<strong><?php echo esc_html( $field->label ); ?></strong>
									<?php if ( $field->help_text ) : ?>
										<br><small class="description"><?php echo esc_html( $field->help_text ); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<code><?php echo esc_html( $field->target_table . '.' . $field->target_column ); ?></code>
								</td>
								<td><?php echo esc_html( ucfirst( $field->display_type ) ); ?></td>
								<td><?php echo $field->required ? '✓ Yes' : '—'; ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-forms&action=edit_field&form_id=' . $form_id . '&field_id=' . $field->id ) ); ?>">Edit</a> |
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=aa-customers-forms&action=delete_field&form_id=' . $form_id . '&field_id=' . $field->id ), 'delete_field_' . $field->id ) ); ?>" onclick="return confirm('Delete this field?')">Delete</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<script>
				jQuery(function($) {
					$('#sortable-fields').sortable({
						handle: '.drag-handle',
						update: function(event, ui) {
							var order = [];
							$('#sortable-fields tr').each(function() {
								order.push($(this).data('field-id'));
							});
							$.post(ajaxurl, {
								action: 'aa_customers_save_field_order',
								form_id: <?php echo esc_js( $form_id ); ?>,
								order: order,
								_wpnonce: '<?php echo wp_create_nonce( 'aa_customers_field_order' ); ?>'
							});
						}
					});
				});
				</script>
			<?php endif; ?>

			<h3 style="margin-top:30px;"><?php esc_html_e( 'Available Fields', 'aa-customers' ); ?></h3>
			<p class="description"><?php esc_html_e( 'These database columns can be added to your form:', 'aa-customers' ); ?></p>

			<table class="wp-list-table widefat fixed striped" style="max-width:800px;">
				<thead>
					<tr>
						<th>Column</th>
						<th>Suggested Type</th>
						<th>Add</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$used_columns = wp_list_pluck( $fields, 'target_column' );
					foreach ( $available_columns as $col ) :
						$is_used = in_array( $col['column'], $used_columns, true );
						?>
						<tr<?php echo $is_used ? ' style="opacity:0.5;"' : ''; ?>>
							<td><code><?php echo esc_html( $col['column'] ); ?></code></td>
							<td><?php echo esc_html( ucfirst( $col['display_type'] ) ); ?></td>
							<td>
								<?php if ( $is_used ) : ?>
									<em>Already added</em>
								<?php else : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-forms&action=add_field&form_id=' . $form_id . '&column=' . $col['column'] ) ); ?>" class="button button-small">Add</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render field editor (add/edit)
	 *
	 * @return void
	 */
	private function render_field_editor() {
		$form_id  = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$field_id = isset( $_GET['field_id'] ) ? absint( $_GET['field_id'] ) : 0;
		$column   = isset( $_GET['column'] ) ? sanitize_text_field( $_GET['column'] ) : '';

		$form  = $this->repository->get_by_id( $form_id );
		$field = $field_id ? $this->repository->get_field_by_id( $field_id ) : null;

		if ( ! $form ) {
			wp_die( 'Form not found.' );
		}

		$available_columns = $this->repository->get_available_columns( 'members' );

		// Pre-fill from column if adding new.
		$prefill = null;
		if ( $column && ! $field ) {
			foreach ( $available_columns as $col ) {
				if ( $col['column'] === $column ) {
					$prefill = $col;
					break;
				}
			}
		}
		?>
		<div class="wrap">
			<h1><?php echo $field ? esc_html__( 'Edit Field', 'aa-customers' ) : esc_html__( 'Add Field', 'aa-customers' ); ?></h1>

			<?php $this->render_admin_notices(); ?>

			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-forms&action=fields&id=' . $form_id ) ); ?>">← Back to <?php echo esc_html( $form->name ); ?></a>
			</p>

			<form method="post" action="">
				<?php wp_nonce_field( 'aa_customers_field_nonce', '_wpnonce' ); ?>
				<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
				<input type="hidden" name="field_id" value="<?php echo esc_attr( $field_id ); ?>">

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="target_column"><?php esc_html_e( 'Target Field', 'aa-customers' ); ?> <span class="required">*</span></label>
						</th>
						<td>
							<select name="target_column" id="target_column" required>
								<option value=""><?php esc_html_e( '— Select a field —', 'aa-customers' ); ?></option>
								<?php foreach ( $available_columns as $col ) : ?>
									<option value="<?php echo esc_attr( $col['column'] ); ?>"
											data-type="<?php echo esc_attr( $col['display_type'] ); ?>"
											data-options="<?php echo esc_attr( wp_json_encode( $col['options'] ) ); ?>"
											<?php selected( $field->target_column ?? $prefill['column'] ?? '', $col['column'] ); ?>>
										<?php echo esc_html( $col['column'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<input type="hidden" name="target_table" value="members">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="label"><?php esc_html_e( 'Field Label', 'aa-customers' ); ?> <span class="required">*</span></label>
						</th>
						<td>
							<input type="text" name="label" id="label" class="regular-text" required
								   value="<?php echo esc_attr( $field->label ?? $this->column_to_label( $prefill['column'] ?? '' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Label shown to users.', 'aa-customers' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="display_type"><?php esc_html_e( 'Display Type', 'aa-customers' ); ?></label>
						</th>
						<td>
							<select name="display_type" id="display_type">
								<option value="text" <?php selected( $field->display_type ?? $prefill['display_type'] ?? 'text', 'text' ); ?>>Text Input</option>
								<option value="textarea" <?php selected( $field->display_type ?? '', 'textarea' ); ?>>Textarea</option>
								<option value="email" <?php selected( $field->display_type ?? '', 'email' ); ?>>Email</option>
								<option value="tel" <?php selected( $field->display_type ?? '', 'tel' ); ?>>Phone</option>
								<option value="number" <?php selected( $field->display_type ?? '', 'number' ); ?>>Number</option>
								<option value="date" <?php selected( $field->display_type ?? '', 'date' ); ?>>Date</option>
								<option value="dropdown" <?php selected( $field->display_type ?? '', 'dropdown' ); ?>>Dropdown</option>
								<option value="radio" <?php selected( $field->display_type ?? '', 'radio' ); ?>>Radio Buttons</option>
								<option value="checkbox" <?php selected( $field->display_type ?? '', 'checkbox' ); ?>>Checkbox</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="placeholder"><?php esc_html_e( 'Placeholder', 'aa-customers' ); ?></label>
						</th>
						<td>
							<input type="text" name="placeholder" id="placeholder" class="regular-text"
								   value="<?php echo esc_attr( $field->placeholder ?? '' ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="help_text"><?php esc_html_e( 'Help Text', 'aa-customers' ); ?></label>
						</th>
						<td>
							<textarea name="help_text" id="help_text" rows="2" class="large-text"><?php echo esc_textarea( $field->help_text ?? '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Optional instructions shown below the field.', 'aa-customers' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Required', 'aa-customers' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="required" value="1" <?php checked( $field->required ?? false ); ?>>
								<?php esc_html_e( 'This field is required', 'aa-customers' ); ?>
							</label>
						</td>
					</tr>

					<tr id="options-row" style="<?php echo in_array( $field->display_type ?? '', array( 'dropdown', 'radio' ), true ) ? '' : 'display:none;'; ?>">
						<th scope="row">
							<label for="options_json"><?php esc_html_e( 'Options', 'aa-customers' ); ?></label>
						</th>
						<td>
							<textarea name="options_json" id="options_json" rows="4" class="large-text"><?php echo esc_textarea( $field->options_json ?? '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One option per line. For ENUM fields, options are auto-detected from the database.', 'aa-customers' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="aa_customers_save_field" class="button button-primary" value="<?php esc_attr_e( 'Save Field', 'aa-customers' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-forms&action=fields&id=' . $form_id ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'aa-customers' ); ?></a>
				</p>
			</form>

			<script>
			jQuery(function($) {
				$('#display_type').on('change', function() {
					var type = $(this).val();
					if (type === 'dropdown' || type === 'radio') {
						$('#options-row').show();
					} else {
						$('#options-row').hide();
					}
				});
			});
			</script>
		</div>
		<?php
	}

	/**
	 * Handle save form
	 *
	 * @return void
	 */
	private function handle_save_form() {
		if ( ! $this->verify_nonce( '_wpnonce', 'aa_customers_form_nonce' ) ) {
			return;
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

		$data = array(
			'name'        => sanitize_text_field( $_POST['name'] ?? '' ),
			'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
			'form_type'   => sanitize_text_field( $_POST['form_type'] ?? 'general' ),
			'product_id'  => ! empty( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : null,
			'status'      => sanitize_text_field( $_POST['status'] ?? 'draft' ),
		);

		if ( $form_id ) {
			$this->repository->update( $form_id, $data );
			$this->redirect_with_message( 'aa-customers-forms', 'updated', array( 'action' => 'edit', 'id' => $form_id ) );
		} else {
			$new_id = $this->repository->create( $data );
			if ( $new_id ) {
				$this->redirect_with_message( 'aa-customers-forms', 'created', array( 'action' => 'fields', 'id' => $new_id ) );
			} else {
				$this->redirect_with_message( 'aa-customers-forms', 'error', array( 'action' => 'add' ) );
			}
		}
	}

	/**
	 * Handle save field
	 *
	 * @return void
	 */
	private function handle_save_field() {
		if ( ! $this->verify_nonce( '_wpnonce', 'aa_customers_field_nonce' ) ) {
			return;
		}

		$form_id  = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$field_id = isset( $_POST['field_id'] ) ? absint( $_POST['field_id'] ) : 0;

		$data = array(
			'target_table'  => sanitize_text_field( $_POST['target_table'] ?? 'members' ),
			'target_column' => sanitize_text_field( $_POST['target_column'] ?? '' ),
			'label'         => sanitize_text_field( $_POST['label'] ?? '' ),
			'display_type'  => sanitize_text_field( $_POST['display_type'] ?? 'text' ),
			'placeholder'   => sanitize_text_field( $_POST['placeholder'] ?? '' ),
			'help_text'     => sanitize_textarea_field( $_POST['help_text'] ?? '' ),
			'required'      => isset( $_POST['required'] ) ? 1 : 0,
			'options_json'  => sanitize_textarea_field( $_POST['options_json'] ?? '' ),
		);

		if ( $field_id ) {
			$this->repository->update_field( $field_id, $data );
		} else {
			$this->repository->add_field( $form_id, $data );
		}

		$this->redirect_with_message( 'aa-customers-forms', 'saved', array( 'action' => 'fields', 'id' => $form_id ) );
	}

	/**
	 * Handle delete form
	 *
	 * @return void
	 */
	private function handle_delete_form() {
		$id = absint( $_GET['id'] );

		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'delete_form_' . $id ) ) {
			wp_die( 'Security check failed.' );
		}

		$this->repository->delete( $id );
		$this->redirect_with_message( 'aa-customers-forms', 'deleted' );
	}

	/**
	 * Handle delete field
	 *
	 * @return void
	 */
	private function handle_delete_field() {
		$form_id  = absint( $_GET['form_id'] ?? 0 );
		$field_id = absint( $_GET['field_id'] );

		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'delete_field_' . $field_id ) ) {
			wp_die( 'Security check failed.' );
		}

		$this->repository->delete_field( $field_id );
		$this->redirect_with_message( 'aa-customers-forms', 'deleted', array( 'action' => 'fields', 'id' => $form_id ) );
	}

	/**
	 * Handle duplicate form
	 *
	 * @return void
	 */
	private function handle_duplicate_form() {
		$id = absint( $_GET['id'] );

		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'duplicate_form_' . $id ) ) {
			wp_die( 'Security check failed.' );
		}

		$new_id = $this->repository->duplicate( $id );

		if ( $new_id ) {
			$this->redirect_with_message( 'aa-customers-forms', 'duplicated', array( 'action' => 'edit', 'id' => $new_id ) );
		} else {
			$this->redirect_with_message( 'aa-customers-forms', 'error' );
		}
	}

	/**
	 * AJAX: Save field order
	 *
	 * @return void
	 */
	public function ajax_save_field_order() {
		check_ajax_referer( 'aa_customers_field_order', '_wpnonce' );

		$form_id = absint( $_POST['form_id'] ?? 0 );
		$order   = isset( $_POST['order'] ) ? array_map( 'absint', $_POST['order'] ) : array();

		if ( $form_id && ! empty( $order ) ) {
			$this->repository->reorder_fields( $form_id, $order );
			wp_send_json_success();
		}

		wp_send_json_error();
	}

	/**
	 * AJAX: Get column info
	 *
	 * @return void
	 */
	public function ajax_get_column_info() {
		$column  = sanitize_text_field( $_POST['column'] ?? '' );
		$columns = $this->repository->get_available_columns( 'members' );

		foreach ( $columns as $col ) {
			if ( $col['column'] === $column ) {
				wp_send_json_success( $col );
			}
		}

		wp_send_json_error();
	}

	/**
	 * Convert column name to human-readable label
	 *
	 * @param string $column Column name.
	 * @return string Label.
	 */
	private function column_to_label( $column ) {
		if ( empty( $column ) ) {
			return '';
		}

		$label = str_replace( '_', ' ', $column );
		$label = ucwords( $label );

		return $label;
	}
}
