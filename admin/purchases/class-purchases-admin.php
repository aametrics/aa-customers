<?php
/**
 * Purchases Admin
 *
 * Handles the Purchases admin interface:
 * - List all purchases
 * - View purchase details
 *
 * @package AA_Customers
 * @subpackage Admin
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Purchases_Admin
 */
class AA_Customers_Purchases_Admin extends AA_Customers_Base_Admin {

	/**
	 * Purchases repository
	 *
	 * @var AA_Customers_Purchases_Repository
	 */
	private $repository;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->repository = new AA_Customers_Purchases_Repository();
	}

	/**
	 * Render purchases page (router)
	 *
	 * @return void
	 */
	public function render() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

		switch ( $action ) {
			case 'view':
				$this->render_view();
				break;
			default:
				$this->render_list();
				break;
		}
	}

	/**
	 * Render purchases list
	 *
	 * @return void
	 */
	private function render_list() {
		$status   = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$paged    = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page = 25;

		$purchases = $this->repository->get_all( array(
			'payment_status' => $status,
			'limit'          => $per_page,
			'offset'         => ( $paged - 1 ) * $per_page,
		) );

		$counts = $this->repository->count_by_status();
		$total  = array_sum( $counts );

		// Get monthly stats.
		$monthly_revenue = $this->repository->get_monthly_revenue();
		$start           = date( 'Y-m-01' );
		$end             = date( 'Y-m-t' );
		$monthly_donations = $this->repository->get_donations( $start, $end );
		?>
		<div class="wrap">
			<h1>Purchases</h1>

			<?php $this->render_admin_notices(); ?>

			<div class="aa-customers-stats-row">
				<div class="aa-customers-stat-box">
					<span class="stat-number">£<?php echo esc_html( number_format( $monthly_revenue, 2 ) ); ?></span>
					<span class="stat-label">Revenue (<?php echo esc_html( date( 'F' ) ); ?>)</span>
				</div>
				<div class="aa-customers-stat-box">
					<span class="stat-number">£<?php echo esc_html( number_format( $monthly_donations, 2 ) ); ?></span>
					<span class="stat-label">Donations (<?php echo esc_html( date( 'F' ) ); ?>)</span>
				</div>
				<div class="aa-customers-stat-box">
					<span class="stat-number"><?php echo esc_html( $counts['succeeded'] ); ?></span>
					<span class="stat-label">Successful Payments</span>
				</div>
				<div class="aa-customers-stat-box">
					<span class="stat-number"><?php echo esc_html( $counts['pending'] ); ?></span>
					<span class="stat-label">Pending</span>
				</div>
			</div>

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-purchases' ) ); ?>" <?php echo empty( $status ) ? 'class="current"' : ''; ?>>All <span class="count">(<?php echo esc_html( $total ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-purchases&status=succeeded' ) ); ?>" <?php echo $status === 'succeeded' ? 'class="current"' : ''; ?>>Succeeded <span class="count">(<?php echo esc_html( $counts['succeeded'] ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-purchases&status=pending' ) ); ?>" <?php echo $status === 'pending' ? 'class="current"' : ''; ?>>Pending <span class="count">(<?php echo esc_html( $counts['pending'] ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-purchases&status=failed' ) ); ?>" <?php echo $status === 'failed' ? 'class="current"' : ''; ?>>Failed <span class="count">(<?php echo esc_html( $counts['failed'] ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-purchases&status=refunded' ) ); ?>" <?php echo $status === 'refunded' ? 'class="current"' : ''; ?>>Refunded <span class="count">(<?php echo esc_html( $counts['refunded'] ); ?>)</span></a></li>
			</ul>

			<table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-id">ID</th>
						<th scope="col" class="manage-column column-date">Date</th>
						<th scope="col" class="manage-column column-member">Member</th>
						<th scope="col" class="manage-column column-product">Product</th>
						<th scope="col" class="manage-column column-amount">Amount</th>
						<th scope="col" class="manage-column column-donation">Donation</th>
						<th scope="col" class="manage-column column-total">Total</th>
						<th scope="col" class="manage-column column-status">Status</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $purchases ) ) : ?>
						<tr>
							<td colspan="8">No purchases found.</td>
						</tr>
					<?php else : ?>
						<?php
						$members_repo  = new AA_Customers_Members_Repository();
						$products_repo = new AA_Customers_Products_Repository();
						?>
						<?php foreach ( $purchases as $purchase ) : ?>
							<?php
							$member  = $members_repo->get_by_id( $purchase->member_id );
							$product = $products_repo->get_by_id( $purchase->product_id );
							?>
							<tr>
								<td class="column-id" data-colname="ID">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-purchases&action=view&id=' . $purchase->id ) ); ?>">
										#<?php echo esc_html( $purchase->id ); ?>
									</a>
								</td>
								<td class="column-date" data-colname="Date">
									<?php echo esc_html( date( 'j M Y H:i', strtotime( $purchase->purchase_date ) ) ); ?>
								</td>
								<td class="column-member" data-colname="Member">
									<?php if ( $member ) : ?>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-members&action=view&id=' . $member->id ) ); ?>">
											<?php echo esc_html( $member->full_name ); ?>
										</a>
									<?php else : ?>
										Unknown
									<?php endif; ?>
								</td>
								<td class="column-product" data-colname="Product">
									<?php echo esc_html( $product ? $product->name : 'Unknown' ); ?>
								</td>
								<td class="column-amount" data-colname="Amount">
									£<?php echo esc_html( number_format( $purchase->amount, 2 ) ); ?>
								</td>
								<td class="column-donation" data-colname="Donation">
									<?php echo $purchase->donation_amount > 0 ? '£' . esc_html( number_format( $purchase->donation_amount, 2 ) ) : '—'; ?>
								</td>
								<td class="column-total" data-colname="Total">
									<strong>£<?php echo esc_html( number_format( $purchase->total_amount, 2 ) ); ?></strong>
								</td>
								<td class="column-status" data-colname="Status">
									<?php echo $this->get_status_badge( $purchase->payment_status ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php $this->render_pagination( $total, $per_page, $paged ); ?>
		</div>

		<style>
			.aa-customers-stats-row { display: flex; gap: 20px; margin-bottom: 20px; }
			.aa-customers-stat-box { background: #fff; border: 1px solid #ccd0d4; padding: 20px; min-width: 150px; text-align: center; }
			.aa-customers-stat-box .stat-number { display: block; font-size: 24px; font-weight: 600; color: #1d2327; }
			.aa-customers-stat-box .stat-label { display: block; font-size: 12px; color: #646970; margin-top: 5px; }
		</style>
		<?php
	}

	/**
	 * Render purchase view
	 *
	 * @return void
	 */
	private function render_view() {
		$id       = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$purchase = $this->repository->get_by_id( $id );

		if ( ! $purchase ) {
			wp_die( 'Purchase not found.' );
		}

		$members_repo  = new AA_Customers_Members_Repository();
		$products_repo = new AA_Customers_Products_Repository();
		$member        = $members_repo->get_by_id( $purchase->member_id );
		$product       = $products_repo->get_by_id( $purchase->product_id );
		?>
		<div class="wrap">
			<h1>
				Purchase #<?php echo esc_html( $purchase->id ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-purchases' ) ); ?>" class="page-title-action">Back to Purchases</a>
			</h1>

			<div class="aa-customers-purchase-view">
				<div class="aa-customers-card">
					<h2>Purchase Details</h2>
					<table class="widefat">
						<tr><th>Date</th><td><?php echo esc_html( date( 'l j F Y \a\t H:i', strtotime( $purchase->purchase_date ) ) ); ?></td></tr>
						<tr><th>Status</th><td><?php echo $this->get_status_badge( $purchase->payment_status ); ?></td></tr>
						<tr><th>Product</th><td><?php echo esc_html( $product ? $product->name : 'Unknown' ); ?></td></tr>
						<tr><th>Type</th><td><?php echo $purchase->is_renewal ? 'Renewal' : 'New Purchase'; ?></td></tr>
					</table>
				</div>

				<div class="aa-customers-card">
					<h2>Payment</h2>
					<table class="widefat">
						<tr><th>Amount</th><td>£<?php echo esc_html( number_format( $purchase->amount, 2 ) ); ?></td></tr>
						<tr><th>Donation</th><td><?php echo $purchase->donation_amount > 0 ? '£' . esc_html( number_format( $purchase->donation_amount, 2 ) ) : '—'; ?></td></tr>
						<tr><th>Total</th><td><strong>£<?php echo esc_html( number_format( $purchase->total_amount, 2 ) ); ?></strong></td></tr>
						<tr><th>Currency</th><td><?php echo esc_html( $purchase->currency ); ?></td></tr>
					</table>
				</div>

				<div class="aa-customers-card">
					<h2>Member</h2>
					<?php if ( $member ) : ?>
						<table class="widefat">
							<tr><th>Name</th><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-customers-members&action=view&id=' . $member->id ) ); ?>"><?php echo esc_html( $member->full_name ); ?></a></td></tr>
							<tr><th>Email</th><td><a href="mailto:<?php echo esc_attr( $member->email ); ?>"><?php echo esc_html( $member->email ); ?></a></td></tr>
						</table>
					<?php else : ?>
						<p>Member not found.</p>
					<?php endif; ?>
				</div>

				<div class="aa-customers-card">
					<h2>Integration IDs</h2>
					<table class="widefat">
						<tr><th>Stripe Payment Intent</th><td><code><?php echo esc_html( $purchase->stripe_payment_intent_id ?: '—' ); ?></code></td></tr>
						<tr><th>Stripe Invoice</th><td><code><?php echo esc_html( $purchase->stripe_invoice_id ?: '—' ); ?></code></td></tr>
						<tr><th>Xero Invoice</th><td><code><?php echo esc_html( $purchase->xero_invoice_id ?: '—' ); ?></code></td></tr>
					</table>
				</div>

				<?php if ( $purchase->notes ) : ?>
					<div class="aa-customers-card" style="grid-column: span 2;">
						<h2>Notes</h2>
						<p><?php echo esc_html( $purchase->notes ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<style>
			.aa-customers-purchase-view { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 20px; }
			.aa-customers-card { background: #fff; border: 1px solid #ccd0d4; padding: 15px; }
			.aa-customers-card h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; font-size: 14px; }
			.aa-customers-card table th { width: 150px; text-align: left; padding: 8px; }
			.aa-customers-card table td { padding: 8px; }
		</style>
		<?php
	}

	/**
	 * Get status badge HTML
	 *
	 * @param string $status Status string.
	 * @return string HTML badge.
	 */
	protected function get_status_badge( $status ) {
		$badges = array(
			'succeeded' => '<span class="aa-customers-badge badge-active">Succeeded</span>',
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

		$base_url = admin_url( 'admin.php?page=aa-customers-purchases' );
		if ( isset( $_GET['status'] ) ) {
			$base_url = add_query_arg( 'status', sanitize_text_field( $_GET['status'] ), $base_url );
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
