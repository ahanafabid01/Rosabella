# KARTLY - E-Commerce Website

A complete, professional e-commerce website built with **PHP, MySQL, HTML, CSS, and JavaScript**. No framework dependencies - pure code that you can host on any shared hosting provider.

## 🚀 Features

### Frontend
- ✅ Fully responsive design (mobile, tablet, desktop)
- ✅ Hero slider with auto-play
- ✅ Product categories grid
- ✅ Featured products with filtering
- ✅ Product quick view
- ✅ Wishlist functionality
- ✅ Shopping cart
- ✅ Newsletter subscription
- ✅ Customer testimonials
- ✅ Trust badges
- ✅ Smooth animations

### Backend
- ✅ MySQL database with full schema
- ✅ User registration & login
- ✅ Product management
- ✅ Shopping cart (session & database)
- ✅ Order processing
- ✅ Coupon system
- ✅ Admin panel ready
- ✅ Secure password hashing
- ✅ CSRF protection

## 📋 Requirements

- PHP 7.4+ (PHP 8.x recommended)
- MySQL 5.7+ / MariaDB 10.3+
- Apache/Nginx web server
- mod_rewrite enabled (for Apache)

## 🛠️ Installation

### Step 1: Download & Extract
Upload all files to your web server's public directory (e.g., `public_html` or `www`).

### Step 2: Create Database
1. Log in to your hosting control panel (cPanel, Plesk, etc.)
2. Go to **MySQL Databases**
3. Create a new database named `kartly_db`
4. Create a MySQL user and assign it to the database
5. Import the `database.sql` file via phpMyAdmin

### Step 3: Configure Database Connection
Edit `config/database.php` and update the credentials:

```php
define('DB_HOST', 'localhost');      // Usually 'localhost'
define('DB_NAME', 'kartly_db');       // Your database name
define('DB_USER', 'your_username');   // Your database username
define('DB_PASS', 'your_password');   // Your database password
```

### Step 4: Set Site URL
In `config/database.php`, update the site URL:

```php
define('SITE_URL', 'https://yourdomain.com');
```

### Step 5: Set Permissions
Make sure the following directories are writable:
- `assets/images/` (for product images)
- `assets/images/products/`
- `assets/images/categories/`

### Step 6: Access Your Website
Visit your domain in a browser. You should see the KARTLY homepage!

## 🔐 Default Admin Login

After importing the database, you can log in as admin:

- **Email:** admin@kartly.com
- **Password:** admin123

⚠️ **Important:** Change the admin password immediately after first login!

## 📁 File Structure

```
kartly-php/
├── assets/
│   ├── css/
│   │   └── style.css          # Main stylesheet
│   ├── js/
│   │   └── main.js            # Main JavaScript
│   └── images/
│       ├── products/          # Product images
│       └── categories/        # Category images
├── config/
│   └── database.php           # Database configuration
├── includes/
│   ├── header.php             # Site header
│   └── footer.php             # Site footer
├── pages/
│   ├── login.php              # User login
│   ├── register.php           # User registration
│   └── account.php            # User account
├── admin/
│   ├── index.php              # Admin dashboard
│   ├── products.php           # Manage products
│   └── orders.php             # Manage orders
├── api/
│   ├── cart.php               # Cart API
│   └── wishlist.php           # Wishlist API
├── index.php                  # Homepage
├── products.php               # Products page
├── cart.php                   # Shopping cart
├── checkout.php               # Checkout
├── product.php                # Single product
└── database.sql               # Database schema & sample data
```

## 🎨 Customization

### Change Colors
Edit CSS variables in `assets/css/style.css`:

```css
:root {
    --color-primary: #e85d04;      /* Main brand color */
    --color-primary-hover: #dc5603;
    --color-text: #1a1a2e;         /* Text color */
    --color-bg: #ffffff;           /* Background color */
}
```

### Change Logo
Replace the logo text in `includes/header.php` or add an image:

```html
<div class="logo">
    <img src="assets/images/logo.png" alt="KARTLY">
</div>
```

### Add Products
1. Log in to admin panel at `admin/index.php`
2. Go to Products → Add New
3. Fill in product details and upload images

## 📱 Responsive Design

The website is fully responsive:
- **Mobile:** < 768px
- **Tablet:** 768px - 1024px
- **Desktop:** > 1024px

## 🌐 Hosting Recommendations

Works on any hosting that supports PHP & MySQL:

| Provider | Recommended Plan | Notes |
|----------|------------------|-------|
| **Hostinger** | Single Shared | Budget-friendly, good support |
| **Bluehost** | Basic | Official WordPress recommended |
| **Namecheap** | Stellar | Affordable, includes domain |
| **A2 Hosting** | Startup | Fast servers, good performance |
| **SiteGround** | StartUp | Excellent support, auto-updates |

## Payments (SSLCOMMERZ, bKash, Nagad)

This project now includes checkout integrations for:
- `SSLCOMMERZ` (redirect + callback + IPN validation)
- `bKash` tokenized checkout (create + execute)
- `Nagad` option routed via SSLCOMMERZ channel selection
- `Cash on Delivery`

### Required setup
1. Open Admin > Settings
2. Configure keys:
   - `payment_sslcommerz_*`
   - `payment_bkash_*`
   - `payment_nagad_*`
3. Set `SITE_URL` in `config/database.php` to your real HTTPS domain
4. Run migrations:
   - `migrations/2026_02_27_add_payment_attempts_table.sql`
   - `migrations/2026_02_27_add_payment_gateway_settings.sql`

### Callback endpoints
- `https://your-domain.com/payment_callback.php?gateway=sslcommerz&action=success`
- `https://your-domain.com/payment_callback.php?gateway=sslcommerz&action=fail`
- `https://your-domain.com/payment_callback.php?gateway=sslcommerz&action=cancel`
- `https://your-domain.com/payment_ipn.php?gateway=sslcommerz`
- `https://your-domain.com/payment_callback.php?gateway=bkash`

## 🔧 Troubleshooting

### Database Connection Error
1. Check database credentials in `config/database.php`
2. Make sure MySQL server is running
3. Verify database user has proper permissions

### Page Not Found (404)
1. Make sure mod_rewrite is enabled (Apache)
2. Check if `.htaccess` file exists
3. Verify file paths are correct

### Images Not Loading
1. Check file permissions on `assets/images/`
2. Verify image paths in database
3. Make sure images are uploaded correctly

## 📧 Support

For questions or issues:
- Email: support@kartly.com
- Create an issue on GitHub

## 📄 License

This project is open-source. Feel free to use it for personal or commercial projects.

---

**Built with ❤️ for easy hosting on any platform!**
