<?php
/**
 * Events Admin
 *
 * Handles the Events admin interface:
 * - List all events
 * - Add new event (with product)
 * - Edit event
 * - View registrations
 *
 * @package AA_Customers
 * @subpackage Admin
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Events_Admin
 */
class AA_Customers_Events_Admin extends AA_Customers_Base_Admin {

	/**
	 * Events repository
	 *
	 * @var AA_Customers_Events_Repository
	 */
	private $events_repo;

	/**
	 * Products repository
	 *
	 * @var AA_Customers_Products_Repository
	 */
	private $products_repo;

	/**
	 * Registrations repository
	 *
	 * @var AA_Customers_Registrations_Repository
	 */
	private $registrations_repo;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->events_repo        = new AA_Customers_Events_Repository();
		$this->products_repo      = new AA_Customers_Products_Repository();
		$this->registrations_repo = new AA_Customers_Registrations_Repository();
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
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'aa-customers-events' ) {
			return;
		}

		if ( isset( $_POST['aa_customers_save_event'] ) ) {
			$this->handle_save_event();
		}

		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
			$this->handle_delete_event();
		}
	}

	/**
	 * Render events page (router)
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
			case 'registrations':
				$this->render_registrations();
				break;
			default:
				$this->render_list();
				break;
		}
	}

	/**
	 * Render events list
	 *
	 * @return void
	 */
	private function render_list() {
		$show = isset( $_GET['show'] ) ? sanitize_text_field( $_GET['show'] ) : 'upcoming';

		$events = $this->events_repo->get_all( array(
			'upcoming' => $show === 'upcoming',
			'past'     => $show === 'past',
		) );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Events</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-events&action=add' ) ); ?>" class="page-title-action">Add New</a>
			<hr class="wp-header-end">

			<?php $this->render_admin_notices(); ?>

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-events&show=upcoming' ) ); ?>" <?php echo $show === 'upcoming' ? 'class="current"' : ''; ?>>Upcoming</a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-events&show=past' ) ); ?>" <?php echo $show === 'past' ? 'class="current"' : ''; ?>>Past</a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-events&show=all' ) ); ?>" <?php echo $show === 'all' ? 'class="current"' : ''; ?>>All</a></li>
			</ul>

			<table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-name column-primary">Event</th>
						<th scope="col" class="manage-column column-date">Date</th>
						<th scope="col" class="manage-column column-location">Location</th>
						<th scope="col" class="manage-column column-capacity">Capacity</th>
						<th scope="col" class="manage-column column-registrations">Registrations</th>
						<th scope="col" class="manage-column column-status">Status</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $events ) ) : ?>
						<tr>
							<td colspan="6">No events found.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $events as $event ) : ?>
							<?php
							$product      = $this->products_repo->get_by_id( $event->product_id );
							$reg_count    = $this->events_repo->get_registration_count( $event->id );
							$is_past      = strtotime( $event->event_date ) < strtotime( 'today' );
							?>
							<tr>
								<td class="column-name column-primary" data-colname="Event">
									<strong>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-events&action=edit&id=' . $event->id ) ); ?>">
											<?php echo esc_html( $product ? $product->name : 'Unknown Event' ); ?>
										</a>
									</strong>
									<div class="row-actions">
										<span class="edit">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-events&action=edit&id=' . $event->id ) ); ?>">Edit</a> |
										</span>
										<span class="registrations">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-events&action=registrations&id=' . $event->id ) ); ?>">Registrations</a> |
										</span>
										<span class="trash">
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=aa-customers-events&action=delete&id=' . $event->id ), 'delete_event_' . $event->id ) ); ?>" class="submitdelete" onclick="return confirm('Are you sure? This will also delete the associated product.');">Delete</a>
										</span>
									</div>
								</td>
								<td class="column-date" data-colname="Date">
									<?php
									echo esc_html( date( 'D j M Y', strtotime( $event->event_date ) ) );
									if ( $event->event_time ) {
										echo ' ' . esc_html( date( 'g:ia', strtotime( $event->event_time ) ) );
									}
									?>
								</td>
								<td class="column-location" data-colname="Location">
									<?php echo esc_html( $event->location ?: '—' ); ?>
								</td>
								<td class="column-capacity" data-colname="Capacity">
									<?php echo $event->capacity ? esc_html( $event->capacity ) : 'Unlimited'; ?>
								</td>
								<td class="column-registrations" data-colname="Registrations">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-events&action=registrations&id=' . $event->id ) ); ?>">
										<?php echo esc_html( $reg_count ); ?>
										<?php if ( $event->capacity ) : ?>
											/ <?php echo esc_html( $event->capacity ); ?>
										<?php endif; ?>
									</a>
								</td>
								<td class="column-status" data-colname="Status">
									<?php if ( $is_past ) : ?>
										<span class="aa-customers-badge badge-cancelled">Past</span>
									<?php elseif ( $product && $product->status === 'active' ) : ?>
										<span class="aa-customers-badge badge-active">Open</span>
									<?php else : ?>
										<span class="aa-customers-badge badge-pending">Draft</span>
									<?php endif; ?>
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
	 * Render add event form
	 *
	 * @return void
	 */
	private function render_add_form() {
		$this->render_event_form( null, null );
	}

	/**
	 * Render edit event form
	 *
	 * @return void
	 */
	private function render_edit_form() {
		$id    = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$event = $this->events_repo->get_by_id( $id );

		if ( ! $event ) {
			wp_die( 'Event not found.' );
		}

		$product = $this->products_repo->get_by_id( $event->product_id );

		$this->render_event_form( $event, $product );
	}

	/**
	 * Render event form (add/edit)
	 *
	 * @param object|null $event Event object or null for new.
	 * @param object|null $product Product object or null for new.
	 * @return void
	 */
	private function render_event_form( $event, $product ) {
		$is_edit = ! is_null( $event );
		$title   = $is_edit ? 'Edit Event' : 'Add New Event';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>

			<?php $this->render_admin_notices(); ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'aa_customers_save_event', 'aa_customers_nonce' ); ?>
				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="event_id" value="<?php echo esc_attr( $event->id ); ?>">
					<input type="hidden" name="product_id" value="<?php echo esc_attr( $event->product_id ); ?>">
				<?php endif; ?>

				<h2>Event Details</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="name">Event Name</label></th>
						<td>
							<input type="text" name="name" id="name" class="regular-text" value="<?php echo esc_attr( $product->name ?? '' ); ?>" required>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="description">Description</label></th>
						<td>
							<textarea name="description" id="description" rows="5" class="large-text"><?php echo esc_textarea( $product->description ?? '' ); ?></textarea>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="event_date">Event Date</label></th>
						<td>
							<input type="date" name="event_date" id="event_date" value="<?php echo esc_attr( $event->event_date ?? '' ); ?>" required>
							<input type="time" name="event_time" id="event_time" value="<?php echo esc_attr( $event->event_time ?? '' ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="end_date">End Date/Time</label></th>
						<td>
							<input type="date" name="end_date" id="end_date" value="<?php echo esc_attr( $event->end_date ?? '' ); ?>">
							<input type="time" name="end_time" id="end_time" value="<?php echo esc_attr( $event->end_time ?? '' ); ?>">
							<p class="description">Optional. Leave blank for single-day events.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="location">Location</label></th>
						<td>
							<input type="text" name="location" id="location" class="regular-text" value="<?php echo esc_attr( $event->location ?? '' ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="location_details">Location Details</label></th>
						<td>
							<textarea name="location_details" id="location_details" rows="3" class="large-text"><?php echo esc_textarea( $event->location_details ?? '' ); ?></textarea>
							<p class="description">Directions, parking info, etc.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="capacity">Capacity</label></th>
						<td>
							<input type="number" name="capacity" id="capacity" min="0" value="<?php echo esc_attr( $event->capacity ?? '' ); ?>">
							<p class="description">Leave blank or 0 for unlimited.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="registration_deadline">Registration Deadline</label></th>
						<td>
							<input type="date" name="registration_deadline" id="registration_deadline" value="<?php echo esc_attr( $event->registration_deadline ?? '' ); ?>">
							<p class="description">Optional. Registration closes at midnight on this date.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="additional_info">Additional Info</label></th>
						<td>
							<textarea name="additional_info" id="additional_info" rows="3" class="large-text"><?php echo esc_textarea( $event->additional_info ?? '' ); ?></textarea>
						</td>
					</tr>
				</table>

				<h2>Pricing</h2>
				<table class="form-table">
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
							<p class="description">Leave empty for no member discount.</p>
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
				</table>

				<h2>Status</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="status">Status</label></th>
						<td>
							<select name="status" id="status">
								<option value="draft" <?php selected( $product->status ?? 'draft', 'draft' ); ?>>Draft (not visible)</option>
								<option value="active" <?php selected( $product->status ?? '', 'active' ); ?>>Active (open for registration)</option>
								<option value="archived" <?php selected( $product->status ?? '', 'archived' ); ?>>Archived</option>
							</select>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="aa_customers_save_event" class="button button-primary" value="<?php echo $is_edit ? 'Update Event' : 'Create Event'; ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-events' ) ); ?>" class="button">Cancel</a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render event registrations
	 *
	 * @return void
	 */
	private function render_registrations() {
		$id    = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$event = $this->events_repo->get_with_product( $id );

		if ( ! $event ) {
			wp_die( 'Event not found.' );
		}

		$registrations = $this->registrations_repo->get_for_event_with_details( $id );
		$counts        = $this->registrations_repo->count_by_status_for_event( $id );
		?>
		<div class="wrap">
			<h1>
				Registrations: <?php echo esc_html( $event->name ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-events' ) ); ?>" class="page-title-action">Back to Events</a>
			</h1>

			<p>
				<strong>Date:</strong> <?php echo esc_html( date( 'l j F Y', strtotime( $event->event_date ) ) ); ?>
				<?php if ( $event->event_time ) : ?>
					at <?php echo esc_html( date( 'g:ia', strtotime( $event->event_time ) ) ); ?>
				<?php endif; ?>
				&nbsp;|&nbsp;
				<strong>Location:</strong> <?php echo esc_html( $event->location ?: 'TBC' ); ?>
				&nbsp;|&nbsp;
				<strong>Capacity:</strong> <?php echo $event->capacity ? esc_html( $counts['confirmed'] . '/' . $event->capacity ) : esc_html( $counts['confirmed'] ) . ' (unlimited)'; ?>
			</p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col">Name</th>
						<th scope="col">Email</th>
						<th scope="col">Type</th>
						<th scope="col">Dietary Requirements</th>
						<th scope="col">Accessibility Needs</th>
						<th scope="col">Status</th>
						<th scope="col">Registered</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $registrations ) ) : ?>
						<tr>
							<td colspan="7">No registrations found.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $registrations as $reg ) : ?>
							<tr>
								<td><?php echo esc_html( $reg->attendee_name ); ?></td>
								<td><a href="mailto:<?php echo esc_attr( $reg->attendee_email ); ?>"><?php echo esc_html( $reg->attendee_email ); ?></a></td>
								<td><?php echo $reg->member_id ? 'Member' : 'Guest'; ?></td>
								<td><?php echo esc_html( $reg->dietary_requirements ?: '—' ); ?></td>
								<td><?php echo esc_html( $reg->accessibility_needs ?: '—' ); ?></td>
								<td>
									<?php
									$status_badges = array(
										'confirmed' => '<span class="aa-customers-badge badge-active">Confirmed</span>',
										'cancelled' => '<span class="aa-customers-badge badge-cancelled">Cancelled</span>',
										'waitlist'  => '<span class="aa-customers-badge badge-pending">Waitlist</span>',
									);
									echo $status_badges[ $reg->status ] ?? esc_html( ucfirst( $reg->status ) );
									?>
								</td>
								<td><?php echo esc_html( date( 'j M Y H:i', strtotime( $reg->registered_at ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<p style="margin-top:20px;">
				<strong>Summary:</strong>
				<?php echo esc_html( $counts['confirmed'] ); ?> confirmed,
				<?php echo esc_html( $counts['waitlist'] ); ?> waitlist,
				<?php echo esc_html( $counts['cancelled'] ); ?> cancelled
			</p>
		</div>
		<?php
	}

	/**
	 * Handle save event
	 *
	 * @return void
	 */
	private function handle_save_event() {
		if ( ! $this->verify_nonce( 'aa_customers_save_event', 'aa_customers_nonce' ) ) {
			return;
		}

		$is_edit    = isset( $_POST['event_id'] ) && absint( $_POST['event_id'] ) > 0;
		$event_id   = $is_edit ? absint( $_POST['event_id'] ) : 0;
		$product_id = $is_edit ? absint( $_POST['product_id'] ) : 0;

		// Product data.
		$product_data = array(
			'type'           => 'event',
			'name'           => sanitize_text_field( $_POST['name'] ?? '' ),
			'description'    => wp_kses_post( $_POST['description'] ?? '' ),
			'price'          => floatval( $_POST['price'] ?? 0 ),
			'member_price'   => $_POST['member_price'] !== '' ? floatval( $_POST['member_price'] ) : null,
			'allow_donation' => isset( $_POST['allow_donation'] ) ? 1 : 0,
			'status'         => sanitize_text_field( $_POST['status'] ?? 'draft' ),
			'is_recurring'   => 0,
		);

		// Event data.
		$event_data = array(
			'event_date'            => sanitize_text_field( $_POST['event_date'] ?? '' ),
			'event_time'            => sanitize_text_field( $_POST['event_time'] ?? '' ) ?: null,
			'end_date'              => sanitize_text_field( $_POST['end_date'] ?? '' ) ?: null,
			'end_time'              => sanitize_text_field( $_POST['end_time'] ?? '' ) ?: null,
			'location'              => sanitize_text_field( $_POST['location'] ?? '' ),
			'location_details'      => wp_kses_post( $_POST['location_details'] ?? '' ),
			'capacity'              => absint( $_POST['capacity'] ?? 0 ) ?: null,
			'registration_deadline' => sanitize_text_field( $_POST['registration_deadline'] ?? '' ) ?: null,
			'additional_info'       => wp_kses_post( $_POST['additional_info'] ?? '' ),
		);

		if ( $is_edit ) {
			// Update product.
			$this->products_repo->update( $product_id, $product_data );

			// Update event.
			$result = $this->events_repo->update( $event_id, $event_data );

			$message = $result !== false ? 'Event updated successfully.' : 'Failed to update event.';
			$type    = $result !== false ? 'success' : 'error';
		} else {
			// Create product first.
			$product_id = $this->products_repo->create( $product_data );

			if ( ! $product_id ) {
				$this->redirect_with_message(
					admin_url( 'admin.php?page=aa-customers-events&action=add' ),
					'Failed to create event product.',
					'error'
				);
				return;
			}

			// Create event.
			$event_data['product_id'] = $product_id;
			$event_id = $this->events_repo->create( $event_data );

			if ( ! $event_id ) {
				// Clean up product if event creation failed.
				$this->products_repo->delete( $product_id );
				$this->redirect_with_message(
					admin_url( 'admin.php?page=aa-customers-events&action=add' ),
					'Failed to create event.',
					'error'
				);
				return;
			}

			$message = 'Event created successfully.';
			$type    = 'success';
		}

		$redirect_url = $event_id && $type === 'success'
			? admin_url( 'admin.php?page=aa-customers-events&action=edit&id=' . $event_id )
			: admin_url( 'admin.php?page=aa-customers-events' );

		$this->redirect_with_message( $redirect_url, $message, $type );
	}

	/**
	 * Handle delete event
	 *
	 * @return void
	 */
	private function handle_delete_event() {
		$id = absint( $_GET['id'] ?? 0 );

		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'delete_event_' . $id ) ) {
			wp_die( 'Security check failed.' );
		}

		$event = $this->events_repo->get_by_id( $id );

		if ( $event ) {
			// Delete event first.
			$this->events_repo->delete( $id );

			// Then delete associated product.
			if ( $event->product_id ) {
				$this->products_repo->delete( $event->product_id );
			}
		}

		$this->redirect_with_message(
			admin_url( 'admin.php?page=aa-customers-events' ),
			'Event deleted successfully.',
			'success'
		);
	}
}
