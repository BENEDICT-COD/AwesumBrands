# AweSum Brands

A branded-merchandise storefront where customers can browse products, request custom quotes, order, and message support — and admins manage everything from a dashboard. Front end is static HTML/CSS/JS; the back end is a single PHP API file backed by MySQL.

## Features

**Customers**
- Register and log in (session-based auth, hashed passwords)
- Browse a live product marketplace (products are managed by admins only — nothing is hardcoded)
- Add products to a cart and check out
- Request a custom print/merch quote
- Send messages to support and view replies, and delete their own messages
- View homepage adverts/specials published by the admin

**Admins**
- Log in with an admin-role account and land on a separate dashboard (`admin.html`)
- Add and remove marketplace products
- Review customer quote requests and respond to them
- Receive customer messages, reply, and delete any message
- Update order status
- Publish and remove homepage adverts (image or video)

## Tech stack

- **Frontend:** Plain HTML, CSS, and vanilla JavaScript (`app.js` holds shared helpers: session cache, cart, API wrapper)
- **Backend:** PHP (`api.php`) exposing a single endpoint dispatched by an `action` query parameter and HTTP method
- **Database:** MySQL / MariaDB, accessed via PDO
- **Auth:** PHP sessions (`$_SESSION`), `password_hash()` / `password_verify()`
- **Payments:** Hooks for an external hosted payment gateway (see [Payments](#payments) below) — no card data is ever handled by this app directly

## Project structure

```
├── index.html              Homepage (products, adverts, quote CTA)
├── marketplace.html        Full product catalog
├── cart.html                Cart (requires login)
├── checkout.html            Delivery details → redirects to payment gateway
├── orders.html               Customer order history
├── quote.html                 Custom quote request form
├── messages.html               Customer support chat thread
├── signup.html / login.html     Auth pages
├── admin.html                    Admin dashboard (products, quotes, messages, orders, adverts)
├── app.js                         Shared client-side helpers (session, cart, API wrapper)
├── api.php                         Backend API (all actions live here)
├── config.php                      DB connection + env-based config
├── payment-webhook.php              Receives payment-completed webhooks from your gateway
└── awesum_brands.sql                  Database schema (see note below)
```

## Setup

### 1. Requirements
- PHP 8+ with PDO MySQL extension
- MySQL or MariaDB
- Apache (e.g. via XAMPP/MAMP) or any PHP-capable web server

### 2. Database
Create a database named `awesum_brands` and import the schema.

> **Before committing/importing:** `awesum_brands.sql` in this repo may currently be a full export from a working instance, which can include real user rows (names, emails, password hashes) and test data. **Do not commit a data-filled dump to a public repository.** Export a schema-only version (phpMyAdmin → Export → uncheck "structure and data", choose "structure only") before pushing, or keep the SQL file out of version control via `.gitignore` and share it privately.

```bash
mysql -u root -p awesum_brands < awesum_brands.sql
```

The `adverts`, `messages`, `orders`, `products`, `quotes`, and `users` tables are created here. `users.role` distinguishes `admin` from `user` accounts.

### 3. Configure the database connection
`config.php` reads all settings from environment variables, with local fallback defaults:

| Variable | Purpose | Local (XAMPP) default |
|---|---|---|
| `AWESUM_DB_HOST` | DB host | `127.0.0.1` |
| `AWESUM_DB_PORT` | DB port | `3306` |
| `AWESUM_DB_NAME` | DB name | `awesum_brands` |
| `AWESUM_DB_USER` | DB user | `root` |
| `AWESUM_DB_PASS` | DB password | *(empty)* |
| `AWESUM_ADMIN_EMAIL` | Reference email for the admin account | *(none)* |
| `AWESUM_APP_ORIGIN` | Allowed CORS origin, if frontend is served separately | *(none)* |
| `AWESUM_PAYMENT_WEBHOOK_SECRET` | HMAC secret for `payment-webhook.php` | *(none)* |

On a production host, set these as real environment variables rather than editing the fallback values in `config.php`. For local development, editing the fallback values directly in `config.php` is the simplest approach.

### 4. Create an admin account
Sign up normally through `signup.html`, then promote that account in the database:

```sql
UPDATE users SET role = 'admin' WHERE email = 'you@example.com';
```

> `AWESUM_ADMIN_EMAIL` in `config.php` is currently just a reference value — signup does **not** automatically grant the admin role to that address. New accounts are always created as `role = 'user'`; promotion is a manual step for now.

Log out and back in after promoting an account — the role is set at login time.

### 5. Serve the project
Place the folder inside your web server's document root (e.g. `htdocs/AwesumBrands` for XAMPP) and visit it through `http://localhost/AwesumBrands/index.html` — the pages must be loaded through PHP/Apache, not opened directly as local files, since they call `api.php`.

## Payments

Checkout creates an order (`status: Payment pending`) and then redirects to an external hosted payment page. To connect a real gateway:

1. Set `window.AWE_SUM_PAYMENT_GATEWAY_URL` (in `app.js`) to your gateway's hosted checkout URL.
2. Configure your gateway to call `payment-webhook.php` on successful payment, signed with an HMAC-SHA256 signature in the `X-AweSum-Signature` header, using the shared secret in `AWESUM_PAYMENT_WEBHOOK_SECRET`.
3. On a valid webhook, the matching order's status is updated to `Payment received`.

No card details are ever submitted to or stored by this application.

## Known limitations / next steps

- Admin replies to messages currently use a browser `prompt()` dialog rather than an inline reply form.
- Product images are stored as URLs/base64 in the `products.image` column; there's no dedicated file upload/storage pipeline.
- No automated tests yet.
- `AWESUM_ADMIN_EMAIL` is not yet enforced automatically at signup (see step 4 above).

## Security notes

- Passwords are hashed with `password_hash()` (bcrypt) — never stored in plain text.
- Sessions are `httponly` and `samesite=Lax`, with `secure` enabled automatically when served over HTTPS.
- All SQL queries use PDO prepared statements.
- Keep `config.php` values (DB credentials, webhook secret) out of version control in production — use environment variables instead.
