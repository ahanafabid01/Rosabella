<?php
/**
 * KARTLY - Admin View Review
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

$reviewImagesEnabled = true;
$allowedStatuses = ['pending', 'approved', 'rejected'];

$viewReviewId = intval($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    $reviewId = intval($_POST['review_id'] ?? 0);

    if ($reviewId <= 0 || $reviewId !== $viewReviewId) {
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

            $imagesStmt = $db->prepare("SELECT image_path FROM review_images WHERE review_id = ?");
            $imagesStmt->execute([$reviewId]);
            $imageRows = $imagesStmt->fetchAll();
            foreach ($imageRows as $imageRow) {
                $imagesToDelete[] = trim((string)($imageRow['image_path'] ?? ''));
            }

            $deleteStmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
            $deleteStmt->execute([$reviewId]);

            $db->commit();

            foreach (array_unique(array_filter($imagesToDelete)) as $imagePath) {
                deleteManagedAdminReviewUpload($imagePath);
            }

            $_SESSION['admin_message'] = 'Review deleted successfully.';
            header('Location: ' . BASE_URL . '/admin/reviews.php');
            exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Unable to delete review right now.';
        }
    }
}

$viewReview = null;
$viewReviewImages = [];

if ($viewReviewId > 0) {
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
        $_SESSION['admin_error'] = 'Review not found.';
        header('Location: ' . BASE_URL . '/admin/reviews.php');
        exit;
    }

    $viewImagesStmt = $db->prepare("SELECT image_path FROM review_images WHERE review_id = ? ORDER BY id ASC");
    $viewImagesStmt->execute([$viewReviewId]);
    $viewReviewImages = $viewImagesStmt->fetchAll();
} else {
    header('Location: ' . BASE_URL . '/admin/reviews.php');
    exit;
}

$pageTitle = 'Review Details #' . intval($viewReview['id']);
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
        <?php renderAdminTopbar($pageTitle); ?>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="admin-detail-header" style="margin-bottom: 2rem;">
            <div>
                <h2 class="admin-page-title" style="margin-bottom: 0.5rem;">Review #<?= intval($viewReview['id']) ?></h2>
                <span class="badge badge-<?= ($viewReview['status'] ?? '') === 'approved' ? 'success' : (($viewReview['status'] ?? '') === 'rejected' ? 'danger' : 'warning') ?>" style="font-size: 0.85rem;">
                    <?= htmlspecialchars(ucfirst($viewReview['status'] ?? 'pending')) ?>
                </span>
            </div>
            <a href="<?= BASE_URL ?>/admin/reviews.php" class="btn btn-secondary">Back to Reviews</a>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 350px; gap: 2rem; align-items: start;">
            <!-- Left Column: Review Content -->
            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden;">
                <div style="padding: 1.5rem; border-bottom: 1px solid var(--color-border); background: var(--color-bg-secondary);">
                    <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem;"><?= htmlspecialchars((string)($viewReview['title'] ?? 'No Title Provided')) ?></h3>
                    <div style="color: #f59e0b; font-size: 1.25rem; letter-spacing: 2px;">
                        <?= htmlspecialchars(renderAdminReviewStars(intval($viewReview['rating'] ?? 0))) ?>
                    </div>
                </div>
                <div style="padding: 1.5rem;">
                    <h4 style="font-size: 0.85rem; color: var(--color-text-light); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Customer Review</h4>
                    <p style="line-height: 1.6; color: var(--color-text); margin-bottom: 1.5rem; font-size: 0.95rem;">
                        <?= nl2br(htmlspecialchars((string)($viewReview['review'] ?? ''))) ?>
                    </p>

                    <?php if (!empty($viewReviewImages)): ?>
                    <h4 style="font-size: 0.85rem; color: var(--color-text-light); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Attached Photos</h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                        <?php foreach ($viewReviewImages as $viewReviewImage): ?>
                        <a href="<?= htmlspecialchars(resolveAdminReviewImageSrc($viewReviewImage['image_path'] ?? '')) ?>" target="_blank" rel="noopener noreferrer" style="display: block; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--color-border);">
                            <img src="<?= htmlspecialchars(resolveAdminReviewImageSrc($viewReviewImage['image_path'] ?? '')) ?>" alt="Review photo" style="width: 80px; height: 80px; object-fit: cover; display: block;">
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Meta & Actions -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                    <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1.25rem;">Review Details</h3>
                    
                    <div style="margin-bottom: 1rem;">
                        <span style="display: block; font-size: 0.8rem; color: var(--color-text-light); margin-bottom: 0.25rem;">Product</span>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <?php if (!empty($viewReview['product_image'])): ?>
                            <img src="<?= htmlspecialchars(resolveAdminReviewImageSrc($viewReview['product_image'])) ?>" alt="" style="width: 40px; height: 40px; border-radius: 4px; object-fit: cover; border: 1px solid var(--color-border);">
                            <?php endif; ?>
                            <span style="font-weight: 500; font-size: 0.95rem;"><?= htmlspecialchars($viewReview['product_name'] ?? 'Unknown Product') ?></span>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <span style="display: block; font-size: 0.8rem; color: var(--color-text-light); margin-bottom: 0.25rem;">Customer</span>
                        <span style="font-weight: 500; font-size: 0.95rem;"><?= htmlspecialchars(trim(($viewReview['first_name'] ?? '') . ' ' . ($viewReview['last_name'] ?? '')) ?: ($viewReview['email'] ?? 'Guest')) ?></span>
                    </div>

                    <div>
                        <span style="display: block; font-size: 0.8rem; color: var(--color-text-light); margin-bottom: 0.25rem;">Submitted On</span>
                        <span style="font-weight: 500; font-size: 0.95rem;"><?= htmlspecialchars(date('M j, Y \a\t g:i A', strtotime($viewReview['created_at'] ?? 'now'))) ?></span>
                    </div>
                </div>

                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                    <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1.25rem;">Actions</h3>
                    
                    <form method="POST" style="margin-bottom: 1rem;">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="review_id" value="<?= intval($viewReview['id']) ?>">
                        <label style="display: block; font-size: 0.85rem; margin-bottom: 0.5rem; color: var(--color-text-light);">Update Status</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <select name="status" class="form-select" style="flex: 1;">
                                <option value="pending" <?= ($viewReview['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= ($viewReview['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= ($viewReview['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </form>

                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this review? This action cannot be undone.');" style="border-top: 1px solid var(--color-border); padding-top: 1rem;">
                        <input type="hidden" name="action" value="delete_review">
                        <input type="hidden" name="review_id" value="<?= intval($viewReview['id']) ?>">
                        <button type="submit" class="btn btn-outline" style="width: 100%; color: var(--color-danger); border-color: var(--color-danger);">Delete Review Permanently</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="js/admin.js"></script>
</body>
</html>
