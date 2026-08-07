# 🔐 Kartly — Full Security Audit Report
**Date:** 2026-08-07  
**Auditor:** Antigravity AI (Full-Stack + CyberSecurity Audit)  
**Codebase:** `c:\xampp\htdocs\Kartly` (PHP/MySQL E-Commerce)  
**Severity Levels:** 🔴 CRITICAL · 🟠 HIGH · 🟡 MEDIUM · 🟢 LOW · ✅ PASS

---

## Executive Summary

| Category | Status |
|---|---|
| SQL Injection | ✅ Largely protected (PDO prepared statements) |
| XSS (Cross-Site Scripting) | 🟠 2 active issues found |
| CSRF Protection | 🔴 CRITICAL — Not enforced on ANY form |
| Session Security | 🔴 CRITICAL — Multiple failures |
| Authentication | 🟠 Brute-force unprotected |
| Sensitive File Exposure | 🔴 CRITICAL — Debug files publicly accessible |
| Error Information Leakage | 🟠 HIGH — Errors exposed in prod mode |
| Security Headers | 🟠 HIGH — Missing critical headers |
| Password Policy | 🟡 MEDIUM — Minimum too weak |
| Rate Limiting / Bot Protection | 🔴 CRITICAL — None whatsoever |
| File Upload Security | 🟢 Good — MIME validation present |
| Database Credentials | 🔴 CRITICAL — Default/empty credentials |
| Secret Key | 🔴 CRITICAL — Placeholder value |
| HTTPS Enforcement | 🟠 HIGH — Disabled (commented out) |

**Overall Risk Level: 🔴 HIGH — Immediate action required before going live.**

---

## 🔴 CRITICAL ISSUES

---

### 1. CSRF Protection — Defined But Never Used

**File:** [`config/database.php`](file:///c:/xampp/htdocs/Kartly/config/database.php#L99-L111)  
**Severity:** 🔴 CRITICAL

The `generateCSRFToken()` and `verifyCSRFToken()` functions **exist** in `database.php` but are **never called anywhere** in the codebase. Every single form — login, register, checkout, contact, profile update, admin settings, admin user management — processes POST requests with **zero CSRF verification**.

This means any malicious third-party website can silently submit forms as an authenticated user (CSRF attack), causing:
- Unauthorized orders placed
- Account profile changes
- Admin settings modified
- Products deleted/modified by a social-engineering attack

**Affected Forms:**
- `public/login.php` — no CSRF token in form
- `public/register.php` — no CSRF token in form
- `public/checkout.php` — no CSRF token (financial transaction!)
- `public/contact.php` — no CSRF token
- `public/account.php` — profile update, review submit
- `admin/settings.php` — all settings forms
- `admin/products.php` — product create/update/delete
- `admin/users.php` — ajax_update, delete_user
- `api/cart.php` — add/remove/update cart
- `api/wishlist.php` — toggle/remove wishlist
- `api/newsletter.php` — subscribe endpoint

**Fix Required:**
1. Add `<?= htmlspecialchars(generateCSRFToken()) ?>` as a hidden field in every form
2. At the top of every POST handler, verify: `verifyCSRFToken($_POST['csrf_token'] ?? '')`
3. For AJAX API endpoints: include CSRF token in request headers and verify on server

---

### 2. Session Fixation — No `session_regenerate_id()` on Login

**File:** [`public/login.php`](file:///c:/xampp/htdocs/Kartly/public/login.php#L27-L34)  
**Severity:** 🔴 CRITICAL

After a successful login, the session ID is never regenerated. An attacker who knows a victim's pre-login session ID (e.g., through session fixation or network sniffing) can **hijack the authenticated session** after the user logs in.

```php
// CURRENT (vulnerable):
$_SESSION['user_id'] = $user['id'];

// REQUIRED FIX:
session_regenerate_id(true);  // Add this BEFORE setting session data
$_SESSION['user_id'] = $user['id'];
```

Same issue exists in `public/register.php` (auto-login after registration).

---

### 3. Session Cookie Not Secured

**File:** [`config/database.php`](file:///c:/xampp/htdocs/Kartly/config/database.php#L38-L41)  
**Severity:** 🔴 CRITICAL

Sessions are started with `session_start()` but no secure cookie parameters are set. This means:
- **HttpOnly not enforced** → JavaScript can steal session cookies (XSS → session hijack)
- **Secure flag not set** → Session cookie transmitted over HTTP (sniffable)
- **SameSite not set** → Session cookie sent in cross-site requests (CSRF amplification)

**Fix Required — add before `session_start()`:**
```php
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => true,      // Only send over HTTPS
    'httponly' => true,      // Block JS access
    'samesite' => 'Lax',    // Block cross-site requests
]);
session_start();
```

---

### 4. Debug Files Publicly Accessible

**Files:** 
- [`public/dump_schema.php`](file:///c:/xampp/htdocs/Kartly/public/dump_schema.php) — Dumps full DB table structure
- [`public/migrate_color.php`](file:///c:/xampp/htdocs/Kartly/public/migrate_color.php) — Runs raw DDL `ALTER TABLE` on the DB

**Severity:** 🔴 CRITICAL

`dump_schema.php` is a debug utility that dumps the full `CREATE TABLE products` schema to any anonymous visitor. This reveals database column names, types, constraints — a roadmap for attackers.

`migrate_color.php` runs a raw `ALTER TABLE cart ADD COLUMN` — a migration script left publicly accessible. Any visitor can trigger it.

**Fix:** **Delete both files immediately.** They have no legitimate purpose in production.

---

### 5. Default / Placeholder Credentials & Secret Key

**File:** [`config/database.php`](file:///c:/xampp/htdocs/Kartly/config/database.php#L10-L32)  
**Severity:** 🔴 CRITICAL

```php
define('DB_USER', 'root');           // Default root user
define('DB_PASS', '');               // EMPTY password!
define('SECRET_KEY', 'your-secret-key-change-this-in-production');
```

- DB is running as `root` with no password — if any path traversal, LFI, or SSRF vulnerability is ever exploited, the attacker has **full unrestricted database access**.
- `SECRET_KEY` has never been changed from its placeholder value. If used for signing/hashing tokens, it provides zero security.

**Fix:**
- Create a dedicated limited-privilege DB user with only necessary permissions
- Set a strong random password on the DB user
- Change `SECRET_KEY` to a long random value: `bin2hex(random_bytes(32))`

---

### 6. Zero Rate Limiting — Brute-Force & Bot Attacks Possible

**Severity:** 🔴 CRITICAL

There is **no rate limiting anywhere** in the application:
- `public/login.php` — Unlimited password guesses with no lockout
- `public/register.php` — Bots can register unlimited fake accounts
- `api/newsletter.php` — Bots can spam-subscribe unlimited email addresses
- `api/cart.php` — No request throttling
- `public/checkout.php` — Order creation has no throttle

An automated bot can:
1. Brute-force admin/user passwords with thousands of attempts per second
2. Create thousands of spam accounts
3. Flood the newsletter table
4. Spam orders

**Fix Required:**
- Implement login attempt tracking in the database or PHP session
- Lock account for N minutes after M failed attempts (e.g., 5 fails → 15 min lockout)
- Implement a server-side rate limiter or use Apache `mod_ratelimit`
- Add CAPTCHA on registration and newsletter endpoints

---

### 7. Error Reporting Enabled in Production Config

**File:** [`config/database.php`](file:///c:/xampp/htdocs/Kartly/config/database.php#L35-L36)  
**Severity:** 🔴 CRITICAL (in production context)

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

On a live server, this will expose PHP stack traces, file paths, database errors, and server internals directly in the browser to any visitor. This is a **critical information disclosure** vulnerability.

**Fix:**
```php
// Production:
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
```

---

## 🟠 HIGH SEVERITY ISSUES

---

### 8. Missing Critical Security Headers

**File:** [`.htaccess`](file:///c:/xampp/htdocs/Kartly/.htaccess)  
**Severity:** 🟠 HIGH

The `.htaccess` only sets `X-Content-Type-Options: nosniff`. The following critical headers are missing:

| Header | Purpose | Risk Without It |
|---|---|---|
| `X-Frame-Options: DENY` | Prevent clickjacking | Iframe-based UI redressing attacks |
| `Content-Security-Policy` | Prevent XSS/data injection | Attackers can inject scripts |
| `Strict-Transport-Security` | Force HTTPS | MITM on HTTP connections |
| `X-XSS-Protection: 1; mode=block` | Block reflected XSS in legacy browsers | XSS in older IE/Edge |
| `Referrer-Policy: strict-origin-when-cross-origin` | Prevent URL leakage | User data in referrer headers |
| `Permissions-Policy` | Disable unused browser APIs | Camera/mic access by injected scripts |

**Fix — add to `.htaccess` inside `<IfModule mod_headers.c>`:**
```apache
Header always set X-Frame-Options "DENY"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains" env=HTTPS
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self';"
```

---

### 9. HTTPS Not Enforced

**File:** [`.htaccess`](file:///c:/xampp/htdocs/Kartly/.htaccess#L10-L11)  
**Severity:** 🟠 HIGH

```apache
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

HTTPS redirect is commented out. In production, all traffic including login credentials, session cookies, and payment data travels unencrypted over HTTP — trivially interceptable by any network-level attacker.

**Fix:** Uncomment those two lines when deploying to a server with an SSL certificate.

---

### 10. XSS — `$additionalStyles` Not Sanitized

**File:** [`includes/header.php`](file:///c:/xampp/htdocs/Kartly/includes/header.php#L165-L167)  
**Severity:** 🟠 HIGH (potential, depending on who sets the variable)

```php
<?php if (isset($additionalStyles)): ?>
    <style><?= $additionalStyles ?></style>  // No escaping!
<?php endif; ?>
```

The `$additionalStyles` variable is injected directly into a `<style>` block without any sanitization. If any page ever sets this from user-controlled data, it becomes a CSS injection / XSS vector. Even from admin-controlled data, CSS injection can be used for data exfiltration.

**Fix:** Only allow this variable from trusted, hardcoded PHP strings. If it comes from database settings, sanitize thoroughly.

---

### 11. SQL Error / Internal Error Exposed via API

**Files:** `api/cart.php`, `api/wishlist.php`, `api/search.php`, `api/product.php`, `api/newsletter.php`  
**Severity:** 🟠 HIGH

```php
respond(false, 'Cart request failed', ['error' => $e->getMessage()], 500);
```

PDO exception messages (which include SQL queries, table names, column names) are returned directly in JSON responses to the client. This provides a roadmap for SQL injection attempts and reconnaissance.

**Fix:**
```php
// Log the real error internally:
error_log('Cart error: ' . $e->getMessage());
// Return generic message to client:
respond(false, 'A server error occurred. Please try again.', [], 500);
```

---

### 12. Admin Panel Uses HTTP Redirect (Not `exit` After Header) — Minor but Real

**File:** [`admin/index.php`](file:///c:/xampp/htdocs/Kartly/admin/index.php#L13-L14)  
**Severity:** 🟢 (actually fine — `exit` IS present, confirmed)

Admin pages correctly use `header() + exit` for auth checks. ✅

---

### 13. Logout Does Not Invalidate Session Properly

**File:** [`public/logout.php`](file:///c:/xampp/htdocs/Kartly/public/logout.php)  
**Severity:** 🟠 HIGH

```php
session_destroy();
```

`session_destroy()` alone is insufficient for secure logout. It destroys the server-side data but:
- Does not clear `$_SESSION` variables in memory
- Does not delete the session cookie from the browser
- Does not regenerate to a new session ID

**Fix:**
```php
session_start();
$_SESSION = [];  // Clear session data
// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
session_regenerate_id(true);
```

---

## 🟡 MEDIUM SEVERITY ISSUES

---

### 14. Weak Password Policy

**File:** [`public/register.php`](file:///c:/xampp/htdocs/Kartly/public/register.php#L26)  
**Severity:** 🟡 MEDIUM

Minimum password length is **6 characters** — far too low. Modern security standards (NIST 800-63B) recommend minimum 8 characters. There are no requirements for complexity (uppercase, numbers, symbols).

**Fix:** Increase minimum to 8+ characters and recommend complexity guidance to users.

---

### 15. No `autocomplete="off"` on Sensitive Admin Forms

**Severity:** 🟡 MEDIUM

Admin forms (settings, user management) don't disable browser autocomplete. Shared or public computers may cache admin credentials in the browser.

---

### 16. `SELECT *` Queries Throughout — Over-exposure of Data

**Files:** `config/database.php`, `public/checkout.php`, `admin/index.php`, etc.  
**Severity:** 🟡 MEDIUM

`SELECT * FROM users`, `SELECT * FROM orders` etc. fetch all columns including potentially sensitive fields (hashed passwords, internal flags) and return them unnecessarily. If ever joined and exposed through an API, this becomes a data leak.

**Fix:** Use explicit column lists in all queries, especially those whose results are serialized and returned to clients.

---

### 17. No File Upload Size Limits in Apache Config

**File:** [`.htaccess`](file:///c:/xampp/htdocs/Kartly/.htaccess#L60-L70)  
**Severity:** 🟡 MEDIUM

`upload_max_filesize = 10M` and `post_max_size = 10M` are set, but only for PHP 7 and PHP 8. An attacker who finds an upload endpoint could attempt **Denial-of-Service** attacks by sending large requests.

**Fix:** Also add Apache-level `LimitRequestBody 10485760` (10 MB).

---

### 18. XSS — `typoFonts` Key Unescaped in CSS

**File:** [`includes/header.php`](file:///c:/xampp/htdocs/Kartly/includes/header.php#L159-L161)  
**Severity:** 🟡 MEDIUM

```php
<?= $tag ?> { font-family: '<?= htmlspecialchars(trim($fontName)) ?>', ...
```

`$tag` (the HTML tag key like `body`, `h1`) is printed unescaped inside a `<style>` block. If an attacker can inject a rogue `typo_` setting key through the admin panel (e.g., `</style><script>alert(1)</script>`), it would break out of the style context. The `preg_match('/^typo_[a-zA-Z0-9_]+$/', $key)` in settings validates saving, but there's no validation when reading.

**Fix:** Apply `htmlspecialchars()` to `$tag` when outputting it.

---

## 🟢 LOW SEVERITY / INFORMATIONAL

---

### 19. `config/` and `database/` Directories Protected by `RedirectMatch`

**File:** [`.htaccess`](file:///c:/xampp/htdocs/Kartly/.htaccess#L19-L20)  
**Status:** ✅ PASS (but with a caveat)

```apache
RedirectMatch 403 ^/(Kartly/)?config/
RedirectMatch 403 ^/(Kartly/)?database/
```

This protects these directories. However, `RedirectMatch` sends a `301/302` before the `403` on some Apache versions. Using `<Directory>` directives in server config or `FilesMatch Deny` is more reliable.

---

### 20. `.git` Directory Accessible

**Severity:** 🟡 MEDIUM

A `.git` folder exists in the web root. If the server serves the `.git` directory, attackers can reconstruct the entire source code using tools like `GitDumper`. While Apache may block it if `Options -Indexes` is set, explicit protection is better.

**Fix — add to `.htaccess`:**
```apache
RedirectMatch 403 ^/\.git
RedirectMatch 403 ^/\.agents
```

---

### 21. HTTPS-Only Cookies Not Set on Payment Pages

**Severity:** 🟠 HIGH (in production)

Payment session data (coupon codes, cart info) is stored in cookies/sessions that are not flagged as Secure. In production, this data could be transmitted over HTTP. See issue #3 for the full fix.

---

### 22. No Account Lockout / Suspicious Login Alerts

**Severity:** 🟡 MEDIUM

There is no mechanism to:
- Alert users of logins from new IPs
- Lock accounts after repeated failed logins
- Log failed login attempts

---

## ✅ WHAT'S DONE WELL

| Feature | Status |
|---|---|
| PDO Prepared Statements | ✅ Used consistently — SQL injection well-protected |
| Password Hashing | ✅ `password_hash(PASSWORD_DEFAULT)` used correctly |
| File Upload MIME Validation | ✅ `finfo_file()` used for true MIME type detection |
| `is_uploaded_file()` Check | ✅ Properly used to prevent upload spoofing |
| `htmlspecialchars()` on outputs | ✅ Consistently used in HTML output |
| Admin Auth Checks | ✅ All admin pages verify `isLoggedIn() && isAdmin()` |
| Open Redirect Prevention | ✅ Login redirect whitelist implemented |
| Directory Listing Disabled | ✅ `Options -Indexes` set |
| Sensitive File Type Block | ✅ `.sql`, `.log`, `.ini`, `.env` blocked |
| Order Ownership Verified | ✅ Reviews and order data check `user_id` |
| Stock Validation on Order | ✅ Pre-flight stock check exists |
| DB Transactions on Orders | ✅ `beginTransaction()`/`rollBack()` used |

---

## 📋 Prioritized Fix Checklist

### Immediate (Pre-Launch Critical):
- [ ] **Delete** `public/dump_schema.php` and `public/migrate_color.php`
- [ ] **Add `session_regenerate_id(true)`** after login and registration
- [ ] **Set secure session cookie params** (HttpOnly, Secure, SameSite=Lax)
- [ ] **Implement CSRF tokens** on all forms (login, register, checkout, contact, account, admin)
- [ ] **Set `display_errors = 0`** in production
- [ ] **Change DB credentials** — no root, no empty password
- [ ] **Change `SECRET_KEY`** to a real random value
- [ ] **Add rate limiting** on login, register, newsletter, and checkout

### High Priority (Within a Week):
- [ ] Fix **logout** to properly clear session + cookie
- [ ] Add **security headers** (X-Frame-Options, CSP, HSTS, etc.)
- [ ] **Enable HTTPS** redirect in `.htaccess`
- [ ] Remove **`$e->getMessage()`** from API JSON responses
- [ ] Protect **`.git` and `.agents`** directories with `RedirectMatch 403`
- [ ] Escape **`$tag`** in CSS output in `header.php`

### Medium Priority (Within a Month):
- [ ] Increase **minimum password length** to 8+ characters
- [ ] Replace **`SELECT *`** with explicit column lists where data is API-exposed
- [ ] Add **`LimitRequestBody`** directive in Apache
- [ ] Implement **login attempt logging** and account lockout

---

*Report generated by full codebase static analysis. All file paths verified against actual source.*
