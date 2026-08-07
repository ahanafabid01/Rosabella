<?php
/**
 * KARTLY - Newsletter API
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = $_POST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $payload = array_merge($payload, $decoded);
        }
    }
}

$email = sanitize($payload['email'] ?? '');
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email']);
    exit;
}

// Rate limit: max 5 newsletter subscriptions per 10 minutes per IP
if (!checkRateLimit('newsletter', 5, 600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO newsletter_subscribers (email, status)
        VALUES (?, 'active')
        ON DUPLICATE KEY UPDATE status = 'active', subscribed_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$email]);

    echo json_encode(['success' => true, 'message' => 'Subscribed successfully']);
} catch (Throwable $e) {
    error_log('Newsletter error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to subscribe right now. Please try again later.']);
}
?>
