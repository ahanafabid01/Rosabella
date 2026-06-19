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
    $reviewTitle = trim((string)($_POST['review_title'] ?? ''));
    $reviewText = trim((string)($_POST['review_text'] ?? ''));

    if ($orderId <= 0 || $productId <= 0) {
        $error = 'Invalid review request.';
    } elseif ($rating < 1 || $rating > 5) {
        $error = 'Please select a valid rating between 1 and 5.';
    } elseif (strlen($reviewTitle) > 255) {
        $error = 'Review title must be 255 characters or less.';
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
                $updateStmt = $db->prepare("UPDATE reviews SET rating = ?, title = ?, review = ?, status = 'pending' WHERE id = ?");
                $updateStmt->execute([$rating, $reviewTitle !== '' ? $reviewTitle : null, $reviewText !== '' ? $reviewText : null, $existingReviewId]);
                $newReviewId = $existingReviewId;
            } else {
                $insertStmt = $db->prepare("INSERT INTO reviews (product_id, user_id, rating, title, review, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                $insertStmt->execute([$productId, $userId, $rating, $reviewTitle !== '' ? $reviewTitle : null, $reviewText !== '' ? $reviewText : null]);
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

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $upazila = sanitize($_POST['upazila'] ?? '');
    $postalCode = sanitize($_POST['postal_code'] ?? '');
    $country = 'Bangladesh';
    
    $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, address=?, city=?, upazila=?, postal_code=?, country=? WHERE id=?");
    if ($stmt->execute([$firstName, $lastName, $phone, $address, $city, $upazila, $postalCode, $country, $userId])) {
        $message = 'Profile updated successfully';
        $user = getCurrentUser(); // Refresh
    } else {
        $error = 'Failed to update profile';
    }
}

// Get user orders
$stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
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

$pageTitle = 'My Account';
require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header -->
    <section class="section section-bg" style="padding: 1.5rem 0 2rem;">
        <div class="container">
            <nav style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem;">
                <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light); display: inline-flex; align-items: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
                <span> / </span>
                <span style="color: var(--color-text);">My Account</span>
            </nav>
            <h1 style="font-size: 2rem; font-weight: 700;">My Account</h1>
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
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <!-- Profile -->
                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">Profile Information</h2>
                    <form method="POST">
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-input" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-input" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-input" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled style="background: var(--color-bg-secondary);">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-input" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-input" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                        </div>
                        <div class="form-grid-3">
                            <div class="form-group">
                                <label class="form-label">Upazila/Thana</label>
                                <input type="text" name="upazila" class="form-input" value="<?= htmlspecialchars($user['upazila'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">District</label>
                                <select name="city" class="form-input select2-district" style="width: 100%;">
                                    <option value="">Select district...</option>
                                    <?php
                                    $districts = ["Bagerhat", "Bandarban", "Barguna", "Barisal", "Bhola", "Bogura", "Brahmanbaria", "Chandpur", "Chapai Nawabganj", "Chattogram - City", "Chattogram - Suburb", "Chuadanga", "Cox's Bazar", "Cumilla", "Dhaka - City", "Dhaka - Suburb", "Dinajpur", "Faridpur", "Feni", "Gaibandha", "Gazipur - City", "Gazipur - Suburb", "Gopalganj", "Habiganj", "Jamalpur", "Jashore", "Jhalokati", "Jhenaidah", "Joypurhat", "Khagrachari", "Khulna - City", "Khulna - Suburb", "Kishoreganj", "Kurigram", "Kushtia", "Lakshmipur", "Lalmonirhat", "Madaripur", "Magura", "Manikganj", "Meherpur", "Moulvibazar", "Munshiganj", "Mymensingh", "Naogaon", "Narail", "Narayanganj", "Narsingdi", "Natore", "Netrokona", "Nilphamari", "Noakhali", "Pabna", "Panchagarh", "Patuakhali", "Pirojpur", "Rajbari", "Rajshahi - Suburb", "Rajshahi City", "Rangamati", "Rangpur - Suburb", "Rangpur City", "Satkhira", "Shariatpur", "Sherpur", "Sirajganj", "Sunamganj", "Sylhet", "Tangail", "Thakurgaon"];
                                    $userCity = $user['city'] ?? '';
                                    foreach ($districts as $district) {
                                        $selected = ($userCity == $district) ? 'selected' : '';
                                        echo "<option value=\"" . htmlspecialchars($district) . "\" $selected>" . htmlspecialchars($district) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Postal Code</label>
                                <input type="text" name="postal_code" class="form-input" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>">
                            </div>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
                
                <!-- Orders -->
                <div id="orders" style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">Recent Orders</h2>
                    
                    <?php if (empty($orders)): ?>
                    <p style="color: var(--color-text-light); text-align: center; padding: 2rem;">You haven't placed any orders yet.</p>
                    <div style="text-align: center;">
                        <a href="<?= BASE_URL ?>/shop" class="btn btn-primary">Start Shopping</a>
                    </div>
                    <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($orders as $order): ?>
                        <?php $orderId = intval($order['id']); ?>
                        <?php $orderItems = $orderItemsByOrder[$orderId] ?? []; ?>
                        <div style="padding: 1rem; background: var(--color-bg-secondary); border-radius: var(--radius-md);">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                                <div>
                                    <p style="font-weight: 600;"><?= htmlspecialchars($order['order_number']) ?></p>
                                    <p style="font-size: 0.75rem; color: var(--color-text-light);"><?= date('F j, Y', strtotime($order['created_at'])) ?></p>
                                </div>
                                <div style="text-align: right;">
                                    <span class="badge badge-<?= $order['status'] === 'delivered' ? 'success' : 'warning' ?>"><?= ucfirst($order['status']) ?></span>
                                    <p style="font-weight: 600; margin-top: 0.25rem;"><?= formatPrice($order['total']) ?></p>
                                    <p style="font-size: 0.75rem; color: var(--color-text-light); margin-top: 0.2rem;">
                                        <?= htmlspecialchars(paymentDisplayName((string)$order['payment_method'])) ?> · <?= htmlspecialchars(ucfirst((string)$order['payment_status'])) ?>
                                    </p>
                                </div>
                            </div>

                            <?php if (!empty($orderItems)): ?>
                            <div style="margin-top: 0.9rem; border-top: 1px solid var(--color-border); padding-top: 0.9rem; display: flex; flex-direction: column; gap: 0.75rem;">
                                <?php foreach ($orderItems as $item): ?>
                                <?php
                                $itemProductId = intval($item['product_id']);
                                $existingReview = $reviewsByProduct[$itemProductId] ?? null;
                                $existingReviewId = intval($existingReview['id'] ?? 0);
                                $existingReviewImages = $existingReviewId > 0 ? ($reviewImagesByReview[$existingReviewId] ?? []) : [];
                                ?>
                                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.75rem;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem; min-width: 0;">
                                            <img src="<?= htmlspecialchars($item['main_image'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=200&q=80') ?>" alt="" style="width: 44px; height: 44px; border-radius: 8px; object-fit: cover; border: 1px solid var(--color-border);">
                                            <div style="min-width: 0;">
                                                <p style="font-weight: 600; line-height: 1.3;"><?= htmlspecialchars($item['product_name']) ?></p>
                                                <p style="font-size: 0.75rem; color: var(--color-text-light);">Qty: <?= intval($item['quantity']) ?></p>
                                            </div>
                                        </div>

                                        <?php if ($order['status'] === 'delivered'): ?>
                                        <span style="font-size: 0.75rem; color: var(--color-text-light);">You can review this item</span>
                                        <?php else: ?>
                                        <span style="font-size: 0.75rem; color: var(--color-text-light);">Review available after delivery</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($order['status'] === 'delivered'): ?>
                                    <?php if ($existingReview): ?>
                                    <div style="margin-top: 0.6rem; font-size: 0.78rem; color: var(--color-text-light);">
                                        Current review status:
                                        <span class="badge badge-<?= $existingReview['status'] === 'approved' ? 'success' : ($existingReview['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                            <?= htmlspecialchars(ucfirst($existingReview['status'])) ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>

                                    <details style="margin-top: 0.65rem;">
                                        <summary style="cursor: pointer; font-weight: 600; color: var(--color-primary);">
                                            <?= $existingReview ? 'Update your review' : 'Write a review' ?>
                                        </summary>
                                        <form method="POST" enctype="multipart/form-data" style="margin-top: 0.85rem; display: flex; flex-direction: column; gap: 0.75rem;">
                                            <input type="hidden" name="submit_review" value="1">
                                            <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                            <input type="hidden" name="product_id" value="<?= $itemProductId ?>">

                                            <div class="form-group" style="margin-bottom: 0;">
                                                <label class="form-label">Rating</label>
                                                <select name="rating" class="form-select" required>
                                                    <option value="">Select rating</option>
                                                    <?php for ($star = 5; $star >= 1; $star--): ?>
                                                    <option value="<?= $star ?>" <?= intval($existingReview['rating'] ?? 0) === $star ? 'selected' : '' ?>>
                                                        <?= $star ?> Star<?= $star > 1 ? 's' : '' ?>
                                                    </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>

                                            <div class="form-group" style="margin-bottom: 0;">
                                                <label class="form-label">Title (optional)</label>
                                                <input type="text" name="review_title" class="form-input" maxlength="255" value="<?= htmlspecialchars((string)($existingReview['title'] ?? '')) ?>" placeholder="Short summary">
                                            </div>

                                            <div class="form-group" style="margin-bottom: 0;">
                                                <label class="form-label">Review</label>
                                                <textarea name="review_text" class="form-textarea" rows="4" maxlength="3000" placeholder="Share your experience..." required><?= htmlspecialchars((string)($existingReview['review'] ?? '')) ?></textarea>
                                            </div>

                                            <div class="form-group" style="margin-bottom: 0;">
                                                <label class="form-label">Photos (optional, up to 4)</label>
                                                <input type="file" name="review_images[]" class="form-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                                                <p style="font-size: 0.75rem; color: var(--color-text-light); margin-top: 0.4rem;">Adding new photos replaces existing photos for this review.</p>
                                            </div>

                                            <?php if (!empty($existingReviewImages)): ?>
                                            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                                <?php foreach ($existingReviewImages as $existingReviewImage): ?>
                                                <img src="<?= htmlspecialchars($existingReviewImage) ?>" alt="Review photo" style="width: 58px; height: 58px; object-fit: cover; border: 1px solid var(--color-border); border-radius: 6px;">
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>

                                            <div>
                                                <button type="submit" class="btn btn-primary">
                                                    <?= $existingReview ? 'Update Review' : 'Submit Review' ?>
                                                </button>
                                            </div>
                                        </form>
                                    </details>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div style="margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="#orders" class="btn btn-outline">My Orders</a>
                <a href="<?= BASE_URL ?>/wishlist" class="btn btn-outline">My Wishlist</a>
                <a href="<?= BASE_URL ?>/track-order" class="btn btn-outline">Track Order</a>
                <a href="<?= BASE_URL ?>/logout" class="btn btn-secondary">Logout</a>
            </div>
        </div>
    </section>

<!-- Select2 CSS & JS for searchable district dropdown -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
.select2-container .select2-selection--single {
    height: 42px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 40px;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: var(--color-text);
    line-height: 40px;
    padding-left: 0.75rem;
}
</style>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2-district').select2({
        placeholder: "Search district...",
        width: '100%'
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
