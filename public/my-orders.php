<?php
/**
 * KARTLY - User Account
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/payment_gateway.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$user = getCurrentUser();
$message = '';
$error = '';
$userId = intval($_SESSION['user_id']);

function ensureReviewImagesTable(PDO $db): bool
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

function normalizeUploadFileArray(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $normalized = [];
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $normalized[] = [
            'name' => $files['name'][$i] ?? '',
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];
    }

    return $normalized;
}

function uploadReviewImage(array $file, ?string &$uploadError): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $uploadError = 'Image upload failed. Please try again.';
        return null;
    }

    $maxSize = 4 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        $uploadError = 'Each review image must be 4 MB or smaller.';
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

    $uploadDir = __DIR__ . '/assets/uploads/reviews';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        $uploadError = 'Unable to create review image upload directory.';
        return null;
    }

    if (!is_writable($uploadDir)) {
        $uploadError = 'Review image upload directory is not writable.';
        return null;
    }

    try {
        $fileName = 'review_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowedMimeToExtension[$mimeType];
    } catch (Throwable $e) {
        $fileName = 'review_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.' . $allowedMimeToExtension[$mimeType];
    }

    $targetPath = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($tmpName, $targetPath)) {
        $uploadError = 'Unable to move uploaded review image.';
        return null;
    }

    return 'assets/uploads/reviews/' . $fileName;
}

function isManagedReviewUploadPath(string $path): bool
{
    return strpos($path, 'assets/uploads/reviews/') === 0;
}

function deleteManagedReviewUpload(string $path): void
{
    if (!isManagedReviewUploadPath($path)) {
        return;
    }

    $absolute = __DIR__ . '/' . $path;
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

$reviewImagesEnabled = ensureReviewImagesTable($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $orderId = intval($_POST['order_id'] ?? 0);
    $productId = intval($_POST['product_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $reviewText = trim((string)($_POST['review_text'] ?? ''));

    if ($orderId <= 0 || $productId <= 0) {
        $error = 'Invalid review request.';
    } elseif ($rating < 1 || $rating > 5) {
        $error = 'Please select a valid rating between 1 and 5.';
    } elseif (strlen($reviewText) > 3000) {
        $error = 'Review must be 3000 characters or less.';
    }

    if ($error === '') {
        $ownershipStmt = $db->prepare("
            SELECT o.id
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            WHERE o.id = ? AND o.user_id = ? AND o.status = 'delivered' AND oi.product_id = ?
            LIMIT 1
        ");
        $ownershipStmt->execute([$orderId, $userId, $productId]);
        if (!$ownershipStmt->fetch()) {
            $error = 'You can only review products from your delivered orders.';
        }
    }

    $uploadedImagePaths = [];
    $uploadError = null;

    if ($error === '' && isset($_FILES['review_images'])) {
        $reviewUploads = normalizeUploadFileArray($_FILES['review_images']);
        $providedUploads = array_values(array_filter($reviewUploads, static function ($upload) {
            return ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        }));

        if (count($providedUploads) > 4) {
            $error = 'You can upload up to 4 images per review.';
        } elseif (!empty($providedUploads) && !$reviewImagesEnabled) {
            $error = 'Review image upload is temporarily unavailable.';
        } else {
            foreach ($providedUploads as $reviewUpload) {
                $uploadedPath = uploadReviewImage($reviewUpload, $uploadError);
                if ($uploadError !== null) {
                    $error = $uploadError;
                    break;
                }
                if ($uploadedPath !== null) {
                    $uploadedImagePaths[] = $uploadedPath;
                }
            }
        }
    }

    if ($error === '') {
        $newReviewId = null;
        $existingReviewId = null;
        $oldImagesToDelete = [];

        try {
            $db->beginTransaction();

            $existingReviewStmt = $db->prepare("
                SELECT id
                FROM reviews
                WHERE user_id = ? AND product_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $existingReviewStmt->execute([$userId, $productId]);
            $existingReview = $existingReviewStmt->fetch();
            $existingReviewId = $existingReview ? intval($existingReview['id']) : null;

            if ($existingReviewId) {
                $updateStmt = $db->prepare("UPDATE reviews SET rating = ?, review = ?, status = 'pending' WHERE id = ?");
                $updateStmt->execute([$rating, $reviewText !== '' ? $reviewText : null, $existingReviewId]);
                $newReviewId = $existingReviewId;
            } else {
                $insertStmt = $db->prepare("INSERT INTO reviews (product_id, user_id, rating, review, status) VALUES (?, ?, ?, ?, 'pending')");
                $insertStmt->execute([$productId, $userId, $rating, $reviewText !== '' ? $reviewText : null]);
                $newReviewId = intval($db->lastInsertId());
            }

            if ($newReviewId && $reviewImagesEnabled && !empty($uploadedImagePaths)) {
                $existingImageStmt = $db->prepare("SELECT image_path FROM review_images WHERE review_id = ?");
                $existingImageStmt->execute([$newReviewId]);
                $existingImageRows = $existingImageStmt->fetchAll();
                foreach ($existingImageRows as $existingImageRow) {
                    $oldImagesToDelete[] = trim((string)($existingImageRow['image_path'] ?? ''));
                }

                $deleteImageRowsStmt = $db->prepare("DELETE FROM review_images WHERE review_id = ?");
                $deleteImageRowsStmt->execute([$newReviewId]);

                $insertImageStmt = $db->prepare("INSERT INTO review_images (review_id, image_path) VALUES (?, ?)");
                foreach ($uploadedImagePaths as $uploadedImagePath) {
                    $insertImageStmt->execute([$newReviewId, $uploadedImagePath]);
                }
            }

            $db->commit();

            foreach (array_unique(array_filter($oldImagesToDelete)) as $oldImagePath) {
                deleteManagedReviewUpload($oldImagePath);
            }

            $message = $existingReviewId
                ? 'Your review was updated and sent for admin approval.'
                : 'Thank you. Your review was submitted and is pending admin approval.';
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Unable to save your review right now. Please try again.';
        }
    }

    if ($error !== '' && !empty($uploadedImagePaths)) {
        foreach (array_unique($uploadedImagePaths) as $uploadedImagePath) {
            deleteManagedReviewUpload($uploadedImagePath);
        }
    }
}

// Profile update logic removed from orders page

// Get user orders
$stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

$orderItemsByOrder = [];
$reviewsByProduct = [];
$reviewImagesByReview = [];

if (!empty($orders)) {
    $orderIds = array_map('intval', array_column($orders, 'id'));
    $orderPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));

    $orderItemsStmt = $db->prepare("
        SELECT oi.order_id, oi.product_id, oi.product_name, oi.quantity, p.main_image
        FROM order_items oi
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id IN ($orderPlaceholders)
        ORDER BY oi.id ASC
    ");
    $orderItemsStmt->execute($orderIds);
    $orderItems = $orderItemsStmt->fetchAll();

    $productIds = [];
    foreach ($orderItems as $orderItem) {
        $orderIdKey = intval($orderItem['order_id']);
        if (!isset($orderItemsByOrder[$orderIdKey])) {
            $orderItemsByOrder[$orderIdKey] = [];
        }
        $orderItemsByOrder[$orderIdKey][] = $orderItem;
        $productIds[] = intval($orderItem['product_id']);
    }
    $productIds = array_values(array_unique(array_filter($productIds)));

    if (!empty($productIds)) {
        $productPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
        $reviewQueryParams = array_merge([$userId], $productIds);
        $reviewsStmt = $db->prepare("
            SELECT *
            FROM reviews
            WHERE user_id = ? AND product_id IN ($productPlaceholders)
            ORDER BY created_at DESC
        ");
        $reviewsStmt->execute($reviewQueryParams);
        $reviewRows = $reviewsStmt->fetchAll();

        $reviewIds = [];
        foreach ($reviewRows as $reviewRow) {
            $productKey = intval($reviewRow['product_id']);
            if (!isset($reviewsByProduct[$productKey])) {
                $reviewsByProduct[$productKey] = $reviewRow;
                $reviewIds[] = intval($reviewRow['id']);
            }
        }

        if ($reviewImagesEnabled && !empty($reviewIds)) {
            $reviewPlaceholders = implode(',', array_fill(0, count($reviewIds), '?'));
            $reviewImagesStmt = $db->prepare("SELECT review_id, image_path FROM review_images WHERE review_id IN ($reviewPlaceholders) ORDER BY id ASC");
            $reviewImagesStmt->execute($reviewIds);
            while ($reviewImageRow = $reviewImagesStmt->fetch()) {
                $reviewIdKey = intval($reviewImageRow['review_id']);
                if (!isset($reviewImagesByReview[$reviewIdKey])) {
                    $reviewImagesByReview[$reviewIdKey] = [];
                }
                $reviewImagesByReview[$reviewIdKey][] = $reviewImageRow['image_path'];
            }
        }
    }
}

$pageTitle = 'My Orders';
require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header -->
    <section class="section section-bg" style="padding: 1.5rem 0 2rem;">
        <div class="container">
            <nav style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem;">
                <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light); display: inline-flex; align-items: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
                <span> / </span>
                <span style="color: var(--color-text);">My Orders</span>
            </nav>
            <h1 style="font-size: 2rem; font-weight: 700;">My Orders</h1>
        </div>
    </section>

    <!-- Account Content -->
    <section class="section">
        <div class="container">
            <?php if ($message): ?>
            <div style="background: rgba(40, 167, 69, 0.1); border: 1px solid var(--color-success); color: var(--color-success); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div style="background: rgba(220, 53, 69, 0.1); border: 1px solid var(--color-danger); color: var(--color-danger); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div>
                <!-- Quick Actions -->
                <div style="margin-bottom: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/account" class="btn btn-outline">My Account</a>
                    <a href="<?= BASE_URL ?>/wishlist" class="btn btn-outline">My Wishlist</a>
                    <a href="<?= BASE_URL ?>/track-order" class="btn btn-outline">Track Order</a>
                    <a href="<?= BASE_URL ?>/logout" class="btn btn-secondary">Logout</a>
                </div>

                <!-- Orders -->
                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">Order History</h2>
                    
                    <?php if (empty($orders)): ?>
                    <p style="color: var(--color-text-light); text-align: center; padding: 2rem;">You haven't placed any orders yet.</p>
                    <div style="text-align: center;">
                        <a href="<?= BASE_URL ?>/shop" class="btn btn-primary">Start Shopping</a>
                    </div>
                    <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; min-width: 600px; text-align: left;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--color-border);">
                                    <th style="padding: 1rem; font-weight: 600;">Order ID</th>
                                    <th style="padding: 1rem; font-weight: 600;">Date</th>
                                    <th style="padding: 1rem; font-weight: 600;">Time</th>
                                    <th style="padding: 1rem; font-weight: 600;">Status</th>
                                    <th style="padding: 1rem; text-align: right; font-weight: 600;">Amount</th>
                                    <th style="padding: 1rem; text-align: center; font-weight: 600;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr style="border-bottom: 1px solid var(--color-border); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='var(--color-bg-secondary)'" onmouseout="this.style.backgroundColor='transparent'">
                                    <td style="padding: 1rem;">
                                        <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                                    </td>
                                    <td style="padding: 1rem;"><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                    <td style="padding: 1rem; color: var(--color-text-light);"><?= date('g:i A', strtotime($order['created_at'])) ?></td>
                                    <td style="padding: 1rem;">
                                        <span class="badge badge-<?= $order['status'] === 'delivered' ? 'success' : ($order['status'] === 'cancelled' ? 'danger' : 'warning') ?>"><?= ucfirst($order['status']) ?></span>
                                    </td>
                                    <td style="padding: 1rem; text-align: right; font-weight: 600;">
                                        <?= formatPrice($order['total']) ?><br>
                                        <span style="font-size: 0.75rem; color: var(--color-text-light); font-weight: normal;"><?= htmlspecialchars(paymentDisplayName((string)$order['payment_method'])) ?></span>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <a href="<?= BASE_URL ?>/track/<?= htmlspecialchars($order['order_number']) ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.875rem;">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </section>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>
