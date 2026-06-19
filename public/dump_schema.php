<?php
require '../config/database.php';
$db = getDB();
$q = $db->query('SHOW CREATE TABLE products');
echo "<pre>";
print_r($q->fetchAll());
echo "</pre>";
