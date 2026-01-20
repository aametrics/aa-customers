<?php
/**
 * Email Service
 *
 * Handles sending email notifications to members.
 * Supports variable replacement for personalization.
 *
 * Variables:
 * - {{member_name}} - Member's full name
 * - {{membership_type}} - Membership type name
 * - {{expiry_date}} - Membership expiry date
 * - {{renewal_link}} - Link to renew membership
 * - {{portal_link}} - Link to Stripe customer portal
 * - {{event_name}} - Event name (for event emails)
 * - {{event_date}} - Event date
 *
 * @package AA_Customers
 * @subpackage Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AA_Customers_Email_Service
 */
class AA_Customers_Email_Service {

	/**
	 * Send email to a member
	 *
	 * @param string $to Email address.
	 * @param string $subject Email subject.
	 * @param string $message Email body (HTML).
	 * @param array  $variables Variables for replacement.
	 * @return bool True on success.
	 */
	public function send( $to, $subject, $message, $variables = array() ) {
		// Replace variables in subject and message.
		$subject = $this->replace_variables( $subject, $variables );
		$message = $this->replace_variables( $message, $variables );

		// Get sender settings.
		$from_name    = AA_Customers_Zap_Storage::get( 'email_from_name', get_bloginfo( 'name' ) );
		$from_address = AA_Customers_Zap_Storage::get( 'email_from_address', get_option( 'admin_email' ) );

		// Set headers.
		$headers = array(
			'From: ' . $from_name . ' <' . $from_address . '>',
			'Content-Type: text/html; charset=UTF-8',
		);

		// Add BCC if enabled.
		$bcc_enabled = AA_Customers_Zap_Storage::get( 'email_bcc_enabled', false );
		$bcc_address = AA_Customers_Zap_Storage::get( 'email_bcc_address', '' );

		if ( $bcc_enabled && ! empty( $bcc_address ) && is_email( $bcc_address ) ) {
			$headers[] = 'Bcc: ' . $bcc_address;
		}

		// Wrap message in basic HTML template.
		$html_message = $this->wrap_in_template( $message );

		// Send email.
		$result = wp_mail( $to, $subject, $html_message, $headers );

		if ( $result ) {
			error_log( 'AA Customers: Email sent to ' . $to . ' - Subject: ' . $subject );
		} else {
			error_log( 'AA Customers: Failed to send email to ' . $to );
		}

		return $result;
	}

	/**
	 * Send welcome email to new member
	 *
	 * @param object $member Member object.
	 * @param object $product Product purchased.
	 * @return bool True on success.
	 */
	public function send_welcome_email( $member, $product ) {
		$template = AA_Customers_Zap_Storage::get( 'email_template_welcome', $this->get_default_welcome_template() );

		$variables = array(
			'member_name'     => $member->full_name,
			'membership_type' => $product->name,
			'expiry_date'     => wp_date( 'j F Y', strtotime( $member->membership_expiry ) ),
			'portal_link'     => home_url( '/member-dashboard' ),
		);

		$subject = AA_Customers_Zap_Storage::get( 'email_subject_welcome', 'Welcome to ' . get_bloginfo( 'name' ) . '!' );

		return $this->send( $member->email, $subject, $template, $variables );
	}

	/**
	 * Send renewal reminder email
	 *
	 * @param object $member Member object.
	 * @param int    $days_until_expiry Days until membership expires.
	 * @return bool True on success.
	 */
	public function send_renewal_reminder( $member, $days_until_expiry ) {
		$template = AA_Customers_Zap_Storage::get( 'email_template_renewal_reminder', $this->get_default_renewal_template() );

		$variables = array(
			'member_name'   => $member->full_name,
			'expiry_date'   => wp_date( 'j F Y', strtotime( $member->membership_expiry ) ),
			'days_until'    => $days_until_expiry,
			'renewal_link'  => home_url( '/join' ),
			'portal_link'   => home_url( '/member-dashboard' ),
		);

		$subject = AA_Customers_Zap_Storage::get( 'email_subject_renewal_reminder', 'Your membership expires soon' );

		return $this->send( $member->email, $subject, $template, $variables );
	}

	/**
	 * Send event registration confirmation
	 *
	 * @param object $member Member object.
	 * @param object $event Event object.
	 * @param object $purchase Purchase object.
	 * @return bool True on success.
	 */
	public function send_event_confirmation( $member, $event, $purchase ) {
		$template = AA_Customers_Zap_Storage::get( 'email_template_event_confirmation', $this->get_default_event_template() );

		$variables = array(
			'member_name' => $member->full_name,
			'event_name'  => $event->name,
			'event_date'  => wp_date( 'l, j F Y', strtotime( $event->event_date ) ),
			'event_time'  => $event->event_time ? wp_date( 'g:i A', strtotime( $event->event_time ) ) : '',
			'location'    => $event->location ?? 'TBC',
			'amount'      => '£' . number_format( $purchase->total_amount, 2 ),
		);

		$subject = 'Registration Confirmed: ' . $event->name;

		return $this->send( $member->email, $subject, $template, $variables );
	}

	/**
	 * Replace variables in template
	 *
	 * @param string $template Template text with {{variables}}.
	 * @param array  $variables Variables to replace.
	 * @return string Template with variables replaced.
	 */
	private function replace_variables( $template, $variables ) {
		foreach ( $variables as $key => $value ) {
			$template = str_replace( '{{' . $key . '}}', $value, $template );
		}

		return $template;
	}

	/**
	 * Wrap message in HTML template
	 *
	 * @param string $message Message body.
	 * @return string Full HTML email.
	 */
	private function wrap_in_template( $message ) {
		$site_name = get_bloginfo( 'name' );

		return '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen, Ubuntu, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
	' . wpautop( $message ) . '
	<hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
	<p style="font-size: 12px; color: #666;">' . esc_html( $site_name ) . '</p>
</body>
</html>';
	}

	/**
	 * Get default welcome email template
	 *
	 * @return string Default template.
	 */
	private function get_default_welcome_template() {
		return 'Dear {{member_name}},

Welcome to our community! Your {{membership_type}} is now active.

Your membership is valid until {{expiry_date}}.

You can access your member dashboard here: {{portal_link}}

If you have any questions, please don\'t hesitate to get in touch.

Best wishes,
The Team';
	}

	/**
	 * Get default renewal reminder template
	 *
	 * @return string Default template.
	 */
	private function get_default_renewal_template() {
		return 'Dear {{member_name}},

This is a friendly reminder that your membership will expire on {{expiry_date}} (in {{days_until}} days).

To continue enjoying your membership benefits, please renew here: {{renewal_link}}

If you have any questions, please don\'t hesitate to get in touch.

Best wishes,
The Team';
	}

	/**
	 * Get default event confirmation template
	 *
	 * @return string Default template.
	 */
	private function get_default_event_template() {
		return 'Dear {{member_name}},

Thank you for registering for {{event_name}}!

<strong>Event Details:</strong>
Date: {{event_date}}
Time: {{event_time}}
Location: {{location}}

Amount paid: {{amount}}

We look forward to seeing you there!

Best wishes,
The Team';
	}
}
