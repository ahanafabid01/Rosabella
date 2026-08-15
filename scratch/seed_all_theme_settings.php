<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

$newSettings = [
    'admin_sidebar_bg'        => '#f1f5f9',
    'admin_sidebar_text'      => '#1e293b',
    'admin_sidebar_hover_bg'  => '#ffffff',
    'admin_sidebar_active_bg' => '#e6fcf5',
    'admin_content_bg'        => '#f8fafc',
    'admin_content_text'      => '#0f172a',
    'admin_primary_color'     => '#0f766e',
];

foreach ($newSettings as $k => $v) {
    $stmt = $db->prepare("
        INSERT INTO settings (setting_key, setting_value, setting_type)
        VALUES (?, ?, 'text')
        ON DUPLICATE KEY UPDATE setting_type = 'text'
    ");
    $stmt->execute([$k, $v]);
}

echo "All theme, font, hover & highlight settings seeded successfully.\n";
