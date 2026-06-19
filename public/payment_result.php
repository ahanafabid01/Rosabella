<?php
/**
 * KARTLY - Payment Result Page
 */
$pageTitle = 'Payment Status';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/payment_gateway.php';

$db = getDB();
$status = strtolower(trim((string)($_GET['status'] ?? 'failed')));
$orderNumber = trim((string)($_GET['order'] ?? ''));
$message = trim((string)($_GET['message'] ?? ''));

$allowedStatuses = ['success', 'failed', 'cancelled'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'failed';
}

$order = null;
if ($orderNumber !== '' && isLoggedIn()) {
    if (isAdmin()) {
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
        $stmt->execute([$orderNumber]);
    } else {
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$orderNumber, (int)$_SESSION['user_id']]);
    }
    $order = $stmt->fetch() ?: null;
}

$title = 'Payment Failed';
$badgeClass = 'danger';
if ($status === 'success') {
    $title = 'Payment Successful';
    $badgeClass = 'success';
} elseif ($status === 'cancelled') {
    $title = 'Payment Cancelled';
    $badgeClass = 'warning';
}

if ($message === '') {
    if ($status === 'success') {
        $message = 'Your payment has been confirmed.';
    } elseif ($status === 'cancelled') {
        $message = 'The payment was cancelled before completion.';
    } else {
        $message = 'We could not complete your payment. Please try again.';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="section section-bg">
    <div class="container">
        <nav style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem;">
            <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light);">Home</a>
            <span> / </span>
            <span style="color: var(--color-text);">Payment Status</span>
        </nav>
        <h1 style="font-size: 2rem; font-weight: 700;">Payment Status</h1>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width: 760px;">
        <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                <h2 style="font-size: 1.35rem; margin: 0;"><?= htmlspecialchars($title) ?></h2>
                <span class="badge badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
            <p style="margin-top: 1rem; color: var(--color-text-light);"><?= htmlspecialchars($message) ?></p>

            <?php if ($orderNumber !== ''): ?>
            <div style="margin-top: 1.25rem; padding: 0.9rem 1rem; border: 1px dashed var(--color-border); border-radius: var(--radius-md);">
                <strong>Order Number:</strong> <?= htmlspecialchars($orderNumber) ?>
            </div>
            <?php endif; ?>

            <?php if ($order): ?>
            <div style="margin-top: 1.25rem; border-top: 1px solid var(--color-border); padding-top: 1rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 0.75rem 1rem;">
                <div>
                    <div style="font-size: 0.75rem; color: var(--color-text-light); text-transform: uppercase;">Payment Method</div>
                    <div style="font-weight: 600;"><?= htmlspecialchars(paymentDisplayName((string)$order['payment_method'])) ?></div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--color-text-light); text-transform: uppercase;">Payment Status</div>
                    <div style="font-weight: 600;"><?= htmlspecialchars(ucfirst((string)$order['payment_status'])) ?></div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--color-text-light); text-transform: uppercase;">Order Status</div>
                    <div style="font-weight: 600;"><?= htmlspecialchars(ucfirst((string)$order['status'])) ?></div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--color-text-light); text-transform: uppercase;">Total</div>
                    <div style="font-weight: 600;"><?= htmlspecialchars(formatPrice((float)$order['total'])) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <?php if (isLoggedIn()): ?>
                <a href="<?= BASE_URL ?>/account" class="btn btn-primary">My Orders</a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/checkout" class="btn btn-outline">Back to Checkout</a>
                <a href="<?= BASE_URL ?>/" class="btn btn-secondary">Continue Shopping</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



