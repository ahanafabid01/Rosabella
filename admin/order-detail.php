<?php
/**
 * Rosabella - Admin Order Detail
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../includes/payment_gateway.php';

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
$orderId = intval($_GET['id'] ?? 0);

if ($orderId <= 0) {
    header('Location: ' . BASE_URL . '/admin/orders');
    exit;
}

function ensureOrderStatusHistoryTable(PDO $db): void
{
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS order_status_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                status VARCHAR(50) NOT NULL,
                note TEXT NULL,
                changed_by VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_osh_order (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $e) {}
}

ensureOrderStatusHistoryTable($db);

$statusMap = [
    'pending'      => ['label' => 'Pending',           'badge' => 'warning'],
    'confirmed'    => ['label' => 'Confirmed',         'badge' => 'info'],
    'processing'   => ['label' => 'Processing',        'badge' => 'primary'],
    'shipped'      => ['label' => 'Shipped',           'badge' => 'indigo'],
    'delivered'    => ['label' => 'Delivered',         'badge' => 'success'],
    'on_hold'      => ['label' => 'Hold',              'badge' => 'warning'],
    'unreachable'  => ['label' => 'Unreachable',       'badge' => 'danger'],
    'not_received' => ['label' => "Didn't Receive",    'badge' => 'danger'],
    'returned'     => ['label' => 'Returned',          'badge' => 'purple'],
    'cancelled'    => ['label' => 'Cancelled',         'badge' => 'secondary'],
    'refunded'     => ['label' => 'Refunded',          'badge' => 'pink'],
    'fake'         => ['label' => 'Fake Order',        'badge' => 'dark-red'],
];

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $status     = sanitize($_POST['status'] ?? 'pending');
    $statusNote = sanitize($_POST['status_note'] ?? '');
    
    if (!isset($statusMap[$status])) {
        $error = 'Invalid order status.';
    } else {
        $oldStatusStmt = $db->prepare("SELECT status, total FROM orders WHERE id = ?");
        $oldStatusStmt->execute([$orderId]);
        $oldRow = $oldStatusStmt->fetch();
        $oldStatus = $oldRow['status'] ?? '';
        $orderTotal = floatval($oldRow['total'] ?? 0);

        if ($status === 'delivered') {
            $stmt = $db->prepare("UPDATE orders SET status = ?, payment_status = 'paid', advance_payment = ? WHERE id = ?");
            $saved = $stmt->execute([$status, $orderTotal, $orderId]);
        } else {
            $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $saved = $stmt->execute([$status, $orderId]);
        }

        if ($saved) {
            $message = 'Order status updated to ' . htmlspecialchars($statusMap[$status]['label']) . '.';
            
            // Insert status audit log into history
            $changedBy = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
            $histStmt  = $db->prepare("INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)");
            $histStmt->execute([$orderId, $status, $statusNote ?: null, $changedBy]);

            // Restock / Destock synchronization logic
            if ($oldStatus && $oldStatus !== $status) {
                $isOldInactive = in_array($oldStatus, ['cancelled', 'refunded', 'fake'], true);
                $isNewInactive = in_array($status, ['cancelled', 'refunded', 'fake'], true);
                
                if (!$isOldInactive && $isNewInactive) {
                    $itemsStmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                    $itemsStmt->execute([$orderId]);
                    $restockStmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                    foreach ($itemsStmt->fetchAll() as $item) {
                        $restockStmt->execute([$item['quantity'], $item['product_id']]);
                    }
                } elseif ($isOldInactive && !$isNewInactive) {
                    $itemsStmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                    $itemsStmt->execute([$orderId]);
                    $destockStmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                    foreach ($itemsStmt->fetchAll() as $item) {
                        $destockStmt->execute([$item['quantity'], $item['product_id']]);
                    }
                }
            }
        } else {
            $error = 'Unable to update order status.';
        }
    }
}

// Handle Amount Edits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_amounts'])) {
    $subtotal   = floatval($_POST['subtotal'] ?? 0);
    $discount   = floatval($_POST['discount'] ?? 0);
    $shipping   = floatval($_POST['shipping_cost'] ?? 0);
    $tax        = floatval($_POST['tax'] ?? 0);
    $advance    = floatval($_POST['advance_payment'] ?? 0);

    $newTotal   = max(0, ($subtotal - $discount)) + $shipping + $tax;

    $stmt = $db->prepare("
        UPDATE orders
        SET subtotal = ?, discount = ?, shipping_cost = ?, tax = ?, advance_payment = ?, total = ?
        WHERE id = ?
    ");

    if ($stmt->execute([$subtotal, $discount, $shipping, $tax, $advance, $newTotal, $orderId])) {
        $message = 'Financial amounts updated successfully.';
        $changedBy = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
        $histStmt  = $db->prepare("INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)");
        $histStmt->execute([$orderId, 'amount_edited', "Edited Financial Amounts (Total: Tk " . number_format($newTotal, 2) . ")", $changedBy]);
    } else {
        $error = 'Unable to update financial amounts.';
    }
}

$stmt = $db->prepare("
    SELECT o.*, u.email AS account_email
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: ' . BASE_URL . '/admin/orders');
    exit;
}

$itemsStmt = $db->prepare("
    SELECT oi.*, p.main_image
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();

// Fetch status history
$historyStmt = $db->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at DESC");
$historyStmt->execute([$orderId]);
$historyLogs = $historyStmt->fetchAll();

// Financial calculations
$paidAmount = ($order['status'] === 'delivered' || $order['payment_status'] === 'paid')
    ? (float)$order['total']
    : floatval($order['advance_payment'] ?? 0);
$dueAmount = ($order['status'] === 'delivered' || $order['payment_status'] === 'paid')
    ? 0
    : max(0, (float)$order['total'] - $paidAmount);

$pageTitle = 'Order Detail';
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php renderAdminSidebar('orders'); ?>

    <main class="admin-content">
        <?php renderAdminTopbar($pageTitle ?? 'Admin Panel'); ?>
        <div class="admin-detail-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 class="admin-page-title" style="margin-bottom: 0.25rem !important;">Order #<?= htmlspecialchars($order['order_number']) ?></h1>
                <div style="font-size: 0.875rem; color: var(--color-text-light);">
                    Placed on <?= htmlspecialchars(date('M j, Y \a\t H:i A', strtotime($order['created_at']))) ?>
                </div>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <a href="<?= BASE_URL ?>/invoice?order=<?= urlencode($order['order_number']) ?>" target="_blank" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Invoice
                </a>
                <a href="<?= BASE_URL ?>/admin/orders" class="btn btn-secondary">Back</a>
            </div>
        </div>

        <?php if ($message): ?><div class="alert alert-success" style="margin-bottom: 1.25rem; border-radius: 8px;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error" style="margin-bottom: 1.25rem; border-radius: 8px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div style="display: flex; flex-direction: column; gap: 1.5rem; min-width: 0; max-width: 100vw;">
            <style>
                .order-detail-layout { display: flex; flex-direction: column; gap: 1.5rem; }
                @media (min-width: 1024px) {
                    .order-detail-layout { display: grid; grid-template-columns: 1.8fr 1fr; gap: 1.5rem; align-items: start; }
                }
                .detail-section-title { font-size: 1.05rem; font-weight: 700; margin-bottom: 1rem; color: var(--color-text); padding-bottom: 0.65rem; border-bottom: 1px solid var(--color-border); }
                .info-label { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-text-light); margin-bottom: 0.25rem; font-weight: 700; }
                .info-value { font-size: 0.95rem; color: var(--color-text); line-height: 1.5; }
                .amount-edit-box { display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--color-border); }
                .overview-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; align-items: start; }
            </style>

            <!-- ── Unified Customer, Shipping & Payment Header Card (FIRST CARD) ── -->
            <div class="admin-card" style="margin-bottom: 0; background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                <h2 class="detail-section-title" style="margin-bottom: 1.15rem; display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Customer & Payment Overview
                </h2>
                <div class="overview-grid">
                    <!-- Customer Contact Info -->
                    <div>
                        <div class="info-label">Contact Person</div>
                        <div class="info-value" style="display: flex; align-items: center; gap: 0.65rem; margin-top: 0.35rem; margin-bottom: 0.85rem;">
                            <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--color-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem;">
                                <?= strtoupper(substr($order['shipping_first_name'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-weight: 700; font-size: 1.02rem; color: #1e293b;"><?= htmlspecialchars(trim(($order['shipping_first_name'] ?? '') . ' ' . ($order['shipping_last_name'] ?? ''))) ?></div>
                                <div style="font-size: 0.82rem; color: #64748b; font-weight: 500;">Customer #<?= intval($order['user_id'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="info-label">Contact Details</div>
                        <div class="info-value" style="font-size: 0.9rem;">
                            <div style="margin-bottom: 0.25rem;"><a href="mailto:<?= htmlspecialchars($order['shipping_email'] ?: ($order['account_email'] ?? '')) ?>" style="color: var(--color-primary); text-decoration: none; font-weight: 600;"><?= htmlspecialchars($order['shipping_email'] ?: ($order['account_email'] ?? '-')) ?></a></div>
                            <div><a href="tel:<?= htmlspecialchars($order['shipping_phone'] ?? '') ?>" style="color: #334155; text-decoration: none; font-weight: 700;"><?= htmlspecialchars($order['shipping_phone'] ?? '-') ?></a></div>
                        </div>
                    </div>

                    <!-- Shipping Address -->
                    <div>
                        <div class="info-label">Shipping Address</div>
                        <div class="info-value" style="background: #f8fafc; padding: 0.85rem 1rem; border-radius: 10px; border: 1px solid #e2e8f0; font-weight: 500; margin-top: 0.35rem; font-size: 0.92rem; line-height: 1.6;">
                            <div style="font-weight: 700; color: #0f766e; margin-bottom: 0.2rem;">📍 <?= htmlspecialchars($order['shipping_address'] ?? '-') ?></div>
                            <div style="color: #475569;"><?= htmlspecialchars($order['shipping_city'] ?? '-') ?>, <?= htmlspecialchars($order['shipping_postal_code'] ?? '-') ?></div>
                            <div style="color: #64748b; font-weight: 600; font-size: 0.85rem; margin-top: 0.2rem;"><?= htmlspecialchars($order['shipping_country'] ?? 'Bangladesh') ?></div>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <div>
                        <div class="info-label">Payment Information</div>
                        <div style="display: flex; flex-direction: column; gap: 0.65rem; margin-top: 0.35rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem;">
                                <span style="color: #64748b; font-weight: 600;">Method:</span>
                                <span style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars(paymentDisplayName((string)($order['payment_method'] ?? ''))) ?></span>
                            </div>
                            <?php if (!empty($order['payment_phone'])): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem;">
                                <span style="color: #64748b; font-weight: 600;">Mobile:</span>
                                <span style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($order['payment_phone']) ?></span>
                            </div>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem;">
                                <span style="color: #64748b; font-weight: 600;">Status:</span>
                                <span class="badge badge-<?= ($order['status'] === 'delivered' || $order['payment_status'] === 'paid') ? 'success' : 'warning' ?>" style="font-size: 0.76rem; padding: 0.25rem 0.6rem; font-weight: 700;">
                                    <?= htmlspecialchars(($order['status'] === 'delivered' || $order['payment_status'] === 'paid') ? 'PAID' : strtoupper($order['payment_status'])) ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem;">
                                <span style="color: #64748b; font-weight: 600;">Trx ID:</span>
                                <span style="font-family: monospace; font-size: 0.85rem; background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 700; color: #334155;">
                                    <?= htmlspecialchars(!empty($order['transaction_id']) ? $order['transaction_id'] : (!empty($order['payment_trx_id']) ? $order['payment_trx_id'] : 'N/A')) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Grid Layout for Items/Summary & Status Controls ── -->
            <div class="order-detail-layout" style="min-width: 0; max-width: 100%;">
                <!-- Left Column: Order Items & Summary -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem; min-width: 0;">
                    <!-- Order Items Table -->
                    <div class="admin-card" style="margin-bottom: 0; overflow: hidden; min-width: 0;">
                        <h2 class="detail-section-title">Order Items</h2>
                        <div class="admin-table-wrap" style="border: none; overflow-x: auto; width: 100%;">
                            <table class="admin-table" style="min-width: 580px; width: 100%;">
                                <thead>
                                <tr>
                                    <th style="padding-left: 0;">Product</th>
                                    <th>Variant</th>
                                    <th>Price</th>
                                    <th style="text-align: center;">Qty</th>
                                    <th style="text-align: right; padding-right: 0;">Total</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td style="padding-left: 0;">
                                            <div class="admin-item-info">
                                                <?php if (!empty($item['main_image'])): ?>
                                                    <?php $imgSrc = filter_var($item['main_image'], FILTER_VALIDATE_URL) ? $item['main_image'] : BASE_URL . '/' . ltrim($item['main_image'], '/'); ?>
                                                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="" class="admin-item-thumb" style="width: 48px; height: 48px; border-radius: 6px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div style="width: 48px; height: 48px; border-radius: 6px; background: #eee;"></div>
                                                <?php endif; ?>
                                                <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                                                    <span style="font-weight: 600; color: var(--color-text);"><?= htmlspecialchars($item['product_name']) ?></span>
                                                    <?php if (!empty($item['product_sku'])): ?>
                                                        <span style="font-size: 0.8rem; color: var(--color-text-light);">SKU: <?= htmlspecialchars($item['product_sku']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.85rem; color: var(--color-text-light); display: flex; flex-direction: column; gap: 0.2rem;">
                                                <?php if (!empty($item['size'])): ?><div>Size: <strong style="color: var(--color-text);"><?= htmlspecialchars($item['size']) ?></strong></div><?php endif; ?>
                                                <?php if (!empty($item['color'])): ?><div>Color: <strong style="color: var(--color-text);"><?= htmlspecialchars($item['color']) ?></strong></div><?php endif; ?>
                                                <?php if (!empty($item['variant'])): ?><div>Variant: <strong style="color: var(--color-text);"><?= htmlspecialchars($item['variant']) ?></strong></div><?php endif; ?>
                                                <?php if (empty($item['size']) && empty($item['color']) && empty($item['variant'])): ?>-<?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?= formatPrice($item['price']) ?></td>
                                        <td style="text-align: center; font-weight: 600;">x<?= intval($item['quantity']) ?></td>
                                        <td style="text-align: right; padding-right: 0; font-weight: 700;"><?= formatPrice($item['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Summary & Financial Edits -->
                    <div class="admin-card" style="margin-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h2 class="detail-section-title" style="margin-bottom: 0; border-bottom: none; padding-bottom: 0;">Order Summary</h2>
                            <button type="button" onclick="document.getElementById('edit-amounts-form').style.display = (document.getElementById('edit-amounts-form').style.display === 'block' ? 'none' : 'block');" class="btn btn-sm btn-outline" style="font-size: 0.82rem; font-weight: 600;">
                                ✏️ Edit Amounts
                            </button>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; color: var(--color-text-light);">
                                <span>Subtotal</span>
                                <span style="color: var(--color-text); font-weight: 600;"><?= formatPrice($order['subtotal']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; color: var(--color-text-light);">
                                <span>Discount</span>
                                <span style="color: var(--color-text); font-weight: 600;"><?= formatPrice($order['discount']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; color: var(--color-text-light);">
                                <span>Shipping</span>
                                <span style="color: var(--color-text); font-weight: 600;"><?= formatPrice($order['shipping_cost']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; color: var(--color-text-light);">
                                <span>Tax</span>
                                <span style="color: var(--color-text); font-weight: 600;"><?= formatPrice($order['tax']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: 800; color: var(--color-text); padding-top: 0.75rem; border-top: 1px dashed var(--color-border); margin-top: 0.25rem;">
                                <span>Total</span>
                                <span><?= formatPrice($order['total']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; color: #0f766e; font-weight: 700; font-size: 0.95rem;">
                                <span>Paid Amount</span>
                                <span><?= formatPrice($paidAmount) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; color: <?= $dueAmount > 0 ? '#ef4444' : '#10b981' ?>; font-weight: 800; font-size: 0.95rem;">
                                <span>Due Amount</span>
                                <span><?= formatPrice($dueAmount) ?></span>
                            </div>
                        </div>

                        <!-- Editable Amounts Form -->
                        <div id="edit-amounts-form" class="amount-edit-box">
                            <form method="POST" style="display: flex; flex-direction: column; gap: 0.85rem;">
                                <?= csrfField() ?>
                                <input type="hidden" name="update_amounts" value="1">
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px;">
                                    <div>
                                        <label class="info-label">Subtotal</label>
                                        <input type="number" step="0.01" min="0" name="subtotal" class="form-input" value="<?= floatval($order['subtotal']) ?>" style="font-size: 0.85rem;">
                                    </div>
                                    <div>
                                        <label class="info-label">Discount</label>
                                        <input type="number" step="0.01" min="0" name="discount" class="form-input" value="<?= floatval($order['discount']) ?>" style="font-size: 0.85rem;">
                                    </div>
                                    <div>
                                        <label class="info-label">Shipping</label>
                                        <input type="number" step="0.01" min="0" name="shipping_cost" class="form-input" value="<?= floatval($order['shipping_cost']) ?>" style="font-size: 0.85rem;">
                                    </div>
                                    <div>
                                        <label class="info-label">Tax</label>
                                        <input type="number" step="0.01" min="0" name="tax" class="form-input" value="<?= floatval($order['tax']) ?>" style="font-size: 0.85rem;">
                                    </div>
                                    <div>
                                        <label class="info-label">Advance Paid</label>
                                        <input type="number" step="0.01" min="0" name="advance_payment" class="form-input" value="<?= floatval($order['advance_payment']) ?>" style="font-size: 0.85rem;">
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 4px;">
                                    <button type="button" onclick="document.getElementById('edit-amounts-form').style.display='none';" class="btn btn-sm btn-secondary">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-primary">Save Amounts</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php $orderNotes = !empty($order['order_notes']) ? $order['order_notes'] : (!empty($order['notes']) ? $order['notes'] : ''); ?>
                    <?php if ($orderNotes): ?>
                    <!-- Order Notes -->
                    <div class="admin-card" style="margin-bottom: 0;">
                        <h2 class="detail-section-title">Order Notes</h2>
                        <div style="font-size: 0.95rem; line-height: 1.6; color: var(--color-text); background: var(--color-bg-secondary); padding: 1rem; border-radius: 8px; border: 1px solid var(--color-border);">
                            <?= nl2br(htmlspecialchars($orderNotes)) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column: Status & Audit History -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem; min-width: 0;">
                    <!-- Status Update Card -->
                    <div class="admin-card" style="margin-bottom: 0; background: #f8fafc; border-color: #e2e8f0;">
                        <h2 class="detail-section-title" style="border-bottom-color: rgba(0,0,0,0.05);">Order Status</h2>
                        <form method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
                            <?= csrfField() ?>
                            <input type="hidden" name="update_status" value="1">
                            <div>
                                <label class="info-label">Select Status</label>
                                <select name="status" class="form-select" style="width: 100%; border-radius: 6px; padding: 0.65rem; font-weight: 600; font-size: 0.9rem;">
                                    <?php foreach ($statusMap as $sKey => $sVal): ?>
                                        <option value="<?= $sKey ?>" <?= $order['status'] === $sKey ? 'selected' : '' ?>><?= htmlspecialchars($sVal['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="info-label">Status Change Note (Optional)</label>
                                <textarea name="status_note" class="form-textarea" rows="2" placeholder="e.g. Phone unreachable on 1st call attempt / Customer requested delay" style="font-size: 0.85rem; padding: 0.5rem;"></textarea>
                            </div>
                            <button class="btn btn-primary" type="submit" style="width: 100%; padding: 0.75rem; border-radius: 6px;">Update Status</button>
                        </form>
                    </div>

                    <!-- Status History & Audit Log Card -->
                    <div class="admin-card" style="margin-bottom: 0;">
                        <h2 class="detail-section-title">Status Change History</h2>
                        <?php if (empty($historyLogs)): ?>
                            <div style="font-size: 0.85rem; color: #94a3b8; font-style: italic;">No status logs recorded yet.</div>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 0.85rem; max-height: 280px; overflow-y: auto; padding-right: 4px;">
                                <?php foreach ($historyLogs as $log): ?>
                                    <?php
                                    $logKey   = $log['status'] ?? 'pending';
                                    $logBadge = $statusMap[$logKey]['badge'] ?? 'secondary';
                                    $logLabel = $statusMap[$logKey]['label'] ?? ucfirst($logKey);
                                    ?>
                                    <div style="padding: 0.65rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.83rem;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <span class="badge badge-<?= $logBadge ?>" style="font-size: 0.72rem; padding: 2px 7px;"><?= htmlspecialchars($logLabel) ?></span>
                                            <span style="color: #94a3b8; font-size: 0.75rem;"><?= date('M j, H:i A', strtotime($log['created_at'])) ?></span>
                                        </div>
                                        <div style="color: #64748b; font-size: 0.78rem;">By: <strong style="color: #334155;"><?= htmlspecialchars($log['changed_by'] ?: 'System') ?></strong></div>
                                        <?php if (!empty($log['note'])): ?>
                                            <div style="margin-top: 4px; padding: 4px 8px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155; font-size: 0.8rem;">
                                                <?= nl2br(htmlspecialchars($log['note'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
    <script src="<?= BASE_URL ?>/admin/js/admin.js"></script>
</body>
</html>
