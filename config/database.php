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
define('SITE_URL', 'http://localhost/kartly');  // Change to your domain
define('SITE_EMAIL', 'support@kartly.com');

// BASE_URL is used for all internal links.
// For local XAMPP: set to '/Kartly'
// For live domain root: set to '' (empty string)
define('BASE_URL', '/Kartly');

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
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
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
    header("Location: " . SITE_URL . "/" . $url);
    exit();
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
?>
