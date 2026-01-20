# AA Customers

Membership management, event sales, and practitioner directory plugin for WordPress.

Built on the Project Phoenix architecture.

## Features

- **Membership Sales** - Annual memberships with Stripe subscriptions
- **Event Sales** - One-time event registrations
- **Practitioner Directory** - Public listing of active members
- **Member Dashboard** - Frontend area for members
- **CRM Admin** - Backend for staff to manage members
- **Stripe Integration** - Payments and subscriptions
- **Xero Integration** - Accounting sync (invoices and contacts)
- **PDF Receipts** - Automated receipt generation

## Requirements

- PHP 7.4+
- WordPress 5.0+
- MySQL 5.7+ (separate database recommended)
- Composer (for dependencies)

## Installation

1. Clone to your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone [repository-url] aa-customers
   ```

2. Install dependencies:
   ```bash
   cd aa-customers
   composer install
   ```

3. Add database configuration to `wp-config.php`:
   ```php
   define('AA_CUSTOMER_DB_HOST', 'localhost');
   define('AA_CUSTOMER_DB_USER', 'your_username');
   define('AA_CUSTOMER_DB_PASS', 'your_password');
   define('AA_CUSTOMER_DB_NAME', 'aa_customer');
   define('AA_CUSTOMER_DB_PREFIX', 'aac_');
   ```

4. Activate the plugin in WordPress admin

5. Configure Stripe and Xero API keys in **AA Customers → Settings**

## Database Tables

| Table | Purpose |
|-------|---------|
| `aac_members` | Member profiles linked to WP users |
| `aac_products` | Memberships and events for sale |
| `aac_events` | Event metadata (dates, locations) |
| `aac_purchases` | All transactions |
| `aac_registrations` | Event registrations |
| `aac_options` | Plugin settings |
| `aac_zap` | Secure configuration storage |

## Configuration

### Stripe Setup

1. Create products in Stripe Dashboard
2. Add API keys in Settings → Stripe
3. Configure webhook endpoint: `https://yoursite.com/wp-json/aa-customers/v1/stripe-webhook`

### Xero Setup

1. Create app at developer.xero.com
2. Add OAuth2 credentials in Settings → Xero
3. Connect and authorize

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test
```

## License

Proprietary - All rights reserved.

## Author

Ayhan Alman
