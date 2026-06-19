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
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$message = '';
$error = '';
$forceListMode = false;

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

$action = $forceListMode ? 'list' : ($_GET['action'] ?? 'list');
$viewReviewId = intval($_GET['id'] ?? 0);
$viewReview = null;
$viewReviewImages = [];

if ($action === 'view' && $viewReviewId > 0) {
    $viewStmt = $db->prepare("
        SELECT r.*, p.name AS product_name, p.main_image AS product_image, u.first_name, u.last_name, u.email
        FROM reviews r
        LEFT JOIN products p ON p.id = r.product_id
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.id = ?
        LIMIT 1
    ");
    $viewStmt->execute([$viewReviewId]);
    $viewReview = $viewStmt->fetch();

    if (!$viewReview) {
        $error = 'Review not found.';
        $action = 'list';
    } elseif ($reviewImagesEnabled) {
        $viewImagesStmt = $db->prepare("SELECT image_path FROM review_images WHERE review_id = ? ORDER BY id ASC");
        $viewImagesStmt->execute([$viewReviewId]);
        $viewReviewImages = $viewImagesStmt->fetchAll();
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

        <?php if ($action === 'view' && $viewReview): ?>
        <div class="admin-card admin-card-gap-lg">
            <div class="admin-detail-header">
                <h2 class="admin-page-title">Review #<?= intval($viewReview['id']) ?></h2>
                <a href="reviews.php" class="btn btn-secondary">Back to Reviews</a>
            </div>

            <div class="admin-detail-meta-grid">
                <div>
                    <div class="admin-meta-label">Product</div>
                    <div class="admin-semi"><?= htmlspecialchars($viewReview['product_name'] ?? 'Unknown Product') ?></div>
                </div>
                <div>
                    <div class="admin-meta-label">Customer</div>
                    <div class="admin-semi"><?= htmlspecialchars(trim(($viewReview['first_name'] ?? '') . ' ' . ($viewReview['last_name'] ?? '')) ?: ($viewReview['email'] ?? 'Guest')) ?></div>
                </div>
                <div>
                    <div class="admin-meta-label">Rating</div>
                    <div class="admin-semi" style="color: #f59e0b;"><?= htmlspecialchars(renderAdminReviewStars(intval($viewReview['rating'] ?? 0))) ?></div>
                </div>
                <div>
                    <div class="admin-meta-label">Submitted</div>
                    <div class="admin-semi"><?= htmlspecialchars(date('M j, Y H:i', strtotime($viewReview['created_at'] ?? 'now'))) ?></div>
                </div>
            </div>

            <div class="admin-card" style="margin-top: 1rem;">
                <div class="admin-meta-label">Status</div>
                <div style="margin-top: 0.35rem;">
                    <span class="badge badge-<?= ($viewReview['status'] ?? '') === 'approved' ? 'success' : (($viewReview['status'] ?? '') === 'rejected' ? 'danger' : 'warning') ?>">
                        <?= htmlspecialchars(ucfirst($viewReview['status'] ?? 'pending')) ?>
                    </span>
                </div>

                <div class="admin-meta-label" style="margin-top: 1rem;">Title</div>
                <div><?= htmlspecialchars((string)($viewReview['title'] ?? '-')) ?></div>

                <div class="admin-meta-label" style="margin-top: 1rem;">Review</div>
                <div class="admin-review-text"><?= nl2br(htmlspecialchars((string)($viewReview['review'] ?? ''))) ?></div>

                <?php if (!empty($viewReviewImages)): ?>
                <div class="admin-meta-label" style="margin-top: 1rem;">Photos</div>
                <div class="admin-review-images">
                    <?php foreach ($viewReviewImages as $viewReviewImage): ?>
                    <a href="<?= htmlspecialchars(resolveAdminReviewImageSrc($viewReviewImage['image_path'] ?? '')) ?>" target="_blank" rel="noopener noreferrer">
                        <img src="<?= htmlspecialchars(resolveAdminReviewImageSrc($viewReviewImage['image_path'] ?? '')) ?>" alt="Review photo" class="admin-review-thumb">
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="admin-form-row-center admin-mt-1">
                <form method="POST" class="admin-form-row-center">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="review_id" value="<?= intval($viewReview['id']) ?>">
                    <select name="status" class="form-select admin-select-220">
                        <option value="pending" <?= ($viewReview['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= ($viewReview['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= ($viewReview['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </form>

                <form method="POST" onsubmit="return confirm('Delete this review permanently?');">
                    <input type="hidden" name="action" value="delete_review">
                    <input type="hidden" name="review_id" value="<?= intval($viewReview['id']) ?>">
                    <button type="submit" class="btn btn-secondary">Delete Review</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="admin-table-wrap">
            <table class="admin-table admin-table-nowrap">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Customer</th>
                    <th>Rating</th>
                    <th>Review</th>
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
                        <td>#<?= intval($review['id']) ?></td>
                        <td><?= htmlspecialchars($review['product_name'] ?? 'Unknown Product') ?></td>
                        <td><?= htmlspecialchars(trim(($review['first_name'] ?? '') . ' ' . ($review['last_name'] ?? '')) ?: ($review['email'] ?? 'Guest')) ?></td>
                        <td style="color: #f59e0b;"><?= htmlspecialchars(renderAdminReviewStars(intval($review['rating'] ?? 0))) ?></td>
                        <td>
                            <?php if (!empty($review['title'])): ?>
                                <div class="admin-semi"><?= htmlspecialchars($review['title']) ?></div>
                            <?php endif; ?>
                            <?php $reviewPreview = trim((string)($review['review'] ?? '')); ?>
                            <?php if (strlen($reviewPreview) > 110) { $reviewPreview = substr($reviewPreview, 0, 110) . '...'; } ?>
                            <div class="admin-note-muted"><?= htmlspecialchars($reviewPreview) ?></div>
                        </td>
                        <td>
                            <span class="badge badge-<?= ($review['status'] ?? '') === 'approved' ? 'success' : (($review['status'] ?? '') === 'rejected' ? 'danger' : 'warning') ?>">
                                <?= htmlspecialchars(ucfirst($review['status'] ?? 'pending')) ?>
                            </span>
                        </td>
                        <td><?= intval($review['image_count'] ?? 0) ?></td>
                        <td><?= htmlspecialchars(date('M j, Y', strtotime($review['created_at'] ?? 'now'))) ?></td>
                        <td>
                            <div class="admin-form-row">
                                <a href="reviews.php?action=view&id=<?= intval($review['id']) ?>" class="btn btn-sm btn-outline">View</a>
                                <form method="POST" class="admin-form-row-center">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="review_id" value="<?= intval($review['id']) ?>">
                                    <select name="status" class="form-select admin-status-select">
                                        <option value="pending" <?= ($review['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="approved" <?= ($review['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                                        <option value="rejected" <?= ($review['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-secondary">Save</button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Delete this review permanently?');">
                                    <input type="hidden" name="action" value="delete_review">
                                    <input type="hidden" name="review_id" value="<?= intval($review['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-secondary">Delete</button>
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

