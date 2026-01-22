<?php
/**
 * Products Admin
 *
 * Handles the Products admin interface:
 * - List all products (memberships and events)
 * - Add new product
 * - Edit product
 *
 * @package AA_Customers
 * @subpackage Admin
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Products_Admin
 */
class AA_Customers_Products_Admin extends AA_Customers_Base_Admin {

	/**
	 * Products repository
	 *
	 * @var AA_Customers_Products_Repository
	 */
	private $repository;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->repository = new AA_Customers_Products_Repository();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Handle form submissions and actions
	 *
	 * @return void
	 */
	public function handle_actions() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'aa-customers-products' ) {
			return;
		}

		if ( isset( $_POST['aa_customers_save_product'] ) ) {
			$this->handle_save_product();
		}

		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
			$this->handle_delete_product();
		}
	}

	/**
	 * Render products page (router)
	 *
	 * @return void
	 */
	public function render() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

		switch ( $action ) {
			case 'add':
				$this->render_add_form();
				break;
			case 'edit':
				$this->render_edit_form();
				break;
			default:
				$this->render_list();
				break;
		}
	}

	/**
	 * Render products list
	 *
	 * @return void
	 */
	private function render_list() {
		$type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';

		$products = $this->repository->get_all( array(
			'type'    => $type,
			'orderby' => 'name',
			'order'   => 'ASC',
		) );

		$all_products = $this->repository->get_all();
		$memberships  = array_filter( $all_products, fn( $p ) => $p->type === 'membership' );
		$events       = array_filter( $all_products, fn( $p ) => $p->type === 'event' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Products</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-products&action=add' ) ); ?>" class="page-title-action">Add New</a>
			<hr class="wp-header-end">

			<?php $this->render_admin_notices(); ?>

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-products' ) ); ?>" <?php echo empty( $type ) ? 'class="current"' : ''; ?>>All <span class="count">(<?php echo count( $all_products ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-products&type=membership' ) ); ?>" <?php echo $type === 'membership' ? 'class="current"' : ''; ?>>Memberships <span class="count">(<?php echo count( $memberships ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-products&type=event' ) ); ?>" <?php echo $type === 'event' ? 'class="current"' : ''; ?>>Events <span class="count">(<?php echo count( $events ); ?>)</span></a></li>
			</ul>

			<table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-name column-primary">Name</th>
						<th scope="col" class="manage-column column-type">Type</th>
						<th scope="col" class="manage-column column-price">Price</th>
						<th scope="col" class="manage-column column-member-price">Member Price</th>
						<th scope="col" class="manage-column column-recurring">Recurring</th>
						<th scope="col" class="manage-column column-status">Status</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $products ) ) : ?>
						<tr>
							<td colspan="6">No products found.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $products as $product ) : ?>
							<tr>
								<td class="column-name column-primary" data-colname="Name">
									<strong>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-products&action=edit&id=' . $product->id ) ); ?>">
											<?php echo esc_html( $product->name ); ?>
										</a>
									</strong>
									<div class="row-actions">
										<span class="edit">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-products&action=edit&id=' . $product->id ) ); ?>">Edit</a> |
										</span>
										<span class="trash">
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=aa-customers-products&action=delete&id=' . $product->id ), 'delete_product_' . $product->id ) ); ?>" class="submitdelete" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
										</span>
									</div>
								</td>
								<td class="column-type" data-colname="Type">
									<?php echo esc_html( ucfirst( $product->type ) ); ?>
								</td>
								<td class="column-price" data-colname="Price">
									£<?php echo esc_html( number_format( $product->price, 2 ) ); ?>
								</td>
								<td class="column-member-price" data-colname="Member Price">
									<?php echo $product->member_price !== null ? '£' . esc_html( number_format( $product->member_price, 2 ) ) : '—'; ?>
								</td>
								<td class="column-recurring" data-colname="Recurring">
									<?php if ( $product->is_recurring ) : ?>
										<span class="dashicons dashicons-yes" style="color:green;"></span>
										<?php echo esc_html( ucfirst( $product->recurring_interval ) . 'ly' ); ?>
									<?php else : ?>
										<span class="dashicons dashicons-no-alt" style="color:#999;"></span>
									<?php endif; ?>
								</td>
								<td class="column-status" data-colname="Status">
									<?php echo $this->get_status_badge( $product->status ); ?>
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
	 * Render add product form
	 *
	 * @return void
	 */
	private function render_add_form() {
		$this->render_product_form( null );
	}

	/**
	 * Render edit product form
	 *
	 * @return void
	 */
	private function render_edit_form() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$product = $this->repository->get_by_id( $id );

		if ( ! $product ) {
			wp_die( 'Product not found.' );
		}

		$this->render_product_form( $product );
	}

	/**
	 * Render product form (add/edit)
	 *
	 * @param object|null $product Product object or null for new.
	 * @return void
	 */
	private function render_product_form( $product ) {
		$is_edit = ! is_null( $product );
		$title   = $is_edit ? 'Edit Product' : 'Add New Product';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>

			<?php $this->render_admin_notices(); ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'aa_customers_save_product', 'aa_customers_nonce' ); ?>
				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->id ); ?>">
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="type">Type</label></th>
						<td>
							<select name="type" id="type" required <?php echo $is_edit ? 'disabled' : ''; ?>>
								<option value="membership" <?php selected( $product->type ?? '', 'membership' ); ?>>Membership</option>
								<option value="event" <?php selected( $product->type ?? '', 'event' ); ?>>Event</option>
							</select>
							<?php if ( $is_edit ) : ?>
								<input type="hidden" name="type" value="<?php echo esc_attr( $product->type ); ?>">
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="name">Name</label></th>
						<td>
							<input type="text" name="name" id="name" class="regular-text" value="<?php echo esc_attr( $product->name ?? '' ); ?>" required>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="slug">Slug</label></th>
						<td>
							<input type="text" name="slug" id="slug" class="regular-text" value="<?php echo esc_attr( $product->slug ?? '' ); ?>">
							<p class="description">Leave empty to auto-generate from name.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="description">Description</label></th>
						<td>
							<textarea name="description" id="description" rows="5" class="large-text"><?php echo esc_textarea( $product->description ?? '' ); ?></textarea>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="price">Price (£)</label></th>
						<td>
							<input type="number" name="price" id="price" step="0.01" min="0" value="<?php echo esc_attr( $product->price ?? '0.00' ); ?>" required>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="member_price">Member Price (£)</label></th>
						<td>
							<input type="number" name="member_price" id="member_price" step="0.01" min="0" value="<?php echo esc_attr( $product->member_price ?? '' ); ?>">
							<p class="description">Leave empty for no member discount. Only applies to events.</p>
						</td>
					</tr>

					<tr class="membership-only" style="<?php echo ( $product->type ?? 'membership' ) !== 'membership' ? 'display:none;' : ''; ?>">
						<th scope="row"><label for="is_recurring">Recurring</label></th>
						<td>
							<label>
								<input type="checkbox" name="is_recurring" id="is_recurring" value="1" <?php checked( $product->is_recurring ?? 1, 1 ); ?>>
								This is a recurring subscription
							</label>
						</td>
					</tr>

					<tr class="membership-only" style="<?php echo ( $product->type ?? 'membership' ) !== 'membership' ? 'display:none;' : ''; ?>">
						<th scope="row"><label for="recurring_interval">Billing Interval</label></th>
						<td>
							<select name="recurring_interval" id="recurring_interval">
								<option value="year" <?php selected( $product->recurring_interval ?? 'year', 'year' ); ?>>Yearly</option>
								<option value="month" <?php selected( $product->recurring_interval ?? '', 'month' ); ?>>Monthly</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="allow_donation">Donations</label></th>
						<td>
							<label>
								<input type="checkbox" name="allow_donation" id="allow_donation" value="1" <?php checked( $product->allow_donation ?? 1, 1 ); ?>>
								Allow optional donation at checkout
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="status">Status</label></th>
						<td>
							<select name="status" id="status">
								<option value="draft" <?php selected( $product->status ?? 'draft', 'draft' ); ?>>Draft</option>
								<option value="active" <?php selected( $product->status ?? '', 'active' ); ?>>Active</option>
								<option value="archived" <?php selected( $product->status ?? '', 'archived' ); ?>>Archived</option>
							</select>
						</td>
					</tr>
				</table>

				<h2>Stripe Integration</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="stripe_product_id">Stripe Product ID</label></th>
						<td>
							<input type="text" name="stripe_product_id" id="stripe_product_id" class="regular-text" value="<?php echo esc_attr( $product->stripe_product_id ?? '' ); ?>">
							<p class="description">e.g., prod_xxxxx</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="stripe_price_id">Stripe Price ID</label></th>
						<td>
							<input type="text" name="stripe_price_id" id="stripe_price_id" class="regular-text" value="<?php echo esc_attr( $product->stripe_price_id ?? '' ); ?>">
							<p class="description">e.g., price_xxxxx</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="aa_customers_save_product" class="button button-primary" value="<?php echo $is_edit ? 'Update Product' : 'Add Product'; ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-products' ) ); ?>" class="button">Cancel</a>
				</p>
			</form>
		</div>

		<script>
		jQuery(function($) {
			$('#type').on('change', function() {
				if ($(this).val() === 'membership') {
					$('.membership-only').show();
				} else {
					$('.membership-only').hide();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle save product
	 *
	 * @return void
	 */
	private function handle_save_product() {
		if ( ! $this->verify_nonce( 'aa_customers_nonce', 'aa_customers_save_product' ) ) {
			return;
		}

		$is_edit    = isset( $_POST['product_id'] ) && absint( $_POST['product_id'] ) > 0;
		$product_id = $is_edit ? absint( $_POST['product_id'] ) : 0;

		$data = array(
			'type'               => sanitize_text_field( $_POST['type'] ?? 'membership' ),
			'name'               => sanitize_text_field( $_POST['name'] ?? '' ),
			'slug'               => sanitize_title( $_POST['slug'] ?? '' ),
			'description'        => wp_kses_post( $_POST['description'] ?? '' ),
			'price'              => floatval( $_POST['price'] ?? 0 ),
			'member_price'       => $_POST['member_price'] !== '' ? floatval( $_POST['member_price'] ) : null,
			'stripe_product_id'  => sanitize_text_field( $_POST['stripe_product_id'] ?? '' ),
			'stripe_price_id'    => sanitize_text_field( $_POST['stripe_price_id'] ?? '' ),
			'is_recurring'       => isset( $_POST['is_recurring'] ) ? 1 : 0,
			'recurring_interval' => sanitize_text_field( $_POST['recurring_interval'] ?? 'year' ),
			'allow_donation'     => isset( $_POST['allow_donation'] ) ? 1 : 0,
			'status'             => sanitize_text_field( $_POST['status'] ?? 'draft' ),
		);

		if ( $is_edit ) {
			$result = $this->repository->update( $product_id, $data );
			$message = $result ? 'Product updated successfully.' : 'Failed to update product.';
			$type = $result ? 'success' : 'error';
		} else {
			$product_id = $this->repository->create( $data );
			$message = $product_id ? 'Product created successfully.' : 'Failed to create product.';
			$type = $product_id ? 'success' : 'error';
		}

		if ( $type === 'success' ) {
			$extra_args = $product_id ? array( 'action' => 'edit', 'id' => $product_id ) : array();
			$this->redirect_with_message( 'aa-customers-products', 'saved', $extra_args );
		} else {
			$this->redirect_with_error( 'aa-customers-products', 'save_failed' );
		}
	}

	/**
	 * Handle delete product
	 *
	 * @return void
	 */
	private function handle_delete_product() {
		$id = absint( $_GET['id'] ?? 0 );

		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'delete_product_' . $id ) ) {
			wp_die( 'Security check failed.' );
		}

		$result = $this->repository->delete( $id );

		$this->redirect_with_message(
			admin_url( 'admin.php?page=aa-customers-products' ),
			$result ? 'Product deleted successfully.' : 'Failed to delete product.',
			$result ? 'success' : 'error'
		);
	}

	/**
	 * Get status badge HTML
	 *
	 * @param string $status Status string.
	 * @return string HTML badge.
	 */
	private function get_status_badge( $status ) {
		$badges = array(
			'active'   => '<span class="aa-customers-badge badge-active">Active</span>',
			'draft'    => '<span class="aa-customers-badge badge-pending">Draft</span>',
			'archived' => '<span class="aa-customers-badge badge-cancelled">Archived</span>',
		);

		return $badges[ $status ] ?? '<span class="aa-customers-badge">' . esc_html( ucfirst( $status ) ) . '</span>';
	}
}
