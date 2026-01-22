<?php
/**
 * Xero Service Class
 *
 * Handles Xero API integration including OAuth2 authentication,
 * contact management, and invoice creation.
 *
 * @package AA_Customers
 * @subpackage Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use XeroAPI\XeroPHP\Api\AccountingApi;
use XeroAPI\XeroPHP\Api\IdentityApi;
use XeroAPI\XeroPHP\Configuration;
use XeroAPI\XeroPHP\Models\Accounting\Contact;
use XeroAPI\XeroPHP\Models\Accounting\Contacts;
use XeroAPI\XeroPHP\Models\Accounting\Invoice;
use XeroAPI\XeroPHP\Models\Accounting\Invoices;
use XeroAPI\XeroPHP\Models\Accounting\LineItem;
use XeroAPI\XeroPHP\Models\Accounting\Payment;
use XeroAPI\XeroPHP\Models\Accounting\Payments;
use XeroAPI\XeroPHP\Models\Accounting\Address;
use XeroAPI\XeroPHP\Models\Accounting\Phone;

/**
 * Class AA_Customers_Xero_Service
 */
class AA_Customers_Xero_Service {

	/**
	 * OAuth2 scopes required
	 */
	const SCOPES = 'openid profile email accounting.transactions accounting.contacts offline_access';

	/**
	 * Xero OAuth2 authorization URL
	 */
	const AUTH_URL = 'https://login.xero.com/identity/connect/authorize';

	/**
	 * Xero OAuth2 token URL
	 */
	const TOKEN_URL = 'https://identity.xero.com/connect/token';

	/**
	 * Client ID
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * Client Secret
	 *
	 * @var string
	 */
	private $client_secret;

	/**
	 * Access Token
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Refresh Token
	 *
	 * @var string
	 */
	private $refresh_token;

	/**
	 * Token Expiry
	 *
	 * @var int
	 */
	private $token_expires;

	/**
	 * Tenant ID (Xero Organization ID)
	 *
	 * @var string
	 */
	private $tenant_id;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->client_id     = AA_Customers_Zap_Storage::get( 'xero_client_id', '' );
		$this->client_secret = AA_Customers_Zap_Storage::get( 'xero_client_secret', '' );
		$this->access_token  = AA_Customers_Zap_Storage::get( 'xero_access_token', '' );
		$this->refresh_token = AA_Customers_Zap_Storage::get( 'xero_refresh_token', '' );
		$this->token_expires = (int) AA_Customers_Zap_Storage::get( 'xero_token_expires', 0 );
		$this->tenant_id     = AA_Customers_Zap_Storage::get( 'xero_tenant_id', '' );
	}

	/**
	 * Check if Xero is configured
	 *
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( $this->client_id ) && ! empty( $this->client_secret );
	}

	/**
	 * Check if connected (has valid tokens)
	 *
	 * @return bool
	 */
	public function is_connected() {
		return ! empty( $this->access_token ) && ! empty( $this->tenant_id );
	}

	/**
	 * Get authorization URL for OAuth2 flow
	 *
	 * @return string Authorization URL.
	 */
	public function get_authorization_url() {
		$state = wp_create_nonce( 'xero_oauth' );
		AA_Customers_Zap_Storage::set( 'xero_oauth_state', $state, 'config' );

		$params = array(
			'response_type' => 'code',
			'client_id'     => $this->client_id,
			'redirect_uri'  => $this->get_redirect_uri(),
			'scope'         => self::SCOPES,
			'state'         => $state,
		);

		return self::AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Get redirect URI
	 *
	 * @return string
	 */
	public function get_redirect_uri() {
		return admin_url( 'admin.php?page=aa-customers-settings&tab=xero&xero_callback=1' );
	}

	/**
	 * Handle OAuth2 callback
	 *
	 * @param string $code Authorization code.
	 * @param string $state State parameter for verification.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function handle_callback( $code, $state ) {
		// Verify state.
		$stored_state = AA_Customers_Zap_Storage::get( 'xero_oauth_state', '' );
		if ( empty( $stored_state ) || $state !== $stored_state ) {
			return new WP_Error( 'invalid_state', 'OAuth state mismatch. Please try again.' );
		}

		// Exchange code for tokens.
		$tokens = $this->exchange_code_for_tokens( $code );
		if ( is_wp_error( $tokens ) ) {
			return $tokens;
		}

		// Store tokens.
		$this->store_tokens( $tokens );

		// Get tenant ID.
		$tenant_result = $this->fetch_tenant_id();
		if ( is_wp_error( $tenant_result ) ) {
			return $tenant_result;
		}

		// Clear state.
		AA_Customers_Zap_Storage::delete( 'xero_oauth_state' );

		return true;
	}

	/**
	 * Exchange authorization code for tokens
	 *
	 * @param string $code Authorization code.
	 * @return array|WP_Error Tokens or error.
	 */
	private function exchange_code_for_tokens( $code ) {
		$response = wp_remote_post( self::TOKEN_URL, array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_secret ),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body' => array(
				'grant_type'   => 'authorization_code',
				'code'         => $code,
				'redirect_uri' => $this->get_redirect_uri(),
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'AA Customers Xero: Token exchange failed - ' . $response->get_error_message() );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			error_log( 'AA Customers Xero: Token exchange error - ' . $body['error'] );
			return new WP_Error( 'token_error', $body['error_description'] ?? $body['error'] );
		}

		return $body;
	}

	/**
	 * Refresh access token
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function refresh_access_token() {
		if ( empty( $this->refresh_token ) ) {
			return new WP_Error( 'no_refresh_token', 'No refresh token available.' );
		}

		$response = wp_remote_post( self::TOKEN_URL, array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_secret ),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body' => array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $this->refresh_token,
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'AA Customers Xero: Token refresh failed - ' . $response->get_error_message() );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			error_log( 'AA Customers Xero: Token refresh error - ' . $body['error'] );
			// Clear tokens if refresh failed.
			$this->disconnect();
			return new WP_Error( 'refresh_error', $body['error_description'] ?? $body['error'] );
		}

		$this->store_tokens( $body );
		return true;
	}

	/**
	 * Store tokens
	 *
	 * @param array $tokens Token response.
	 */
	private function store_tokens( $tokens ) {
		$this->access_token  = $tokens['access_token'];
		$this->refresh_token = $tokens['refresh_token'] ?? $this->refresh_token;
		$this->token_expires = time() + ( $tokens['expires_in'] ?? 1800 );

		AA_Customers_Zap_Storage::set( 'xero_access_token', $this->access_token, 'sensitive' );
		AA_Customers_Zap_Storage::set( 'xero_refresh_token', $this->refresh_token, 'sensitive' );
		AA_Customers_Zap_Storage::set( 'xero_token_expires', $this->token_expires, 'config' );
	}

	/**
	 * Fetch tenant ID from connections
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function fetch_tenant_id() {
		$response = wp_remote_get( 'https://api.xero.com/connections', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$connections = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $connections ) ) {
			return new WP_Error( 'no_tenants', 'No Xero organizations found.' );
		}

		// Use the first tenant.
		$this->tenant_id = $connections[0]['tenantId'];
		AA_Customers_Zap_Storage::set( 'xero_tenant_id', $this->tenant_id, 'config' );
		AA_Customers_Zap_Storage::set( 'xero_tenant_name', $connections[0]['tenantName'] ?? '', 'config' );

		return true;
	}

	/**
	 * Ensure we have a valid access token
	 *
	 * @return bool|WP_Error True if valid, WP_Error on failure.
	 */
	private function ensure_valid_token() {
		if ( ! $this->is_connected() ) {
			return new WP_Error( 'not_connected', 'Xero is not connected.' );
		}

		// Refresh token if expired or expiring in next 5 minutes.
		if ( $this->token_expires < ( time() + 300 ) ) {
			$refresh_result = $this->refresh_access_token();
			if ( is_wp_error( $refresh_result ) ) {
				return $refresh_result;
			}
		}

		return true;
	}

	/**
	 * Disconnect from Xero
	 */
	public function disconnect() {
		AA_Customers_Zap_Storage::delete( 'xero_access_token' );
		AA_Customers_Zap_Storage::delete( 'xero_refresh_token' );
		AA_Customers_Zap_Storage::delete( 'xero_token_expires' );
		AA_Customers_Zap_Storage::delete( 'xero_tenant_id' );
		AA_Customers_Zap_Storage::delete( 'xero_tenant_name' );

		$this->access_token  = '';
		$this->refresh_token = '';
		$this->token_expires = 0;
		$this->tenant_id     = '';
	}

	/**
	 * Get API configuration
	 *
	 * @return Configuration
	 */
	private function get_config() {
		$config = Configuration::getDefaultConfiguration();
		$config->setAccessToken( $this->access_token );
		return $config;
	}

	/**
	 * Create or update a contact in Xero
	 *
	 * @param array $contact_data Contact data.
	 * @return object|WP_Error Xero contact or error.
	 */
	public function create_or_update_contact( $contact_data ) {
		$token_check = $this->ensure_valid_token();
		if ( is_wp_error( $token_check ) ) {
			return $token_check;
		}

		try {
			$config = $this->get_config();
			$api    = new AccountingApi( new \GuzzleHttp\Client(), $config );

			// Check if contact exists by email.
			$existing = null;
			if ( ! empty( $contact_data['email'] ) ) {
				try {
					$existing_contacts = $api->getContacts(
						$this->tenant_id,
						null,
						'EmailAddress=="' . $contact_data['email'] . '"'
					);
					if ( $existing_contacts->getContacts() && count( $existing_contacts->getContacts() ) > 0 ) {
						$existing = $existing_contacts->getContacts()[0];
					}
				} catch ( \Exception $e ) {
					// Contact doesn't exist, will create new.
				}
			}

			// Build contact object.
			$contact = new Contact();
			$contact->setName( $contact_data['name'] ?? '' );
			$contact->setFirstName( $contact_data['first_name'] ?? '' );
			$contact->setLastName( $contact_data['last_name'] ?? '' );
			$contact->setEmailAddress( $contact_data['email'] ?? '' );

			// Add phone if available.
			if ( ! empty( $contact_data['phone'] ) ) {
				$phone = new Phone();
				$phone->setPhoneType( Phone::PHONE_TYPE_DEFAULT );
				$phone->setPhoneNumber( $contact_data['phone'] );
				$contact->setPhones( array( $phone ) );
			}

			// Add address if available.
			if ( ! empty( $contact_data['address'] ) ) {
				$address = new Address();
				$address->setAddressType( Address::ADDRESS_TYPE_POBOX );
				$address->setAddressLine1( $contact_data['address']['line1'] ?? '' );
				$address->setAddressLine2( $contact_data['address']['line2'] ?? '' );
				$address->setCity( $contact_data['address']['city'] ?? '' );
				$address->setRegion( $contact_data['address']['state'] ?? '' );
				$address->setPostalCode( $contact_data['address']['postal_code'] ?? '' );
				$address->setCountry( $contact_data['address']['country'] ?? '' );
				$contact->setAddresses( array( $address ) );
			}

			$contacts = new Contacts();
			$contacts->setContacts( array( $contact ) );

			if ( $existing ) {
				// Update existing contact.
				$contact->setContactId( $existing->getContactId() );
				$result = $api->updateContact( $this->tenant_id, $existing->getContactId(), $contacts );
			} else {
				// Create new contact.
				$result = $api->createContacts( $this->tenant_id, $contacts );
			}

			$created_contacts = $result->getContacts();
			if ( ! empty( $created_contacts ) ) {
				error_log( 'AA Customers Xero: Contact created/updated - ' . $created_contacts[0]->getContactId() );
				return $created_contacts[0];
			}

			return new WP_Error( 'contact_error', 'Failed to create contact in Xero.' );

		} catch ( \Exception $e ) {
			error_log( 'AA Customers Xero: Contact error - ' . $e->getMessage() );
			return new WP_Error( 'xero_error', $e->getMessage() );
		}
	}

	/**
	 * Create an invoice in Xero
	 *
	 * @param object $xero_contact Xero contact object.
	 * @param array  $invoice_data Invoice data.
	 * @return object|WP_Error Xero invoice or error.
	 */
	public function create_invoice( $xero_contact, $invoice_data ) {
		$token_check = $this->ensure_valid_token();
		if ( is_wp_error( $token_check ) ) {
			return $token_check;
		}

		try {
			$config = $this->get_config();
			$api    = new AccountingApi( new \GuzzleHttp\Client(), $config );

			// Build line items.
			$line_items = array();

			// Main product/service.
			$line_item = new LineItem();
			$line_item->setDescription( $invoice_data['description'] ?? 'Membership' );
			$line_item->setQuantity( 1 );
			$line_item->setUnitAmount( $invoice_data['amount'] ?? 0 );
			$line_item->setAccountCode( $invoice_data['account_code'] ?? '200' ); // Default sales account.
			$line_items[] = $line_item;

			// Add donation if present.
			if ( ! empty( $invoice_data['donation'] ) && $invoice_data['donation'] > 0 ) {
				$donation_item = new LineItem();
				$donation_item->setDescription( 'Donation' );
				$donation_item->setQuantity( 1 );
				$donation_item->setUnitAmount( $invoice_data['donation'] );
				$donation_item->setAccountCode( $invoice_data['donation_account_code'] ?? '200' );
				$line_items[] = $donation_item;
			}

			// Build invoice.
			$invoice = new Invoice();
			$invoice->setType( Invoice::TYPE_ACCREC ); // Accounts Receivable (Sales Invoice).
			$invoice->setContact( $xero_contact );
			$invoice->setLineItems( $line_items );
			$invoice->setDate( new \DateTime( $invoice_data['date'] ?? 'now' ) );
			$invoice->setDueDate( new \DateTime( $invoice_data['due_date'] ?? 'now' ) );
			$invoice->setReference( $invoice_data['reference'] ?? '' );
			$invoice->setStatus( Invoice::STATUS_AUTHORISED );
			$invoice->setCurrencyCode( $invoice_data['currency'] ?? 'GBP' );

			$invoices = new Invoices();
			$invoices->setInvoices( array( $invoice ) );

			$result = $api->createInvoices( $this->tenant_id, $invoices );

			$created_invoices = $result->getInvoices();
			if ( ! empty( $created_invoices ) ) {
				$xero_invoice = $created_invoices[0];
				error_log( 'AA Customers Xero: Invoice created - ' . $xero_invoice->getInvoiceId() );

				// If payment info provided, record payment.
				if ( ! empty( $invoice_data['paid'] ) && $invoice_data['paid'] ) {
					$this->record_payment( $xero_invoice, $invoice_data );
				}

				return $xero_invoice;
			}

			return new WP_Error( 'invoice_error', 'Failed to create invoice in Xero.' );

		} catch ( \Exception $e ) {
			error_log( 'AA Customers Xero: Invoice error - ' . $e->getMessage() );
			return new WP_Error( 'xero_error', $e->getMessage() );
		}
	}

	/**
	 * Record a payment against an invoice
	 *
	 * @param object $xero_invoice Xero invoice object.
	 * @param array  $payment_data Payment data.
	 * @return object|WP_Error Xero payment or error.
	 */
	public function record_payment( $xero_invoice, $payment_data ) {
		try {
			$config = $this->get_config();
			$api    = new AccountingApi( new \GuzzleHttp\Client(), $config );

			// Get payment account (bank account).
			$payment_account_code = $payment_data['payment_account_code'] ?? null;

			// If no payment account specified, try to get Stripe account.
			if ( empty( $payment_account_code ) ) {
				$payment_account_code = AA_Customers_Zap_Storage::get( 'xero_stripe_account_code', '090' );
			}

			$payment = new Payment();
			$payment->setInvoice( $xero_invoice );
			$payment->setAmount( $xero_invoice->getTotal() );
			$payment->setDate( new \DateTime( $payment_data['payment_date'] ?? 'now' ) );
			$payment->setReference( $payment_data['stripe_payment_id'] ?? 'Stripe Payment' );

			// Set the bank account.
			$account = new \XeroAPI\XeroPHP\Models\Accounting\Account();
			$account->setCode( $payment_account_code );
			$payment->setAccount( $account );

			$payments = new Payments();
			$payments->setPayments( array( $payment ) );

			$result = $api->createPayment( $this->tenant_id, $payment );

			error_log( 'AA Customers Xero: Payment recorded for invoice ' . $xero_invoice->getInvoiceId() );
			return $result;

		} catch ( \Exception $e ) {
			error_log( 'AA Customers Xero: Payment error - ' . $e->getMessage() );
			return new WP_Error( 'xero_error', $e->getMessage() );
		}
	}

	/**
	 * Sync a payment to Xero
	 *
	 * Called after Stripe webhook confirms payment.
	 *
	 * @param array $payment_data Payment data from Stripe/our DB.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function sync_payment( $payment_data ) {
		if ( ! $this->is_connected() ) {
			error_log( 'AA Customers Xero: Not connected, skipping sync.' );
			return new WP_Error( 'not_connected', 'Xero is not connected.' );
		}

		// Create/update contact.
		$contact_data = array(
			'name'       => $payment_data['customer_name'] ?? '',
			'first_name' => $payment_data['first_name'] ?? '',
			'last_name'  => $payment_data['last_name'] ?? '',
			'email'      => $payment_data['email'] ?? '',
			'phone'      => $payment_data['phone'] ?? '',
			'address'    => $payment_data['address'] ?? array(),
		);

		$xero_contact = $this->create_or_update_contact( $contact_data );
		if ( is_wp_error( $xero_contact ) ) {
			return $xero_contact;
		}

		// Create invoice.
		$invoice_data = array(
			'description'       => $payment_data['product_name'] ?? 'Membership',
			'amount'            => $payment_data['amount'] ?? 0,
			'donation'          => $payment_data['donation'] ?? 0,
			'currency'          => $payment_data['currency'] ?? 'GBP',
			'reference'         => $payment_data['stripe_payment_id'] ?? '',
			'date'              => $payment_data['date'] ?? date( 'Y-m-d' ),
			'due_date'          => $payment_data['date'] ?? date( 'Y-m-d' ),
			'paid'              => true,
			'stripe_payment_id' => $payment_data['stripe_payment_id'] ?? '',
		);

		$xero_invoice = $this->create_invoice( $xero_contact, $invoice_data );
		if ( is_wp_error( $xero_invoice ) ) {
			return $xero_invoice;
		}

		return true;
	}

	/**
	 * Test connection by fetching organization info
	 *
	 * @return array|WP_Error Organization info or error.
	 */
	public function test_connection() {
		$token_check = $this->ensure_valid_token();
		if ( is_wp_error( $token_check ) ) {
			return $token_check;
		}

		try {
			$config = $this->get_config();
			$api    = new AccountingApi( new \GuzzleHttp\Client(), $config );

			$org = $api->getOrganisations( $this->tenant_id );
			$organisations = $org->getOrganisations();

			if ( ! empty( $organisations ) ) {
				return array(
					'name'     => $organisations[0]->getName(),
					'legal'    => $organisations[0]->getLegalName(),
					'country'  => $organisations[0]->getCountryCode(),
					'timezone' => $organisations[0]->getTimezone(),
				);
			}

			return new WP_Error( 'org_error', 'Could not fetch organization info.' );

		} catch ( \Exception $e ) {
			error_log( 'AA Customers Xero: Connection test failed - ' . $e->getMessage() );
			return new WP_Error( 'xero_error', $e->getMessage() );
		}
	}
}
