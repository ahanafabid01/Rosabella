<?php
/**
 * KARTLY - Invoice / PDF Download
 * Accessible via:
 *   /invoice?order=KAR-XXXX          (logged-in user, owns the order)
 *   /invoice?order=KAR-XXXX&email=x  (guest, must supply order email)
 */

// Use the shared config — provides getDB(), isLoggedIn(), getCurrentUser(),
// formatPrice(), sanitize(), BASE_URL, SITE_URL, SITE_NAME, session_start(), etc.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/payment_gateway.php'; // provides paymentDisplayName()

// Fetch branding from DB
$siteLogo = getSetting('site_logo') ?: '';
$siteName = getSetting('site_name') ?: SITE_NAME;

$orderNumber = isset($_GET['order']) ? trim($_GET['order']) : '';
$emailParam  = isset($_GET['email']) ? trim($_GET['email']) : '';

$order      = null;
$orderItems = [];
$coupon     = null;
$error      = '';

if ($orderNumber) {
    $db = getDB();

    // Logged-in user: verify ownership via user_id
    if (isLoggedIn()) {
        $user = getCurrentUser();
        if ($user) {
            $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
            $stmt->execute([$orderNumber, $user['id']]);
            $order = $stmt->fetch();
        }
    }

    // Guest fallback — email must match the order's shipping email
    if (!$order && $emailParam) {
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? AND shipping_email = ?");
        $stmt->execute([$orderNumber, $emailParam]);
        $order = $stmt->fetch();
    }

    if ($order) {
        // Fetch order items joined with product image
        $stmt = $db->prepare("
            SELECT oi.*, p.main_image
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order['id']]);
        $orderItems = $stmt->fetchAll();

        // Fetch coupon details if applied
        if (!empty($order['coupon_id'])) {
            $stmt = $db->prepare("SELECT * FROM coupons WHERE id = ?");
            $stmt->execute([$order['coupon_id']]);
            $coupon = $stmt->fetch();
        }
    } else {
        $error = 'Order not found or access denied. Please check your order number.';
    }
} else {
    $error = 'No order number provided.';
}

// ── Calculate paid / due ──────────────────────────────────────────────────────
$paidAmount = 0;
$dueAmount  = 0;
if ($order) {
    $dueAmount = (float)$order['total'];
    if ($order['payment_method'] === 'cod') {
        $paidAmount = 200;                              // COD advance
        $dueAmount  = max(0, $order['total'] - 200);
    } elseif ($order['payment_status'] === 'paid') {
        $paidAmount = (float)$order['total'];
        $dueAmount  = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= $order ? '#' . htmlspecialchars($order['order_number']) : 'Error' ?> – <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Force print backgrounds & colors exactly as on screen */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            font-size: 14px;
            color: #1a1a2e;
            background: #f0f2f5;
            min-height: 100vh;
        }

        /* ── Screen toolbar ── */
        .invoice-toolbar {
            background: #1a1a2e;
            color: #fff;
            padding: 0.85rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .invoice-toolbar .brand {
            font-size: 1.05rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .toolbar-actions { display: flex; gap: 0.75rem; align-items: center; }
        .btn-inv {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.55rem 1.2rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: opacity 0.2s, transform 0.15s;
            text-decoration: none;
            font-family: inherit;
        }
        .btn-inv:hover { opacity: 0.85; transform: translateY(-1px); }
        .btn-pdf  { background: linear-gradient(135deg, #c0392b, #e74c3c); color: #fff; }
        .btn-back { background: rgba(255,255,255,0.13); color: #fff; }

        /* ── Invoice paper ── */
        .invoice-paper {
            max-width: 820px;
            margin: 2rem auto 3rem;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 40px rgba(0,0,0,0.12);
        }

        /* ── Light adaptive header ── */
        .inv-header {
            background: #ffffff;
            color: #1a1a2e;
            padding: 1.75rem 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            border-bottom: 3px solid #e74c3c;
        }
        .inv-brand         { font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; }
        .inv-brand span    { color: #e74c3c; }
        .inv-meta          { text-align: right; }
        .inv-meta-label    { font-size: 0.75rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: #9ca3af; margin-bottom: 0.35rem; }
        .inv-order-num     { font-size: 1.4rem; font-weight: 800; letter-spacing: -0.01em; color: #1a1a2e; }
        .inv-date          { font-size: 0.8rem; color: #6b7280; margin-top: 0.3rem; }
        .inv-status-badge  {
            display: inline-block;
            margin-top: 0.5rem;
            padding: 0.28rem 0.85rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }
        .status-pending    { background: #fef9c3; color: #a16207;  border: 1px solid #fde047; }
        .status-processing { background: #dbeafe; color: #1d4ed8;  border: 1px solid #93c5fd; }
        .status-shipped    { background: #ede9fe; color: #7e22ce;  border: 1px solid #c4b5fd; }
        .status-delivered  { background: #dcfce7; color: #15803d;  border: 1px solid #86efac; }
        .status-cancelled  { background: #fee2e2; color: #b91c1c;  border: 1px solid #fca5a5; }
        .status-refunded   { background: #f3f4f6; color: #4b5563;  border: 1px solid #d1d5db; }

        /* ── Body ── */
        .inv-body { padding: 2rem 2.5rem; }

        .inv-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 580px) {
            .inv-two-col { grid-template-columns: 1fr; }
            .inv-header  { padding: 1.25rem 1.25rem; }
            .inv-body    { padding: 1.25rem; }
        }

        .inv-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #9ca3af;
            margin-bottom: 0.55rem;
            padding-bottom: 0.4rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .inv-addr-text {
            font-size: 0.9rem;
            line-height: 1.75;
            color: #374151;
        }
        .inv-addr-text strong { color: #1a1a2e; font-weight: 700; }
        .inv-phone {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            margin-top: 0.4rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: #e74c3c;
        }
        .inv-pay-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
            padding: 0.3rem 0.7rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* ── Products table ── */
        .inv-table-wrap {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 1.75rem;
            overflow-x: auto;
        }
        .inv-table { width: 100%; border-collapse: collapse; min-width: 500px; }
        .inv-table thead tr { background: #f8f9fa; }
        .inv-table th {
            padding: 0.7rem 1rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #6b7280;
            text-align: left;
            white-space: nowrap;
        }
        .inv-table th:last-child, .inv-table td:last-child { text-align: right; }
        .inv-table td {
            padding: 0.85rem 1rem;
            border-top: 1px solid #f3f4f6;
            font-size: 0.875rem;
            vertical-align: middle;
            color: #374151;
        }
        .inv-table tbody tr:hover { background: #fafbfc; }
        .inv-prod-img {
            width: 48px; height: 48px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            display: block;
        }
        .inv-prod-name { font-weight: 600; color: #1a1a2e; white-space: normal; max-width: 200px; }
        .inv-prod-meta { font-size: 0.76rem; color: #9ca3af; margin-top: 0.15rem; }

        /* ── Totals ── */
        .inv-totals-wrap { display: flex; justify-content: flex-end; margin-bottom: 2rem; }
        .inv-totals {
            min-width: 270px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }
        .inv-tot-row {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 1.1rem;
            font-size: 0.875rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .inv-tot-row:last-child { border-bottom: none; }
        .inv-tot-row.grand { background: #1a1a2e; color: #fff; font-weight: 700; font-size: 0.95rem; }
        .inv-tot-row.paid  { color: #16a34a; font-weight: 600; }
        .inv-tot-row.due   { color: #dc2626; font-weight: 600; }

        /* ── Footer ── */
        .inv-footer {
            background: #f8f9fa;
            border-top: 1px solid #e5e7eb;
            padding: 1.1rem 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .inv-footer-note  { font-size: 0.78rem; color: #9ca3af; }
        .inv-footer-brand { font-size: 0.8rem; color: #6b7280; font-weight: 700; }
        .inv-footer-brand span { color: #e74c3c; }

        /* ── Error page ── */
        .inv-error-page {
            max-width: 460px;
            margin: 5rem auto;
            text-align: center;
            padding: 2.5rem 2rem;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        .inv-error-page h2 { color: #dc2626; margin-bottom: 0.75rem; font-size: 1.3rem; }
        .inv-error-page p  { color: #6b7280; font-size: 0.9rem; line-height: 1.6; }

        /* ── PRINT ── */
        @media print {
            @page { size: A4; margin: 12mm 14mm; }
            body { background: #fff !important; font-size: 12px !important; }
            .invoice-toolbar { display: none !important; }
            .invoice-paper   { max-width: 100%; margin: 0; box-shadow: none; border-radius: 0; }
            .inv-header { padding: 1.25rem 1.5rem; }
            .inv-body   { padding: 1.25rem 1.5rem; }
            .inv-footer { padding: 0.75rem 1.5rem; }
            .inv-table-wrap  { page-break-inside: avoid; }
            .inv-totals-wrap { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<?php if ($order): ?>

<!-- Toolbar (hidden on print) -->
<div class="invoice-toolbar">
    <div class="brand">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="2.2">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
            <line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
        </svg>
        <?= htmlspecialchars(SITE_NAME) ?> &mdash; Invoice
    </div>
    <div class="toolbar-actions">
        <a href="javascript:history.back()" class="btn-inv btn-back">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
            </svg>
            Back
        </a>
        <button class="btn-inv btn-pdf" onclick="window.print()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <polyline points="8 17 12 21 16 17"/>
                <line x1="12" y1="12" x2="12" y2="21"/>
                <path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/>
            </svg>
            Download PDF
        </button>
    </div>
</div>

<!-- Invoice Paper -->
<div class="invoice-paper">

    <!-- Header -->
    <div class="inv-header">
        <div>
            <div class="inv-brand">
            <?php if ($siteLogo): ?>
                <img src="<?= htmlspecialchars(SITE_URL . '/' . ltrim($siteLogo, '/')) ?>"
                     alt="<?= htmlspecialchars($siteName) ?>"
                     style="height:44px; width:auto; max-width:180px; object-fit:contain; display:block;">
            <?php else: ?>
                <span style="font-size:1.7rem; font-weight:800; letter-spacing:-0.02em; color:#1a1a2e;"><?= htmlspecialchars($siteName) ?></span>
            <?php endif; ?>
        </div>
        </div>
        <div class="inv-meta">
            <div class="inv-meta-label">Invoice</div>
            <div class="inv-order-num">#<?= htmlspecialchars($order['order_number']) ?></div>
            <div class="inv-date">Placed on <?= date('d M Y, g:i A', strtotime($order['created_at'])) ?></div>
        </div>
    </div>

    <!-- Body -->
    <div class="inv-body">

        <!-- Ship To / Payment Info -->
        <div class="inv-two-col">
            <div>
                <div class="inv-label">Ship To</div>
                <div class="inv-addr-text">
                    <strong><?= htmlspecialchars(trim($order['shipping_first_name'] . ' ' . $order['shipping_last_name'])) ?></strong><br>
                    <?= nl2br(htmlspecialchars((string)$order['shipping_address'])) ?><br>
                    <?php if (!empty($order['shipping_city'])): ?>
                        <?= htmlspecialchars($order['shipping_city']) ?><br>
                    <?php endif; ?>
                    <?= htmlspecialchars((string)$order['shipping_country']) ?>
                </div>
                <?php if (!empty($order['shipping_phone'])): ?>
                <div class="inv-phone">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.15 11.83a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.06 1h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 21 16z"/>
                    </svg>
                    <?= htmlspecialchars($order['shipping_phone']) ?>
                </div>
                <?php endif; ?>
            </div>

            <div>
                <div class="inv-label">Payment Details</div>
                <div class="inv-addr-text">
                    <strong>Method</strong><br>
                    <span class="inv-pay-badge">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                        <?= htmlspecialchars(paymentDisplayName((string)$order['payment_method'])) ?>
                    </span>
                    <br><br>
                    <?php if (!empty($order['shipping_email'])): ?>
                    <strong>Email</strong><br>
                    <?= htmlspecialchars($order['shipping_email']) ?>
                    <?php endif; ?>
                    <?php if (!empty($order['transaction_id']) || !empty($order['payment_trx_id'])): ?>
                    <br><br>
                    <strong>Transaction ID</strong><br>
                    <span style="font-size:0.82rem; color:#6b7280;"><?= htmlspecialchars((string)($order['payment_trx_id'] ?: $order['transaction_id'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="inv-label" style="margin-bottom:0.75rem;">
            Order Items <span style="color:#6b7280; font-weight:500;">(<?= count($orderItems) ?> item<?= count($orderItems) !== 1 ? 's' : '' ?>)</span>
        </div>
        <div class="inv-table-wrap">
            <table class="inv-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product</th>
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                    <tr>
                        <td>
                            <?php
                            $imgSrc = !empty($item['main_image'])
                                ? SITE_URL . '/' . ltrim($item['main_image'], '/')
                                : 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=80&q=70';
                            ?>
                            <img class="inv-prod-img"
                                 src="<?= htmlspecialchars($imgSrc) ?>"
                                 alt="<?= htmlspecialchars($item['product_name']) ?>">
                        </td>
                        <td>
                            <div class="inv-prod-name"><?= htmlspecialchars($item['product_name']) ?></div>
                            <?php if (!empty($item['color'])): ?>
                                <div class="inv-prod-meta">Color: <?= htmlspecialchars($item['color']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($item['size'])): ?>
                                <div class="inv-prod-meta">Size: <?= htmlspecialchars($item['size']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($item['variant'])): ?>
                                <div class="inv-prod-meta">Variant: <?= htmlspecialchars($item['variant']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($item['product_sku'])): ?>
                                <div class="inv-prod-meta">SKU: <?= htmlspecialchars($item['product_sku']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center; font-weight:600;"><?= intval($item['quantity']) ?></td>
                        <td style="text-align:right;"><?= formatPrice($item['price']) ?></td>
                        <td style="text-align:right; font-weight:700;"><?= formatPrice($item['price'] * $item['quantity']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="inv-totals-wrap">
            <div class="inv-totals">
                <div class="inv-tot-row">
                    <span>Sub-Total</span>
                    <span><?= formatPrice($order['subtotal']) ?></span>
                </div>
                <div class="inv-tot-row">
                    <span>Delivery</span>
                    <span><?= formatPrice($order['shipping_cost']) ?></span>
                </div>
                <?php if (!empty($order['tax']) && $order['tax'] > 0): ?>
                <div class="inv-tot-row">
                    <span>Tax</span>
                    <span><?= formatPrice($order['tax']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['discount']) && $order['discount'] > 0): ?>
                <div class="inv-tot-row" style="color:#16a34a;">
                    <span>
                        Discount
                        <?php if ($coupon): ?>
                            <small style="background:#dcfce7; border-radius:4px; padding:0.1rem 0.4rem; font-size:0.72rem; color:#15803d; margin-left:0.3rem;">
                                <?= htmlspecialchars($coupon['code']) ?>
                            </small>
                        <?php endif; ?>
                    </span>
                    <span>-<?= formatPrice($order['discount']) ?></span>
                </div>
                <?php endif; ?>
                <div class="inv-tot-row grand">
                    <span>Total</span>
                    <span><?= formatPrice($order['total']) ?></span>
                </div>
                <div class="inv-tot-row paid">
                    <span>Paid</span>
                    <span><?= formatPrice($paidAmount) ?></span>
                </div>
                <div class="inv-tot-row due">
                    <span>Due</span>
                    <span><?= formatPrice($dueAmount) ?></span>
                </div>
            </div>
        </div>

        <!-- Order Notes -->
        <?php $notes = !empty($order['order_notes']) ? $order['order_notes'] : (!empty($order['notes']) ? $order['notes'] : ''); ?>
        <?php if ($notes): ?>
        <div style="background:#f8f9fa; border-radius:8px; padding:1rem 1.25rem;">
            <div class="inv-label" style="margin-bottom:0.4rem;">Order Notes</div>
            <p style="font-size:0.875rem; color:#374151; line-height:1.65;"><?= nl2br(htmlspecialchars($notes)) ?></p>
        </div>
        <?php endif; ?>

    </div><!-- /inv-body -->

    <!-- Footer -->
    <div class="inv-footer">
        <div class="inv-footer-note">Thank you for shopping with us. This is a computer-generated invoice.</div>
    </div>

</div><!-- /invoice-paper -->

<script>
    // Auto-trigger print if ?print=1
    if (new URLSearchParams(window.location.search).get('print') === '1') {
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 500);
        });
    }
</script>

<?php else: ?>

<!-- Error state -->
<div style="padding: 1rem; text-align:center;">
    <div class="inv-error-page">
        <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="1.5" style="margin: 0 auto 1rem; display:block;">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <h2>Invoice Not Found</h2>
        <p style="margin-top: 0.6rem;"><?= htmlspecialchars($error) ?></p>
        <a href="<?= BASE_URL ?>/track-order"
           style="display:inline-block; margin-top:1.75rem; padding:0.65rem 1.5rem; background:#1a1a2e; color:#fff; border-radius:8px; font-weight:600; text-decoration:none; font-size:0.875rem;">
            &larr; Track Your Order
        </a>
    </div>
</div>

<?php endif; ?>

</body>
</html>
