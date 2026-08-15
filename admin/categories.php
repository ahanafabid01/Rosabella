<?php
/**
 * Rosabella - Admin Categories Management
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/image_helper.php';
require_once __DIR__ . '/includes/layout.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$db = getDB();

// ── Security: Verify CSRF on all admin POST requests ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
}

$message = '';
$error = '';

$action = $_GET['action'] ?? 'list';
$categoryId = intval($_GET['id'] ?? 0);
$search = sanitize($_GET['search'] ?? '');

if ($action === 'delete' && $categoryId > 0) {
    $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
    if ($stmt->execute([$categoryId])) {
        $message = 'Category deleted successfully.';
    } else {
        $error = 'Unable to delete category.';
    }
    $action = 'list';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'], true)) {
    $name = sanitize($_POST['name'] ?? '');
    $slug = sanitize($_POST['slug'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $image = sanitize($_POST['current_image'] ?? '');
    
    // Handle file upload
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../assets/images/categories/';
        $newPath = optimizeAndSaveImage($_FILES['image_file'], $uploadDir, 600);
        if ($newPath) {
            $image = $newPath;
        }
    }
    $parentId = intval($_POST['parent_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'active');
    $sortOrder = intval($_POST['sort_order'] ?? 0);
    $showOnHome = isset($_POST['show_on_home']) ? 1 : 0;

    if (!$name) {
        $error = 'Category name is required.';
    } else {
        if (!$slug) {
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        }

        try {
            if ($action === 'edit' && $categoryId > 0) {
                $stmt = $db->prepare("
                    UPDATE categories
                    SET name = ?, slug = ?, description = ?, image = ?, parent_id = ?, status = ?, sort_order = ?, show_on_home = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name,
                    $slug,
                    $description ?: null,
                    $image ?: null,
                    $parentId > 0 ? $parentId : null,
                    in_array($status, ['active', 'inactive'], true) ? $status : 'active',
                    $sortOrder,
                    $showOnHome,
                    $categoryId
                ]);
                $savedCatId = $categoryId;
                $message = 'Category updated successfully.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO categories (name, slug, description, image, parent_id, status, sort_order, show_on_home)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $name,
                    $slug,
                    $description ?: null,
                    $image ?: null,
                    $parentId > 0 ? $parentId : null,
                    in_array($status, ['active', 'inactive'], true) ? $status : 'active',
                    $sortOrder,
                    $showOnHome
                ]);
                $savedCatId = intval($db->lastInsertId());
                $message = 'Category created successfully.';
            }

            // Save Category Master Attributes mapping
            if ($savedCatId > 0) {
                $db->prepare("DELETE FROM category_attribute_mapping WHERE category_id = ?")->execute([$savedCatId]);
                if (!empty($_POST['master_attributes']) && is_array($_POST['master_attributes'])) {
                    $stmtMap = $db->prepare("INSERT IGNORE INTO category_attribute_mapping (attribute_id, category_id) VALUES (?, ?)");
                    foreach ($_POST['master_attributes'] as $mAttrId) {
                        $mIdClean = intval($mAttrId);
                        if ($mIdClean > 0) {
                            $stmtMap->execute([$mIdClean, $savedCatId]);
                        }
                    }
                }
            }

            $action = 'list';
            $categoryId = 0;
        } catch (Throwable $e) {
            $error = 'Unable to save category: ' . $e->getMessage();
        }
    }
}

$editingCategory = null;
$assignedMasterIds = [];
$allMasterAttributes = $db->query("SELECT * FROM global_attributes WHERE apply_to_all = 0 ORDER BY sort_order ASC, attribute_name ASC")->fetchAll();

if ($action === 'edit' && $categoryId > 0) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $editingCategory = $stmt->fetch();
    if (!$editingCategory) {
        $error = 'Category not found.';
        $action = 'list';
    } else {
        $stmtMap = $db->prepare("SELECT attribute_id FROM category_attribute_mapping WHERE category_id = ?");
        $stmtMap->execute([$editingCategory['id']]);
        $assignedMasterIds = $stmtMap->fetchAll(PDO::FETCH_COLUMN);
    }
}

$nextSortOrder = 0;
if ($action === 'add') {
    $stmt = $db->query("SELECT MAX(sort_order) FROM categories");
    $maxSort = $stmt->fetchColumn();
    $nextSortOrder = ($maxSort !== false) ? intval($maxSort) + 1 : 0;
}

$parentOptionsStmt = $db->prepare("SELECT id, name FROM categories WHERE (? = 0 OR id != ?) ORDER BY name");
$parentOptionsStmt->execute([$categoryId, $categoryId]);
$parentOptions = $parentOptionsStmt->fetchAll();

$where = '';
$params = [];
if ($search) {
    $where = "WHERE c.name LIKE ? OR c.slug LIKE ?";
    $params = ["%$search%", "%$search%"];
}

// Pagination Setup
$perPage = max(1, min(100, intval($_GET['per_page'] ?? 15)));
$page = max(1, intval($_GET['page'] ?? 1));

$countStmt = $db->prepare("SELECT COUNT(*) FROM categories c $where");
$countStmt->execute($params);
$totalCategories = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalCategories / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT c.*, p.name AS parent_name
    FROM categories c
    LEFT JOIN categories p ON p.id = c.parent_id
    $where
    ORDER BY c.sort_order ASC, c.name ASC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$categories = $stmt->fetchAll();

// Fetch attribute summaries for list view
$stmtAttrSummary = $db->query("SELECT category_id, GROUP_CONCAT(attribute_name SEPARATOR ', ') as attr_summary FROM category_attributes GROUP BY category_id");
$attrSummaries = $stmtAttrSummary->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Categories Management';
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php renderAdminSidebar('categories'); ?>

    <main class="admin-content">
        <?php renderAdminTopbar($pageTitle ?? 'Admin Panel'); ?>
<div class="admin-header">
            <h1 class="admin-page-title">
                <?= $action === 'edit' ? 'Edit Category' : ($action === 'add' ? 'Add Category' : 'Categories') ?>
            </h1>
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="btn btn-primary">+ Add Category</a>
            <?php endif; ?>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <?php if ($action === 'list'): ?>
            <div class="admin-card">
                <form method="GET" class="admin-form-row">
                    <input type="text" class="form-input admin-input-max-320" name="search" placeholder="Search categories by name or slug..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-secondary">Search</button>
                </form>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Parent</th>
                        <th>Configured Attributes</th>
                        <th>Status</th>
                        <th>Sort</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <?php $catAttrs = $attrSummaries[$cat['id']] ?? ''; ?>
                        <tr>
                            <td><?= intval($cat['id']) ?></td>
                            <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                            <td><?= htmlspecialchars($cat['slug']) ?></td>
                            <td><?= htmlspecialchars($cat['parent_name'] ?? '-') ?></td>
                            <td>
                                <?php if ($catAttrs): ?>
                                    <span class="badge badge-info" style="font-size: 0.75rem; font-weight: 600;">🏷️ <?= htmlspecialchars($catAttrs) ?></span>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; color: #94a3b8;">None</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= $cat['status'] === 'active' ? 'success' : 'warning' ?>"><?= htmlspecialchars(ucfirst($cat['status'])) ?></span></td>
                            <td><?= intval($cat['sort_order']) ?></td>
                            <td>
                                <div class="admin-actions-row">
                                    <a class="btn-action-icon edit" href="?action=edit&id=<?= intval($cat['id']) ?>" title="Edit Category">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>
                                    <a class="btn-action-icon delete" href="?action=delete&id=<?= intval($cat['id']) ?>" onclick="return confirm('Delete this category?');" title="Delete Category">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php renderAdminPagination($page, $totalCategories, $perPage, BASE_URL . '/admin/categories', array_filter(['search' => $search])); ?>
        <?php else: ?>
            <div class="admin-card" style="max-width: 860px; margin: 0 auto;">
                <form method="POST" enctype="multipart/form-data">
                        <!-- Security: CSRF token -->
                        <?= csrfField() ?>
                    <div class="form-group">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-input" required value="<?= htmlspecialchars($editingCategory['name'] ?? $_POST['name'] ?? '') ?>" placeholder="e.g., Women's Fashion, Luxury Flowers, Gift Sets">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" class="form-input" value="<?= htmlspecialchars($editingCategory['slug'] ?? $_POST['slug'] ?? '') ?>" placeholder="e.g., womens-fashion (auto-generated if empty)">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Parent Category</label>
                        <select name="parent_id" class="form-select">
                            <option value="0">None</option>
                            <?php foreach ($parentOptions as $parent): ?>
                                <option value="<?= intval($parent['id']) ?>" <?= intval($editingCategory['parent_id'] ?? $_POST['parent_id'] ?? 0) === intval($parent['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($parent['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category Image</label>
                        <?php if (!empty($editingCategory['image'])): ?>
                            <?php 
                                $imgSrc = str_starts_with($editingCategory['image'], 'http') 
                                    ? $editingCategory['image'] 
                                    : '../' . $editingCategory['image']; 
                            ?>
                            <div class="admin-image-preview-wrap">
                                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="Current Category Image" class="admin-image-preview">
                            </div>
                        <?php endif; ?>
                        <input type="hidden" name="current_image" value="<?= htmlspecialchars($editingCategory['image'] ?? $_POST['current_image'] ?? '') ?>">
                        <input type="file" name="image_file" class="form-input" accept="image/*">
                        <div class="admin-upload-help">Upload a transparent PNG or JPG. Leave blank to keep current image.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" placeholder="Write a brief overview of this category for shoppers and search engines..."><?= htmlspecialchars($editingCategory['description'] ?? $_POST['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Category Attributes (Sizes, Colors, Variants) Section -->
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <h3 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0f766e" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                                    Assign Master Attributes (Sizes, Colors, Variants)
                                </h3>
                                <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
                                    Select Master Attributes to apply to this Category. Or manage global attributes on the <a href="attributes.php" target="_blank" style="color: #0f766e; font-weight: 700;">Attributes Page</a>.
                                </p>
                            </div>
                            <a href="attributes.php?action=add" class="btn btn-sm btn-outline" style="font-size: 0.8rem;">
                                + Create Master Attribute
                            </a>
                        </div>

                        <!-- Checkbox list of Master Attributes -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px; max-height: 200px; overflow-y: auto; padding: 10px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px;">
                            <?php if (empty($allMasterAttributes)): ?>
                                <div style="font-size: 0.8rem; color: #94a3b8; padding: 6px;">No master attributes created yet. Global attributes (apply_to_all) will automatically apply.</div>
                            <?php else: ?>
                                <?php foreach ($allMasterAttributes as $mAttr): ?>
                                    <?php $isChecked = in_array(intval($mAttr['id']), $assignedMasterIds, true); ?>
                                    <label style="display: flex; align-items: flex-start; gap: 6px; font-size: 0.82rem; font-weight: 600; color: #334155; cursor: pointer; padding: 6px; border-radius: 6px; background: #f8fafc; border: 1px solid #f1f5f9;">
                                        <input type="checkbox" name="master_attributes[]" value="<?= $mAttr['id'] ?>" <?= $isChecked ? 'checked' : '' ?> style="width: 16px; height: 16px; margin-top: 2px;">
                                        <div>
                                            <div><?= htmlspecialchars($mAttr['attribute_name']) ?></div>
                                            <div style="font-size: 0.72rem; color: #64748b; font-weight: normal;"><?= htmlspecialchars(substr($mAttr['attribute_values'], 0, 35)) ?><?= strlen($mAttr['attribute_values']) > 35 ? '...' : '' ?></div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="admin-two-col-grid">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= ($editingCategory['status'] ?? $_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($editingCategory['status'] ?? $_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sort Order</label>
                            <input type="number" min="0" name="sort_order" class="form-input" value="<?= htmlspecialchars((string)($editingCategory['sort_order'] ?? $_POST['sort_order'] ?? $nextSortOrder)) ?>" placeholder="e.g., 1">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 2rem;">
                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="show_on_home" value="1" <?= (!isset($editingCategory) || $editingCategory['show_on_home']) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                            <span style="font-weight: 500;">Show this category on the Home Page</span>
                        </label>
                        <div class="admin-upload-help" style="margin-top: 0.25rem;">If unchecked, this category will not appear in the "Categories" section on the index page.</div>
                    </div>
                    <div class="admin-actions-row">
                        <button class="btn btn-primary" type="submit">Save Category</button>
                        <a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/categories">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>
</div>
<script src="js/admin.js"></script>
<script>
function addAttrRow(name = '', type = 'variant', values = '') {
    const container = document.getElementById('cat-attributes-list');
    if (!container) return;
    const rowId = 'attr-row-' + Date.now() + '-' + Math.floor(Math.random()*1000);
    const div = document.createElement('div');
    div.id = rowId;
    div.style = "display: grid; grid-template-columns: 1.5fr 1fr 3fr auto; gap: 8px; align-items: center; background: #ffffff; padding: 10px 12px; border-radius: 8px; border: 1px solid #cbd5e1;";
    div.innerHTML = `
        <div>
            <label style="display:block; font-size: 0.72rem; font-weight:700; color:#475569; margin-bottom:2px;">Attribute Name</label>
            <input type="text" name="attr_name[]" class="form-input" value="${name}" placeholder="e.g., Sizes, Colors, Storage" required style="padding: 4px 8px; font-size: 0.82rem;">
        </div>
        <div>
            <label style="display:block; font-size: 0.72rem; font-weight:700; color:#475569; margin-bottom:2px;">Type</label>
            <select name="attr_type[]" class="form-select" style="padding: 4px 8px; font-size: 0.82rem;">
                <option value="size" ${type === 'size' ? 'selected' : ''}>Size</option>
                <option value="color" ${type === 'color' ? 'selected' : ''}>Color</option>
                <option value="variant" ${type === 'variant' ? 'selected' : ''}>Variant</option>
            </select>
        </div>
        <div>
            <label style="display:block; font-size: 0.72rem; font-weight:700; color:#475569; margin-bottom:2px;">Allowed Values (Comma Separated)</label>
            <input type="text" name="attr_values[]" class="form-input" value="${values}" placeholder="e.g., S, M, L, XL or Red, Blue, Black" required style="padding: 4px 8px; font-size: 0.82rem;">
        </div>
        <div style="padding-top: 14px;">
            <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('${rowId}').remove()" style="color: #ef4444; padding: 6px 10px;" title="Remove Attribute">✕</button>
        </div>
    `;
    container.appendChild(div);
}

// Initial populate existing attributes
document.addEventListener('DOMContentLoaded', () => {
    const existing = <?= json_encode($existingAttributes ?? []) ?>;
    if (existing && existing.length > 0) {
        existing.forEach(a => {
            addAttrRow(a.attribute_name, a.attribute_type, a.attribute_values);
        });
    }
});
</script>
</body>
</html>
