<?php
/**
 * KARTLY - Admin Categories Management
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $slug = sanitize($_POST['slug'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $image = sanitize($_POST['current_image'] ?? '');
    
    // Handle file upload
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../assets/images/categories/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', basename($_FILES['image_file']['name']));
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetPath)) {
            $image = 'assets/images/categories/' . $fileName;
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
                $message = 'Category created successfully.';
            }
            $action = 'list';
            $categoryId = 0;
        } catch (Throwable $e) {
            $error = 'Unable to save category. Ensure slug is unique.';
        }
    }
}

$editingCategory = null;
if ($action === 'edit' && $categoryId > 0) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $editingCategory = $stmt->fetch();
    if (!$editingCategory) {
        $error = 'Category not found.';
        $action = 'list';
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
$stmt = $db->prepare("
    SELECT c.*, p.name AS parent_name
    FROM categories c
    LEFT JOIN categories p ON p.id = c.parent_id
    $where
    ORDER BY c.sort_order ASC, c.name ASC
");
$stmt->execute($params);
$categories = $stmt->fetchAll();

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
    <title><?= $pageTitle ?> - KARTLY Admin</title>
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
                    <input type="text" class="form-input admin-input-max-320" name="search" placeholder="Search categories..." value="<?= htmlspecialchars($search) ?>">
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
                        <th>Status</th>
                        <th>Sort</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?= intval($cat['id']) ?></td>
                            <td><?= htmlspecialchars($cat['name']) ?></td>
                            <td><?= htmlspecialchars($cat['slug']) ?></td>
                            <td><?= htmlspecialchars($cat['parent_name'] ?? '-') ?></td>
                            <td><span class="badge badge-<?= $cat['status'] === 'active' ? 'success' : 'warning' ?>"><?= htmlspecialchars(ucfirst($cat['status'])) ?></span></td>
                            <td><?= intval($cat['sort_order']) ?></td>
                            <td>
                                <div class="admin-actions-row">
                                    <a class="btn btn-sm btn-outline" href="?action=edit&id=<?= intval($cat['id']) ?>">Edit</a>
                                    <a class="btn btn-sm btn-secondary" href="?action=delete&id=<?= intval($cat['id']) ?>" onclick="return confirm('Delete this category?');">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="admin-card" style="max-width: 800px; margin: 0 auto;">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-input" required value="<?= htmlspecialchars($editingCategory['name'] ?? $_POST['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" class="form-input" value="<?= htmlspecialchars($editingCategory['slug'] ?? $_POST['slug'] ?? '') ?>" placeholder="Auto-generated if empty">
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
                        <textarea name="description" class="form-textarea"><?= htmlspecialchars($editingCategory['description'] ?? $_POST['description'] ?? '') ?></textarea>
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
                            <input type="number" name="sort_order" class="form-input" value="<?= htmlspecialchars((string)($editingCategory['sort_order'] ?? $_POST['sort_order'] ?? $nextSortOrder)) ?>">
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
</body>
</html>


