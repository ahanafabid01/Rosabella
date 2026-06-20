<?php
/**
 * KARTLY - Database Configuration
 * Update these values with your hosting provider's database credentials
 */

// Database Configuration
define('DB_HOST', 'localhost');      // Usually 'localhost' on shared hosting
define('DB_NAME', 'kartly_db');       // Database name
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

// Security
define('SECRET_KEY', 'your-secret-key-change-this-in-production');

// Error Reporting (Set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session bootstrap (safe for repeated includes)
if (session_status() === PHP_SESSION_NONE) {
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
 * Verify CSRF Token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
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
