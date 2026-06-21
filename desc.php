<?php
require 'config/database.php';
$db = getDB();
print_r($db->query('DESCRIBE products')->fetchAll(PDO::FETCH_ASSOC));
print_r($db->query('DESCRIBE order_items')->fetchAll(PDO::FETCH_ASSOC));
print_r($db->query('DESCRIBE cart_items')->fetchAll(PDO::FETCH_ASSOC));
