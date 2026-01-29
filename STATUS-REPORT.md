# AA Customers Plugin - Status Report

**Date:** January 26, 2026  
**Version:** 1.0.0  
**Repository:** github.com/aametrics/aa-customers

---

## Executive Summary

The AA Customers CRM plugin is a comprehensive WordPress membership and customer management system with integrated Stripe payments and Xero accounting sync. The plugin is functional and deployed on the production server at ukagp.org/join.

---

## What's Built

### 1. Core Infrastructure

| Component | Status | Description |
|-----------|--------|-------------|
| Separate Database | ✅ Complete | Uses `ukagp_crm` database with `crm_` prefix, isolated from WordPress |
| Database Schema | ✅ Complete | 8 tables: members, products, events, purchases, registrations, options, zap, forms, form_fields |
| Repository Pattern | ✅ Complete | Clean data access layer for all entities |
| Service Layer | ✅ Complete | Business logic encapsulation |

### 2. Admin Interface

| Feature | Status | Location |
|---------|--------|----------|
| Dashboard | ✅ Complete | AA Customers → Dashboard |
| Members Management | ✅ Complete | AA Customers → Members |
| Products Management | ✅ Complete | AA Customers → Products |
| Events Management | ✅ Complete | AA Customers → Events |
| Purchases/Transactions | ✅ Complete | AA Customers → Purchases |
| Data Collection Forms | ✅ Complete | AA Customers → Data Collection |
| Settings (General/Stripe/Xero) | ✅ Complete | AA Customers → Settings |

### 3. Stripe Integration

| Feature | Status | Notes |
|---------|--------|-------|
| API Configuration | ✅ Complete | Test/Live keys in settings |
| Checkout Sessions | ✅ Complete | Creates hosted checkout |
| Webhook Handling | ✅ Complete | Processes `checkout.session.completed` |
| Customer Management | ✅ Complete | Creates/retrieves Stripe customers |
| Subscription Support | ✅ Complete | Handles recurring payments |
| Configurable Fields | ✅ Complete | Toggle address/phone collection in settings |

### 4. Xero Integration

| Feature | Status | Notes |
|---------|--------|-------|
| OAuth2 Authentication | ✅ Complete | Connect/disconnect in settings |
| Token Management | ✅ Complete | Auto-refresh tokens |
| Contact Sync | ✅ Complete | Creates/updates Xero contacts |
| Invoice Creation | ✅ Complete | Creates invoices with line items |
| Payment Recording | ✅ Complete | Records payments against invoices |
| Tax Type Configuration | ✅ Complete | Configurable VAT/tax handling |

### 5. Frontend

| Feature | Status | Shortcode |
|---------|--------|-----------|
| Join Page | ✅ Complete | `[aa_join]` |
| Member Dashboard | ✅ Complete | `[aa_member_dashboard]` |
| Practitioner Directory | ✅ Complete | `[aa_practitioner_directory]` |
| Events List | ✅ Complete | `[aa_events]` |
| Single Event | ✅ Complete | `[aa_event id="X"]` |
| Checkout Success | ✅ Complete | `[aa_checkout_success]` |
| Multi-Step Checkout | ✅ Complete | `[aa_checkout product_id="X"]` |

### 6. Data Collection Forms (NEW)

| Feature | Status | Description |
|---------|--------|-------------|
| Form Builder | ✅ Complete | Create forms with custom fields |
| Field Mapping | ✅ Complete | Map to `crm_members` columns |
| Display Types | ✅ Complete | Text, textarea, dropdown, radio, checkbox, date, email, phone, number |
| Auto-Detection | ✅ Complete | Automatically suggests field types from DB schema |
| Drag & Drop Ordering | ✅ Complete | Reorder fields visually |
| Product Linking | ✅ Complete | Link forms to specific products or use as defaults |
| Form Duplication | ✅ Complete | Clone forms for versioning |

### 7. Checkout Flow (NEW)

| Step | Status | Description |
|------|--------|-------------|
| Email Check | ✅ Complete | Detects existing accounts |
| Login Flow | ✅ Complete | For returning users |
| Registration | ✅ Complete | Creates WP user + member profile |
| Custom Fields | ✅ Complete | Renders Data Collection form fields |
| Pending Status | ✅ Complete | New members set to "pending" for review |
| Stripe Redirect | ✅ Complete | Sends to payment |
| Success Page | ✅ Complete | Configurable return URL |
| Xero Sync | ✅ Complete | Triggered by webhook |

---

## Database Tables

```
ukagp_crm.crm_members        - Member profiles
ukagp_crm.crm_products       - Memberships and events for sale
ukagp_crm.crm_events         - Event metadata
ukagp_crm.crm_purchases      - Transaction history
ukagp_crm.crm_registrations  - Event registrations
ukagp_crm.crm_options        - Plugin options
ukagp_crm.crm_zap            - Secure configuration storage
ukagp_crm.crm_forms          - Form definitions (NEW)
ukagp_crm.crm_form_fields    - Form field configurations (NEW)
```

---

## Configuration Required

### Settings → General
- Checkout Success URL: `/checkout-success`
- Member Dashboard URL: `/member-dashboard`

### Settings → Stripe
- Secret Key (test/live)
- Webhook Secret
- Collect Billing Address: Toggle
- Collect Phone Number: Toggle

### Settings → Xero
- Client ID & Secret (from Xero Developer Portal)
- Sales Account Code
- Bank Account Code (for Stripe payments)
- Tax Type: No Tax / Standard VAT / Reduced / Zero-Rated

---

## Deployment Notes

### Files to Upload (SFTP)
All files in `/wp-content/plugins/aa-customers/` EXCEPT:
- `vendor/` folder must be uploaded manually (excluded from SFTP sync)

### Server Requirements
- PHP 7.4+
- Composer dependencies installed (`vendor/` folder)
- Separate MySQL database (`ukagp_crm`)

### Production URL
- Main site: https://ukagp.org/join/
- Admin: https://ukagp.org/join/wp-admin/admin.php?page=aa-customers

---

## What's Next (Pending)

### 1. Messaging System
- [ ] Email templates admin interface
- [ ] System message management (confirmations, reminders)
- [ ] Password reset integration
- [ ] Copy templates from aa-planner

### 2. Member Status Workflow
- [ ] Additional status types beyond "pending"
- [ ] Admin review/approval workflow
- [ ] Status change notifications

### 3. PDF Generation
- [ ] Invoice PDFs
- [ ] Membership certificates
- [ ] Integration with dompdf (already in composer.json)

### 4. Directory Enhancements
- [ ] Advanced practitioner search/filters
- [ ] Photo uploads
- [ ] Map integration

### 5. Event Features
- [ ] Waitlist management
- [ ] Capacity tracking
- [ ] Reminder emails

---

## File Structure

```
aa-customers/
├── aa-customers.php              # Main plugin file
├── composer.json                 # Dependencies
├── README.md                     # Installation instructions
├── STATUS-REPORT.md              # This file
│
├── admin/
│   ├── class-admin.php           # Main admin controller
│   ├── members/                  # Member management
│   ├── products/                 # Product management
│   ├── events/                   # Event management
│   ├── purchases/                # Transaction history
│   ├── forms/                    # Data Collection (NEW)
│   ├── settings/                 # Plugin settings
│   └── shared/                   # Base admin class
│
├── core/
│   ├── database/
│   │   ├── class-db-connection.php
│   │   └── class-schema.php
│   ├── repositories/
│   │   ├── class-members-repository.php
│   │   ├── class-products-repository.php
│   │   ├── class-events-repository.php
│   │   ├── class-purchases-repository.php
│   │   ├── class-registrations-repository.php
│   │   └── class-forms-repository.php  # NEW
│   └── services/
│       ├── class-stripe-service.php
│       ├── class-xero-service.php
│       ├── class-membership-service.php
│       └── class-zap-storage.php
│
├── frontend/
│   ├── class-shortcodes.php      # All frontend shortcodes
│   └── class-ajax-handler.php    # AJAX endpoints
│
└── assets/
    ├── css/
    │   ├── admin.css
    │   └── frontend.css
    └── js/
        ├── admin.js
        └── frontend.js
```

---

## Git History (Recent)

```
9499b96 Add Data Collection forms system and multi-step checkout flow
54d9a12 Add configurable Stripe checkout fields and Xero tax type
b2069c7 Add configurable checkout success URL in settings
0ad41fb Add Xero integration for automatic invoice sync
b26a9d7 Add billing address collection and fix admin form issues
3b082aa Fix production deployment issues
```

---

## Known Issues

1. **WordPress Notices**: The Salient theme triggers `_load_textdomain_just_in_time` warnings (theme issue, not plugin)
2. **Meta.php Warnings**: Some `Undefined array key 0` warnings from WordPress core (unrelated to plugin)

---

## Testing Checklist

- [x] Plugin activates without errors
- [x] Database tables created correctly
- [x] Settings save correctly
- [x] Products can be created/edited
- [x] Stripe checkout creates session
- [x] Stripe webhook processes payments
- [x] Xero connection works
- [x] Xero receives invoices
- [x] Data Collection forms can be created
- [x] Form fields can be added/reordered
- [ ] Full checkout flow tested end-to-end with new user
- [ ] Full checkout flow tested with returning user
- [ ] Email notifications sent (pending Messaging system)

---

*Report generated: January 26, 2026*
