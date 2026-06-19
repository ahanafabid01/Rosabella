<?php
require '../config/database.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE cart ADD COLUMN color VARCHAR(50) DEFAULT NULL AFTER size");
    echo "Color column added successfully.";
} catch (PDOException $e) {
    echo "Error or already exists: " . $e->getMessage();
}
