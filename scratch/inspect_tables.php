<?php
require_once __DIR__ . '/../../config/database.php';
$db = getDB();
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables in DB:\n";
print_r($tables);

foreach (['categories', 'products'] as $t) {
    if (in_array($t, $tables)) {
        echo "\nColumns in $t:\n";
        $cols = $db->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            echo " - {$c['Field']} ({$c['Type']})\n";
        }
    }
}
