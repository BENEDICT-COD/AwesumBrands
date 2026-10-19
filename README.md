# AweSum Brands

AweSum Brands is a small-business branding and print storefront. Customers can browse products and previous work, request custom quotes, place orders, and contact the business. Administrators manage the catalogue, homepage content, quotes, messages, and orders from the admin panel.

## Features

- **Homepage and business information:** brand landing page, editable company contact details, homepage statistics, and promotional content.
- **Marketplace:** product catalogue backed by the database; administrators can add products and images or deactivate listings.
- **Cart and checkout:** cart stored in the visitor's browser, authenticated order creation, and server-calculated product prices, 15% VAT, and totals.
- **Hosted payments:** PayFast checkout handoff and a payment notification endpoint. Card details are entered on the payment provider's site, not on this website.
- **Accounts:** email/password registration and sign-in, optional Google sign-in, profile and notification preferences, password change, and email password reset.
- **Custom quotes:** customers submit a product/design brief and optional image; administrators set a response, design draft, and price; customers can accept the design or request changes. Accepted quotes can be added to an order.
- **Previous work and adverts:** public project gallery and adverts maintained by administrators.
- **Orders:** customers view their own orders and payment status; administrators can view and update orders.
- **Messages:** visitors and customers can contact the business; customers and administrators can view conversations, and administrators can reply.
- **Admin panel:** manage orders, products, adverts, projects, homepage content/statistics, customer accounts, quotes, and messages.
- **Legal and account pages:** terms, privacy, returns, account settings, sign-in, sign-up, and password recovery.

## Technology

- PHP API and server-side sessions
- MySQL or MariaDB with PDO
- Plain HTML, CSS, and JavaScript (no frontend framework or package manager required)
- Node.js built-in test runner for JavaScript and HTTP tests
- GitHub Actions for automated syntax and test checks

## Repository structure

```text
.
├── index.html                 # Homepage
├── marketplace.html           # Product catalogue
├── cart.html                  # Browser-side cart
├── checkout.html              # Delivery and payment handoff
├── quote.html                 # Custom quote request and review
├── orders.html                # Customer order history
├── messages.html              # Customer message history
├── admin.html                 # Administrator dashboard
├── login.html                 # Sign-in and Google sign-in
├── signup.html                # Account registration
├── settings.html              # Account profile and preferences
├── forgot-password.html       # Password reset request
├── reset-password.html        # Password reset completion
├── change-password.html       # Signed-in password change
├── terms.html                 # Terms and conditions
├── privacy.html               # Privacy policy
├── returns.html               # Returns and refunds policy
├── api.php                    # JSON API and application/database operations
├── payment-webhook.php        # PayFast ITN payment notification handler
├── config.php                 # PHP requirements, database and integration configuration
├── extras.php                 # Email, password-reset, Google token and quote helpers
├── app.js                     # Shared browser-side application logic and API client
├── business-info.js           # Loads business contact details from the API
├── awesumj5e6x4_awesum.sql    # MySQL/MariaDB schema dump (includes INSERT data)
├── images/                    # Site logo and homepage imagery
├── tests/                     # PHP and Node.js unit, system, performance and stress tests
└── .github/workflows/
    └── automated-tests.yml    # CI workflow
```

The pages are regular HTML documents and share application behavior through `app.js`. `api.php` is the main server-side entry point; browser requests use `api.php?action=<action>` and send or receive JSON. `payment-webhook.php` is called separately by PayFast.

## Requirements

- PHP 7.4 or newer. The CI workflow currently tests on PHP 8.2.
- PHP extensions: `pdo_mysql`, `mbstring`, and `json`. Enable `curl` for PayFast notifications and Google sign-in.
- MySQL or MariaDB.
- Apache with `mod_rewrite` and `mod_headers` if using the included `htaccess` rules.
- Node.js 20 or newer to run the test suite (no npm dependencies are required).

## Local setup

1. Clone the repository and configure a MySQL/MariaDB database.
2. Import `awesumj5e6x4_awesum.sql` into that database using a database client such as phpMyAdmin or the MySQL command-line client. **Review and sanitize the dump before sharing or using it outside a trusted development environment:** it contains `INSERT` records as well as schema definitions.
3. Configure the database connection used by `config.php` with the environment variables `AWESUM_DB_HOST`, `AWESUM_DB_PORT`, `AWESUM_DB_NAME`, `AWESUM_DB_USER`, and `AWESUM_DB_PASS`. Do not commit database credentials.
4. Configure optional integrations in the server-side configuration: `AWESUM_GOOGLE_CLIENT_ID` for Google sign-in, `AWESUM_MAIL_FROM` for outgoing mail, and the PayFast merchant settings in `config.php`. Set the correct payment mode and valid credentials before enabling payments; never publish merchant secrets.
5. Serve the repository root with PHP's built-in development server:

   ```sh
   php -S 127.0.0.1:8080 -t .
   ```

6. Open `http://127.0.0.1:8080/index.html`.

Database access is needed for API-backed features. In a local setup, PHP's built-in server does not apply the Apache-specific rewrite, HTTPS, or security-header rules in `htaccess`.

## API overview

All actions below are served by `api.php?action=<action>`. JSON request bodies are used for writes. The API uses same-origin PHP sessions; actions marked **signed-in** require an authenticated user, and **admin** actions require an administrator account. Public read actions expose active public content.

| Action | Method | Access | Purpose |
|---|---|---|---|
| `signup` | POST | Public | Create a customer account |
| `login` | POST | Public | Sign in with email and password |
| `google-login` | POST | Public | Verify Google credentials and sign in |
| `logout` | POST | Signed-in | End the current session |
| `session` | GET | Public | Return the current user and admin status |
| `auth-config` | GET | Public | Return public authentication configuration |
| `profile` | GET, PUT | Signed-in | Read or update the current user's profile |
| `preferences` | GET, PUT | Signed-in | Read or update notification preferences |
| `forgot-password` | POST | Public | Request a time-limited password reset email |
| `reset-password` | POST | Public | Set a new password using a reset token |
| `change-password` | POST | Signed-in | Change the current user's password |
| `customers` | GET | Admin | List customer accounts |
| `admin-reset-password` | POST | Admin | Send a customer a password-reset email |
| `products` | GET | Public | List active marketplace products |
| `products` | POST, DELETE | Admin | Add a product or deactivate a product |
| `stats` | GET | Public | Read homepage statistics |
| `stats` | PUT | Admin | Update homepage statistics |
| `site-content` | GET | Public | Read business contact details |
| `site-content` | PUT | Admin | Update business contact details |
| `projects` | GET | Public | List active previous-work projects |
| `projects` | POST, DELETE | Admin | Add a project or deactivate a project |
| `adverts` | GET | Public | List active adverts |
| `adverts` | POST | Admin | Add an advert |
| `advert` | DELETE | Admin | Deactivate an advert |
| `quote` | POST | Signed-in | Submit a custom quote request |
| `quotes` | GET | Signed-in | List the user's quotes; admins can list all quotes |
| `quote-status` | PUT | Admin | Update quote status, response, price, or design draft |
| `quote-client-review` | PUT | Signed-in | Accept a quote or request changes to the design |
| `quote` | DELETE | Signed-in | Delete an eligible quote |
| `message` | POST | Public or signed-in | Send a message to the business |
| `messages` | GET | Signed-in | List the user's messages; admins can list all messages |
| `message-reply` | PUT | Admin | Reply to a message |
| `admin-message` | POST | Admin | Send a message to a customer |
| `message` | DELETE | Signed-in | Delete an owned message; admins can delete messages |
| `order` | POST | Signed-in | Create an order from active products and accepted quotes |
| `orders` | GET | Signed-in | List the user's orders; admins can list all orders |
| `order-status` | PUT | Admin | Update an order's status |
| `payfast-checkout` | POST | Signed-in | Create the signed PayFast checkout handoff for a pending order |
| `schema-check` | GET | Admin | Check the application's expected database tables and columns |

Successful API responses are JSON. Errors generally include an `error` message and an appropriate HTTP status. Product prices and order totals are recalculated on the server; the browser does not determine the payable amount. The server limits order size and quantities and applies VAT using whole-cent arithmetic.

PayFast sends payment notifications to `payment-webhook.php` using an ITN POST. The handler validates the signature, order reference, amount, and PayFast confirmation before marking a pending order as paid. A working cURL extension and correctly configured PayFast credentials are required.

## Data model and tables

The main storefront data is stored in MySQL/MariaDB. The checked-in SQL file is a database dump, not a migration framework. Some tables/columns are also created or added by the application when first needed.

| Table | Purpose |
|---|---|
| `users` | Storefront customer and administrator accounts, password hashes, company and role |
| `products` | Marketplace products, prices, image paths, and active/inactive status |
| `orders` | Order reference, customer, JSON-encoded line items, total, delivery address and status |
| `quotes` | Customer briefs, design images/drafts, review decisions, quoted price and linked order |
| `messages` | Customer/admin message threads, replies, sender and status |
| `adverts` | Homepage or promotional advert media and active status |
| `projects` | Previous-work gallery items, categories, image paths and display size |
| `site_settings` | Key/value JSON settings for homepage statistics, business content, and user notification preferences |
| `rate_limits` | Request-attempt timestamps used for rate limits |
| `password_resets` | Hashed, expiring, single-use password reset tokens |

`site_settings`, `password_resets`, and `projects` are created automatically if missing. The application also adds `quoted_price` and `order_reference` to older `quotes` tables when needed.

The dump additionally contains `user`, `job`, `asset`, `reflection`, `site_photo`, and `team_member`. These are separate/older operational or field-work tables; the current storefront API uses `users` (plural) for website accounts and does not expose CRUD actions for those field-work tables.

**Images:** the API validates uploaded image content and stores files under `uploads/images/`; database fields contain the relative image path. The upload directory must be writable by PHP. Back up uploaded files as well as the database.

## Browser-side data

`app.js` uses `localStorage` keys prefixed with `awesum_` for cached page data, including the cart and displayed content. This is browser storage, not the authoritative account, product, order, or quote database. Authentication is maintained by the server-side PHP session cookie. The API client sends same-origin credentials and refreshes session state when requests indicate that a session has expired.

## Testing

PHP unit tests and JavaScript unit tests do not need a database. HTTP system, performance, and stress tests need the local PHP server running on `127.0.0.1:8080`.

In one terminal, start the server from the repository root:

```sh
php -S 127.0.0.1:8080 -t .
```

In another terminal, run the checks from the repository root:

```sh
php tests/unit.php
node --test tests/unit.test.cjs
node --test tests/system.test.cjs
node --test tests/performance.test.cjs
node --test tests/stress.test.cjs
```

The CI workflow also checks PHP and JavaScript syntax. The system tests check that pages and locally referenced assets are available; performance tests enforce a 2-second 95th-percentile ceiling for the homepage and shared script; stress tests issue 80 concurrent requests with a 20-second completion limit.

## Deployment notes

- Keep database credentials, Google client configuration, and PayFast merchant credentials private. Use server environment or deployment-specific configuration; do not commit secrets.
- Use HTTPS in production. The included `htaccess` redirects to HTTPS, disables directory listings, hides `.git` and `docs`, denies access to database dumps and several private file types, and sets selected security headers. These rules require compatible Apache modules and an active TLS certificate.
- Configure a writable uploads directory and include it in backups.
- Configure mail delivery if password-reset emails and quote notifications are required.
- Confirm PayFast mode, merchant credentials, return/cancel URLs, and notification URL before accepting live payments.
- The SQL dump contains row data. Do not publish a database dump containing real customer, account, or operational information; export a sanitized schema/seed dataset for public use.
