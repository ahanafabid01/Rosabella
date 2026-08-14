<?php
/**
 * Rosabella - Admin Master Attributes Management
 * Create global master attributes and apply them to specific or all categories.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once __DIR__ . '/includes/layout.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$db = getDB();

// Security: Verify CSRF on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
}

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$attrId = intval($_GET['id'] ?? 0);

// Handle Delete Attribute
if ($action === 'delete' && $attrId > 0) {
    $stmt = $db->prepare("DELETE FROM global_attributes WHERE id = ?");
    if ($stmt->execute([$attrId])) {
        $message = 'Attribute deleted successfully.';
    } else {
        $error = 'Unable to delete attribute.';
    }
    $action = 'list';
}

// Handle Form Submit (Add / Edit Attribute + Category Mappings)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attribute'])) {
    $attrName = sanitize($_POST['attribute_name'] ?? '');
    $attrType = sanitize($_POST['attribute_type'] ?? 'variant');
    $attrValues = sanitize($_POST['attribute_values'] ?? '');
    $applyToAll = isset($_POST['apply_to_all']) ? 1 : 0;
    $assignedCategories = isset($_POST['categories']) && is_array($_POST['categories']) ? array_map('intval', $_POST['categories']) : [];

    if (!$attrName) {
        $error = 'Attribute Name is required.';
    } elseif (!$attrValues) {
        $error = 'Allowed Values are required.';
    } else {
        try {
            if ($action === 'edit' && $attrId > 0) {
                $stmt = $db->prepare("UPDATE global_attributes SET attribute_name = ?, attribute_type = ?, attribute_values = ?, apply_to_all = ? WHERE id = ?");
                $stmt->execute([$attrName, $attrType, $attrValues, $applyToAll, $attrId]);
                $savedId = $attrId;
                $message = 'Attribute updated successfully.';
            } else {
                $stmt = $db->prepare("INSERT INTO global_attributes (attribute_name, attribute_type, attribute_values, apply_to_all) VALUES (?, ?, ?, ?)");
                $stmt->execute([$attrName, $attrType, $attrValues, $applyToAll]);
                $savedId = intval($db->lastInsertId());
                $message = 'Attribute created successfully.';
            }

            // Sync category mappings
            if ($savedId > 0) {
                $db->prepare("DELETE FROM category_attribute_mapping WHERE attribute_id = ?")->execute([$savedId]);
                if ($applyToAll === 0 && !empty($assignedCategories)) {
                    $stmtMap = $db->prepare("INSERT IGNORE INTO category_attribute_mapping (attribute_id, category_id) VALUES (?, ?)");
                    foreach ($assignedCategories as $cId) {
                        if ($cId > 0) {
                            $stmtMap->execute([$savedId, $cId]);
                        }
                    }
                }
            }

            $action = 'list';
            $attrId = 0;
        } catch (Throwable $e) {
            $error = 'Unable to save attribute: ' . $e->getMessage();
        }
    }
}

// Fetch all categories for checkboxes and filters
$allCategories = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// Fetch single attribute for edit with assigned categories
$editingAttr = null;
$assignedCategoryIds = [];
if ($action === 'edit' && $attrId > 0) {
    $stmt = $db->prepare("SELECT * FROM global_attributes WHERE id = ?");
    $stmt->execute([$attrId]);
    $editingAttr = $stmt->fetch();
    if (!$editingAttr) {
        $error = 'Attribute not found.';
        $action = 'list';
    } else {
        $stmtMap = $db->prepare("SELECT category_id FROM category_attribute_mapping WHERE attribute_id = ?");
        $stmtMap->execute([$attrId]);
        $assignedCategoryIds = $stmtMap->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Filters for list view
$search = sanitize($_GET['search'] ?? '');
$typeFilter = sanitize($_GET['type'] ?? '');
$filterCategory = intval($_GET['category_id'] ?? 0);

$where = [];
$params = [];

if ($typeFilter !== '') {
    $where[] = "ga.attribute_type = ?";
    $params[] = $typeFilter;
}
if ($filterCategory > 0) {
    $where[] = "(ga.apply_to_all = 1 OR cam.category_id = ?)";
    $params[] = $filterCategory;
}
if ($search !== '') {
    $where[] = "(ga.attribute_name LIKE ? OR ga.attribute_values LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Pagination
$perPage = max(1, min(100, intval($_GET['per_page'] ?? 15)));
$page = max(1, intval($_GET['page'] ?? 1));

$countStmt = $db->prepare("
    SELECT COUNT(DISTINCT ga.id) 
    FROM global_attributes ga
    LEFT JOIN category_attribute_mapping cam ON cam.attribute_id = ga.id
    $whereSql
");
$countStmt->execute($params);
$totalAttributes = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalAttributes / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT ga.*, GROUP_CONCAT(c.name SEPARATOR ', ') AS assigned_category_names, COUNT(cam.category_id) AS category_count
    FROM global_attributes ga
    LEFT JOIN category_attribute_mapping cam ON cam.attribute_id = ga.id
    LEFT JOIN categories c ON c.id = cam.category_id
    $whereSql
    GROUP BY ga.id
    ORDER BY ga.sort_order ASC, ga.attribute_name ASC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$attributesList = $stmt->fetchAll();

$pageTitle = 'Attributes Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $siteFavicon = getSetting('site_favicon'); if ($siteFavicon): ?>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . '/' . htmlspecialchars($siteFavicon) ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Rosabella Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <script src="../assets/js/color-picker-autocomplete.js"></script>
    <style>
    /* ── Attributes Page Mobile Overrides ── */
    .attr-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
    .attr-header-text { flex: 1 1 auto; }
    .attr-header-text h1 { margin: 0; }
    .attr-header-text p { font-size: 0.85rem; color: #64748b; margin: 2px 0 0; }
    .attr-filter-form { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: center; }
    .attr-filter-form .form-input,
    .attr-filter-form .form-select { flex: 1 1 160px; min-width: 0; }
    .attr-filter-form .btn { flex: 0 0 auto; }
    /* Card view for mobile attributes table */
    .attr-mobile-card { display: none; }
    .attr-mobile-card-item { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem; margin-bottom: 0.75rem; }
    .attr-mobile-card-item + .attr-mobile-card-item { margin-top: 0; }
    .attr-mobile-name { font-weight: 700; font-size: 0.95rem; color: #0f172a; margin-bottom: 0.4rem; }
    .attr-mobile-row { display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.8rem; color: #64748b; margin-bottom: 0.35rem; }
    .attr-mobile-row strong { color: #334155; min-width: 70px; flex-shrink: 0; }
    .attr-mobile-pills { display: flex; flex-wrap: wrap; gap: 4px; }
    .attr-mobile-actions { display: flex; gap: 0.5rem; margin-top: 0.75rem; }
    .attr-mobile-actions .btn { flex: 1; text-align: center; font-size: 0.8rem; padding: 0.4rem 0.6rem; }
    /* Preset buttons */
    .attr-preset-bar { display: flex; flex-wrap: wrap; gap: 0.4rem; }
    /* Form header */
    .attr-form-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.25rem; }
    .attr-form-header h3 { margin: 0; }
    @media (max-width: 600px) {
        .admin-table-wrap { display: none; }
        .attr-mobile-card { display: block; }
        .attr-header { flex-direction: column; }
        .attr-header .btn { width: 100%; text-align: center; }
        .attr-filter-form .form-input,
        .attr-filter-form .form-select { flex: 1 1 100%; }
        .attr-form-header { flex-direction: column; }
        .attr-preset-bar button { font-size: 0.75rem; padding: 0.35rem 0.55rem; }
    }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php renderAdminSidebar('attributes'); ?>

    <main class="admin-content">
        <?php renderAdminTopbar($pageTitle); ?>

        <!-- Page Header -->
        <div class="attr-header">
            <div class="attr-header-text">
                <h1 class="admin-title">Attributes Management</h1>
                <p>Create global master attributes (Sizes, Colors, Variants) and apply them to specific categories.</p>
            </div>
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="btn btn-primary" style="white-space: nowrap;">+ Create Master Attribute</a>
            <?php endif; ?>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- Filter Toolbar -->
            <div class="admin-card">
                <form method="GET" class="attr-filter-form">
                    <input type="text" name="search" class="form-input" placeholder="Search attribute or options..." value="<?= htmlspecialchars($search) ?>">

                    <select name="category_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">All Categories</option>
                        <?php foreach ($allCategories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $filterCategory == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="size" <?= $typeFilter === 'size' ? 'selected' : '' ?>>Size</option>
                        <option value="color" <?= $typeFilter === 'color' ? 'selected' : '' ?>>Color</option>
                        <option value="variant" <?= $typeFilter === 'variant' ? 'selected' : '' ?>>Variant</option>
                    </select>

                    <button type="submit" class="btn btn-secondary">Filter</button>
                    <?php if ($search || $filterCategory || $typeFilter): ?>
                        <a href="attributes.php" class="btn btn-outline">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Master Attributes Table (Desktop) -->
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Attribute Name</th>
                            <th>Type</th>
                            <th>Applied Categories</th>
                            <th>Allowed Values / Options</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attributesList)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 2.5rem;">
                                    No attributes found. Click <strong>+ Create Master Attribute</strong> above to add one.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attributesList as $attr): ?>
                                <?php
                                    $badgeClass = 'info';
                                    if ($attr['attribute_type'] === 'size') $badgeClass = 'success';
                                    elseif ($attr['attribute_type'] === 'color') $badgeClass = 'warning';
                                    $valuesList = explode(',', $attr['attribute_values']);
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: #0f172a; font-size: 0.9rem;"><?= htmlspecialchars($attr['attribute_name']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $badgeClass ?>" style="font-size: 0.75rem; text-transform: uppercase;">
                                            <?= htmlspecialchars($attr['attribute_type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($attr['apply_to_all']): ?>
                                            <span class="badge badge-success" style="font-size: 0.75rem; font-weight: 700;">🌐 All Categories</span>
                                        <?php elseif (!empty($attr['assigned_category_names'])): ?>
                                            <span class="badge badge-secondary" style="font-size: 0.75rem; font-weight: 600;" title="<?= htmlspecialchars($attr['assigned_category_names']) ?>">
                                                🏷️ <?= $attr['category_count'] ?> Category(ies)
                                            </span>
                                            <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                <?= htmlspecialchars($attr['assigned_category_names']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-size: 0.75rem; color: #94a3b8;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-wrap: wrap; gap: 4px; max-width: 380px;">
                                            <?php foreach (array_slice($valuesList, 0, 8) as $val): ?>
                                                <span style="display: inline-block; padding: 2px 7px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.76rem; font-weight: 600; color: #334155;">
                                                    <?= htmlspecialchars(trim($val)) ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if (count($valuesList) > 8): ?>
                                                <span style="font-size: 0.75rem; color: #64748b; font-weight: 600;">+<?= count($valuesList) - 8 ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="admin-actions-row" style="justify-content: flex-end;">
                                            <a class="btn btn-sm btn-outline" href="?action=edit&id=<?= $attr['id'] ?>">Edit</a>
                                            <a class="btn btn-sm btn-secondary" href="?action=delete&id=<?= $attr['id'] ?>" onclick="return confirm('Delete this attribute?');">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Master Attributes Card View (Mobile) -->
            <div class="attr-mobile-card">
                <?php if (empty($attributesList)): ?>
                    <div style="text-align: center; color: #94a3b8; padding: 2.5rem 1rem; background: #fff; border-radius: 10px; border: 1px solid #e2e8f0;">
                        No attributes found. Click <strong>+ Create Master Attribute</strong> above to add one.
                    </div>
                <?php else: ?>
                    <?php foreach ($attributesList as $attr): ?>
                        <?php
                            $badgeClass = 'info';
                            if ($attr['attribute_type'] === 'size') $badgeClass = 'success';
                            elseif ($attr['attribute_type'] === 'color') $badgeClass = 'warning';
                            $valuesList = explode(',', $attr['attribute_values']);
                        ?>
                        <div class="attr-mobile-card-item">
                            <div class="attr-mobile-name"><?= htmlspecialchars($attr['attribute_name']) ?></div>

                            <div class="attr-mobile-row">
                                <strong>Type:</strong>
                                <span class="badge badge-<?= $badgeClass ?>" style="font-size: 0.72rem; text-transform: uppercase;"><?= htmlspecialchars($attr['attribute_type']) ?></span>
                            </div>

                            <div class="attr-mobile-row">
                                <strong>Categories:</strong>
                                <span>
                                    <?php if ($attr['apply_to_all']): ?>
                                        <span class="badge badge-success" style="font-size: 0.72rem;">🌐 All</span>
                                    <?php elseif (!empty($attr['assigned_category_names'])): ?>
                                        <span title="<?= htmlspecialchars($attr['assigned_category_names']) ?>"><?= $attr['category_count'] ?> categor<?= $attr['category_count'] == 1 ? 'y' : 'ies' ?></span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">None</span>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="attr-mobile-row" style="flex-direction: column; align-items: flex-start;">
                                <strong style="margin-bottom: 4px;">Options:</strong>
                                <div class="attr-mobile-pills">
                                    <?php foreach (array_slice($valuesList, 0, 6) as $val): ?>
                                        <span style="padding: 2px 7px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.75rem; font-weight: 600; color: #334155;">
                                            <?= htmlspecialchars(trim($val)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($valuesList) > 6): ?>
                                        <span style="font-size: 0.75rem; color: #64748b; font-weight: 600; padding: 2px 4px;">+<?= count($valuesList) - 6 ?> more</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="attr-mobile-actions">
                                <a class="btn btn-sm btn-outline" href="?action=edit&id=<?= $attr['id'] ?>">Edit</a>
                                <a class="btn btn-sm btn-secondary" href="?action=delete&id=<?= $attr['id'] ?>" onclick="return confirm('Delete this attribute?');">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php renderAdminPagination($page, $totalAttributes, $perPage, BASE_URL . '/admin/attributes.php', array_filter(['search' => $search, 'category_id' => $filterCategory, 'type' => $typeFilter])); ?>

        <?php else: ?>
            <!-- Form: Add / Edit Master Attribute -->
            <div class="admin-card" style="max-width: 760px; margin: 0 auto;">
                <!-- Form Header + Presets -->
                <div class="attr-form-header">
                    <h3 class="admin-section-heading" style="margin: 0;">
                        <?= $action === 'edit' ? 'Edit Master Attribute' : 'Create Master Attribute' ?>
                    </h3>
                    <div class="attr-preset-bar">
                        <button type="button" class="btn btn-sm btn-secondary" onclick="loadPreset('Standard Clothing Sizes', 'size', 'XS, S, M, L, XL, XXL, 3XL')">+ Sizes Preset</button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="loadPreset('Shoe Sizes', 'size', '38, 39, 40, 41, 42, 43, 44, 45')">+ Shoes Preset</button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="loadPreset('Standard Colors', 'color', 'Black, White, Navy Blue, Crimson Red, Olive Green, Beige, Gray')">+ Colors Preset</button>
                    </div>
                </div>

                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="save_attribute" value="1">

                    <div class="admin-two-col-grid">
                        <div class="form-group">
                            <label class="form-label">Attribute Name *</label>
                            <input type="text" id="attr_name_input" name="attribute_name" class="form-input" required
                                value="<?= htmlspecialchars($editingAttr['attribute_name'] ?? '') ?>"
                                placeholder="e.g., Clothing Sizes, Color Palette, Storage Capacity">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Attribute Type *</label>
                            <select id="attr_type_select" name="attribute_type" class="form-select" required>
                                <option value="size" <?= ($editingAttr['attribute_type'] ?? '') === 'size' ? 'selected' : '' ?>>Size</option>
                                <option value="color" <?= ($editingAttr['attribute_type'] ?? '') === 'color' ? 'selected' : '' ?>>Color</option>
                                <option value="variant" <?= ($editingAttr['attribute_type'] ?? 'variant') === 'variant' ? 'selected' : '' ?>>Variant</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Allowed Options / Values * <span style="font-weight: 400; color: #64748b;">(Comma Separated)</span></label>
                        <textarea id="attr_values_input" name="attribute_values" class="form-textarea" style="height: 90px;" required
                            placeholder="e.g., S, M, L, XL, XXL  or  Red, Crimson Red, Navy Blue, Rose Gold, Black, White"><?= htmlspecialchars($editingAttr['attribute_values'] ?? '') ?></textarea>
                        <div class="admin-upload-help" style="margin-top: 4px;">Enter comma-separated values. For Color type, use the swatch picker below!</div>

                        <!-- Color Swatch Search Autocomplete Container -->
                        <div id="color-swatch-picker-widget" style="margin-top: 10px; display: none; background: #f0fdf4; border: 1.5px solid #a7f3d0; border-radius: 10px; padding: 12px;">
                            <label style="font-size: 0.82rem; font-weight: 700; color: #047857; display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#047857" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.92 0 1.7-.72 1.7-1.65 0-.43-.17-.83-.44-1.14-.29-.33-.46-.77-.46-1.21 0-.93.75-1.7 1.68-1.7H16c3.31 0 6-2.69 6-6 0-4.97-4.48-9-10-9z"/></svg>
                                Search &amp; Pick E-Commerce Colors (~150 Swatches)
                            </label>
                        </div>
                    </div>

                    <!-- Category Assignment Section -->
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.5rem;">
                            <label class="form-label" style="font-weight: 700; color: #0f172a; margin: 0;">🏷️ Apply to Categories</label>
                            <label style="font-size: 0.8rem; font-weight: 600; color: #0f766e; cursor: pointer; display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                                <input type="checkbox" id="toggle-all-cats" name="apply_to_all" value="1"
                                    <?= (!empty($editingAttr['apply_to_all']) || $action === 'add') ? 'checked' : '' ?>
                                    onchange="toggleCategoryGrid(this.checked)"
                                    style="width: 16px; height: 16px;">
                                Apply to ALL Categories
                            </label>
                        </div>
                        <p style="font-size: 0.78rem; color: #64748b; margin-bottom: 1rem;">
                            Uncheck "Apply to ALL Categories" to select specific categories below.
                        </p>

                        <div id="category-checkboxes-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px; max-height: 240px; overflow-y: auto; padding: 8px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; <?= (!empty($editingAttr['apply_to_all']) || $action === 'add') ? 'opacity: 0.5; pointer-events: none;' : '' ?>">
                            <?php foreach ($allCategories as $cat): ?>
                                <?php $isAssigned = in_array(intval($cat['id']), $assignedCategoryIds, true); ?>
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 0.82rem; font-weight: 600; color: #334155; cursor: pointer; padding: 6px 8px; border-radius: 6px; background: #f8fafc; border: 1px solid #f1f5f9;">
                                    <input type="checkbox" name="categories[]" value="<?= $cat['id'] ?>" <?= $isAssigned ? 'checked' : '' ?> style="width: 16px; height: 16px; flex-shrink: 0;">
                                    <span style="line-height: 1.3;"><?= htmlspecialchars($cat['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="admin-actions-row" style="margin-top: 1.5rem; flex-wrap: wrap;">
                        <button class="btn btn-primary" type="submit" style="flex: 1 1 auto;">Save Attribute</button>
                        <a class="btn btn-secondary" href="attributes.php" style="flex: 1 1 auto; text-align: center;">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>
</div>
<script src="js/admin.js"></script>
<script src="../assets/js/color-picker-autocomplete.js"></script>
<script>
function toggleCategoryGrid(applyToAll) {
    const grid = document.getElementById('category-checkboxes-grid');
    if (grid) {
        if (applyToAll) {
            grid.style.opacity = '0.5';
            grid.style.pointerEvents = 'none';
        } else {
            grid.style.opacity = '1';
            grid.style.pointerEvents = 'auto';
        }
    }
}

let colorPickerInited = false;
function handleTypeChange() {
    const typeSelect = document.getElementById('attr_type_select');
    const colorWidget = document.getElementById('color-swatch-picker-widget');
    if (typeSelect && colorWidget) {
        if (typeSelect.value === 'color') {
            colorWidget.style.display = 'block';
            if (!colorPickerInited && window.initColorSearchPicker) {
                window.initColorSearchPicker('color-swatch-picker-widget', 'attr_values_input');
                colorPickerInited = true;
            }
        } else {
            colorWidget.style.display = 'none';
        }
    }
}

function loadPreset(name, type, values) {
    document.getElementById('attr_name_input').value = name;
    document.getElementById('attr_type_select').value = type;
    document.getElementById('attr_values_input').value = values;
    handleTypeChange();
    if (type === 'color' && window.initColorSearchPicker) {
        const input = document.getElementById('attr_values_input');
        if (input) input.dispatchEvent(new Event('input'));
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const typeSelect = document.getElementById('attr_type_select');
    if (typeSelect) {
        typeSelect.addEventListener('change', handleTypeChange);
        handleTypeChange();
    }
});
</script>
</body>
</html>
