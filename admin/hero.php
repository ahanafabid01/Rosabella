<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/layout.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$db = getDB();
$action = $_GET['action'] ?? 'list';
$error = '';
$message = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("SELECT image_path FROM hero_slides WHERE id = ?");
    $stmt->execute([$id]);
    $slide = $stmt->fetch();
    
    if ($slide) {
        $stmt = $db->prepare("DELETE FROM hero_slides WHERE id = ?");
        $stmt->execute([$id]);
        
        // Optionally delete the file if it's local
        $filePath = __DIR__ . '/../' . $slide['image_path'];
        if (file_exists($filePath) && strpos($slide['image_path'], 'assets/images/hero/') === 0) {
            unlink($filePath);
        }
        $message = "Slide deleted successfully.";
    }
    $action = 'list';
}

// Handle Form Submission (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $title = $_POST['title'] ?? '';
    $subtitle = $_POST['subtitle'] ?? '';
    $link_url = $_POST['link_url'] ?? '';
    $position = $_POST['position'] ?? 'main';
    $status = $_POST['status'] ?? 'active';
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    
    $imagePath = $_POST['existing_image'] ?? '';
    
    // File upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/images/hero/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $_FILES['image']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $imagePath = 'assets/images/hero/' . $fileName;
        } else {
            $error = "Failed to upload image.";
        }
    }
    
    if (!$error) {
        if ($id) {
            // Update
            $stmt = $db->prepare("UPDATE hero_slides SET image_path = ?, title = ?, subtitle = ?, link_url = ?, position = ?, status = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$imagePath, $title, $subtitle, $link_url, $position, $status, $sort_order, $id]);
            $message = "Slide updated successfully.";
        } else {
            if (!$imagePath) {
                $error = "Image is required.";
            } else {
                // Insert
                $stmt = $db->prepare("INSERT INTO hero_slides (image_path, title, subtitle, link_url, position, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$imagePath, $title, $subtitle, $link_url, $position, $status, $sort_order]);
                $message = "Slide added successfully.";
            }
        }
        
        if (!$error) {
            $action = 'list';
        }
    }
}

// Fetch data for forms
$editingSlide = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM hero_slides WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $editingSlide = $stmt->fetch();
    if (!$editingSlide) {
        $error = "Slide not found.";
        $action = 'list';
    }
}

// Fetch list
$slides = [];
if ($action === 'list') {
    $stmt = $db->query("SELECT * FROM hero_slides ORDER BY position ASC, sort_order ASC, created_at DESC");
    $slides = $stmt->fetchAll();
}

$pageTitle = 'Hero Banners';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php renderAdminSidebar('hero'); ?>
        <main class="admin-content">
            <?php renderAdminTopbar($pageTitle); ?>
            
            <div class="admin-header">
                <h1 class="admin-page-title">
                    <?= $action === 'edit' ? 'Edit Hero Banner' : ($action === 'add' ? 'Add Hero Banner' : 'Manage Hero Banners') ?>
                </h1>
                <?php if ($action === 'list'): ?>
                    <a href="?action=add" class="btn btn-primary">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add Banner
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/admin/hero" class="btn btn-outline">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        Back to Banners
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <?php if ($action === 'list'): ?>
                <div class="admin-card" style="padding: 0; overflow: hidden;">
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Position</th>
                                    <th>Title</th>
                                    <th>Order</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($slides)): ?>
                                    <tr><td colspan="6" style="text-align: center; padding: 2rem;">No banners found. Add one to get started!</td></tr>
                                <?php else: ?>
                                    <?php foreach ($slides as $slide): ?>
                                    <tr>
                                        <td>
                                            <img src="<?= '../' . htmlspecialchars($slide['image_path']) ?>" alt="Banner" style="width: 80px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid var(--color-border);">
                                        </td>
                                        <td><span class="badge badge-info"><?= htmlspecialchars($slide['position']) ?></span></td>
                                        <td><?= htmlspecialchars($slide['title'] ?: 'No Title') ?></td>
                                        <td><?= $slide['sort_order'] ?></td>
                                        <td><span class="badge badge-<?= $slide['status'] === 'active' ? 'success' : 'warning' ?>"><?= ucfirst($slide['status']) ?></span></td>
                                        <td>
                                            <div class="admin-actions-row">
                                                <a href="?action=edit&id=<?= $slide['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                                                <a href="?delete=<?= $slide['id'] ?>" class="btn btn-sm btn-outline" style="border-color: var(--color-danger); color: var(--color-danger);" onclick="return confirm('Are you sure you want to delete this banner?');">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="admin-card" style="max-width: 800px; margin: 0 auto;">
                    <form method="POST" enctype="multipart/form-data">
                        <?php if ($editingSlide): ?>
                            <input type="hidden" name="id" value="<?= $editingSlide['id'] ?>">
                            <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editingSlide['image_path']) ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label class="form-label">Banner Image <?= !$editingSlide ? '*' : '' ?></label>
                            <?php if ($editingSlide && $editingSlide['image_path']): ?>
                                <div style="margin-bottom: 1rem;">
                                    <img src="<?= '../' . htmlspecialchars($editingSlide['image_path']) ?>" style="max-width: 100%; height: auto; border-radius: 8px; border: 1px solid var(--color-border);">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="image" class="form-input" accept="image/*" <?= !$editingSlide ? 'required' : '' ?>>
                            <div class="admin-upload-help">Upload a high-quality JPG or PNG. Main slider recommends 1200x600. Side banners recommend 600x400.</div>
                        </div>

                        <div class="admin-two-col-grid">
                            <div class="form-group">
                                <label class="form-label">Position</label>
                                <select name="position" class="form-input" required>
                                    <option value="main" <?= ($editingSlide['position'] ?? '') === 'main' ? 'selected' : '' ?>>Main Slider</option>
                                    <option value="side_top" <?= ($editingSlide['position'] ?? '') === 'side_top' ? 'selected' : '' ?>>Side Banner (Top)</option>
                                    <option value="side_bottom" <?= ($editingSlide['position'] ?? '') === 'side_bottom' ? 'selected' : '' ?>>Side Banner (Bottom)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Link URL (Optional)</label>
                                <input type="text" name="link_url" class="form-input" value="<?= htmlspecialchars($editingSlide['link_url'] ?? '') ?>" placeholder="e.g. category/electronics">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Title Text (Optional overlay)</label>
                            <input type="text" name="title" class="form-input" value="<?= htmlspecialchars($editingSlide['title'] ?? '') ?>" placeholder="e.g. Super Sale">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Subtitle Text (Optional overlay)</label>
                            <input type="text" name="subtitle" class="form-input" value="<?= htmlspecialchars($editingSlide['subtitle'] ?? '') ?>" placeholder="e.g. Up to 50% off">
                        </div>

                        <div class="admin-two-col-grid">
                            <div class="form-group">
                                <label class="form-label">Sort Order</label>
                                <input type="number" name="sort_order" class="form-input" value="<?= htmlspecialchars($editingSlide['sort_order'] ?? '0') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-input" required>
                                    <option value="active" <?= ($editingSlide['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= ($editingSlide['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                                <?= $editingSlide ? 'Update Banner' : 'Upload Banner' ?>
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script src="js/admin.js"></script>
</body>
</html>
