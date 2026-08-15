<?php
/**
 * Rosabella - Database Configuration
 * Update these values with your hosting provider's database credentials
 */

// Database Configuration
define('DB_HOST', 'localhost');      // Usually 'localhost' on shared hosting
define('DB_NAME', 'rosabella_db');       // Database name
define('DB_USER', 'root');            // Database username
define('DB_PASS', '');                // Database password
define('DB_CHARSET', 'utf8mb4');

// Site Configuration
define('SITE_NAME', 'ROSABELLA');
define('SITE_EMAIL', 'support@rosabella.com');

$requestScheme = 'http';
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $requestScheme = 'https';
} elseif (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
    $requestScheme = 'https';
}
$requestHost = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Dynamically detect BASE_URL: on localhost, use the folder name; on production, use root
$isLocalHost = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $requestHost) === 1;
if ($isLocalHost) {
    // Get the actual project folder name from the current file path
    // __DIR__ is the config folder, so dirname(__DIR__) is the project root
    $projectRoot = dirname(__DIR__);
    $folderName = basename($projectRoot);
    define('BASE_URL', ($folderName && $folderName !== 'htdocs') ? '/' . $folderName : '');
} else {
    define('BASE_URL', '');  // Production: served from domain root
}
define('SITE_URL', $requestScheme . '://' . $requestHost . BASE_URL);

// Security — IMPORTANT: Change this to a long random value in production!
// Generate one with: bin2hex(random_bytes(32))
define('SECRET_KEY', 'your-secret-key-change-this-in-production');

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
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
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

/**
 * ─── STOREFRONT MAINTENANCE MODE GUARD ─────────────────────────────────────
 * When Maintenance Mode is enabled in Admin Settings, non-admin visitors
 * receive a sleek 503 Maintenance page, while admins retain full access.
 */
function checkMaintenanceMode(): void
{
    if (php_sapi_name() === 'cli') {
        return;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // Whitelist administrative, authentication, and static asset routes
    if (
        strpos($uri, '/admin') !== false ||
        strpos($uri, '/login') !== false ||
        strpos($uri, '/logout') !== false ||
        strpos($uri, '/assets') !== false ||
        strpos($uri, '/uploads') !== false
    ) {
        return;
    }

    // Allow logged-in administrators to view storefront normally
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        return;
    }

    // Check maintenance mode state from database settings
    try {
        $isMaintenance = getSetting('maintenance_mode');
        if ($isMaintenance === '1' || $isMaintenance === 'true') {
            $msg = getSetting('maintenance_message') ?: 'We are currently upgrading our store experience to serve you better. We will be back online shortly!';
            $siteName = getSetting('site_name') ?: 'Rosabella';
            
            http_response_code(503);
            header('Retry-After: 3600');
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Under Maintenance &mdash; <?= htmlspecialchars($siteName) ?></title>
                <link rel="preconnect" href="https://fonts.googleapis.com">
                <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
                <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
                <style>
                    * { box-sizing: border-box; margin: 0; padding: 0; }
                    body {
                        font-family: 'Inter', system-ui, -apple-system, sans-serif;
                        background: radial-gradient(circle at top, #1e293b 0%, #0f172a 100%);
                        color: #f8fafc;
                        min-height: 100vh;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        padding: 1.5rem;
                    }
                    .maint-container {
                        background: rgba(30, 41, 59, 0.85);
                        backdrop-filter: blur(12px);
                        border: 1px solid rgba(255, 255, 255, 0.1);
                        border-radius: 20px;
                        max-width: 540px;
                        width: 100%;
                        padding: 3rem 2.25rem;
                        text-align: center;
                        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
                        animation: fadeInUp 0.4s ease-out;
                    }
                    @keyframes fadeInUp {
                        from { opacity: 0; transform: translateY(20px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                    .maint-icon-badge {
                        width: 72px;
                        height: 72px;
                        background: rgba(15, 118, 110, 0.18);
                        border: 1px solid rgba(45, 212, 191, 0.35);
                        color: #2dd4bf;
                        border-radius: 50%;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        margin-bottom: 1.75rem;
                        box-shadow: 0 0 30px rgba(45, 212, 191, 0.2);
                    }
                    h1 {
                        font-family: 'Plus Jakarta Sans', sans-serif;
                        font-size: 1.65rem;
                        font-weight: 700;
                        color: #ffffff;
                        margin-bottom: 0.85rem;
                        letter-spacing: -0.02em;
                    }
                    p {
                        font-size: 0.95rem;
                        color: #94a3b8;
                        line-height: 1.65;
                        margin-bottom: 2rem;
                    }
                    .maint-status-pill {
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                        padding: 6px 14px;
                        border-radius: 999px;
                        background: rgba(239, 68, 68, 0.15);
                        border: 1px solid rgba(239, 68, 68, 0.3);
                        color: #fca5a5;
                        font-size: 0.78rem;
                        font-weight: 600;
                        text-transform: uppercase;
                        letter-spacing: 0.05em;
                        margin-bottom: 1.5rem;
                    }
                    .maint-status-dot {
                        width: 8px;
                        height: 8px;
                        border-radius: 50%;
                        background: #ef4444;
                        box-shadow: 0 0 8px #ef4444;
                        animation: pulse 1.5s infinite;
                    }
                    @keyframes pulse {
                        0%, 100% { opacity: 1; transform: scale(1); }
                        50% { opacity: 0.4; transform: scale(0.85); }
                    }
                    .maint-actions {
                        display: flex;
                        justify-content: center;
                        gap: 12px;
                    }
                    .admin-link {
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                        color: #2dd4bf;
                        text-decoration: none;
                        font-size: 0.84rem;
                        font-weight: 600;
                        padding: 0.6rem 1.2rem;
                        border: 1px solid rgba(45, 212, 191, 0.35);
                        border-radius: 10px;
                        background: rgba(15, 118, 110, 0.12);
                        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
                    }
                    .admin-link:hover {
                        background: rgba(15, 118, 110, 0.25);
                        border-color: #2dd4bf;
                        transform: translateY(-1px);
                    }
                </style>
            </head>
            <body>
                <div class="maint-container">
                    <div class="maint-status-pill">
                        <span class="maint-status-dot"></span>
                        <span>Scheduled System Maintenance</span>
                    </div>
                    <div class="maint-icon-badge">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <h1>We'll Be Back Soon</h1>
                    <p><?= nl2br(htmlspecialchars($msg)) ?></p>
                    <div class="maint-actions">
                        <a href="<?= BASE_URL ?>/login" class="admin-link">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <span>Staff & Admin Sign In</span>
                        </a>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    } catch (Throwable $e) {
        // Fail open if database is momentarily unreachable during boot
    }
}

// Automatically enforce maintenance mode on storefront requests
checkMaintenanceMode();

