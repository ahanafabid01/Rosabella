<?php
/**
 * Rosabella – Admin Notifications API (DB-backed)
 * GET  → syncs live events to DB, returns JSON notification rows
 * POST → mark_read | mark_all_read | delete
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../admin/includes/notification_sync.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$db = getDB();

// ── POST: Actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read' && isset($_POST['id'])) {
        $db->prepare("UPDATE admin_notifications SET is_read = 1, read_at = NOW() WHERE id = ?")
           ->execute([(int)$_POST['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'mark_all_read') {
        $db->exec("UPDATE admin_notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        $db->prepare("DELETE FROM admin_notifications WHERE id = ?")->execute([(int)$_POST['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// ── GET: Sync then return rows ─────────────────────────────────────────────────
try {
    syncAdminNotifications($db);

    $rows = $db->query("
        SELECT * FROM admin_notifications
        ORDER BY is_read ASC, (priority = 'high') DESC, (priority = 'medium') DESC, created_at DESC
        LIMIT 80
    ")->fetchAll(PDO::FETCH_ASSOC);

    $unread = (int)$db->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0")->fetchColumn();

    echo json_encode([
        'success'       => true,
        'total_unread'  => $unread,
        'notifications' => $rows,
        'generated_at'  => date('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal error', 'total_unread' => 0, 'notifications' => []]);
}
