<?php
/**
 * Members Admin
 *
 * Handles the Members admin interface:
 * - List all members
 * - Add new member
 * - Edit member
 * - View member details
 *
 * @package AA_Customers
 * @subpackage Admin
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Members_Admin
 */
class AA_Customers_Members_Admin extends AA_Customers_Base_Admin {

	/**
	 * Members repository
	 *
	 * @var AA_Customers_Members_Repository
	 */
	private $repository;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->repository = new AA_Customers_Members_Repository();
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
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'aa-customers-members' ) {
			return;
		}

		// Handle save member.
		if ( isset( $_POST['aa_customers_save_member'] ) ) {
			$this->handle_save_member();
		}

		// Handle delete member.
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
			$this->handle_delete_member();
		}
	}

	/**
	 * Render members page (router)
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
			case 'view':
				$this->render_view();
				break;
			default:
				$this->render_list();
				break;
		}
	}

	/**
	 * Render members list
	 *
	 * @return void
	 */
	private function render_list() {
		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$paged  = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page = 20;

		$members = $this->repository->get_all( array(
			'search'  => $search,
			'status'  => $status,
			'limit'   => $per_page,
			'offset'  => ( $paged - 1 ) * $per_page,
			'orderby' => 'full_name',
			'order'   => 'ASC',
		) );

		$counts = $this->repository->count_by_status();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Members</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-members&action=add' ) ); ?>" class="page-title-action">Add New</a>
			<hr class="wp-header-end">

			<?php $this->render_admin_notices(); ?>

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-members' ) ); ?>" <?php echo empty( $status ) ? 'class="current"' : ''; ?>>All <span class="count">(<?php echo esc_html( $counts['total'] ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-members&status=active' ) ); ?>" <?php echo $status === 'active' ? 'class="current"' : ''; ?>>Active <span class="count">(<?php echo esc_html( $counts['active'] ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-members&status=pending' ) ); ?>" <?php echo $status === 'pending' ? 'class="current"' : ''; ?>>Pending <span class="count">(<?php echo esc_html( $counts['pending'] ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-members&status=expired' ) ); ?>" <?php echo $status === 'expired' ? 'class="current"' : ''; ?>>Expired <span class="count">(<?php echo esc_html( $counts['expired'] ); ?>)</span></a></li>
			</ul>

			<form method="get">
				<input type="hidden" name="page" value="aa-customers-members">
				<?php if ( $status ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
				<?php endif; ?>
				<p class="search-box">
					<label class="screen-reader-text" for="member-search-input">Search Members:</label>
					<input type="search" id="member-search-input" name="s" value="<?php echo esc_attr( $search ); ?>">
					<input type="submit" id="search-submit" class="button" value="Search Members">
				</p>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-name column-primary">Name</th>
						<th scope="col" class="manage-column column-email">Email</th>
						<th scope="col" class="manage-column column-status">Status</th>
						<th scope="col" class="manage-column column-expiry">Expiry</th>
						<th scope="col" class="manage-column column-directory">Directory</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $members ) ) : ?>
						<tr>
							<td colspan="5">No members found.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $members as $member ) : ?>
							<tr>
								<td class="column-name column-primary" data-colname="Name">
									<strong>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-members&action=edit&id=' . $member->id ) ); ?>">
											<?php echo esc_html( $member->full_name ); ?>
										</a>
									</strong>
									<div class="row-actions">
										<span class="edit">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-members&action=edit&id=' . $member->id ) ); ?>">Edit</a> |
										</span>
										<span class="view">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-members&action=view&id=' . $member->id ) ); ?>">View</a> |
										</span>
										<span class="trash">
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=aa-customers-members&action=delete&id=' . $member->id ), 'delete_member_' . $member->id ) ); ?>" class="submitdelete" onclick="return confirm('Are you sure you want to delete this member?');">Delete</a>
										</span>
									</div>
								</td>
								<td class="column-email" data-colname="Email">
									<a href="mailto:<?php echo esc_attr( $member->email ); ?>"><?php echo esc_html( $member->email ); ?></a>
								</td>
								<td class="column-status" data-colname="Status">
									<?php echo $this->get_status_badge( $member->membership_status ); ?>
								</td>
								<td class="column-expiry" data-colname="Expiry">
									<?php echo $member->membership_expiry ? esc_html( date( 'j M Y', strtotime( $member->membership_expiry ) ) ) : '—'; ?>
								</td>
								<td class="column-directory" data-colname="Directory">
									<?php echo $member->show_in_directory ? '<span class="dashicons dashicons-yes" style="color:green;"></span>' : '<span class="dashicons dashicons-no-alt" style="color:#999;"></span>'; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php $this->render_pagination( $counts['total'], $per_page, $paged ); ?>
		</div>
		<?php
	}

	/**
	 * Render add member form
	 *
	 * @return void
	 */
	private function render_add_form() {
		$this->render_member_form( null );
	}

	/**
	 * Render edit member form
	 *
	 * @return void
	 */
	private function render_edit_form() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$member = $this->repository->get_by_id( $id );

		if ( ! $member ) {
			wp_die( 'Member not found.' );
		}

		$this->render_member_form( $member );
	}

	/**
	 * Render member form (add/edit)
	 *
	 * @param object|null $member Member object or null for new.
	 * @return void
	 */
	private function render_member_form( $member ) {
		$is_edit = ! is_null( $member );
		$title   = $is_edit ? 'Edit Member' : 'Add New Member';

		// Get WordPress users for linking.
		$wp_users = get_users( array( 'orderby' => 'display_name' ) );

		// Get membership products.
		$products_repo = new AA_Customers_Products_Repository();
		$membership_products = $products_repo->get_active_memberships();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>

			<?php $this->render_admin_notices(); ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'aa_customers_save_member', 'aa_customers_nonce' ); ?>
				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="member_id" value="<?php echo esc_attr( $member->id ); ?>">
				<?php endif; ?>

				<table class="form-table">
					<?php if ( ! $is_edit ) : ?>
						<tr>
							<th scope="row"><label for="wp_user_id">WordPress User</label></th>
							<td>
								<select name="wp_user_id" id="wp_user_id" required>
									<option value="">— Select User —</option>
									<?php foreach ( $wp_users as $user ) : ?>
										<option value="<?php echo esc_attr( $user->ID ); ?>">
											<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">Link to an existing WordPress user account.</p>
							</td>
						</tr>
					<?php endif; ?>

					<tr>
						<th scope="row"><label for="full_name">Full Name</label></th>
						<td>
							<input type="text" name="full_name" id="full_name" class="regular-text" value="<?php echo esc_attr( $member->full_name ?? '' ); ?>" required>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="email">Email</label></th>
						<td>
							<input type="email" name="email" id="email" class="regular-text" value="<?php echo esc_attr( $member->email ?? '' ); ?>" required>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="phone">Phone</label></th>
						<td>
							<input type="text" name="phone" id="phone" class="regular-text" value="<?php echo esc_attr( $member->phone ?? '' ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="address_line_1">Address</label></th>
						<td>
							<input type="text" name="address_line_1" id="address_line_1" class="regular-text" placeholder="Address Line 1" value="<?php echo esc_attr( $member->address_line_1 ?? '' ); ?>"><br>
							<input type="text" name="address_line_2" id="address_line_2" class="regular-text" placeholder="Address Line 2" value="<?php echo esc_attr( $member->address_line_2 ?? '' ); ?>" style="margin-top:5px;"><br>
							<input type="text" name="city" id="city" class="regular-text" placeholder="City" value="<?php echo esc_attr( $member->city ?? '' ); ?>" style="margin-top:5px;"><br>
							<input type="text" name="postcode" id="postcode" style="width:150px;margin-top:5px;" placeholder="Postcode" value="<?php echo esc_attr( $member->postcode ?? '' ); ?>">
							<input type="text" name="country" id="country" style="width:200px;margin-top:5px;" placeholder="Country" value="<?php echo esc_attr( $member->country ?? 'United Kingdom' ); ?>">
						</td>
					</tr>
				</table>

				<h2>Membership</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="membership_status">Status</label></th>
						<td>
							<select name="membership_status" id="membership_status">
								<option value="none" <?php selected( $member->membership_status ?? 'none', 'none' ); ?>>None</option>
								<option value="active" <?php selected( $member->membership_status ?? '', 'active' ); ?>>Active</option>
								<option value="pending" <?php selected( $member->membership_status ?? '', 'pending' ); ?>>Pending</option>
								<option value="past_due" <?php selected( $member->membership_status ?? '', 'past_due' ); ?>>Past Due</option>
								<option value="cancelled" <?php selected( $member->membership_status ?? '', 'cancelled' ); ?>>Cancelled</option>
								<option value="expired" <?php selected( $member->membership_status ?? '', 'expired' ); ?>>Expired</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="membership_type_id">Membership Type</label></th>
						<td>
							<select name="membership_type_id" id="membership_type_id">
								<option value="">— None —</option>
								<?php foreach ( $membership_products as $product ) : ?>
									<option value="<?php echo esc_attr( $product->id ); ?>" <?php selected( $member->membership_type_id ?? '', $product->id ); ?>>
										<?php echo esc_html( $product->name . ' (£' . number_format( $product->price, 2 ) . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="membership_start">Start Date</label></th>
						<td>
							<input type="date" name="membership_start" id="membership_start" value="<?php echo esc_attr( $member->membership_start ?? '' ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="membership_expiry">Expiry Date</label></th>
						<td>
							<input type="date" name="membership_expiry" id="membership_expiry" value="<?php echo esc_attr( $member->membership_expiry ?? '' ); ?>">
						</td>
					</tr>
				</table>

				<h2>Practitioner Directory</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="show_in_directory">Show in Directory</label></th>
						<td>
							<label>
								<input type="checkbox" name="show_in_directory" id="show_in_directory" value="1" <?php checked( $member->show_in_directory ?? 0, 1 ); ?>>
								Display this member in the public practitioner directory
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="practitioner_bio">Bio</label></th>
						<td>
							<textarea name="practitioner_bio" id="practitioner_bio" rows="5" class="large-text"><?php echo esc_textarea( $member->practitioner_bio ?? '' ); ?></textarea>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="practitioner_specialties">Specialties</label></th>
						<td>
							<input type="text" name="practitioner_specialties" id="practitioner_specialties" class="regular-text" value="<?php echo esc_attr( $member->practitioner_specialties ?? '' ); ?>">
							<p class="description">Comma-separated list of specialties.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="practitioner_location">Location</label></th>
						<td>
							<input type="text" name="practitioner_location" id="practitioner_location" class="regular-text" value="<?php echo esc_attr( $member->practitioner_location ?? '' ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="practitioner_website">Website</label></th>
						<td>
							<input type="url" name="practitioner_website" id="practitioner_website" class="regular-text" value="<?php echo esc_attr( $member->practitioner_website ?? '' ); ?>">
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="aa_customers_save_member" class="button button-primary" value="<?php echo $is_edit ? 'Update Member' : 'Add Member'; ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-members' ) ); ?>" class="button">Cancel</a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render member view
	 *
	 * @return void
	 */
	private function render_view() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$member = $this->repository->get_by_id( $id );

		if ( ! $member ) {
			wp_die( 'Member not found.' );
		}

		// Get purchases.
		$purchases_repo = new AA_Customers_Purchases_Repository();
		$purchases = $purchases_repo->get_by_member( $id );

		// Get registrations.
		$registrations_repo = new AA_Customers_Registrations_Repository();
		$registrations = $registrations_repo->get_by_member( $id );

		// Get membership info.
		$membership_service = new AA_Customers_Membership_Service();
		$membership_info = $membership_service->get_membership_info( $id );
		?>
		<div class="wrap">
			<h1>
				<?php echo esc_html( $member->full_name ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-members&action=edit&id=' . $member->id ) ); ?>" class="page-title-action">Edit</a>
			</h1>

			<div class="aa-customers-member-view">
				<div class="aa-customers-card">
					<h2>Contact Information</h2>
					<table class="widefat">
						<tr><th>Email</th><td><a href="mailto:<?php echo esc_attr( $member->email ); ?>"><?php echo esc_html( $member->email ); ?></a></td></tr>
						<tr><th>Phone</th><td><?php echo esc_html( $member->phone ?: '—' ); ?></td></tr>
						<tr><th>Address</th><td>
							<?php
							$address_parts = array_filter( array(
								$member->address_line_1,
								$member->address_line_2,
								$member->city,
								$member->postcode,
								$member->country,
							) );
							echo esc_html( implode( ', ', $address_parts ) ?: '—' );
							?>
						</td></tr>
						<tr><th>WordPress User</th><td>
							<?php
							$wp_user = get_user_by( 'ID', $member->wp_user_id );
							if ( $wp_user ) {
								echo '<a href="' . esc_url( get_edit_user_link( $wp_user->ID ) ) . '">' . esc_html( $wp_user->display_name ) . '</a>';
							} else {
								echo '—';
							}
							?>
						</td></tr>
					</table>
				</div>

				<div class="aa-customers-card">
					<h2>Membership</h2>
					<table class="widefat">
						<tr><th>Status</th><td><?php echo $this->get_status_badge( $membership_info['status'] ); ?></td></tr>
						<tr><th>Type</th><td><?php echo esc_html( $membership_info['type_name'] ?: '—' ); ?></td></tr>
						<tr><th>Start Date</th><td><?php echo $membership_info['start_date'] ? esc_html( date( 'j M Y', strtotime( $membership_info['start_date'] ) ) ) : '—'; ?></td></tr>
						<tr><th>Expiry Date</th><td><?php echo $membership_info['expiry_date'] ? esc_html( date( 'j M Y', strtotime( $membership_info['expiry_date'] ) ) ) : '—'; ?></td></tr>
						<tr><th>Stripe Customer</th><td><?php echo esc_html( $member->stripe_customer_id ?: '—' ); ?></td></tr>
						<tr><th>Stripe Subscription</th><td><?php echo esc_html( $member->stripe_subscription_id ?: '—' ); ?></td></tr>
					</table>
				</div>

				<div class="aa-customers-card">
					<h2>Purchase History</h2>
					<?php if ( empty( $purchases ) ) : ?>
						<p>No purchases found.</p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th>Date</th>
									<th>Product</th>
									<th>Amount</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $purchases as $purchase ) : ?>
									<?php
									$products_repo = new AA_Customers_Products_Repository();
									$product = $products_repo->get_by_id( $purchase->product_id );
									?>
									<tr>
										<td><?php echo esc_html( date( 'j M Y', strtotime( $purchase->purchase_date ) ) ); ?></td>
										<td><?php echo esc_html( $product ? $product->name : 'Unknown' ); ?></td>
										<td>£<?php echo esc_html( number_format( $purchase->total_amount, 2 ) ); ?></td>
										<td><?php echo $this->get_payment_status_badge( $purchase->payment_status ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<style>
			.aa-customers-member-view { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-top: 20px; }
			.aa-customers-card { background: #fff; border: 1px solid #ccd0d4; padding: 15px; }
			.aa-customers-card h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; }
			.aa-customers-card table th { width: 150px; text-align: left; padding: 8px; }
			.aa-customers-card table td { padding: 8px; }
		</style>
		<?php
	}

	/**
	 * Handle save member
	 *
	 * @return void
	 */
	private function handle_save_member() {
		if ( ! $this->verify_nonce( 'aa_customers_save_member', 'aa_customers_nonce' ) ) {
			return;
		}

		$is_edit   = isset( $_POST['member_id'] ) && absint( $_POST['member_id'] ) > 0;
		$member_id = $is_edit ? absint( $_POST['member_id'] ) : 0;

		$data = array(
			'full_name'               => sanitize_text_field( $_POST['full_name'] ?? '' ),
			'email'                   => sanitize_email( $_POST['email'] ?? '' ),
			'phone'                   => sanitize_text_field( $_POST['phone'] ?? '' ),
			'address_line_1'          => sanitize_text_field( $_POST['address_line_1'] ?? '' ),
			'address_line_2'          => sanitize_text_field( $_POST['address_line_2'] ?? '' ),
			'city'                    => sanitize_text_field( $_POST['city'] ?? '' ),
			'postcode'                => sanitize_text_field( $_POST['postcode'] ?? '' ),
			'country'                 => sanitize_text_field( $_POST['country'] ?? '' ),
			'membership_status'       => sanitize_text_field( $_POST['membership_status'] ?? 'none' ),
			'membership_type_id'      => absint( $_POST['membership_type_id'] ?? 0 ) ?: null,
			'membership_start'        => sanitize_text_field( $_POST['membership_start'] ?? '' ) ?: null,
			'membership_expiry'       => sanitize_text_field( $_POST['membership_expiry'] ?? '' ) ?: null,
			'show_in_directory'       => isset( $_POST['show_in_directory'] ) ? 1 : 0,
			'practitioner_bio'        => wp_kses_post( $_POST['practitioner_bio'] ?? '' ),
			'practitioner_specialties' => sanitize_text_field( $_POST['practitioner_specialties'] ?? '' ),
			'practitioner_location'   => sanitize_text_field( $_POST['practitioner_location'] ?? '' ),
			'practitioner_website'    => esc_url_raw( $_POST['practitioner_website'] ?? '' ),
		);

		if ( $is_edit ) {
			$result = $this->repository->update( $member_id, $data );
			$message = $result ? 'Member updated successfully.' : 'Failed to update member.';
			$type = $result ? 'success' : 'error';
		} else {
			$data['wp_user_id'] = absint( $_POST['wp_user_id'] ?? 0 );

			if ( empty( $data['wp_user_id'] ) ) {
				$this->redirect_with_message(
					admin_url( 'admin.php?page=aa-customers-members&action=add' ),
					'Please select a WordPress user.',
					'error'
				);
				return;
			}

			$member_id = $this->repository->create( $data );
			$message = $member_id ? 'Member created successfully.' : 'Failed to create member.';
			$type = $member_id ? 'success' : 'error';
		}

		$redirect_url = $member_id && $type === 'success'
			? admin_url( 'admin.php?page=aa-customers-members&action=edit&id=' . $member_id )
			: admin_url( 'admin.php?page=aa-customers-members' );

		$this->redirect_with_message( $redirect_url, $message, $type );
	}

	/**
	 * Handle delete member
	 *
	 * @return void
	 */
	private function handle_delete_member() {
		$id = absint( $_GET['id'] ?? 0 );

		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'delete_member_' . $id ) ) {
			wp_die( 'Security check failed.' );
		}

		$result = $this->repository->delete( $id );

		$this->redirect_with_message(
			admin_url( 'admin.php?page=aa-customers-members' ),
			$result ? 'Member deleted successfully.' : 'Failed to delete member.',
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
			'active'    => '<span class="aa-customers-badge badge-active">Active</span>',
			'pending'   => '<span class="aa-customers-badge badge-pending">Pending</span>',
			'past_due'  => '<span class="aa-customers-badge badge-warning">Past Due</span>',
			'cancelled' => '<span class="aa-customers-badge badge-cancelled">Cancelled</span>',
			'expired'   => '<span class="aa-customers-badge badge-expired">Expired</span>',
			'none'      => '<span class="aa-customers-badge badge-none">None</span>',
		);

		return $badges[ $status ] ?? '<span class="aa-customers-badge">' . esc_html( ucfirst( $status ) ) . '</span>';
	}

	/**
	 * Get payment status badge
	 *
	 * @param string $status Payment status.
	 * @return string HTML badge.
	 */
	private function get_payment_status_badge( $status ) {
		$badges = array(
			'succeeded' => '<span class="aa-customers-badge badge-active">Paid</span>',
			'pending'   => '<span class="aa-customers-badge badge-pending">Pending</span>',
			'failed'    => '<span class="aa-customers-badge badge-expired">Failed</span>',
			'refunded'  => '<span class="aa-customers-badge badge-cancelled">Refunded</span>',
		);

		return $badges[ $status ] ?? '<span class="aa-customers-badge">' . esc_html( ucfirst( $status ) ) . '</span>';
	}

	/**
	 * Render pagination
	 *
	 * @param int $total Total items.
	 * @param int $per_page Items per page.
	 * @param int $current Current page.
	 * @return void
	 */
	private function render_pagination( $total, $per_page, $current ) {
		$total_pages = ceil( $total / $per_page );

		if ( $total_pages <= 1 ) {
			return;
		}

		$base_url = admin_url( 'admin.php?page=aa-customers-members' );
		if ( isset( $_GET['status'] ) ) {
			$base_url = add_query_arg( 'status', sanitize_text_field( $_GET['status'] ), $base_url );
		}
		if ( isset( $_GET['s'] ) ) {
			$base_url = add_query_arg( 's', sanitize_text_field( $_GET['s'] ), $base_url );
		}
		?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<span class="displaying-num"><?php echo esc_html( $total ); ?> items</span>
				<span class="pagination-links">
					<?php if ( $current > 1 ) : ?>
						<a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $current - 1, $base_url ) ); ?>">‹</a>
					<?php else : ?>
						<span class="tablenav-pages-navspan button disabled">‹</span>
					<?php endif; ?>

					<span class="paging-input">
						<?php echo esc_html( $current ); ?> of <span class="total-pages"><?php echo esc_html( $total_pages ); ?></span>
					</span>

					<?php if ( $current < $total_pages ) : ?>
						<a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $current + 1, $base_url ) ); ?>">›</a>
					<?php else : ?>
						<span class="tablenav-pages-navspan button disabled">›</span>
					<?php endif; ?>
				</span>
			</div>
		</div>
		<?php
	}
}
