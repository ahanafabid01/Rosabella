<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

$newSettings = [
    'admin_sidebar_text' => '#1e293b',
    'admin_content_text' => '#0f172a',
];

foreach ($newSettings as $k => $v) {
    $stmt = $db->prepare("
        INSERT INTO settings (setting_key, setting_value, setting_type)
        VALUES (?, ?, 'text')
        ON DUPLICATE KEY UPDATE setting_type = 'text'
    ");
    $stmt->execute([$k, $v]);
}

echo "Sidebar & Content font color settings initialized successfully.\n";
