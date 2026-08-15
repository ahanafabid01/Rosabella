<?php
/**
 * Rosabella – Hero Banner Management with Pagination & Filters
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/image_helper.php';
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
        
        // Optionally delete local image file
        $filePath = __DIR__ . '/../' . $slide['image_path'];
        if (file_exists($filePath) && strpos($slide['image_path'], 'assets/images/hero/') === 0) {
            @unlink($filePath);
        }
        $message = "Banner deleted successfully.";
    }
    $action = 'list';
}

// Handle Form Submission (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $title = sanitize($_POST['title'] ?? '');
    $subtitle = sanitize($_POST['subtitle'] ?? '');
    $link_url = sanitize($_POST['link_url'] ?? '');
    $position = sanitize($_POST['position'] ?? 'main');
    $status = sanitize($_POST['status'] ?? 'active');
    
    if (!isset($_POST['sort_order']) || $_POST['sort_order'] === '') {
        $stmtMax = $db->prepare("SELECT MAX(sort_order) FROM hero_slides WHERE position = ?");
        $stmtMax->execute([$position]);
        $maxOrder = $stmtMax->fetchColumn();
        $sort_order = $maxOrder !== null ? ((int)$maxOrder + 1) : 0;
    } else {
        $sort_order = (int)$_POST['sort_order'];
    }
    
    $imagePath = $_POST['existing_image'] ?? '';
    
    // File upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/images/hero/';
        $newPath = optimizeAndSaveImage($_FILES['image'], $uploadDir, 1600);
        if ($newPath) {
            $imagePath = $newPath;
        } else {
            $error = "Failed to process and upload image as WebP.";
        }
    }
    
    if (!$error) {
        if ($id) {
            // Update
            $stmt = $db->prepare("UPDATE hero_slides SET image_path = ?, title = ?, subtitle = ?, link_url = ?, position = ?, status = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$imagePath, $title, $subtitle, $link_url, $position, $status, $sort_order, $id]);
            $message = "Banner updated successfully.";
        } else {
            if (!$imagePath) {
                $error = "Banner image is required.";
            } else {
                // Insert
                $stmt = $db->prepare("INSERT INTO hero_slides (image_path, title, subtitle, link_url, position, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$imagePath, $title, $subtitle, $link_url, $position, $status, $sort_order]);
                $message = "Banner added successfully.";
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
        $error = "Banner not found.";
        $action = 'list';
    }
}

// ── Position Map ─────────────────────────────────────────────────────────────
$positionMap = [
    'main'        => ['label' => 'Main Slider',          'badge' => 'primary', 'desc' => '1200x600 px'],
    'side_top'    => ['label' => 'Side Banner (Top)',    'badge' => 'indigo',  'desc' => '600x400 px'],
    'side_bottom' => ['label' => 'Side Banner (Bottom)', 'badge' => 'purple',  'desc' => '600x400 px'],
];

// ── List & Pagination Logic ──────────────────────────────────────────────────
$slides = [];
$totalSlides = 0;
$posFilter = sanitize($_GET['position'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');

$perPage = max(1, min(50, intval($_GET['per_page'] ?? 10)));
$page    = max(1, intval($_GET['page'] ?? 1));

if ($action === 'list') {
    $whereParts = [];
    $queryParams = [];

    if ($posFilter !== '') {
        $whereParts[] = "position = ?";
        $queryParams[] = $posFilter;
    }

    if ($statusFilter !== '') {
        $whereParts[] = "status = ?";
        $queryParams[] = $statusFilter;
    }

    if ($search !== '') {
        $whereParts[] = "(title LIKE ? OR subtitle LIKE ? OR link_url LIKE ?)";
        $sLike = "%$search%";
        $queryParams[] = $sLike;
        $queryParams[] = $sLike;
        $queryParams[] = $sLike;
    }

    $whereSql = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

    // Count Total Filtered Banners
    $countStmt = $db->prepare("SELECT COUNT(*) FROM hero_slides $whereSql");
    $countStmt->execute($queryParams);
    $totalSlides = (int)$countStmt->fetchColumn();

    $totalPages = max(1, ceil($totalSlides / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;

    // Fetch Paginated Banners
    $stmt = $db->prepare("
        SELECT * 
        FROM hero_slides 
        $whereSql 
        ORDER BY position ASC, sort_order ASC, created_at DESC 
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($queryParams);
    $slides = $stmt->fetchAll();
}

$pageTitle = 'Hero Banners';
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
    <style>
        .hero-img-preview {
            width: 90px;
            height: 52px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            display: block;
        }
        .hero-filter-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .hero-filter-row {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            flex-wrap: nowrap;
        }
        .hero-filter-search {
            position: relative;
            flex: 1 1 auto;
            min-width: 200px;
        }
        .hero-filter-search input {
            width: 100%;
            height: 36px;
            padding: 0 10px 0 2.2rem;
            border-radius: 7px;
            border: 1px solid #cbd5e1;
            font-size: 0.82rem;
            color: #334155;
            outline: none;
            transition: all 0.15s ease;
        }
        .hero-filter-search input:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.1);
        }
        .hero-filter-search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #94a3b8;
        }
        .hero-filter-select {
            height: 36px;
            font-size: 0.82rem;
            padding: 0 0.65rem;
            border-radius: 7px;
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            color: #334155;
            width: 155px;
            flex-shrink: 0;
        }
        .hero-filter-btns {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }
        .hero-filter-btns .btn {
            height: 36px;
            font-size: 0.82rem;
            padding: 0 14px;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* Mobile Hero Cards */
        .as-mobile-hero-wrap {
            display: none;
        }
        .as-hero-m-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.85rem 1rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        @media (max-width: 768px) {
            .hero-filter-row {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 8px !important;
            }
            .hero-filter-search {
                width: 100% !important;
                flex: 1 1 100% !important;
                min-width: 0 !important;
            }
            .hero-filter-controls {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 6px !important;
                width: 100% !important;
            }
            .hero-filter-select {
                width: 100% !important;
                min-width: 0 !important;
            }
            .hero-filter-btns {
                display: grid !important;
                grid-template-columns: <?= ($posFilter || $statusFilter || $search) ? '1fr 1fr' : '1fr' ?> !important;
                gap: 6px !important;
                width: 100% !important;
            }
            .hero-filter-btns .btn {
                width: 100% !important;
                height: 36px !important;
            }
            .admin-table-wrap {
                display: none !important;
            }
            .as-mobile-hero-wrap {
                display: flex !important;
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php renderAdminSidebar('hero'); ?>
        <main class="admin-content">
            <?php renderAdminTopbar($pageTitle); ?>
            
            <div class="admin-header" style="margin-bottom: 1.25rem;">
                <div>
                    <h1 class="admin-title" style="margin: 0;">
                        <?= $action === 'edit' ? 'Edit Hero Banner' : ($action === 'add' ? 'Add Hero Banner' : 'Manage Hero Banners') ?>
                    </h1>
                    <?php if ($action === 'list'): ?>
                        <div style="font-size: 0.82rem; color: #64748b; margin-top: 2px;">
                            Configure homepage banner carousels, side promotional banners, and visual promotions.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="admin-actions-row">
                    <?php if ($action === 'list'): ?>
                        <a href="?action=add" class="btn btn-primary" style="height: 36px; display: inline-flex; align-items: center; gap: 5px; font-size: 0.82rem; border-radius: 7px; padding: 0 14px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            <span>Add Banner</span>
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/admin/hero" class="btn btn-secondary" style="height: 36px; display: inline-flex; align-items: center; gap: 5px; font-size: 0.82rem; border-radius: 7px; padding: 0 12px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                            <span>Back to Banners</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($message): ?><div class="alert alert-success" style="margin-bottom: 1.25rem;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger" style="margin-bottom: 1.25rem;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <?php if ($action === 'list'): ?>
                <!-- Filter Bar -->
                <div class="hero-filter-card">
                    <form method="GET" action="<?= BASE_URL ?>/admin/hero" style="margin: 0;">
                        <div class="hero-filter-row">
                            <!-- Search -->
                            <div class="hero-filter-search">
                                <svg class="hero-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" name="search" placeholder="Search title, subtitle, link..." value="<?= htmlspecialchars($search) ?>">
                            </div>

                            <div class="hero-filter-controls" style="display: flex; align-items: center; gap: 8px;">
                                <!-- Position Filter -->
                                <select name="position" class="hero-filter-select form-select" onchange="this.form.submit()">
                                    <option value="">All Positions</option>
                                    <option value="main" <?= $posFilter === 'main' ? 'selected' : '' ?>>Main Slider</option>
                                    <option value="side_top" <?= $posFilter === 'side_top' ? 'selected' : '' ?>>Side Banner (Top)</option>
                                    <option value="side_bottom" <?= $posFilter === 'side_bottom' ? 'selected' : '' ?>>Side Banner (Bottom)</option>
                                </select>

                                <!-- Status Filter -->
                                <select name="status" class="hero-filter-select form-select" onchange="this.form.submit()">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>

                            <!-- Buttons -->
                            <div class="hero-filter-btns">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <?php if ($posFilter || $statusFilter || $search): ?>
                                    <a href="<?= BASE_URL ?>/admin/hero" class="btn btn-secondary">Clear</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Desktop Table -->
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 110px;">Image</th>
                                <th>Position</th>
                                <th>Title &amp; Subtitle</th>
                                <th style="text-align: center; width: 80px;">Order</th>
                                <th style="text-align: center; width: 100px;">Status</th>
                                <th style="text-align: right; width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($slides)): ?>
                                <tr><td colspan="6" style="text-align: center; padding: 3rem 1rem; color: #94a3b8;">No banners found. Click "+ Add Banner" to upload one!</td></tr>
                            <?php else: ?>
                                <?php foreach ($slides as $slide): 
                                    $posCfg = $positionMap[$slide['position']] ?? ['label' => ucfirst($slide['position']), 'badge' => 'secondary'];
                                    $imgSrc = !empty($slide['image_path']) ? BASE_URL . '/' . htmlspecialchars($slide['image_path']) : BASE_URL . '/assets/images/placeholder.png';
                                ?>
                                <tr>
                                    <td>
                                        <img src="<?= $imgSrc ?>" alt="Banner" class="hero-img-preview">
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $posCfg['badge'] ?>" style="font-size: 0.74rem; font-weight: 600; padding: 3px 8px;">
                                            <?= htmlspecialchars($posCfg['label']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: #0f172a; font-size: 0.85rem;">
                                            <?= htmlspecialchars($slide['title'] ?: 'No Title') ?>
                                        </div>
                                        <?php if (!empty($slide['subtitle'])): ?>
                                            <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px;">
                                                <?= htmlspecialchars($slide['subtitle']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($slide['link_url'])): ?>
                                            <div style="font-size: 0.70rem; color: #0f766e; margin-top: 2px; font-family: monospace;">
                                                🔗 <?= htmlspecialchars($slide['link_url']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center; font-weight: 700; color: #334155; font-size: 0.88rem;">
                                        <?= (int)$slide['sort_order'] ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge badge-<?= $slide['status'] === 'active' ? 'success' : 'warning' ?>" style="font-size: 0.74rem; font-weight: 600; padding: 2px 8px;">
                                            <?= ucfirst($slide['status']) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="admin-actions-row" style="justify-content: flex-end; gap: 6px;">
                                            <a href="?action=edit&id=<?= $slide['id'] ?>" class="btn btn-sm btn-outline" style="height: 28px; font-size: 0.76rem; padding: 0 8px; border-radius: 6px;">Edit</a>
                                            <a href="?delete=<?= $slide['id'] ?>" class="btn btn-sm btn-outline" style="height: 28px; font-size: 0.76rem; padding: 0 8px; border-radius: 6px; border-color: #ef4444; color: #ef4444;" onclick="return confirm('Are you sure you want to delete this banner?');">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Hero Cards (<= 768px) -->
                <div class="as-mobile-hero-wrap">
                    <?php if (empty($slides)): ?>
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2.5rem 1rem; text-align: center; color: #94a3b8;">
                            No banners found.
                        </div>
                    <?php else: ?>
                        <?php foreach ($slides as $slide): 
                            $posCfg = $positionMap[$slide['position']] ?? ['label' => ucfirst($slide['position']), 'badge' => 'secondary'];
                            $imgSrc = !empty($slide['image_path']) ? BASE_URL . '/' . htmlspecialchars($slide['image_path']) : BASE_URL . '/assets/images/placeholder.png';
                        ?>
                        <div class="as-hero-m-card">
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <img src="<?= $imgSrc ?>" alt="Banner" class="hero-img-preview" style="width: 80px; height: 48px;">
                                <div style="flex: 1; overflow: hidden;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.85rem; text-overflow: ellipsis; white-space: nowrap; overflow: hidden;">
                                        <?= htmlspecialchars($slide['title'] ?: 'No Title') ?>
                                    </div>
                                    <div style="display: flex; gap: 6px; align-items: center; margin-top: 3px;">
                                        <span class="badge badge-<?= $posCfg['badge'] ?>" style="font-size: 0.68rem; padding: 1px 6px;">
                                            <?= htmlspecialchars($posCfg['label']) ?>
                                        </span>
                                        <span class="badge badge-<?= $slide['status'] === 'active' ? 'success' : 'warning' ?>" style="font-size: 0.68rem; padding: 1px 6px;">
                                            <?= ucfirst($slide['status']) ?>
                                        </span>
                                        <span style="font-size: 0.70rem; color: #64748b;">Order: <?= (int)$slide['sort_order'] ?></span>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: flex-end; gap: 6px; border-top: 1px dashed #e2e8f0; padding-top: 6px;">
                                <a href="?action=edit&id=<?= $slide['id'] ?>" class="btn btn-sm btn-outline" style="height: 30px; font-size: 0.76rem; padding: 0 12px; border-radius: 6px;">Edit</a>
                                <a href="?delete=<?= $slide['id'] ?>" class="btn btn-sm btn-outline" style="height: 30px; font-size: 0.76rem; padding: 0 12px; border-radius: 6px; border-color: #ef4444; color: #ef4444;" onclick="return confirm('Are you sure you want to delete this banner?');">Delete</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination System -->
                <?php renderAdminPagination($page, $totalSlides, $perPage, BASE_URL . '/admin/hero', array_filter(['position' => $posFilter, 'status' => $statusFilter, 'search' => $search])); ?>

            <?php else: ?>
                <!-- Add / Edit Banner Form (Full Width UX) -->
                <div class="admin-card">
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <?php if ($editingSlide): ?>
                            <input type="hidden" name="id" value="<?= $editingSlide['id'] ?>">
                            <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editingSlide['image_path']) ?>">
                        <?php endif; ?>
                        
                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label class="form-label" style="font-weight: 600; color: #0f172a;">Banner Image <?= !$editingSlide ? '<span style="color: #ef4444;">*</span>' : '' ?></label>
                            <?php if ($editingSlide && $editingSlide['image_path']): ?>
                                <div style="margin-bottom: 0.75rem;">
                                    <img src="<?= BASE_URL . '/' . htmlspecialchars($editingSlide['image_path']) ?>" style="max-height: 180px; width: auto; border-radius: 8px; border: 1px solid #e2e8f0;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="image" class="form-input" accept="image/*" <?= !$editingSlide ? 'required' : '' ?> style="max-width: 450px;">
                            <div style="font-size: 0.76rem; color: #64748b; margin-top: 4px;">Upload high-quality JPG, PNG, or WebP. Main slider recommended: 1200×600 px. Side banners: 600×400 px.</div>
                        </div>

                        <div class="admin-two-col-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #0f172a;">Banner Position <span style="color: #ef4444;">*</span></label>
                                <select name="position" class="form-select" required style="height: 38px;">
                                    <option value="main" <?= ($editingSlide['position'] ?? '') === 'main' ? 'selected' : '' ?>>Main Slider (1200×600 px)</option>
                                    <option value="side_top" <?= ($editingSlide['position'] ?? '') === 'side_top' ? 'selected' : '' ?>>Side Banner Top (600×400 px)</option>
                                    <option value="side_bottom" <?= ($editingSlide['position'] ?? '') === 'side_bottom' ? 'selected' : '' ?>>Side Banner Bottom (600×400 px)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #0f172a;">Link URL (Optional)</label>
                                <input type="text" name="link_url" class="form-input" value="<?= htmlspecialchars($editingSlide['link_url'] ?? '') ?>" placeholder="e.g. category/electronics or /product/slug" style="height: 38px;">
                            </div>
                        </div>

                        <div class="admin-two-col-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #0f172a;">Title Text (Optional Overlay)</label>
                                <input type="text" name="title" class="form-input" value="<?= htmlspecialchars($editingSlide['title'] ?? '') ?>" placeholder="e.g. Mega Summer Clearance" style="height: 38px;">
                            </div>

                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #0f172a;">Subtitle Text (Optional Overlay)</label>
                                <input type="text" name="subtitle" class="form-input" value="<?= htmlspecialchars($editingSlide['subtitle'] ?? '') ?>" placeholder="e.g. Up to 60% Off Selected Collections" style="height: 38px;">
                            </div>
                        </div>

                        <div class="admin-two-col-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #0f172a;">Display Sort Order</label>
                                <input type="number" name="sort_order" class="form-input" value="<?= isset($editingSlide['sort_order']) ? htmlspecialchars($editingSlide['sort_order']) : '' ?>" placeholder="Leave blank for automatic sequence" style="height: 38px;">
                                <div style="font-size: 0.74rem; color: #64748b; margin-top: 3px;">Lower numbers appear first. Leave blank to auto-append to the end.</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #0f172a;">Status <span style="color: #ef4444;">*</span></label>
                                <select name="status" class="form-select" required style="height: 38px;">
                                    <option value="active" <?= ($editingSlide['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active (Published)</option>
                                    <option value="inactive" <?= ($editingSlide['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive (Draft/Hidden)</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 8px; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                            <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 1.5rem; font-size: 0.85rem; border-radius: 7px;">
                                <?= $editingSlide ? 'Save Banner Changes' : 'Upload & Publish Banner' ?>
                            </button>
                            <a href="<?= BASE_URL ?>/admin/hero" class="btn btn-secondary" style="height: 38px; padding: 0 1rem; font-size: 0.85rem; border-radius: 7px; display: inline-flex; align-items: center;">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script src="js/admin.js"></script>
</body>
</html>
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #0f172a;">Banner Position <span style="color: #ef4444;">*</span></label>
                                <select name="position" class="form-select" required style="height: 38px;">
                                    <option value="main" <?= ($editingSlide['position'] ?? '') === 'main' ? 'selected' : '' ?>>Main Slider (1200×600 px)</option>
                                    <option value="side_top" <?= ($editingSlide['position'] ?? '') === 'side_top' ? 'selected' : '' ?>>Side Banner Top (600×400 px)</option>
                                    <option value="side_bottom" <?= ($editingSlide['position'] ?? '') === 'side_bottom' ? 'selected' : '' ?>>Side Banner Bottom (600×400 px)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #0f172a;">Link URL (Optional)</label>
                                <input type="text" name="link_url" class="form-input" value="<?= htmlspecialchars($editingSlide['link_url'] ?? '') ?>" placeholder="e.g. category/electronics or /product/slug" style="height: 38px;">
                            </div>
                        </div>

                        <div class="admin-two-col-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #0f172a;">Title Text (Optional Overlay)</label>
                                <input type="text" name="title" class="form-input" value="<?= htmlspecialchars($editingSlide['title'] ?? '') ?>" placeholder="e.g. Mega Summer Clearance" style="height: 38px;">
                            </div>

                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #0f172a;">Subtitle Text (Optional Overlay)</label>
                                <input type="text" name="subtitle" class="form-input" value="<?= htmlspecialchars($editingSlide['subtitle'] ?? '') ?>" placeholder="e.g. Up to 60% Off Selected Collections" style="height: 38px;">
                            </div>
                        </div>

                        <div class="admin-two-col-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #0f172a;">Display Sort Order</label>
                                <input type="number" name="sort_order" class="form-input" value="<?= isset($editingSlide['sort_order']) ? htmlspecialchars($editingSlide['sort_order']) : '' ?>" placeholder="Leave blank for automatic sequence" style="height: 38px;">
                                <div style="font-size: 0.74rem; color: #64748b; margin-top: 3px;">Lower numbers appear first. Leave blank to auto-append to the end.</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #0f172a;">Status <span style="color: #ef4444;">*</span></label>
                                <select name="status" class="form-select" required style="height: 38px;">
                                    <option value="active" <?= ($editingSlide['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active (Published)</option>
                                    <option value="inactive" <?= ($editingSlide['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive (Draft/Hidden)</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 8px; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                            <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 1.5rem; font-size: 0.85rem; border-radius: 7px;">
                                <?= $editingSlide ? 'Save Banner Changes' : 'Upload & Publish Banner' ?>
                            </button>
                            <a href="<?= BASE_URL ?>/admin/hero" class="btn btn-secondary" style="height: 38px; padding: 0 1rem; font-size: 0.85rem; border-radius: 7px; display: inline-flex; align-items: center;">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script src="js/admin.js"></script>
</body>
</html>
