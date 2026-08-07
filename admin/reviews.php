<?php
/**
 * KARTLY - Admin Reviews Management
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

// \u2500\u2500 Security: Verify CSRF on all admin POST requests \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
}

$message = $_SESSION['admin_message'] ?? '';
$error = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_message'], $_SESSION['admin_error']);

function ensureAdminReviewImagesTable(PDO $db): bool
{
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS review_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                review_id INT NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_review_images_review (review_id),
                CONSTRAINT fk_review_images_review
                    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function resolveAdminReviewImageSrc(?string $imagePath): string
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

function isManagedAdminReviewUploadPath(string $path): bool
{
    return strpos($path, 'assets/uploads/reviews/') === 0;
}

function deleteManagedAdminReviewUpload(string $path): void
{
    if (!isManagedAdminReviewUploadPath($path)) {
        return;
    }

    $absolute = __DIR__ . '/../' . $path;
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

function renderAdminReviewStars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

$reviewImagesEnabled = ensureAdminReviewImagesTable($db);
$allowedStatuses = ['pending', 'approved', 'rejected'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    $reviewId = intval($_POST['review_id'] ?? 0);

    if ($reviewId <= 0) {
        $error = 'Invalid review selected.';
    } elseif ($postAction === 'update_status') {
        $newStatus = sanitize($_POST['status'] ?? '');
        if (!in_array($newStatus, $allowedStatuses, true)) {
            $error = 'Invalid review status.';
        } else {
            $updateStmt = $db->prepare("UPDATE reviews SET status = ? WHERE id = ?");
            if ($updateStmt->execute([$newStatus, $reviewId])) {
                $message = 'Review status updated.';
            } else {
                $error = 'Unable to update review status.';
            }
        }
    } elseif ($postAction === 'delete_review') {
        $imagesToDelete = [];
        try {
            $db->beginTransaction();

            if ($reviewImagesEnabled) {
                $imagesStmt = $db->prepare("SELECT image_path FROM review_images WHERE review_id = ?");
                $imagesStmt->execute([$reviewId]);
                $imageRows = $imagesStmt->fetchAll();
                foreach ($imageRows as $imageRow) {
                    $imagesToDelete[] = trim((string)($imageRow['image_path'] ?? ''));
                }
            }

            $deleteStmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
            $deleteStmt->execute([$reviewId]);

            $db->commit();

            foreach (array_unique(array_filter($imagesToDelete)) as $imagePath) {
                deleteManagedAdminReviewUpload($imagePath);
            }

            $message = 'Review deleted successfully.';
            $forceListMode = true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Unable to delete review right now.';
        }
    }
}

$statusFilter = sanitize($_GET['status'] ?? '');
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}
$search = trim((string)($_GET['search'] ?? ''));

$whereParts = [];
$params = [];
if ($statusFilter !== '') {
    $whereParts[] = 'r.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $whereParts[] = '(p.name LIKE ? OR u.email LIKE ? OR r.title LIKE ? OR r.review LIKE ?)';
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}
$whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
$imageCountSelect = $reviewImagesEnabled
    ? '(SELECT COUNT(*) FROM review_images ri WHERE ri.review_id = r.id) AS image_count'
    : '0 AS image_count';

$listStmt = $db->prepare("
    SELECT r.*, p.name AS product_name, u.first_name, u.last_name, u.email, $imageCountSelect
    FROM reviews r
    LEFT JOIN products p ON p.id = r.product_id
    LEFT JOIN users u ON u.id = r.user_id
    $whereSql
    ORDER BY r.created_at DESC
");
$listStmt->execute($params);
$reviews = $listStmt->fetchAll();

$pageTitle = 'Reviews Management';
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
    <?php renderAdminSidebar('reviews'); ?>

    <main class="admin-content">
        <?php renderAdminTopbar($pageTitle ?? 'Admin Panel'); ?>
<div class="admin-header">
            <h1 class="admin-title">Reviews</h1>
            <form method="GET" class="admin-actions-row">
                <input
                    type="text"
                    name="search"
                    class="form-input admin-input-max-320"
                    placeholder="Search product, user, or review"
                    value="<?= htmlspecialchars($search) ?>"
                >
                <select name="status" class="form-select admin-select-max-180">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="admin-table-wrap">
            <table class="admin-table admin-table-nowrap">
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Customer</th>
                    <th>Rating</th>
                    <th>Status</th>
                    <th>Images</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($reviews)): ?>
                    <tr>
                        <td colspan="9" class="admin-text-muted">No reviews found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                    <tr>
                        <td><?= htmlspecialchars($review['product_name'] ?? 'Unknown Product') ?></td>
                        <td><?= htmlspecialchars(trim(($review['first_name'] ?? '') . ' ' . ($review['last_name'] ?? '')) ?: ($review['email'] ?? 'Guest')) ?></td>
                        <td style="color: #f59e0b;"><?= htmlspecialchars(renderAdminReviewStars(intval($review['rating'] ?? 0))) ?></td>
                        <td>
                            <span class="badge badge-<?= ($review['status'] ?? '') === 'approved' ? 'success' : (($review['status'] ?? '') === 'rejected' ? 'danger' : 'warning') ?>">
                                <?= htmlspecialchars(ucfirst($review['status'] ?? 'pending')) ?>
                            </span>
                        </td>
                        <td><?= intval($review['image_count'] ?? 0) ?></td>
                        <td><?= htmlspecialchars(date('M j, Y', strtotime($review['created_at'] ?? 'now'))) ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: nowrap;">
                                <a href="<?= BASE_URL ?>/admin/view-review?id=<?= intval($review['id']) ?>" class="btn btn-sm btn-outline" style="white-space: nowrap;">View</a>
                                <form method="POST" onsubmit="return confirm('Delete this review permanently?');" style="margin: 0;">
                        <!-- Security: CSRF token -->
                        <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_review">
                                    <input type="hidden" name="review_id" value="<?= intval($review['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-secondary" style="white-space: nowrap;">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<script src="js/admin.js"></script>
</body>
</html>


