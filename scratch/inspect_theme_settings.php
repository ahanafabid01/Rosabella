<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'admin_%'");
print_r($stmt->fetchAll(PDO::FETCH_KEY_PAIR));
