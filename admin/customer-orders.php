<?php
/**
 * Rosabella – Customer Profile & Order History Intelligence
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../includes/payment_gateway.php';

// Auth Guard
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$db = getDB();
$message = '';
$error = '';

$customerId = intval($_GET['id'] ?? $_GET['customer_id'] ?? 0);

if ($customerId <= 0) {
    header('Location: ' . BASE_URL . '/admin/customers');
    exit;
}

// ── Fetch Customer Information ───────────────────────────────────────────────
$custStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$custStmt->execute([$customerId]);
$customer = $custStmt->fetch();

if (!$customer) {
    header('Location: ' . BASE_URL . '/admin/customers');
    exit;
}

$customerFullName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
if (empty($customerFullName)) {
    $customerFullName = 'Customer #' . $customer['id'];
}

function getCustInitials(string $fn, string $ln): string {
    $i = mb_substr(trim($fn), 0, 1, 'UTF-8');
    if (!empty($ln)) {
        $i .= mb_substr(trim($ln), 0, 1, 'UTF-8');
    }
    return strtoupper($i ?: 'C');
}

$initials = getCustInitials($customer['first_name'] ?? '', $customer['last_name'] ?? '');

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

// ── Fetch Customer Lifetime Summary Metrics ───────────────────────────────────
$custPhone = trim($customer['phone'] ?? '');

$metricParams = [$customerId];
$phoneClause = '';
if ($custPhone !== '') {
    $phoneClause = "OR (shipping_phone = ?)";
    $metricParams[] = $custPhone;
}

$metricsQuery = "
    SELECT 
        COUNT(id) as total_orders,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) as total_spent,
        COALESCE(SUM(total), 0) as gross_spent,
        COALESCE(SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END), 0) as delivered_orders,
        COALESCE(SUM(CASE WHEN status IN ('pending', 'confirmed', 'processing', 'shipped') THEN 1 ELSE 0 END), 0) as active_orders,
        COALESCE(SUM(CASE WHEN status IN ('cancelled', 'returned', 'fake') THEN 1 ELSE 0 END), 0) as returned_cancelled,
        MAX(created_at) as latest_order_date
    FROM orders
    WHERE user_id = ? $phoneClause
";
$metricStmt = $db->prepare($metricsQuery);
$metricStmt->execute($metricParams);
$metrics = $metricStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_orders' => 0,
    'total_spent' => 0,
    'gross_spent' => 0,
    'delivered_orders' => 0,
    'active_orders' => 0,
    'returned_cancelled' => 0,
    'latest_order_date' => null
];

$totalOrdersCount = (int)$metrics['total_orders'];
$totalSpentAmount = (float)$metrics['total_spent'];
$grossSpentAmount = (float)$metrics['gross_spent'];
$avgOrderValue = $totalOrdersCount > 0 ? ($grossSpentAmount / $totalOrdersCount) : 0;

// ── Filter Orders ─────────────────────────────────────────────────────────────
$statusFilter = sanitize($_GET['status'] ?? '');
$search       = sanitize($_GET['search'] ?? '');

$whereParts = ["(o.user_id = ?" . ($custPhone !== '' ? " OR o.shipping_phone = ?" : "") . ")"];
$queryParams = [$customerId];
if ($custPhone !== '') {
    $queryParams[] = $custPhone;
}

if ($statusFilter !== '') {
    $whereParts[] = "o.status = ?";
    $queryParams[] = $statusFilter;
}

if ($search !== '') {
    $whereParts[] = "(o.order_number LIKE ? OR o.shipping_first_name LIKE ? OR o.shipping_last_name LIKE ? OR o.shipping_phone LIKE ?)";
    $sLike = "%$search%";
    $queryParams[] = $sLike;
    $queryParams[] = $sLike;
    $queryParams[] = $sLike;
    $queryParams[] = $sLike;
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);

// Pagination
$perPage = max(1, min(50, intval($_GET['per_page'] ?? 15)));
$page    = max(1, intval($_GET['page'] ?? 1));

$countStmt = $db->prepare("SELECT COUNT(*) FROM orders o $whereSql");
$countStmt->execute($queryParams);
$totalFilteredOrders = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalFilteredOrders / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Fetch Orders
$ordersStmt = $db->prepare("
    SELECT o.* 
    FROM orders o 
    $whereSql 
    ORDER BY o.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$ordersStmt->execute($queryParams);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

// Pre-fetch items for each order
$orderIds = array_column($orders, 'id');
$orderItemsMap = [];
if (!empty($orderIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsStmt = $db->prepare("
        SELECT oi.*, p.main_image, p.name as product_title
        FROM order_items oi
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id IN ($inPlaceholders)
        ORDER BY oi.id ASC
    ");
    $itemsStmt->execute($orderIds);
    $rawItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rawItems as $ri) {
        $orderItemsMap[$ri['order_id']][] = $ri;
    }
}

$pageTitle = 'Order History – ' . $customerFullName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $siteFavicon = getSetting('site_favicon'); if ($siteFavicon): ?>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . '/' . htmlspecialchars($siteFavicon) ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> – Rosabella Admin</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .cust-profile-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .cust-profile-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .cust-avatar-large {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.35rem;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(15, 118, 110, 0.2);
        }
        .cust-stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 1.25rem;
            padding-top: 1.25rem;
            border-top: 1px solid #f1f5f9;
        }
        .cust-stat-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.9rem 1.1rem;
        }
        .cust-stat-val {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }
        .cust-stat-label {
            font-size: 0.76rem;
            color: #64748b;
            font-weight: 500;
            margin-top: 2px;
        }
        .order-item-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 8px;
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 6px;
            font-size: 0.78rem;
            color: #334155;
            margin-bottom: 4px;
        }
        .order-item-img {
            width: 26px;
            height: 26px;
            border-radius: 4px;
            object-fit: cover;
            background: #e2e8f0;
            flex-shrink: 0;
        }
        @media (max-width: 900px) {
            .cust-stat-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 600px) {
            .cust-stat-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <?php renderAdminSidebar('customers'); ?>

        <!-- Main Content -->
        <main class="admin-content">
            <?php renderAdminTopbar($pageTitle); ?>

            <!-- Breadcrumb Navigation -->
            <div style="margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                <div style="font-size: 0.82rem; color: #64748b; display: flex; align-items: center; gap: 6px;">
                    <a href="<?= BASE_URL ?>/admin/customers" style="color: #0f766e; text-decoration: none; font-weight: 500;">Customers</a>
                    <span>/</span>
                    <span style="color: #0f172a; font-weight: 600;"><?= htmlspecialchars($customerFullName) ?></span>
                    <span>/</span>
                    <span>Order History</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <a href="<?= BASE_URL ?>/admin/customers" class="btn btn-secondary" style="height: 34px; font-size: 0.80rem; display: inline-flex; align-items: center; gap: 5px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        <span>Back to Customers</span>
                    </a>
                    <a href="<?= BASE_URL ?>/admin/order-create?phone=<?= urlencode($customer['phone'] ?? '') ?>&name=<?= urlencode($customerFullName) ?>&city=<?= urlencode($customer['city'] ?? '') ?>" class="btn btn-primary" style="height: 34px; font-size: 0.80rem; display: inline-flex; align-items: center; gap: 5px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span>+ Create Order for Customer</span>
                    </a>
                </div>
            </div>

            <!-- Customer Profile & Intelligence Header -->
            <div class="cust-profile-card">
                <div class="cust-profile-header">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div class="cust-avatar-large"><?= $initials ?></div>
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <h2 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #0f172a;"><?= htmlspecialchars($customerFullName) ?></h2>
                                <span class="badge <?= $customer['status'] === 'active' ? 'badge-success' : ($customer['status'] === 'banned' ? 'badge-danger' : 'badge-warning') ?>" style="text-transform: capitalize; font-size: 0.72rem; padding: 2px 7px;">
                                    <?= htmlspecialchars($customer['status'] ?? 'active') ?>
                                </span>
                                <span style="font-size: 0.74rem; color: #94a3b8; font-weight: 500;">ID #<?= $customer['id'] ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 14px; margin-top: 4px; flex-wrap: wrap; font-size: 0.82rem; color: #475569;">
                                <div style="display: inline-flex; align-items: center; gap: 5px;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                    <a href="mailto:<?= htmlspecialchars($customer['email']) ?>" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($customer['email']) ?></a>
                                </div>
                                <?php if (!empty($customer['phone'])): ?>
                                <div style="display: inline-flex; align-items: center; gap: 5px;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                    <a href="tel:<?= htmlspecialchars($customer['phone']) ?>" style="color: inherit; text-decoration: none; font-weight: 500;"><?= htmlspecialchars($customer['phone']) ?></a>
                                </div>
                                <?php endif; ?>
                                <div style="display: inline-flex; align-items: center; gap: 5px;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    <span><?= htmlspecialchars(implode(', ', array_filter([$customer['address'] ?? '', $customer['upazila'] ?? '', $customer['city'] ?? '']))) ?: 'Bangladesh' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div style="font-size: 0.76rem; color: #64748b; text-align: right;">
                        <div>Customer Since: <strong><?= date('M j, Y', strtotime($customer['created_at'])) ?></strong></div>
                        <?php if ($metrics['latest_order_date']): ?>
                        <div style="margin-top: 2px;">Last Order: <strong><?= date('M j, Y h:i A', strtotime($metrics['latest_order_date'])) ?></strong></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 4 Performance Metric Boxes -->
                <div class="cust-stat-grid">
                    <div class="cust-stat-box">
                        <div class="cust-stat-val"><?= number_format($totalOrdersCount) ?></div>
                        <div class="cust-stat-label">Total Orders Placed</div>
                    </div>
                    <div class="cust-stat-box">
                        <div class="cust-stat-val" style="color: #0f766e;">Tk <?= number_format($totalSpentAmount) ?></div>
                        <div class="cust-stat-label">Paid Lifetime Spend</div>
                    </div>
                    <div class="cust-stat-box">
                        <div class="cust-stat-val">Tk <?= number_format($avgOrderValue) ?></div>
                        <div class="cust-stat-label">Average Order Value</div>
                    </div>
                    <div class="cust-stat-box">
                        <div class="cust-stat-val" style="color: #059669;"><?= number_format((int)$metrics['delivered_orders']) ?></div>
                        <div class="cust-stat-label">Delivered Orders (<?= $totalOrdersCount > 0 ? round(($metrics['delivered_orders'] / $totalOrdersCount) * 100) : 0 ?>% Success)</div>
                    </div>
                </div>
            </div>

            <!-- ── Orders Filter & Search Toolbar ── -->
            <div class="admin-card" style="margin-bottom: 1.25rem; padding: 1rem 1.25rem;">
                <form method="GET" action="<?= BASE_URL ?>/admin/customer-orders" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                    <input type="hidden" name="id" value="<?= $customerId ?>">

                    <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1; min-width: 260px; flex-wrap: wrap;">
                        <div style="position: relative; flex: 1; min-width: 200px;">
                            <input type="text" name="search" class="form-input" placeholder="Search order number or recipient..." value="<?= htmlspecialchars($search) ?>" style="padding-left: 2.2rem; height: 36px; font-size: 0.82rem; width: 100%; border-radius: 7px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </div>

                        <select name="status" class="form-select" style="height: 36px; font-size: 0.82rem; width: auto; min-width: 140px; border-radius: 7px;" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <?php foreach ($statusMap as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 6px;">
                        <button type="submit" class="btn btn-primary" style="height: 36px; font-size: 0.82rem; padding: 0 1rem; border-radius: 7px;">Filter</button>
                        <?php if ($search || $statusFilter): ?>
                            <a href="<?= BASE_URL ?>/admin/customer-orders?id=<?= $customerId ?>" class="btn btn-secondary" style="height: 36px; font-size: 0.82rem; padding: 0 0.75rem; border-radius: 7px; display: inline-flex; align-items: center;">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- ── Orders Table ── -->
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width: 120px;">Order #</th>
                            <th style="width: 140px;">Date &amp; Time</th>
                            <th style="min-width: 240px;">Ordered Items</th>
                            <th>Shipping Destination</th>
                            <th style="text-align: center;">Payment</th>
                            <th style="text-align: center;">Status</th>
                            <th style="text-align: right; width: 110px;">Total</th>
                            <th style="text-align: right; width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #94a3b8; padding: 3.5rem 1rem;">
                                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.8" style="display: block; margin: 0 auto 10px;"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                    <div style="font-weight: 600; color: #64748b; font-size: 0.95rem;">No orders found for this customer.</div>
                                    <p style="margin: 4px 0 12px; font-size: 0.80rem; color: #94a3b8;">This customer has not placed any orders matching the selected criteria.</p>
                                    <a href="<?= BASE_URL ?>/admin/order-create?phone=<?= urlencode($customer['phone'] ?? '') ?>&name=<?= urlencode($customerFullName) ?>&city=<?= urlencode($customer['city'] ?? '') ?>" class="btn btn-sm btn-primary" style="display: inline-flex; align-items: center; gap: 5px;">
                                        + Create Customer's First Order
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $ord): 
                                $items = $orderItemsMap[$ord['id']] ?? [];
                                $stBadge = $statusMap[$ord['status']]['badge'] ?? 'secondary';
                                $stLabel = $statusMap[$ord['status']]['label'] ?? ucfirst($ord['status']);
                                $payStatus = $ord['payment_status'] ?? 'unpaid';
                                $payMethod = ucfirst(str_replace('_', ' ', $ord['payment_method'] ?? 'COD'));
                            ?>
                            <tr>
                                <!-- Order # -->
                                <td>
                                    <a href="<?= BASE_URL ?>/admin/order/<?= $ord['id'] ?>" style="font-weight: 700; color: #0f766e; text-decoration: none; font-size: 0.85rem; font-family: monospace;">
                                        #<?= htmlspecialchars($ord['order_number'] ?: $ord['id']) ?>
                                    </a>
                                </td>

                                <!-- Date & Time -->
                                <td>
                                    <div style="font-size: 0.80rem; font-weight: 500; color: #0f172a;">
                                        <?= date('M j, Y', strtotime($ord['created_at'])) ?>
                                    </div>
                                    <div style="font-size: 0.72rem; color: #64748b;">
                                        <?= date('h:i A', strtotime($ord['created_at'])) ?>
                                    </div>
                                </td>

                                <!-- Ordered Items Preview -->
                                <td>
                                    <?php if (empty($items)): ?>
                                        <span style="font-size: 0.76rem; color: #94a3b8;">No item details</span>
                                    <?php else: ?>
                                        <div style="display: flex; flex-direction: column; gap: 3px;">
                                            <?php foreach (array_slice($items, 0, 3) as $it): 
                                                $imgUrl = !empty($it['main_image']) ? BASE_URL . '/' . htmlspecialchars($it['main_image']) : BASE_URL . '/assets/images/placeholder.png';
                                                $vInfo = implode(' / ', array_filter([$it['size'] ?? '', $it['color'] ?? '', $it['variant'] ?? '']));
                                            ?>
                                            <div class="order-item-chip">
                                                <img src="<?= $imgUrl ?>" class="order-item-img" alt="product">
                                                <div style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                    <span style="font-weight: 500;"><?= htmlspecialchars($it['product_name'] ?? $it['product_title'] ?? 'Product') ?></span>
                                                    <?php if (!empty($vInfo)): ?>
                                                        <span style="color: #64748b; font-size: 0.72rem;">(<?= htmlspecialchars($vInfo) ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="font-weight: 600; color: #0f172a; white-space: nowrap;">
                                                    &times; <?= (int)$it['quantity'] ?>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php if (count($items) > 3): ?>
                                                <div style="font-size: 0.72rem; color: #0f766e; font-weight: 600; padding-left: 4px;">
                                                    +<?= count($items) - 3 ?> more item(s)
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Shipping Destination -->
                                <td>
                                    <div style="font-size: 0.80rem; font-weight: 500; color: #334155;">
                                        <?= htmlspecialchars(trim(($ord['shipping_first_name'] ?? '') . ' ' . ($ord['shipping_last_name'] ?? ''))) ?: htmlspecialchars($customerFullName) ?>
                                    </div>
                                    <div style="font-size: 0.74rem; color: #64748b; margin-top: 1px;">
                                        <?= htmlspecialchars(implode(', ', array_filter([$ord['shipping_address'] ?? '', $ord['shipping_city'] ?? '']))) ?: 'Local Delivery' ?>
                                    </div>
                                    <?php if (!empty($ord['shipping_phone'])): ?>
                                        <div style="font-size: 0.72rem; color: #64748b; margin-top: 1px;">
                                            📞 <?= htmlspecialchars($ord['shipping_phone']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Payment Status & Method -->
                                <td style="text-align: center;">
                                    <span class="badge <?= $payStatus === 'paid' ? 'badge-success' : ($payStatus === 'partial' ? 'badge-warning' : 'badge-danger') ?>" style="font-size: 0.72rem; text-transform: capitalize; padding: 2px 7px;">
                                        <?= htmlspecialchars($payStatus) ?>
                                    </span>
                                    <div style="font-size: 0.70rem; color: #64748b; margin-top: 3px; font-weight: 500;">
                                        <?= htmlspecialchars($payMethod) ?>
                                    </div>
                                </td>

                                <!-- Order Status -->
                                <td style="text-align: center;">
                                    <span class="badge badge-<?= $stBadge ?>" style="font-size: 0.74rem; padding: 3px 8px; font-weight: 600;">
                                        <?= htmlspecialchars($stLabel) ?>
                                    </span>
                                </td>

                                <!-- Total Amount -->
                                <td style="text-align: right;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.90rem;">
                                        Tk <?= number_format((float)$ord['total']) ?>
                                    </div>
                                    <?php if ((float)$ord['advance_payment'] > 0 && $payStatus !== 'paid'): ?>
                                        <div style="font-size: 0.70rem; color: #059669;">
                                            Adv: Tk <?= number_format((float)$ord['advance_payment']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td style="text-align: right;">
                                    <div class="admin-actions-row" style="justify-content: flex-end; gap: 5px;">
                                        <a href="<?= BASE_URL ?>/admin/order/<?= $ord['id'] ?>" class="btn btn-sm btn-primary" style="padding: 0 8px; height: 28px; font-size: 0.76rem; display: inline-flex; align-items: center; gap: 4px; border-radius: 6px;" title="View Complete Order Details">
                                            <span>View</span>
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Standard Pagination -->
            <?php renderAdminPagination($page, $totalFilteredOrders, $perPage, BASE_URL . '/admin/customer-orders', array_filter(['id' => $customerId, 'search' => $search, 'status' => $statusFilter])); ?>

        </main>
    </div>
</body>
</html>
