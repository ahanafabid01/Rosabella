<?php
/**
 * KARTLY - Database Configuration
 * Update these values with your hosting provider's database credentials
 */

// Database Configuration
define('DB_HOST', 'localhost');      // Usually 'localhost' on shared hosting
define('DB_NAME', 'rosabella_db');       // Database name
define('DB_USER', 'root');            // Database username
define('DB_PASS', '');                // Database password
define('DB_CHARSET', 'utf8mb4');

// Site Configuration
define('SITE_NAME', 'KARTLY');
define('SITE_EMAIL', 'support@kartly.com');

$requestScheme = 'http';
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $requestScheme = 'https';
} elseif (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
    $requestScheme = 'https';
}
$requestHost = $_SERVER['HTTP_HOST'] ?? 'localhost';

// XAMPP uses /Kartly locally; hosted production is served from the domain root.
$isLocalHost = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $requestHost) === 1;
define('BASE_URL', $isLocalHost ? '/Kartly' : '');
define('SITE_URL', $requestScheme . '://' . $requestHost . BASE_URL);

// Security — IMPORTANT: Change this to a long random value in production!
// Generate one with: bin2hex(random_bytes(32))
define('SECRET_KEY', '0a4b9c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b');

// ─── Error Reporting ──────────────────────────────────────────────────────
// IMPORTANT: Set display_errors to 0 on a live server.
// Errors are logged to a file instead of shown to visitors.
error_reporting(E_ALL);
ini_set('display_errors', 0);        // Never show errors to users
ini_set('log_errors', 1);            // Always log errors
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// ─── Secure Session Bootstrap ─────────────────────────────────────────────
// Must be called BEFORE session_start() on every page.
if (session_status() === PHP_SESSION_NONE) {
    // Secure cookie parameters: HttpOnly + SameSite=Lax + Secure (on HTTPS)
    $cookieSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    session_set_cookie_params([
        'lifetime' => 0,                    // Session cookie (browser close = logout)
        'path'     => '/',
        'domain'   => '',
        'secure'   => $cookieSecure,        // Only sent over HTTPS in production
        'httponly' => true,                 // JavaScript cannot access the cookie
        'samesite' => 'Lax',               // Blocks cross-site cookie submission
    ]);
    session_start();
}

/**
 * Database Connection Class
 */
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Database Connection Failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}

/**
 * Get database connection
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Sanitize input
 */
function sanitize($data) {
    if (is_array($data)) {
        foreach ($data as $k => $v) {
            $data[$k] = sanitize($v);
        }
        return $data;
    }
    return trim($data);
}

/**
 * Generate CSRF Token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token — does NOT rotate the token.
 * Token is session-scoped; it lives for the life of the session.
 * This keeps AJAX calls working after the first POST without needing
 * to refresh the token on every request.
 */
function verifyCSRFToken($token) {
    if (empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Format price
 */
function formatPrice($price) {
    return 'Tk ' . number_format($price, 2);
}

/**
 * Redirect function
 */
function redirect($url) {
    header("Location: " . cleanUrl($url, true));
    exit();
}

/**
 * Build a clean public URL and normalize old internal .php paths.
 */
function cleanUrl(string $path = '', bool $absolute = false): string
{
    $path = trim($path);

    if ($path === '') {
        return $absolute ? rtrim(SITE_URL, '/') . '/' : (BASE_URL !== '' ? BASE_URL . '/' : '/');
    }

    if (preg_match('~^(https?:)?//~i', $path) || preg_match('~^(mailto:|tel:|#)~i', $path)) {
        return $path;
    }

    $parts = parse_url($path);
    $route = trim((string)($parts['path'] ?? ''), '/');
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $localBase = trim(BASE_URL, '/');
    if ($localBase !== '' && ($route === $localBase || str_starts_with($route, $localBase . '/'))) {
        $route = trim(substr($route, strlen($localBase)), '/');
    }

    $route = preg_replace('#^(public|pages)/#', '', $route) ?? $route;
    $route = preg_replace('/\.php$/', '', $route) ?? $route;

    if ($route === 'products') {
        if (!empty($query['category'])) {
            $route = 'category/' . rawurlencode((string)$query['category']);
            unset($query['category']);
        } elseif (($query['filter'] ?? '') === 'sale') {
            $route = 'sale';
            unset($query['filter']);
        } elseif (($query['filter'] ?? '') === 'new') {
            $route = 'new-arrivals';
            unset($query['filter']);
        } elseif (($query['filter'] ?? '') === 'bestseller') {
            $route = 'best-sellers';
            unset($query['filter']);
        } else {
            $route = 'shop';
        }
    }

    $base = $absolute ? rtrim(SITE_URL, '/') : rtrim(BASE_URL, '/');
    $url = $base . '/' . ltrim($route, '/');

    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    if (!empty($parts['fragment'])) {
        $url .= '#' . $parts['fragment'];
    }

    return $url;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Get current user
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, phone, address, city, country, role, status, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Get cart count
 */
function getCartCount() {
    $db = getDB();
    $sessionId = session_id();
    
    if (isLoggedIn()) {
        $stmt = $db->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ? OR session_id = ?");
        $stmt->execute([$_SESSION['user_id'], $sessionId]);
    } else {
        $stmt = $db->prepare("SELECT SUM(quantity) as count FROM cart WHERE session_id = ?");
        $stmt->execute([$sessionId]);
    }
    
    $result = $stmt->fetch();
    return $result['count'] ?? 0;
}

/**
 * Get wishlist count
 */
function getWishlistCount() {
    $db = getDB();
    $sessionId = session_id();
    
    if (isLoggedIn()) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ? OR session_id = ?");
        $stmt->execute([$_SESSION['user_id'], $sessionId]);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM wishlist WHERE session_id = ?");
        $stmt->execute([$sessionId]);
    }
    
    $result = $stmt->fetch();
    return $result['count'] ?? 0;
}

/**
 * Get setting value
 */
function getSetting($key) {
    $db = getDB();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : null;
}

/**
 * ─── RATE LIMITING ──────────────────────────────────────────────────────────
 * Session-based sliding-window rate limiter.
 * Protects login, register, newsletter, and checkout from bot/brute-force attacks.
 *
 * @param string $action    A unique key for the action being rate-limited (e.g. 'login')
 * @param int    $maxHits   Maximum allowed attempts within $windowSeconds
 * @param int    $windowSeconds  Time window in seconds
 * @return bool  TRUE if under limit (allowed), FALSE if limit exceeded (blocked)
 */
function checkRateLimit(string $action, int $maxHits = 5, int $windowSeconds = 300): bool
{
    $key = 'rl_' . $action . '_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }

    // Remove attempts outside the sliding window
    $_SESSION[$key] = array_values(array_filter(
        $_SESSION[$key],
        static fn($ts) => ($now - $ts) < $windowSeconds
    ));

    if (count($_SESSION[$key]) >= $maxHits) {
        return false; // Limit exceeded — block
    }

    // Record this attempt
    $_SESSION[$key][] = $now;
    return true; // Under limit — allow
}

/**
 * Get remaining cooldown seconds for a rate-limited action.
 * Returns 0 if not currently rate-limited.
 */
function getRateLimitCooldown(string $action, int $maxHits = 5, int $windowSeconds = 300): int
{
    $key = 'rl_' . $action . '_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $now = time();

    if (!isset($_SESSION[$key]) || count($_SESSION[$key]) < $maxHits) {
        return 0;
    }

    $active = array_filter($_SESSION[$key], static fn($ts) => ($now - $ts) < $windowSeconds);
    if (count($active) < $maxHits) {
        return 0;
    }

    $oldest = min($active);
    return max(0, ($oldest + $windowSeconds) - $now);
}

/**
 * ─── SECURITY HELPER: Redirect with a message (used after blocked requests) ──
 */
function securityBlock(string $message, int $statusCode = 429): void
{
    http_response_code($statusCode);
    // For JSON-expecting callers
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    // For form-based callers, store in session and let the page handle it
    $_SESSION['security_block_message'] = $message;
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/'));
    exit;
}

/**
 * ─── SECURITY HELPER: Output CSRF token hidden field HTML ──────────────────
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
}

/**
 * ─── SECURITY HELPER: Assert CSRF token on POST requests ───────────────────
 * Call at the top of any POST handler. Terminates with 403 if invalid.
 * For browser form submissions, rotates the token after success and embeds
 * the new token in X-CSRF-Token response header for SPA/AJAX to pick up.
 */
function requireCSRF(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verifyCSRFToken((string)$token)) {
        http_response_code(403);
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh and try again.']);
            exit;
        }
        // Show a user-friendly page for browser form submissions
        $msg = 'Security token mismatch. Please go back, refresh the page, and try again.';
        die('<html><head><title>Security Error</title></head><body style="font-family:sans-serif;padding:3rem;text-align:center"><h2>&#128274; Security Error</h2><p>' . htmlspecialchars($msg) . '</p><a href="javascript:history.back()">Go Back</a></body></html>');
    }
    // For non-AJAX browser forms: rotate the CSRF token after a valid submission
    // so that the back-button / replay attack is blocked.
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
              || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
              || !empty($_SERVER['HTTP_X_CSRF_TOKEN']);
    if (!$isAjax) {
        // Rotate token for form POSTs; the next page render will write the new token into the form.
        unset($_SESSION['csrf_token']);
    }
}

