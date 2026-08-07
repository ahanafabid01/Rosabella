<?php
/**
 * KARTLY - Secure Logout
 * Properly destroys the session: clears data, deletes cookie, regenerates ID.
 */
if (session_status() === PHP_SESSION_NONE) {
    // Apply same secure cookie params before starting (needed for cookie deletion)
    $cookieSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $cookieSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// 1. Clear all session variables
$_SESSION = [];

// 2. Delete the session cookie from the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3. Destroy the server-side session
session_destroy();

// 4. Start a new clean session so BASE_URL is available after require
require_once __DIR__ . '/../config/database.php';

// 5. Redirect to homepage
header('Location: ' . BASE_URL . '/');
exit;
