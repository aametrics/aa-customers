<?php
/**
 * PDF Service
 *
 * Generates PDF documents using Dompdf.
 * Used for receipts, membership certificates, etc.
 *
 * @package AA_Customers
 * @subpackage Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_PDF_Service
 */
class AA_Customers_PDF_Service {

	/**
	 * Generate PDF from HTML content
	 *
	 * @param string $html HTML content.
	 * @param string $filename Filename (without path).
	 * @param string $orientation Page orientation: 'portrait' or 'landscape'.
	 * @return string|false File path or false on failure.
	 */
	public function generate_pdf( $html, $filename, $orientation = 'portrait' ) {
		try {
			// Load Dompdf.
			$autoload = AA_CUSTOMERS_PLUGIN_DIR . 'vendor/autoload.php';
			if ( ! file_exists( $autoload ) ) {
				error_log( 'AA Customers: Dompdf not found. Run composer install.' );
				return false;
			}

			require_once $autoload;

			$options = new \Dompdf\Options();
			$options->set( 'isRemoteEnabled', true );
			$options->set( 'isHtml5ParserEnabled', true );

			$dompdf = new \Dompdf\Dompdf( $options );
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', $orientation );
			$dompdf->render();

			// Get PDF content.
			$pdf_content = $dompdf->output();

			// Save to uploads directory.
			$upload_dir = wp_upload_dir();
			$pdf_dir    = $upload_dir['basedir'] . '/aa-customers';

			// Create directory if needed.
			if ( ! file_exists( $pdf_dir ) ) {
				wp_mkdir_p( $pdf_dir );
			}

			// Save PDF.
			$file_path = $pdf_dir . '/' . sanitize_file_name( $filename );
			file_put_contents( $file_path, $pdf_content );

			error_log( 'AA Customers: PDF generated at ' . $file_path );

			return $file_path;

		} catch ( Exception $e ) {
			error_log( 'AA Customers: PDF generation failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Generate purchase receipt PDF
	 *
	 * @param object $purchase Purchase object.
	 * @param object $member Member object.
	 * @param object $product Product object.
	 * @return array|WP_Error Array with 'file_path' and 'filename' or WP_Error.
	 */
	public function generate_receipt( $purchase, $member, $product ) {
		try {
			$html = $this->build_receipt_html( $purchase, $member, $product );

			$filename  = 'receipt-' . $purchase->id . '-' . date( 'Ymd' ) . '.pdf';
			$file_path = $this->generate_pdf( $html, $filename );

			if ( ! $file_path ) {
				return new WP_Error( 'pdf_failed', 'Failed to generate receipt PDF' );
			}

			return array(
				'file_path' => $file_path,
				'filename'  => $filename,
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'pdf_exception', $e->getMessage() );
		}
	}

	/**
	 * Build receipt HTML
	 *
	 * @param object $purchase Purchase object.
	 * @param object $member Member object.
	 * @param object $product Product object.
	 * @return string HTML content.
	 */
	private function build_receipt_html( $purchase, $member, $product ) {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		$purchase_date = wp_date( 'j F Y', strtotime( $purchase->purchase_date ) );
		$amount        = number_format( $purchase->amount, 2 );
		$donation      = number_format( $purchase->donation_amount, 2 );
		$total         = number_format( $purchase->total_amount, 2 );

		$html = '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		body {
			font-family: Arial, sans-serif;
			font-size: 12px;
			line-height: 1.5;
			color: #333;
			margin: 40px;
		}
		.header {
			border-bottom: 2px solid #333;
			padding-bottom: 20px;
			margin-bottom: 30px;
		}
		.header h1 {
			margin: 0;
			font-size: 24px;
		}
		.receipt-number {
			color: #666;
			margin-top: 5px;
		}
		.details {
			margin-bottom: 30px;
		}
		.details table {
			width: 100%;
		}
		.details td {
			padding: 5px 0;
			vertical-align: top;
		}
		.details .label {
			color: #666;
			width: 150px;
		}
		.items {
			margin-bottom: 30px;
		}
		.items table {
			width: 100%;
			border-collapse: collapse;
		}
		.items th, .items td {
			padding: 10px;
			text-align: left;
			border-bottom: 1px solid #ddd;
		}
		.items th {
			background: #f5f5f5;
		}
		.items .amount {
			text-align: right;
		}
		.totals {
			text-align: right;
			margin-bottom: 30px;
		}
		.totals table {
			margin-left: auto;
		}
		.totals td {
			padding: 5px 10px;
		}
		.totals .label {
			color: #666;
		}
		.totals .total-row {
			font-weight: bold;
			font-size: 14px;
			border-top: 2px solid #333;
		}
		.footer {
			margin-top: 50px;
			padding-top: 20px;
			border-top: 1px solid #ddd;
			color: #666;
			font-size: 10px;
		}
	</style>
</head>
<body>
	<div class="header">
		<h1>' . esc_html( $site_name ) . '</h1>
		<div class="receipt-number">Receipt #' . esc_html( $purchase->id ) . '</div>
	</div>

	<div class="details">
		<table>
			<tr>
				<td class="label">Date:</td>
				<td>' . esc_html( $purchase_date ) . '</td>
			</tr>
			<tr>
				<td class="label">Bill To:</td>
				<td>' . esc_html( $member->full_name ) . '<br>' . esc_html( $member->email ) . '</td>
			</tr>
			<tr>
				<td class="label">Payment Status:</td>
				<td>' . ucfirst( esc_html( $purchase->payment_status ) ) . '</td>
			</tr>
		</table>
	</div>

	<div class="items">
		<table>
			<thead>
				<tr>
					<th>Description</th>
					<th class="amount">Amount</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>' . esc_html( $product->name ) . '</td>
					<td class="amount">£' . esc_html( $amount ) . '</td>
				</tr>';

		if ( $purchase->donation_amount > 0 ) {
			$html .= '
				<tr>
					<td>Donation</td>
					<td class="amount">£' . esc_html( $donation ) . '</td>
				</tr>';
		}

		$html .= '
			</tbody>
		</table>
	</div>

	<div class="totals">
		<table>
			<tr>
				<td class="label">Subtotal:</td>
				<td>£' . esc_html( $amount ) . '</td>
			</tr>';

		if ( $purchase->donation_amount > 0 ) {
			$html .= '
			<tr>
				<td class="label">Donation:</td>
				<td>£' . esc_html( $donation ) . '</td>
			</tr>';
		}

		$html .= '
			<tr class="total-row">
				<td class="label">Total:</td>
				<td>£' . esc_html( $total ) . '</td>
			</tr>
		</table>
	</div>

	<div class="footer">
		<p>Thank you for your purchase.</p>
		<p>' . esc_html( $site_name ) . ' | ' . esc_url( $site_url ) . '</p>
	</div>
</body>
</html>';

		return $html;
	}

	/**
	 * Stream PDF to browser (for direct download)
	 *
	 * @param string $html HTML content.
	 * @param string $filename Filename for download.
	 * @return void
	 */
	public function stream_pdf( $html, $filename ) {
		try {
			$autoload = AA_CUSTOMERS_PLUGIN_DIR . 'vendor/autoload.php';
			if ( ! file_exists( $autoload ) ) {
				wp_die( 'PDF library not available.' );
			}

			require_once $autoload;

			$options = new \Dompdf\Options();
			$options->set( 'isRemoteEnabled', true );
			$options->set( 'isHtml5ParserEnabled', true );

			$dompdf = new \Dompdf\Dompdf( $options );
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();

			// Stream to browser.
			$dompdf->stream( sanitize_file_name( $filename ), array( 'Attachment' => true ) );
			exit;

		} catch ( Exception $e ) {
			wp_die( 'Failed to generate PDF: ' . esc_html( $e->getMessage() ) );
		}
	}
}
