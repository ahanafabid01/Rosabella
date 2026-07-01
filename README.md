# KARTLY — Premium E-Commerce Platform

> A full-featured, PHP-powered e-commerce platform built for the Bangladeshi market with multi-gateway payment processing, a complete admin dashboard, and clean URL routing.

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Database Schema](#database-schema)
- [Getting Started](#getting-started)
  - [Prerequisites](#prerequisites)
  - [Installation](#installation)
  - [Configuration](#configuration)
- [Payment Gateways](#payment-gateways)
- [Admin Panel](#admin-panel)
- [Routing](#routing)
- [API Endpoints](#api-endpoints)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)

---

## Overview

**Kartly** is a self-hosted, PHP e-commerce solution designed for modern online retail in Bangladesh. It ships with an intuitive storefront, a powerful admin dashboard, and native integrations for local payment gateways — all running on a standard LAMP/XAMPP stack with zero external PHP framework dependencies.

---

## Features

### 🛍️ Storefront
- Full product catalog with categories, filters, search, and pagination
- Product detail pages with image gallery, reviews, and related products
- Persistent shopping cart (session-based for guests, user-linked for logged-in customers)
- Wishlist with session and user-account persistence
- Coupon / discount code system (percentage or fixed amount)
- Order tracking by order number
- Countdown-timer deal banners managed from the admin
- Gift cards page
- Size guide, shipping info, returns, and all standard informational pages

### 👤 Customer Accounts
- Registration and login with bcrypt password hashing
- Account management (profile, address, password change)
- Order history with status tracking
- Review submission with image uploads

### 🔐 Admin Dashboard (`/admin`)
- Overview dashboard with sales metrics
- **Products** — create, edit, delete; manage gallery images, SKU, stock levels, sale price
- **Categories** — hierarchical category management with slugs
- **Orders** — view, filter, update status and payment status; full order detail view
- **Users** — browse, activate/ban/deactivate customer accounts
- **Coupons** — create percentage or fixed-amount coupons with validity periods and usage caps
- **Reviews** — approve, reject, or view submitted customer reviews with images
- **Deals** — hero-banner deal cards with countdown timers, overlay colours, and sort order
- **Settings** — site-wide settings (name, currency, tax, shipping threshold, payment gateway credentials)

### 💳 Payments
| Gateway | Status |
|---|---|
| Cash on Delivery (COD) | ✅ Built-in |
| SSLCOMMERZ | ✅ Built-in |
| bKash (Tokenized) | ✅ Built-in |
| Nagad (via SSLCOMMERZ) | ✅ Built-in |

All gateways support sandbox / live toggle from the admin settings panel with no code changes required.

### 🗺️ Clean URL Routing
- SEO-friendly, extension-free URLs (`/shop`, `/product/slug`, `/category/fashion`, `/sale`, `/new-arrivals`)
- Apache `mod_rewrite` based — no framework router needed
- Automatic detection of local XAMPP vs. production domain root

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Language** | PHP 8.0+ |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ |
| **Web Server** | Apache (XAMPP locally, any Apache host in production) |
| **Frontend** | Vanilla HTML5, CSS3, JavaScript |
| **Database Access** | PDO with prepared statements |
| **HTTP Client** | PHP cURL (for payment API calls) |
| **Password Hashing** | PHP `password_hash()` / `password_verify()` (bcrypt) |
| **CSRF Protection** | Token-based via PHP sessions |

---

## Project Structure

```
Kartly/
├── admin/                  # Admin dashboard (protected by .htaccess)
│   ├── index.php           # Admin landing / redirect
│   ├── products.php        # Product management
│   ├── categories.php      # Category management
│   ├── orders.php          # Order management
│   ├── order-detail.php    # Single order view
│   ├── users.php           # Customer management
│   ├── coupons.php         # Coupon management
│   ├── reviews.php         # Review moderation
│   ├── view-review.php     # Single review view
│   ├── deals.php           # Deal banner management
│   ├── hero.php            # Hero section settings
│   ├── settings.php        # Site-wide settings
│   ├── css/                # Admin-specific styles
│   └── js/                 # Admin-specific scripts
│
├── api/                    # Lightweight AJAX endpoints
│   ├── cart.php            # Cart add/update/remove
│   ├── product.php         # Product quick-view data
│   ├── search.php          # Search suggestions
│   ├── newsletter.php      # Newsletter subscription
│   └── wishlist.php        # Wishlist toggle
│
├── assets/
│   ├── css/                # Global stylesheet assets
│   ├── js/                 # Global JavaScript
│   ├── images/             # Static images (categories, logos, etc.)
│   └── uploads/            # User & product uploaded files
│
├── config/
│   └── database.php        # DB credentials, constants, helper functions
│
├── database/
│   ├── schema.sql          # Full DB schema + seed data
│   └── migrations/         # Incremental migration scripts
│
├── errors/
│   └── 404.php             # Custom 404 error page
│
├── includes/
│   ├── header.php          # Global site header / nav
│   ├── footer.php          # Global site footer
│   ├── router.php          # Clean URL dispatcher
│   ├── payment_gateway.php # Payment gateway helpers (SSLCOMMERZ, bKash, Nagad)
│   └── image_helper.php    # Image upload & resize utilities
│
├── pages/                  # Static informational pages
│   ├── about.php
│   ├── help.php
│   ├── shipping.php
│   ├── returns.php
│   ├── terms.php
│   ├── privacy.php
│   ├── cookies.php
│   ├── careers.php
│   ├── press.php
│   ├── affiliate.php
│   ├── accessibility.php
│   ├── sustainability.php
│   └── size-guide.php
│
├── public/                 # Customer-facing dynamic pages
│   ├── products.php        # Shop / category / filter listing
│   ├── product.php         # Single product detail
│   ├── cart.php            # Shopping cart
│   ├── checkout.php        # Checkout flow
│   ├── payment_callback.php # Gateway redirect handler
│   ├── payment_ipn.php     # Gateway IPN/webhook handler
│   ├── payment_result.php  # Post-payment result page
│   ├── account.php         # Customer account / profile
│   ├── my-orders.php       # Order history
│   ├── track-order.php     # Guest order tracking
│   ├── wishlist.php        # Wishlist page
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── contact.php
│   └── gift-cards.php
│
├── index.php               # Application entry point
├── style_head.css          # Main stylesheet
├── .htaccess               # Apache rewrite rules & security headers
└── README.md
```

---

## Database Schema

The `database/schema.sql` file creates the `kartly_db` database and all tables, then seeds default data. Key tables:

| Table | Description |
|---|---|
| `users` | Customers and admins (`role` enum) |
| `categories` | Hierarchical product categories (self-referencing `parent_id`) |
| `products` | Full product catalogue (SKU, pricing, stock, SEO meta, gallery) |
| `product_images` | Additional gallery images per product |
| `coupons` | Discount codes (percentage or fixed, date-bounded, usage-capped) |
| `orders` | Orders with full shipping details and payment status |
| `order_items` | Line items for each order (snapshot of product name/price at time of purchase) |
| `payment_attempts` | Audit log of every gateway API call |
| `cart` | Session + user-linked cart rows |
| `wishlist` | Session + user-linked wishlist rows |
| `reviews` | Customer product reviews with approval workflow |
| `review_images` | Images attached to reviews |
| `deals` | Hero deal banners with countdown timers |
| `contact_messages` | Inbound contact form submissions |
| `newsletter_subscribers` | Email newsletter list |
| `settings` | Key-value store for all site configuration |

---

## Getting Started

### Prerequisites

- **XAMPP** (or any Apache + PHP 8.0+ + MySQL stack)
- PHP extensions: `pdo_mysql`, `curl`, `gd` (for image processing)
- Apache `mod_rewrite` enabled

### Installation

1. **Clone the repository** into your web root:
   ```bash
   git clone https://github.com/ahanafabid01/Kartly-New.git C:/xampp/htdocs/Kartly
   ```

2. **Start XAMPP** — ensure Apache and MySQL services are running.

3. **Import the database schema:**
   - Open **phpMyAdmin** → `http://localhost/phpmyadmin`
   - Click **Import** and select `database/schema.sql`
   - This creates the `kartly_db` database, all tables, indexes, and seed data

4. **Visit the site:**
   ```
   http://localhost/Kartly
   ```

### Configuration

All configuration lives in **`config/database.php`**. Edit the constants at the top of the file:

```php
// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'kartly_db');
define('DB_USER', 'root');
define('DB_PASS', '');          // ← Set your MySQL password

// Security — change before going live!
define('SECRET_KEY', 'your-secret-key-change-this-in-production');
```

> **Production note:** Set `error_reporting(0)` and `ini_set('display_errors', 0)` before deploying to a live server.

#### Default Admin Credentials

| Field | Value |
|---|---|
| **URL** | `http://localhost/Kartly/admin` |
| **Email** | `admin@kartly.com` |
| **Password** | `admin123` |

> ⚠️ Change the admin password immediately after your first login.

---

## Payment Gateways

All gateway credentials are managed through **Admin → Settings** — no code changes required.

### SSLCOMMERZ
1. Create an account at [sslcommerz.com](https://sslcommerz.com)
2. In **Admin → Settings**, enter your **Store ID** and **Store Password**
3. Toggle **Sandbox mode** off when ready for production

### bKash (Tokenized Checkout)
1. Apply for a bKash merchant account
2. Enter **App Key**, **App Secret**, **Username**, and **Password** in **Admin → Settings**
3. Toggle **Sandbox mode** off for live transactions

### Nagad
- Nagad payments are routed through the SSLCOMMERZ gateway (multi-card support)
- Enable SSLCOMMERZ first; then enable Nagad from settings

### Cash on Delivery
- Enabled by default — toggle off from **Admin → Settings** if not needed

---

## Admin Panel

Access the admin panel at `/admin` (or `http://localhost/Kartly/admin` locally).

The admin `.htaccess` restricts direct file access. Admin session checks are enforced on every admin page — unauthenticated requests are redirected to the login page.

**Key admin workflows:**

| Task | Path |
|---|---|
| Add a new product | Admin → Products → Add New |
| Process an order | Admin → Orders → click order → update status |
| Create a coupon | Admin → Coupons → Add New |
| Configure payment gateways | Admin → Settings → Payment section |
| Manage deal banners | Admin → Deals |
| Moderate reviews | Admin → Reviews |

---

## Routing

Kartly uses Apache `mod_rewrite` to serve clean, extension-free URLs. The `.htaccess` forwards all non-file, non-directory requests to `index.php`, which delegates to `includes/router.php`.

| URL Pattern | Resolves To |
|---|---|
| `/shop` | `public/products.php` |
| `/sale` | `public/products.php?filter=sale` |
| `/new-arrivals` | `public/products.php?filter=new` |
| `/best-sellers` | `public/products.php?filter=bestseller` |
| `/category/{slug}` | `public/products.php?category={slug}` |
| `/product/{slug}` | `public/product.php?slug={slug}` |
| `/cart` | `public/cart.php` |
| `/checkout` | `public/checkout.php` |
| `/wishlist` | `public/wishlist.php` |
| `/account` | `public/account.php` |
| `/my-orders` | `public/my-orders.php` |
| `/track/{order_number}` | `public/track-order.php?order={order_number}` |
| `/login` | `public/login.php` |
| `/register` | `public/register.php` |
| `/about`, `/help`, `/shipping`, … | `pages/{page}.php` |

Unmatched routes return a custom **404** page.

---

## API Endpoints

The `api/` directory exposes lightweight JSON endpoints consumed by the frontend via `fetch` / AJAX:

| Endpoint | Method | Description |
|---|---|---|
| `api/cart.php` | POST | Add, update quantity, or remove a cart item |
| `api/wishlist.php` | POST | Toggle a product in/out of the wishlist |
| `api/product.php` | GET | Fetch quick-view product data by ID or slug |
| `api/search.php` | GET | Live search suggestions as the user types |
| `api/newsletter.php` | POST | Subscribe an email to the newsletter list |

All endpoints return JSON and validate CSRF tokens on mutating requests.

---

## Security

- **Passwords** — hashed with `password_hash()` (bcrypt, cost 10)
- **SQL Injection** — all DB queries use PDO prepared statements
- **CSRF** — every mutating form submits and validates a per-session CSRF token
- **XSS** — output is HTML-encoded with `htmlspecialchars()` throughout
- **Admin protection** — `.htaccess` denies direct PHP execution in the admin directory; session role check on every admin page
- **Input sanitisation** — the global `sanitize()` helper trims and strips dangerous characters from all user input

---

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Commit your changes: `git commit -m "Add my feature"`
4. Push to the branch: `git push origin feature/my-feature`
5. Open a Pull Request

Please follow the existing code style (PSR-inspired, no external frameworks) and test your changes locally with XAMPP before submitting.

---

## License

This project is proprietary software. All rights reserved.

---

<p align="center">Built with ❤️ for the Bangladeshi e-commerce ecosystem</p>
