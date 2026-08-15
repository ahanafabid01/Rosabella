<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

$themeSettings = [
    'admin_sidebar_bg'    => '#f1f5f9',
    'admin_content_bg'    => '#f8fafc',
    'admin_primary_color' => '#0f766e',
];

foreach ($themeSettings as $k => $v) {
    $stmt = $db->prepare("
        INSERT INTO settings (setting_key, setting_value, setting_type)
        VALUES (?, ?, 'text')
        ON DUPLICATE KEY UPDATE setting_type = 'text'
    ");
    $stmt->execute([$k, $v]);
}

echo "Theme settings initialized successfully.\n";
