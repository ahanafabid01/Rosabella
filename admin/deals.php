<?php
/**
 * Rosabella - Admin Deals Management
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

// ── Security: Verify CSRF on all admin POST requests ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
}

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$dealId = intval($_GET['id'] ?? 0);
$search = trim((string)($_GET['search'] ?? ''));
$allowedBadgeStyles = ['primary', 'success', 'danger', 'warning'];
$allowedStatuses = ['active', 'inactive'];

function ensureDealsTable(PDO $db): bool
{
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS deals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                subtitle VARCHAR(255),
                badge_text VARCHAR(60),
                badge_style ENUM('primary', 'success', 'danger', 'warning') DEFAULT 'primary',
                timer_text VARCHAR(32),
                countdown_end_at DATETIME NULL,
                image_path VARCHAR(255),
                link_url VARCHAR(255) NOT NULL DEFAULT 'sale',
                overlay_start VARCHAR(40) DEFAULT 'rgba(15, 118, 110, 0.84)',
                overlay_end VARCHAR(40) DEFAULT 'rgba(11, 91, 85, 0.62)',
                image_position VARCHAR(100) DEFAULT 'center center',
                sort_order INT DEFAULT 0,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_deals_status_sort (status, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        try {
            $db->exec("ALTER TABLE deals ADD COLUMN countdown_end_at DATETIME NULL AFTER timer_text");
        } catch (Throwable $e) {
            // Existing installs may already have this column.
        }

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function upsertSettingValue(PDO $db, string $key, string $value, string $type = 'text'): void
{
    $stmt = $db->prepare("
        INSERT INTO settings (setting_key, setting_value, setting_type)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            setting_type = VALUES(setting_type)
    ");
    $stmt->execute([$key, $value, $type]);
}

function ensureDealsSettings(PDO $db): void
{
    $defaults = [
        'home_deals_title' => 'Hot Deals',
        'home_deals_subtitle' => "Don't miss out on these amazing offers",
        'home_deals_cta_label' => 'View All Deals',
        'home_deals_cta_url' => 'sale',
    ];

    foreach ($defaults as $key => $defaultValue) {
        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_type)
            VALUES (?, ?, 'text')
            ON DUPLICATE KEY UPDATE setting_key = setting_key
        ");
        $stmt->execute([$key, $defaultValue]);
    }
}

function parseDateTimeLocal(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function toDateTimeLocalValue(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $timestamp);
}

function resolveAdminDealImageSrc(?string $imagePath): string
{
    $imagePath = trim((string)$imagePath);
    if ($imagePath === '') {
        return '';
    }

    if (preg_match('/^(https?:)?\/\//i', $imagePath) || strpos($imagePath, 'data:') === 0 || strpos($imagePath, '../') === 0 || strpos($imagePath, '/') === 0) {
        return $imagePath;
    }

    return '../' . $imagePath;
}

function isManagedDealUploadPath(string $path): bool
{
    return strpos($path, 'assets/uploads/deals/') === 0;
}

function deleteManagedDealUpload(string $path): void
{
    if (!isManagedDealUploadPath($path)) {
        return;
    }

    $absolute = __DIR__ . '/../' . $path;
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

function uploadDealImage(array $file, ?string &$uploadError): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $uploadError = 'Image upload failed. Please try again.';
        return null;
    }

    $maxSize = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        $uploadError = 'Image is too large. Maximum allowed size is 5 MB.';
        return null;
    }

    $tmpName = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmpName)) {
        $uploadError = 'Invalid upload source detected.';
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $tmpName) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMimeToExtension = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowedMimeToExtension[$mimeType])) {
        $uploadError = 'Only JPG, PNG, WEBP, and GIF images are allowed.';
        return null;
    }

    $uploadDir = __DIR__ . '/../assets/uploads/deals';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        $uploadError = 'Unable to create image upload directory.';
        return null;
    }

    if (!is_writable($uploadDir)) {
        $uploadError = 'Deal image upload directory is not writable.';
        return null;
    }

    require_once __DIR__ . '/../includes/image_helper.php';
    $newPath = optimizeAndSaveImage($file, $uploadDir, 1200);
    if (!$newPath) {
        $uploadError = 'Unable to process and move uploaded image as WebP.';
        return null;
    }

    return $newPath;
}

$dealsTableReady = ensureDealsTable($db);
if (!$dealsTableReady) {
    $error = 'Deals module is unavailable because the table could not be initialized.';
}
ensureDealsSettings($db);

if ($dealsTableReady && $action === 'delete' && $dealId > 0) {
    $existingStmt = $db->prepare("SELECT image_path FROM deals WHERE id = ?");
    $existingStmt->execute([$dealId]);
    $existingDeal = $existingStmt->fetch();

    $deleteStmt = $db->prepare("DELETE FROM deals WHERE id = ?");
    if ($deleteStmt->execute([$dealId])) {
        if ($existingDeal && !empty($existingDeal['image_path'])) {
            deleteManagedDealUpload((string)$existingDeal['image_path']);
        }
        $message = 'Deal deleted successfully.';
    } else {
        $error = 'Unable to delete deal.';
    }
    $action = 'list';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_section_settings'])) {
    $sectionTitle = trim((string)($_POST['home_deals_title'] ?? ''));
    $sectionSubtitle = trim((string)($_POST['home_deals_subtitle'] ?? ''));
    $sectionCtaLabel = trim((string)($_POST['home_deals_cta_label'] ?? ''));
    $sectionCtaUrl = trim((string)($_POST['home_deals_cta_url'] ?? ''));

    if ($sectionTitle === '') {
        $error = 'Deals section title is required.';
    } else {
        upsertSettingValue($db, 'home_deals_title', $sectionTitle);
        upsertSettingValue($db, 'home_deals_subtitle', $sectionSubtitle);
        upsertSettingValue($db, 'home_deals_cta_label', $sectionCtaLabel);
        upsertSettingValue($db, 'home_deals_cta_url', $sectionCtaUrl !== '' ? $sectionCtaUrl : 'sale');
        $message = 'Deals section settings updated successfully.';
    }
}

if ($dealsTableReady && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_deal'])) {
    $title = trim((string)($_POST['title'] ?? ''));
    $subtitle = trim((string)($_POST['subtitle'] ?? ''));
    $badgeText = trim((string)($_POST['badge_text'] ?? ''));
    $badgeStyle = sanitize($_POST['badge_style'] ?? 'primary');
    $countdownEndAtRaw = trim((string)($_POST['countdown_end_at'] ?? ''));
    $linkUrl = trim((string)($_POST['link_url'] ?? ''));
    $imagePathInput = trim((string)($_POST['image_path'] ?? ''));
    $imagePosition = trim((string)($_POST['image_position'] ?? ''));
    $overlayStart = trim((string)($_POST['overlay_start'] ?? ''));
    $overlayEnd = trim((string)($_POST['overlay_end'] ?? ''));
    $sortOrder = intval($_POST['sort_order'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'active');

    $currentDeal = null;
    if ($action === 'edit' && $dealId > 0) {
        $currentStmt = $db->prepare("SELECT * FROM deals WHERE id = ?");
        $currentStmt->execute([$dealId]);
        $currentDeal = $currentStmt->fetch();
        if (!$currentDeal) {
            $error = 'Deal not found.';
        }
    }

    if ($title === '') {
        $error = 'Deal title is required.';
    } elseif (!in_array($badgeStyle, $allowedBadgeStyles, true)) {
        $error = 'Invalid badge style.';
    } elseif (!in_array($status, $allowedStatuses, true)) {
        $error = 'Invalid status.';
    }

    $oldImagePath = trim((string)($currentDeal['image_path'] ?? ''));
    $imagePath = $oldImagePath;
    $newUploadedImage = null;
    $uploadError = null;

    if ($imagePathInput !== '') {
        $imagePath = $imagePathInput;
    }
    if (isset($_POST['remove_image'])) {
        $imagePath = '';
    }
    if (isset($_FILES['image_file'])) {
        $uploadedImage = uploadDealImage($_FILES['image_file'], $uploadError);
        if ($uploadError !== null) {
            $error = $uploadError;
        } elseif ($uploadedImage !== null) {
            $newUploadedImage = $uploadedImage;
            $imagePath = $uploadedImage;
        }
    }

    if ($linkUrl === '') {
        $linkUrl = 'sale';
    }
    if ($imagePosition === '') {
        $imagePosition = 'center center';
    }
    if ($overlayStart === '') {
        $overlayStart = 'rgba(15, 118, 110, 0.84)';
    }
    if ($overlayEnd === '') {
        $overlayEnd = 'rgba(11, 91, 85, 0.62)';
    }
    $countdownEndAt = parseDateTimeLocal($countdownEndAtRaw);
    if ($countdownEndAtRaw !== '' && $countdownEndAt === null) {
        $error = 'Invalid countdown end date/time.';
    }

    if ($error === '') {
        if ($action === 'edit' && $dealId > 0) {
            $stmt = $db->prepare("
                UPDATE deals
                SET title = ?, subtitle = ?, badge_text = ?, badge_style = ?, timer_text = ?, countdown_end_at = ?, image_path = ?, link_url = ?,
                    overlay_start = ?, overlay_end = ?, image_position = ?, sort_order = ?, status = ?
                WHERE id = ?
            ");
            $saved = $stmt->execute([
                $title,
                $subtitle !== '' ? $subtitle : null,
                $badgeText !== '' ? $badgeText : null,
                $badgeStyle,
                null,
                $countdownEndAt,
                $imagePath !== '' ? $imagePath : null,
                $linkUrl,
                $overlayStart,
                $overlayEnd,
                $imagePosition,
                $sortOrder,
                $status,
                $dealId,
            ]);
            if ($saved) {
                $message = 'Deal updated successfully.';
                $action = 'list';
            } else {
                $error = 'Unable to update deal.';
            }
        } else {
            $stmt = $db->prepare("
                INSERT INTO deals (title, subtitle, badge_text, badge_style, timer_text, countdown_end_at, image_path, link_url, overlay_start, overlay_end, image_position, sort_order, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $saved = $stmt->execute([
                $title,
                $subtitle !== '' ? $subtitle : null,
                $badgeText !== '' ? $badgeText : null,
                $badgeStyle,
                null,
                $countdownEndAt,
                $imagePath !== '' ? $imagePath : null,
                $linkUrl,
                $overlayStart,
                $overlayEnd,
                $imagePosition,
                $sortOrder,
                $status,
            ]);
            if ($saved) {
                $message = 'Deal created successfully.';
                $action = 'list';
            } else {
                $error = 'Unable to create deal.';
            }
        }
    }

    if ($error !== '' && $newUploadedImage !== null) {
        deleteManagedDealUpload($newUploadedImage);
    } elseif ($error === '' && $newUploadedImage !== null && $oldImagePath !== '' && $oldImagePath !== $newUploadedImage) {
        deleteManagedDealUpload($oldImagePath);
    } elseif ($error === '' && isset($_POST['remove_image']) && $oldImagePath !== '' && $imagePath === '') {
        deleteManagedDealUpload($oldImagePath);
    } elseif ($error === '' && $oldImagePath !== '' && $imagePath !== '' && $oldImagePath !== $imagePath) {
        deleteManagedDealUpload($oldImagePath);
    }
}

$sectionSettings = [
    'home_deals_title' => getSetting('home_deals_title') ?: 'Hot Deals',
    'home_deals_subtitle' => getSetting('home_deals_subtitle') ?: "Don't miss out on these amazing offers",
    'home_deals_cta_label' => getSetting('home_deals_cta_label') ?: 'View All Deals',
    'home_deals_cta_url' => getSetting('home_deals_cta_url') ?: 'sale',
];

$editingDeal = null;
$nextSortOrder = 0;
if ($dealsTableReady) {
    if ($action === 'edit' && $dealId > 0) {
        $stmt = $db->prepare("SELECT * FROM deals WHERE id = ?");
        $stmt->execute([$dealId]);
        $editingDeal = $stmt->fetch();
        if (!$editingDeal) {
            $error = 'Deal not found.';
            $action = 'list';
        }
    } elseif ($action === 'add') {
        $stmt = $db->query("SELECT MAX(sort_order) as max_sort FROM deals");
        $row = $stmt->fetch();
        if ($row && $row['max_sort'] !== null) {
            $nextSortOrder = intval($row['max_sort']) + 1;
        }
    }
}

$whereSql = '';
$params = [];
if ($dealsTableReady && $search !== '') {
    $whereSql = "WHERE title LIKE ? OR subtitle LIKE ?";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$deals = [];
if ($dealsTableReady) {
    $stmt = $db->prepare("SELECT * FROM deals $whereSql ORDER BY sort_order ASC, created_at DESC");
    $stmt->execute($params);
    $deals = $stmt->fetchAll();
}

$allCategories = [];
try {
    $catStmt = $db->query("SELECT id, name, slug FROM categories ORDER BY name ASC");
    if ($catStmt) {
        $allCategories = $catStmt->fetchAll();
    }
} catch (Exception $e) {}

$pageTitle = 'Deals Management';
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
    <?php renderAdminSidebar('deals'); ?>

    <main class="admin-content">
        <?php renderAdminTopbar($pageTitle ?? 'Admin Panel'); ?>
<div class="admin-header">
            <h1 class="admin-page-title">
                <?= $action === 'edit' ? 'Edit Deal' : ($action === 'add' ? 'Add Deal' : 'Deals') ?>
            </h1>
            <?php if ($action === 'list' && $dealsTableReady): ?>
                <a href="?action=add" class="btn btn-primary">+ Add Deal</a>
            <?php endif; ?>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <?php if ($action === 'list'): ?>
            <div class="admin-card">
                <h2 class="admin-subtitle">Homepage Deals Section</h2>
                <form method="POST">
                        <!-- Security: CSRF token -->
                        <?= csrfField() ?>
                    <input type="hidden" name="save_section_settings" value="1">
                    <div class="admin-two-col-grid">
                        <div class="form-group">
                            <label class="form-label">Section Title</label>
                            <input type="text" name="home_deals_title" class="form-input" required value="<?= htmlspecialchars($sectionSettings['home_deals_title']) ?>" placeholder="e.g., Deals of the Day">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Section Subtitle</label>
                            <input type="text" name="home_deals_subtitle" class="form-input" value="<?= htmlspecialchars($sectionSettings['home_deals_subtitle']) ?>" placeholder="e.g., Don't miss out on our limited-time exclusive offers">
                        </div>
                    </div>
                    <div class="admin-two-col-grid">
                        <div class="form-group">
                            <label class="form-label">Button Label</label>
                            <input type="text" name="home_deals_cta_label" class="form-input" value="<?= htmlspecialchars($sectionSettings['home_deals_cta_label']) ?>" placeholder="e.g., View All Deals">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Button URL</label>
                            <input type="text" name="home_deals_cta_url" class="form-input" value="<?= htmlspecialchars($sectionSettings['home_deals_cta_url']) ?>" placeholder="e.g., sale or shop">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Section Settings</button>
                </form>
            </div>

            <?php if ($dealsTableReady): ?>
                <div class="admin-card">
                    <form method="GET" class="admin-form-row">
                        <input type="text" class="form-input admin-input-max-320" name="search" placeholder="Search deals by title or subtitle..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-secondary">Search</button>
                    </form>
                </div>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Deal</th>
                            <th>Badge</th>
                            <th>Ends At</th>
                            <th>Link</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($deals)): ?>
                            <tr>
                                <td colspan="8" class="admin-text-muted">No deals found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($deals as $deal): ?>
                            <tr>
                                <td><?= intval($deal['id']) ?></td>
                                <td>
                                    <div class="admin-item-info">
                                        <?php if (!empty($deal['image_path'])): ?>
                                            <img src="<?= htmlspecialchars(resolveAdminDealImageSrc($deal['image_path'])) ?>" alt="" class="admin-item-thumb">
                                        <?php endif; ?>
                                        <div>
                                            <div class="admin-semi"><?= htmlspecialchars($deal['title']) ?></div>
                                            <div class="admin-note-muted"><?= htmlspecialchars((string)($deal['subtitle'] ?? '')) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($deal['badge_text'])): ?>
                                        <span class="badge badge-<?= htmlspecialchars($deal['badge_style']) ?>"><?= htmlspecialchars($deal['badge_text']) ?></span>
                                    <?php else: ?>
                                        <span class="admin-text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($deal['countdown_end_at']) ? htmlspecialchars(date('M j, Y H:i', strtotime($deal['countdown_end_at']))) : '-' ?></td>
                                <td>
                                    <?php
                                        $displayLink = $deal['link_url'];
                                        if ($deal['link_url'] === 'sale') {
                                            $displayLink = '★ Sale & Discount';
                                        } elseif (in_array($deal['link_url'], ['shop', 'products.php'], true)) {
                                            $displayLink = 'All Products';
                                        } else {
                                            foreach ($allCategories as $cat) {
                                                if (in_array($deal['link_url'], ['category/' . $cat['slug'], 'products.php?category=' . $cat['slug']], true)) {
                                                    $displayLink = 'Category: ' . $cat['name'];
                                                    break;
                                                }
                                            }
                                        }
                                    ?>
                                    <span class="admin-cell-mono"><?= htmlspecialchars($displayLink) ?></span>
                                </td>
                                <td><?= intval($deal['sort_order']) ?></td>
                                <td><span class="badge badge-<?= $deal['status'] === 'active' ? 'success' : 'warning' ?>"><?= htmlspecialchars(ucfirst($deal['status'])) ?></span></td>
                                <td>
                                    <div class="admin-actions-row">
                                        <a class="btn-action-icon edit" href="?action=edit&id=<?= intval($deal['id']) ?>" title="Edit Deal">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </a>
                                        <a class="btn-action-icon delete" href="?action=delete&id=<?= intval($deal['id']) ?>" onclick="return confirm('Delete this deal?');" title="Delete Deal">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="admin-card">
                <form method="POST" enctype="multipart/form-data">
                        <!-- Security: CSRF token -->
                        <?= csrfField() ?>
                    <input type="hidden" name="save_deal" value="1">
                    <div class="form-group">
                        <label class="form-label">Deal Title *</label>
                        <input type="text" name="title" class="form-input" required value="<?= htmlspecialchars($editingDeal['title'] ?? $_POST['title'] ?? '') ?>" placeholder="e.g., Midnight Flash Sale, Weekend Special">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Subtitle</label>
                        <input type="text" name="subtitle" class="form-input" value="<?= htmlspecialchars($editingDeal['subtitle'] ?? $_POST['subtitle'] ?? '') ?>" placeholder="e.g., Up to 50% off selected luxury items">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Select Category</label>
                        <?php $currentLink = $editingDeal['link_url'] ?? $_POST['link_url'] ?? 'sale'; ?>
                        <select name="link_url" class="form-select">
                            <option value="sale" <?= ($currentLink === 'sale') ? 'selected' : '' ?>>★ All Sale & Discount Products</option>
                            <option value="shop" <?= (in_array($currentLink, ['shop', 'products.php'], true)) ? 'selected' : '' ?>>All Products (Storefront)</option>
                            <optgroup label="Categories">
                                <?php foreach ($allCategories as $cat): ?>
                                    <?php $catVal = 'category/' . $cat['slug']; ?>
                                    <?php $legacyCatVal = 'products.php?category=' . $cat['slug']; ?>
                                    <option value="<?= htmlspecialchars($catVal) ?>" <?= (in_array($currentLink, [$catVal, $legacyCatVal], true)) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>

                    <div class="admin-two-col-grid">
                        <div class="form-group">
                            <label class="form-label">Countdown Ends At</label>
                            <input type="datetime-local" name="countdown_end_at" class="form-input" value="<?= htmlspecialchars(isset($_POST['countdown_end_at']) ? (string)$_POST['countdown_end_at'] : toDateTimeLocalValue($editingDeal['countdown_end_at'] ?? null)) ?>">
                            <p class="admin-text-muted" style="margin-top: 0.25rem; font-size: 0.85rem;">Countdown is automatic.</p>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <?php $selectedStatus = $editingDeal['status'] ?? $_POST['status'] ?? 'active'; ?>
                            <select name="status" class="form-select">
                                <option value="active" <?= $selectedStatus === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $selectedStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="admin-two-col-grid">
                        <div class="form-group">
                            <label class="form-label">Sort Order</label>
                            <input type="number" min="0" name="sort_order" class="form-input" value="<?= htmlspecialchars((string)($editingDeal['sort_order'] ?? $_POST['sort_order'] ?? $nextSortOrder)) ?>" placeholder="e.g., 0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Upload Image <span class="admin-text-muted" style="font-weight:400;">(Optional)</span></label>
                            <input type="file" name="image_file" class="form-input" accept="image/jpeg,image/png,image/webp,image/gif">
                        </div>
                    </div>

                    <?php if (!empty($editingDeal['image_path'])): ?>
                        <div class="admin-image-preview-wrap">
                            <img src="<?= htmlspecialchars(resolveAdminDealImageSrc($editingDeal['image_path'])) ?>" alt="Current deal image" class="admin-image-preview">
                        </div>
                        <label class="admin-checkbox-row">
                            <input type="checkbox" name="remove_image" value="1">
                            Remove current image
                        </label>
                    <?php endif; ?>

                    <details style="margin-top: 1.5rem; margin-bottom: 1.5rem; padding: 1rem; border: 1px solid var(--color-border); border-radius: 6px; background: rgba(0,0,0,0.02);">
                        <summary style="cursor: pointer; font-weight: 600; font-size: 0.95rem; user-select: none;">Advanced Settings (Links, Badges, Colors)</summary>
                        <div style="margin-top: 1rem;">
                            
                            <div class="admin-two-col-grid">
                                <div class="form-group">
                                    <label class="form-label">Badge Text</label>
                                    <input type="text" name="badge_text" class="form-input" value="<?= htmlspecialchars($editingDeal['badge_text'] ?? $_POST['badge_text'] ?? '') ?>" placeholder="e.g., Limited Time, Hot Deal, 50% OFF">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Badge Style</label>
                                    <?php $selectedBadgeStyle = $editingDeal['badge_style'] ?? $_POST['badge_style'] ?? 'primary'; ?>
                                    <select name="badge_style" class="form-select">
                                        <?php foreach ($allowedBadgeStyles as $badgeStyle): ?>
                                            <option value="<?= $badgeStyle ?>" <?= $selectedBadgeStyle === $badgeStyle ? 'selected' : '' ?>>
                                                <?= ucfirst($badgeStyle) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">External Image URL <span class="admin-text-muted" style="font-weight:400;">(Optional - overrides upload)</span></label>
                                <input type="url" name="image_path" class="form-input" value="<?= htmlspecialchars($editingDeal['image_path'] ?? $_POST['image_path'] ?? '') ?>" placeholder="https://example.com/image.jpg">
                            </div>

                            <div class="admin-two-col-grid">
                                <div class="form-group">
                                    <label class="form-label">Overlay Start Color</label>
                                    <input type="text" name="overlay_start" class="form-input" value="<?= htmlspecialchars($editingDeal['overlay_start'] ?? $_POST['overlay_start'] ?? 'rgba(15, 118, 110, 0.84)') ?>" placeholder="rgba(15, 118, 110, 0.84)">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Overlay End Color</label>
                                    <input type="text" name="overlay_end" class="form-input" value="<?= htmlspecialchars($editingDeal['overlay_end'] ?? $_POST['overlay_end'] ?? 'rgba(11, 91, 85, 0.62)') ?>" placeholder="rgba(11, 91, 85, 0.62)">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Image Position</label>
                                <input type="text" name="image_position" class="form-input" value="<?= htmlspecialchars($editingDeal['image_position'] ?? $_POST['image_position'] ?? 'center center') ?>" placeholder="e.g., center center, center top">
                            </div>

                        </div>
                    </details>

                    <div class="admin-actions-row">
                        <button class="btn btn-primary" type="submit">Save Deal</button>
                        <a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/deals">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>
</div>
<script src="js/admin.js"></script>
</body>
</html>
