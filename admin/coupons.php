<?php
/**
 * Rosabella - Admin Coupons Management
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
$couponId = intval($_GET['id'] ?? 0);
$search = sanitize($_GET['search'] ?? '');

if ($action === 'delete' && $couponId > 0) {
    $stmt = $db->prepare("DELETE FROM coupons WHERE id = ?");
    if ($stmt->execute([$couponId])) {
        $message = 'Coupon deleted successfully.';
    } else {
        $error = 'Unable to delete coupon.';
    }
    $action = 'list';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(sanitize($_POST['code'] ?? ''));
    $type = sanitize($_POST['type'] ?? 'percentage');
    $value = floatval($_POST['value'] ?? 0);
    $minOrderAmount = floatval($_POST['min_order_amount'] ?? 0);
    $maxUses = $_POST['max_uses'] === '' ? null : intval($_POST['max_uses']);
    $startDate = sanitize($_POST['start_date'] ?? '');
    $endDate = sanitize($_POST['end_date'] ?? '');
    $status = sanitize($_POST['status'] ?? 'active');

    if (!$code || $value <= 0) {
        $error = 'Code and discount value are required.';
    } elseif (!in_array($type, ['percentage', 'fixed'], true)) {
        $error = 'Invalid coupon type.';
    } else {
        try {
            if ($action === 'edit' && $couponId > 0) {
                $stmt = $db->prepare("
                    UPDATE coupons
                    SET code = ?, type = ?, value = ?, min_order_amount = ?, max_uses = ?, start_date = ?, end_date = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $code, $type, $value, $minOrderAmount,
                    $maxUses, $startDate ?: null, $endDate ?: null,
                    in_array($status, ['active', 'inactive'], true) ? $status : 'active',
                    $couponId
                ]);
                $message = 'Coupon updated successfully.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO coupons (code, type, value, min_order_amount, max_uses, start_date, end_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $code, $type, $value, $minOrderAmount,
                    $maxUses, $startDate ?: null, $endDate ?: null,
                    in_array($status, ['active', 'inactive'], true) ? $status : 'active'
                ]);
                $message = 'Coupon created successfully.';
            }
            $action = 'list';
            $couponId = 0;
        } catch (Throwable $e) {
            $error = 'Unable to save coupon. Ensure code is unique.';
        }
    }
}

$editingCoupon = null;
if ($action === 'edit' && $couponId > 0) {
    $stmt = $db->prepare("SELECT * FROM coupons WHERE id = ?");
    $stmt->execute([$couponId]);
    $editingCoupon = $stmt->fetch();
    if (!$editingCoupon) {
        $error = 'Coupon not found.';
        $action = 'list';
    }
}

$where = '';
$params = [];
if ($search) {
    $where = "WHERE code LIKE ?";
    $params[] = "%$search%";
}

// Pagination Setup
$perPage = max(1, min(100, intval($_GET['per_page'] ?? 15)));
$page = max(1, intval($_GET['page'] ?? 1));

$countStmt = $db->prepare("SELECT COUNT(*) FROM coupons $where");
$countStmt->execute($params);
$totalCoupons = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalCoupons / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT * FROM coupons $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$coupons = $stmt->fetchAll();

$pageTitle = 'Coupons Management';
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
    <?php renderAdminSidebar('coupons'); ?>

    <main class="admin-content">
        <?php renderAdminTopbar($pageTitle ?? 'Admin Panel'); ?>
<div class="admin-header">
            <h1 class="admin-page-title">
                <?= $action === 'edit' ? 'Edit Coupon' : ($action === 'add' ? 'Add Coupon' : 'Coupons') ?>
            </h1>
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="btn btn-primary">+ Add Coupon</a>
            <?php endif; ?>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <?php if ($action === 'list'): ?>
            <div class="admin-card">
                <form method="GET" class="admin-form-row">
                    <input class="form-input admin-input-max-280" type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by coupon code...">
                    <button class="btn btn-secondary" type="submit">Search</button>
                </form>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Value</th>
                        <th>Total Uses</th>
                        <th>Per-User Limit</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($coupons as $coupon): ?>
                        <tr>
                            <td><?= intval($coupon['id']) ?></td>
                            <td><?= htmlspecialchars($coupon['code']) ?></td>
                            <td><?= htmlspecialchars($coupon['type']) ?></td>
                            <td><?= $coupon['type'] === 'percentage' ? intval($coupon['value']) . '%' : formatPrice($coupon['value']) ?></td>
                            <td><?= intval($coupon['used_count']) ?></td>
                            <td><?= $coupon['max_uses'] !== null ? intval($coupon['max_uses']) : 'Unlimited' ?></td>
                            <td>
                                <?= $coupon['start_date'] ? htmlspecialchars($coupon['start_date']) : '-' ?>
                                to
                                <?= $coupon['end_date'] ? htmlspecialchars($coupon['end_date']) : '-' ?>
                            </td>
                            <td><span class="badge badge-<?= $coupon['status'] === 'active' ? 'success' : 'warning' ?>"><?= htmlspecialchars(ucfirst($coupon['status'])) ?></span></td>
                            <td>
                                <div class="admin-actions-row">
                                    <a class="btn-action-icon edit" href="?action=edit&id=<?= intval($coupon['id']) ?>" title="Edit Coupon">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>
                                    <a class="btn-action-icon delete" href="?action=delete&id=<?= intval($coupon['id']) ?>" onclick="return confirm('Delete this coupon?');" title="Delete Coupon">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php renderAdminPagination($page, $totalCoupons, $perPage, BASE_URL . '/admin/coupons', array_filter(['search' => $search])); ?>
        <?php else: ?>
            <div class="admin-card">
                <form method="POST">
                        <!-- Security: CSRF token -->
                        <?= csrfField() ?>
                    <div class="form-group">
                        <label class="form-label">Code *</label>
                        <input type="text" name="code" class="form-input" required value="<?= htmlspecialchars($editingCoupon['code'] ?? $_POST['code'] ?? '') ?>" placeholder="e.g., SUMMER20, ROSE10, WELCOME">
                    </div>
                    <div class="admin-two-col-grid">
                        <div class="form-group">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select">
                                <option value="percentage" <?= ($editingCoupon['type'] ?? $_POST['type'] ?? 'percentage') === 'percentage' ? 'selected' : '' ?>>Percentage</option>
                                <option value="fixed" <?= ($editingCoupon['type'] ?? $_POST['type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Value *</label>
                            <input type="number" step="0.01" min="0" name="value" class="form-input" required value="<?= htmlspecialchars((string)($editingCoupon['value'] ?? $_POST['value'] ?? '')) ?>" placeholder="e.g., 20">
                        </div>
                    </div>
                    <div class="admin-two-col-grid">
                        <div class="form-group">
                            <label class="form-label">Minimum Order Amount</label>
                            <input type="number" step="0.01" min="0" name="min_order_amount" class="form-input" value="<?= htmlspecialchars((string)($editingCoupon['min_order_amount'] ?? $_POST['min_order_amount'] ?? '')) ?>" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Max Uses (Per User)</label>
                            <input type="number" min="0" name="max_uses" class="form-input" value="<?= htmlspecialchars((string)($editingCoupon['max_uses'] ?? $_POST['max_uses'] ?? '')) ?>" placeholder="e.g., 1 (leave blank for unlimited)">
                        </div>
                    </div>
                    <div class="admin-two-col-grid">
                        <div class="form-group">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-input" value="<?= htmlspecialchars($editingCoupon['start_date'] ?? $_POST['start_date'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-input" value="<?= htmlspecialchars($editingCoupon['end_date'] ?? $_POST['end_date'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= ($editingCoupon['status'] ?? $_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($editingCoupon['status'] ?? $_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="admin-actions-row">
                        <button class="btn btn-primary" type="submit">Save Coupon</button>
                        <a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/coupons">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>
</div>
    <script src="js/admin.js"></script>
</body>
</html>
