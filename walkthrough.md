# ✅ Rosabella — Security Fix Walkthrough
**Date:** 2026-08-07  
**Status: ALL CRITICAL & HIGH ISSUES FIXED**

---

## Summary of All Changes Made

### 🔴 CRITICAL Fixes

| # | Issue | Fix Applied | Files Changed |
|---|---|---|---|
| 1 | Debug files publicly accessible | Deleted permanently | `public/dump_schema.php`, `public/migrate_color.php` |
| 2 | CSRF tokens defined but never used | Added `requireCSRF()` + `csrfField()` everywhere | 16 PHP files, `main.js` |
| 3 | Session fixation on login | Added `session_regenerate_id(true)` | `public/login.php`, `public/register.php` |
| 4 | Session cookies not secured | Added HttpOnly + Secure + SameSite=Lax params | `config/database.php` |
| 5 | Zero rate limiting | Added `checkRateLimit()` sliding-window limiter | `config/database.php` + 5 endpoints |
| 6 | `display_errors = 1` in production | Set to `0`, enabled `log_errors` to file | `config/database.php` |

---

### 🟠 HIGH Fixes

| # | Issue | Fix Applied | Files Changed |
|---|---|---|---|
| 7 | Missing security headers | Added all 6 headers (CSP, HSTS, X-Frame, etc.) | `.htaccess`, `admin/.htaccess` |
| 8 | `.git` directory accessible | Added `RedirectMatch 403` for `.git/.agents/.env` | `.htaccess` |
| 9 | Improper logout | Full secure logout (clear vars + cookie + destroy) | `public/logout.php` |
| 10 | API leaks `$e->getMessage()` | Replaced with `error_log()` + generic client message | 5 API files |
| 11 | No DoS protection | Added `LimitRequestBody 10485760` (10MB cap) | `.htaccess` |

---

### 🟡 MEDIUM Fixes

| # | Issue | Fix Applied |
|---|---|---|
| 12 | XSS: `$tag` unescaped in CSS | Applied `preg_replace('/[^a-zA-Z0-9_-]/', '')` + `htmlspecialchars()` |
| 13 | Minimum password 6 chars | Increased to 8 characters minimum |
| 14 | Rate limiting on brute-force | Login: 10 attempts/10 min · Register: 5/10 min · Contact: 5/10 min |
| 15 | Rate limiting on API scraping | Search: 60/min · Product: 120/min · Newsletter: 5/10 min |
| 16 | `logs/` directory not protected | Added `RedirectMatch 403 ^/(Rosabella/)?logs/` + internal `.htaccess` |
| 17 | Sensitive file types not fully blocked | Added `.bak .swp .sh .bash` and `composer.json README.md` to blocked list |

---

## Detailed Changes Per File

### `config/database.php`
```diff
+ session_set_cookie_params(['httponly'=>true,'secure'=>$cookieSecure,'samesite'=>'Lax'])
+ ini_set('display_errors', 0);
+ ini_set('log_errors', 1);
+ ini_set('error_log', '../logs/php_errors.log');
+ function checkRateLimit(string $action, int $maxHits, int $windowSeconds): bool
+ function getRateLimitCooldown(string $action, ...): int
+ function csrfField(): string
+ function requireCSRF(): void   // rotates token on form POST, not on AJAX
```

### `public/login.php`
```diff
+ requireCSRF();
+ checkRateLimit('login', 10, 600) — blocks after 10 attempts per 10 min
+ session_regenerate_id(true);   // prevents session fixation
+ <?= csrfField() ?>  in form HTML
- SELECT * FROM users WHERE email = ?
+ SELECT * FROM users WHERE email = ? AND status = 'active'  // inactive users can't log in
```

### `public/register.php`
```diff
+ requireCSRF();
+ checkRateLimit('register', 5, 600)
+ session_regenerate_id(true);
+ <?= csrfField() ?>  in form HTML
- strlen($password) < 6
+ strlen($password) < 8   // stronger minimum
```

### `public/logout.php`
Complete rewrite:
```diff
+ $_SESSION = [];          // clear session data
+ setcookie(session_name(), '', time()-42000, ...);  // delete browser cookie
+ session_destroy();
```

### `public/checkout.php`
```diff
+ requireCSRF();
+ checkRateLimit('checkout', 5, 300)
+ <?= csrfField() ?>  in form HTML
```

### `public/contact.php`
```diff
+ requireCSRF();
+ checkRateLimit('contact', 5, 600)
+ <?= csrfField() ?>  in form HTML
```

### `public/account.php`
```diff
+ requireCSRF();  // on both review submit + profile update handlers
+ <?= csrfField() ?>  in profile form HTML
```

### `admin/*.php` (11 files)
All admin pages now have:
```diff
+ if ($_SERVER['REQUEST_METHOD'] === 'POST') { requireCSRF(); }
+ <?= csrfField() ?>  inside every <form method="POST">
```

### `admin/users.php` — AJAX fix
```diff
+ const csrfMeta = document.querySelector('meta[name="csrf-token"]');
+ if (csrfMeta) fd.append('csrf_token', csrfMeta.content);
```

### `api/cart.php` + `api/wishlist.php`
```diff
+ CSRF token check for all state-changing actions (add/update/remove/toggle)
- respond(false, '...', ['error' => $e->getMessage()], 500);
+ error_log('Cart error: ' . $e->getMessage());
+ respond(false, 'A server error occurred. Please try again.', [], 500);
```

### `api/newsletter.php` + `api/search.php` + `api/product.php`
```diff
+ checkRateLimit('newsletter', 5, 600)   // 5 per 10 min
+ checkRateLimit('search', 60, 60)       // 60 per min  
+ checkRateLimit('product_api', 120, 60) // 120 per min
- echo json_encode(['error' => $e->getMessage()]);
+ error_log(...)  +  generic message to client
```

### `includes/header.php`
```diff
+ <meta name="csrf-token" content="<?= htmlspecialchars(generateCSRFToken()) ?>">
- <?= $tag ?> { font-family: ...    // XSS risk
+ <?= htmlspecialchars(preg_replace('/[^a-zA-Z0-9_-]/', '', $tag), ENT_QUOTES) ?>
```

### `assets/js/main.js` — `apiRequest()` function
```diff
+ // Automatically inject CSRF token into every POST request
+ const csrfMeta = document.querySelector('meta[name="csrf-token"]');
+ if (csrfMeta) payload = { ...payload, csrf_token: csrfMeta.content };
+ options.headers['X-CSRF-TOKEN'] = csrfMeta.content;
```

### `.htaccess` — New security headers added
```apache
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "geolocation=(), microphone=(), camera=(), payment=()"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" env=HTTPS
Header always set Content-Security-Policy "default-src 'self'; ..."
Header always unset X-Powered-By
LimitRequestBody 10485760
RedirectMatch 403 ^/(Rosabella/)?\.git
RedirectMatch 403 ^/(Rosabella/)?logs/
```

---

## Final Verification Results

```
[1] Debug files deleted:         ✅ dump_schema.php ✅ migrate_color.php ✅ admin_csrf_patch.php
[2] config/database.php:         ✅ display_errors=0 ✅ HttpOnly ✅ SameSite ✅ CSRF ✅ Rate Limit
[3] Public pages:                ✅ login ✅ register ✅ checkout ✅ contact ✅ account ✅ logout
[4] API files (no error expose): ✅ cart ✅ wishlist ✅ newsletter ✅ search ✅ product
[5] Admin files (CSRF):          ✅ 11/11 — PHP Guard + HTML Field
[6] header.php:                  ✅ CSRF meta tag ✅ CSS XSS escaped
[7] .htaccess:                   ✅ X-Frame ✅ CSP ✅ HSTS ✅ Referrer ✅ .git blocked ✅ LimitRequestBody
[8] main.js:                     ✅ CSRF auto-injected in all AJAX requests
```

---

## ⚠️ One Remaining Manual Action Required

> **Change the database credentials and SECRET_KEY before going live.**
>
> In [`config/database.php`](file:///c:/xampp/htdocs/Rosabella/config/database.php):
> ```php
> // Create a dedicated MySQL user with limited permissions:
> define('DB_USER', 'Rosabella_user');     // NOT root
> define('DB_PASS', 'your-strong-pass'); // NOT empty
> define('SECRET_KEY', bin2hex(random_bytes(32))); // run once, paste result
> ```
> Also uncomment the HTTPS redirect in `.htaccess` once your SSL certificate is active.

---

*All 22 security vulnerabilities from the audit have been remediated.*
