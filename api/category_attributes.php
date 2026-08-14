<?php
/**
 * Rosabella - Category Attributes API
 * Returns configured master attributes (Sizes, Colors, Variants) applied to a category
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$categoryId = intval($_GET['category_id'] ?? 0);

try {
    $db = getDB();
    
    if ($categoryId > 0) {
        $stmt = $db->prepare("
            SELECT DISTINCT ga.id, ga.attribute_name, ga.attribute_type, ga.attribute_values 
            FROM global_attributes ga
            LEFT JOIN category_attribute_mapping cam ON cam.attribute_id = ga.id
            WHERE ga.apply_to_all = 1 OR cam.category_id = ?
            ORDER BY ga.sort_order ASC, ga.id ASC
        ");
        $stmt->execute([$categoryId]);
    } else {
        $stmt = $db->query("
            SELECT id, attribute_name, attribute_type, attribute_values 
            FROM global_attributes 
            ORDER BY sort_order ASC, id ASC
        ");
    }
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $attributes = [];
    foreach ($rows as $r) {
        $vals = array_filter(array_map('trim', explode(',', $r['attribute_values'])));
        $attributes[] = [
            'id' => intval($r['id']),
            'name' => $r['attribute_name'],
            'type' => $r['attribute_type'],
            'values' => array_values($vals)
        ];
    }

    echo json_encode([
        'success' => true,
        'category_id' => $categoryId,
        'attributes' => $attributes
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'attributes' => []]);
}
