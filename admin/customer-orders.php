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

function getCustOrderInitials(string $fn, string $ln): string {
    $i = mb_substr(trim($fn), 0, 1, 'UTF-8');
    if (!empty($ln)) {
        $i .= mb_substr(trim($ln), 0, 1, 'UTF-8');
    }
    return strtoupper($i ?: 'C');
}

$initials = getCustOrderInitials($customer['first_name'] ?? '', $customer['last_name'] ?? '');

$statusMap = [
    'pending'      => ['label' => 'Pending',           'badge' => 'warning',   'color' => '#f59e0b'],
    'confirmed'    => ['label' => 'Confirmed',         'badge' => 'info',      'color' => '#0284c7'],
    'processing'   => ['label' => 'Processing',        'badge' => 'primary',   'color' => '#2563eb'],
    'shipped'      => ['label' => 'Shipped',           'badge' => 'indigo',    'color' => '#4f46e5'],
    'delivered'    => ['label' => 'Delivered',         'badge' => 'success',   'color' => '#10b981'],
    'on_hold'      => ['label' => 'Hold',              'badge' => 'warning',   'color' => '#d97706'],
    'unreachable'  => ['label' => 'Unreachable',       'badge' => 'danger',    'color' => '#ef4444'],
    'not_received' => ['label' => "Didn't Receive",    'badge' => 'danger',    'color' => '#dc2626'],
    'returned'     => ['label' => 'Returned',          'badge' => 'purple',    'color' => '#8b5cf6'],
    'cancelled'    => ['label' => 'Cancelled',         'badge' => 'secondary', 'color' => '#64748b'],
    'refunded'     => ['label' => 'Refunded',          'badge' => 'pink',      'color' => '#ec4899'],
    'fake'         => ['label' => 'Fake Order',        'badge' => 'dark-red',  'color' => '#991b1b'],
];

// ── Customer Lifetime Summary Metrics ─────────────────────────────────────────
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

// Status counts specifically for this customer
$custStatusCountStmt = $db->prepare("
    SELECT status, COUNT(*) as cnt 
    FROM orders 
    WHERE user_id = ? $phoneClause 
    GROUP BY status
");
$custStatusCountStmt->execute($metricParams);
$custStatusCounts = $custStatusCountStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// ── Filter Orders ─────────────────────────────────────────────────────────────
$statusFilter = sanitize($_GET['status'] ?? '');
$search       = sanitize($_GET['search'] ?? '');
$sortBy       = sanitize($_GET['sort'] ?? 'newest');

$activeFilterCount = 0;
if ($statusFilter !== '') $activeFilterCount++;
if ($search !== '') $activeFilterCount++;
if ($sortBy !== 'newest') $activeFilterCount++;

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

// Sorting
$orderSql = 'o.created_at DESC';
if ($sortBy === 'oldest') {
    $orderSql = 'o.created_at ASC';
} elseif ($sortBy === 'amount_desc') {
    $orderSql = 'o.total DESC';
} elseif ($sortBy === 'amount_asc') {
    $orderSql = 'o.total ASC';
}

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
    ORDER BY $orderSql 
    LIMIT $perPage OFFSET $offset
");
$ordersStmt->execute($queryParams);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Order History - ' . $customerFullName;
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        /* ── Header Toolbar ── */
        .as-page-header-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 1.25rem;
        }
        .as-page-header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ── Customer Profile Card ── */
        .as-cust-banner {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.25rem;
        }
        .as-cust-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            flex-shrink: 0;
            box-shadow: 0 3px 8px rgba(15, 118, 110, 0.2);
            letter-spacing: 0.5px;
        }
        .as-cust-title-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .as-cust-meta-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 4px;
            flex-wrap: wrap;
            font-size: 0.82rem;
            color: #475569;
        }
        .as-cust-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* ── KPI Metric Cards ── */
        .as-cust-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.85rem;
            margin-bottom: 1.25rem;
        }
        .as-cust-kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1rem 1.15rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            transition: all 0.15s ease;
        }
        .as-cust-kpi-card:hover {
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }
        .as-cust-kpi-val {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }
        .as-cust-kpi-label {
            font-size: 0.74rem;
            font-weight: 500;
            color: #64748b;
            margin-top: 2px;
        }
        .as-cust-kpi-icon {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .as-cust-kpi-icon.blue   { background: #eff6ff; color: #2563eb; }
        .as-cust-kpi-icon.teal   { background: #f0fdfa; color: #0f766e; }
        .as-cust-kpi-icon.amber  { background: #fffbeb; color: #d97706; }
        .as-cust-kpi-icon.emerald{ background: #ecfdf5; color: #059669; }

        /* ── Status Pills Filter Row ── */
        .as-status-pills-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 10px;
            margin-bottom: 1.25rem;
        }
        .as-status-pill-card {
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.65rem 0.85rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            transition: all 0.15s ease;
        }
        .as-status-pill-card:hover {
            border-color: #cbd5e1;
            background: #fafafa;
        }
        .as-status-pill-card.active-pill {
            border-color: #0f766e !important;
            background: #f0fdfa !important;
            box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.15);
        }

        /* ── Executive Multi-Filter Card ── */
        .as-filter-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .as-filter-form {
            display: flex;
            align-items: center;
            width: 100%;
            margin: 0;
        }
        .as-filter-row {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            flex-wrap: nowrap;
        }
        .as-filter-search-wrap {
            position: relative;
            flex: 1 1 auto;
            min-width: 200px;
        }
        .as-filter-search-wrap input {
            width: 100%;
            height: 36px;
            padding: 0 10px 0 2.2rem;
            border-radius: 7px;
            border: 1px solid #cbd5e1;
            font-size: 0.82rem;
            color: #334155;
            background: #ffffff;
            outline: none;
            transition: all 0.15s ease;
        }
        .as-filter-search-wrap input:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.1);
        }
        .as-filter-search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #94a3b8;
        }
        .as-filter-controls-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .as-filter-select {
            height: 36px;
            font-size: 0.82rem;
            font-weight: 400;
            padding: 0 0.65rem;
            border-radius: 7px;
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            color: #334155;
            width: 150px;
            flex-shrink: 0;
        }
        .as-filter-btns {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }
        .as-filter-btns .btn {
            height: 36px;
            font-size: 0.82rem;
            padding: 0 14px;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* ── Mobile Order Cards ── */
        .as-mobile-orders-wrap {
            display: none;
        }
        .as-order-m-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.85rem 1rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }
        .as-order-m-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
        }
        .as-order-m-num {
            font-family: monospace;
            font-size: 0.88rem;
            font-weight: 700;
            color: #0f766e;
            text-decoration: none;
        }
        .as-order-m-date {
            font-size: 0.72rem;
            color: #64748b;
            margin-top: 1px;
        }
        .as-order-m-body {
            padding: 8px 0;
            display: flex;
            flex-direction: column;
            gap: 5px;
            font-size: 0.78rem;
        }
        .as-order-m-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #334155;
        }
        .as-order-m-lbl {
            color: #64748b;
            font-weight: 500;
        }
        .as-order-m-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            padding-top: 8px;
            border-top: 1px dashed #e2e8f0;
            margin-top: 4px;
        }

        /* ── Mobile Optimization (<= 768px) ── */
        @media (max-width: 768px) {
            .as-page-header-wrap {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.75rem !important;
            }
            .as-page-header-actions {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 8px !important;
                width: 100% !important;
            }
            .as-page-header-actions .btn {
                width: 100% !important;
                height: 38px !important;
                justify-content: center !important;
                padding: 0 8px !important;
                font-size: 0.80rem !important;
                white-space: nowrap !important;
                text-overflow: ellipsis !important;
                overflow: hidden !important;
            }

            .as-cust-banner {
                padding: 1rem !important;
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.75rem !important;
            }
            .as-cust-banner-main {
                display: flex !important;
                align-items: center !important;
                gap: 12px !important;
            }
            .as-cust-meta-row {
                display: flex !important;
                flex-direction: column !important;
                gap: 6px !important;
                margin-top: 8px !important;
                padding-top: 8px !important;
                border-top: 1px dashed #e2e8f0 !important;
            }
            .as-cust-meta-item {
                font-size: 0.80rem !important;
            }
            .as-cust-joined-meta {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                font-size: 0.74rem !important;
                padding-top: 8px !important;
                border-top: 1px solid #f1f5f9 !important;
                width: 100% !important;
            }

            .as-cust-kpi-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 8px !important;
            }
            .as-cust-kpi-card {
                padding: 0.75rem 0.85rem !important;
            }
            .as-cust-kpi-val {
                font-size: 1.1rem !important;
            }
            .as-cust-kpi-label {
                font-size: 0.70rem !important;
            }
            .as-cust-kpi-icon {
                width: 32px !important;
                height: 32px !important;
            }
            .as-cust-kpi-icon svg {
                width: 16px !important;
                height: 16px !important;
            }

            .as-status-pills-row {
                display: flex !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 6px !important;
                gap: 6px !important;
                margin-bottom: 1rem !important;
                scrollbar-width: none;
            }
            .as-status-pills-row::-webkit-scrollbar {
                display: none;
            }
            .as-status-pill-card {
                flex: 0 0 auto !important;
                min-width: 96px !important;
                padding: 0.45rem 0.65rem !important;
            }
            .as-status-pill-card .as-pill-cnt {
                font-size: 1rem !important;
            }
            .as-status-pill-card .as-pill-lbl {
                font-size: 0.68rem !important;
            }

            /* Compact Mobile Filter Toolbar */
            .as-filter-card {
                padding: 10px 12px !important;
            }
            .as-filter-row {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 8px !important;
            }
            .as-filter-search-wrap {
                width: 100% !important;
                flex: 1 1 100% !important;
            }
            .as-filter-controls-row {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 6px !important;
                width: 100% !important;
            }
            .as-filter-select {
                width: 100% !important;
                flex: 1 1 100% !important;
                min-width: 0 !important;
            }
            .as-filter-btns {
                display: grid !important;
                grid-template-columns: <?= ($activeFilterCount > 0) ? '1fr 1fr' : '1fr' ?> !important;
                gap: 6px !important;
                width: 100% !important;
                margin-left: 0 !important;
            }
            .admin-table-wrap {
                display: block !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
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

            <!-- Page Header Toolbar -->
            <div class="as-page-header-wrap">
                <div>
                    <h1 class="admin-title" style="display: flex; align-items: center; gap: 8px; margin: 0; font-size: 1.25rem;">
                        <span>Customer Order History</span>
                    </h1>
                    <div style="font-size: 0.82rem; color: #64748b; margin-top: 3px;">
                        Detailed purchasing record for <strong style="color: #0f172a;"><?= htmlspecialchars($customerFullName) ?></strong>.
                    </div>
                </div>
                <div class="as-page-header-actions">
                    <a href="<?= BASE_URL ?>/admin/customers" class="btn btn-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        <span>Back</span>
                    </a>
                    <a href="<?= BASE_URL ?>/admin/order-create?phone=<?= urlencode($customer['phone'] ?? '') ?>&name=<?= urlencode($customerFullName) ?>&city=<?= urlencode($customer['city'] ?? '') ?>" class="btn btn-primary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span>Create Order</span>
                    </a>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($message): ?>
            <div class="alert alert-success" style="margin-bottom: 1.25rem;">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Customer Intelligence Profile Banner -->
            <div class="as-cust-banner">
                <div style="flex: 1; width: 100%;">
                    <div class="as-cust-banner-main">
                        <div class="as-cust-avatar"><?= $initials ?></div>
                        <div>
                            <div class="as-cust-title-row">
                                <h2 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: #0f172a;"><?= htmlspecialchars($customerFullName) ?></h2>
                                <span class="badge <?= $customer['status'] === 'active' ? 'badge-success' : ($customer['status'] === 'banned' ? 'badge-danger' : 'badge-warning') ?>" style="text-transform: capitalize; font-size: 0.72rem; padding: 2px 7px;">
                                    <?= htmlspecialchars($customer['status'] ?? 'active') ?>
                                </span>
                                <span style="font-size: 0.74rem; color: #94a3b8; font-weight: 500;">ID #<?= $customer['id'] ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="as-cust-meta-row">
                        <div class="as-cust-meta-item">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            <a href="mailto:<?= htmlspecialchars($customer['email']) ?>" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($customer['email']) ?></a>
                        </div>
                        <?php if (!empty($customer['phone'])): ?>
                        <div class="as-cust-meta-item">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            <a href="tel:<?= htmlspecialchars($customer['phone']) ?>" style="color: inherit; text-decoration: none; font-weight: 500;"><?= htmlspecialchars($customer['phone']) ?></a>
                        </div>
                        <?php endif; ?>
                        <div class="as-cust-meta-item">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span><?= htmlspecialchars(implode(', ', array_filter([$customer['address'] ?? '', $customer['upazila'] ?? '', $customer['city'] ?? '']))) ?: 'Bangladesh' ?></span>
                        </div>
                    </div>
                </div>

                <div class="as-cust-joined-meta">
                    <div>Joined: <strong><?= date('M j, Y', strtotime($customer['created_at'])) ?></strong></div>
                    <?php if ($metrics['latest_order_date']): ?>
                    <div>Last Order: <strong><?= date('M j, Y h:i A', strtotime($metrics['latest_order_date'])) ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 4 Executive KPI Metric Cards (Responsive 2x2 Grid on Mobile) -->
            <div class="as-cust-kpi-grid">
                <!-- Total Orders -->
                <div class="as-cust-kpi-card">
                    <div>
                        <div class="as-cust-kpi-val"><?= number_format($totalOrdersCount) ?></div>
                        <div class="as-cust-kpi-label">Total Orders</div>
                    </div>
                    <div class="as-cust-kpi-icon blue">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    </div>
                </div>

                <!-- Total Paid Spend -->
                <div class="as-cust-kpi-card">
                    <div>
                        <div class="as-cust-kpi-val" style="color: #0f766e;">Tk <?= number_format($totalSpentAmount) ?></div>
                        <div class="as-cust-kpi-label">Paid Lifetime Spend</div>
                    </div>
                    <div class="as-cust-kpi-icon teal">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                    </div>
                </div>

                <!-- Average Order Value -->
                <div class="as-cust-kpi-card">
                    <div>
                        <div class="as-cust-kpi-val">Tk <?= number_format($avgOrderValue) ?></div>
                        <div class="as-cust-kpi-label">Avg Order Value</div>
                    </div>
                    <div class="as-cust-kpi-icon amber">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    </div>
                </div>

                <!-- Delivered Success Rate -->
                <div class="as-cust-kpi-card">
                    <div>
                        <div class="as-cust-kpi-val" style="color: #059669;"><?= number_format((int)$metrics['delivered_orders']) ?></div>
                        <div class="as-cust-kpi-label">Delivered (<?= $totalOrdersCount > 0 ? round(($metrics['delivered_orders'] / $totalOrdersCount) * 100) : 0 ?>%)</div>
                    </div>
                    <div class="as-cust-kpi-icon emerald">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                </div>
            </div>

            <!-- Status Filter Pills Row (Horizontal Scroll on Mobile) -->
            <div class="as-status-pills-row">
                <?php
                $statPills = [
                    'all'         => ['title' => 'All Orders',        'color' => '#0f172a', 'cnt' => $totalOrdersCount],
                    'pending'     => ['title' => 'Pending',           'color' => '#f59e0b', 'cnt' => $custStatusCounts['pending'] ?? 0],
                    'confirmed'   => ['title' => 'Confirmed',         'color' => '#0284c7', 'cnt' => $custStatusCounts['confirmed'] ?? 0],
                    'processing'  => ['title' => 'Processing',        'color' => '#2563eb', 'cnt' => $custStatusCounts['processing'] ?? 0],
                    'shipped'     => ['title' => 'Shipped',           'color' => '#4f46e5', 'cnt' => $custStatusCounts['shipped'] ?? 0],
                    'delivered'   => ['title' => 'Delivered',         'color' => '#10b981', 'cnt' => $custStatusCounts['delivered'] ?? 0],
                    'on_hold'     => ['title' => 'On Hold',           'color' => '#d97706', 'cnt' => $custStatusCounts['on_hold'] ?? 0],
                    'returned'    => ['title' => 'Returned',          'color' => '#8b5cf6', 'cnt' => $custStatusCounts['returned'] ?? 0],
                    'cancelled'   => ['title' => 'Cancelled',         'color' => '#64748b', 'cnt' => $custStatusCounts['cancelled'] ?? 0],
                ];
                foreach ($statPills as $pKey => $pCfg):
                    $isActive = ($pKey === 'all' && empty($statusFilter)) || ($statusFilter === $pKey);
                    $url = BASE_URL . '/admin/customer-orders?id=' . $customerId . ($pKey !== 'all' ? '&status=' . urlencode($pKey) : '') . ($search ? '&search=' . urlencode($search) : '') . ($sortBy !== 'newest' ? '&sort=' . urlencode($sortBy) : '');
                ?>
                    <a href="<?= $url ?>" class="as-status-pill-card <?= $isActive ? 'active-pill' : '' ?>">
                        <div>
                            <div class="as-pill-cnt" style="font-size: 1.1rem; font-weight: 700; color: <?= $pCfg['color'] ?>; line-height: 1.2;"><?= $pCfg['cnt'] ?></div>
                            <div class="as-pill-lbl" style="font-size: 0.72rem; font-weight: 500; color: #64748b; margin-top: 2px;"><?= htmlspecialchars($pCfg['title']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- ── Executive Multi-Filter Toolbar ── -->
            <div class="as-filter-card">
                <form method="GET" action="<?= BASE_URL ?>/admin/customer-orders" class="as-filter-form">
                    <input type="hidden" name="id" value="<?= $customerId ?>">
                    <div class="as-filter-row">
                        <!-- Search Field with embedded icon -->
                        <div class="as-filter-search-wrap">
                            <svg class="as-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" name="search" placeholder="Search order #, recipient, phone..." value="<?= htmlspecialchars($search) ?>">
                        </div>

                        <div class="as-filter-controls-row">
                            <!-- Status Selector -->
                            <select name="status" class="as-filter-select form-select" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <?php foreach ($statusMap as $sKey => $sVal): ?>
                                    <option value="<?= $sKey ?>" <?= $statusFilter === $sKey ? 'selected' : '' ?>><?= htmlspecialchars($sVal['label']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Sort Selector -->
                            <select name="sort" class="as-filter-select form-select" onchange="this.form.submit()">
                                <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Sort: Newest</option>
                                <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Sort: Oldest</option>
                                <option value="amount_desc" <?= $sortBy === 'amount_desc' ? 'selected' : '' ?>>Sort: Highest Tk</option>
                                <option value="amount_asc" <?= $sortBy === 'amount_asc' ? 'selected' : '' ?>>Sort: Lowest Tk</option>
                            </select>
                        </div>

                        <!-- Action Buttons -->
                        <div class="as-filter-btns">
                            <button type="submit" class="btn btn-primary" style="height: 36px; font-size: 0.82rem; padding: 0 14px; border-radius: 7px;">Filter</button>
                            <?php if ($activeFilterCount > 0): ?>
                                <a href="<?= BASE_URL ?>/admin/customer-orders?id=<?= $customerId ?>" class="btn btn-secondary" style="height: 36px; font-size: 0.82rem; padding: 0 10px; border-radius: 7px; display: inline-flex; align-items: center;">Clear</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Desktop Orders Table -->
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Order #</th>
                            <th>Date</th>
                            <th>Shipping &amp; Recipient</th>
                            <th style="text-align: right;">Total</th>
                            <th style="text-align: center;">Payment</th>
                            <th style="text-align: center;">Status</th>
                            <th>Gateway</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 3.5rem 1rem; color: #94a3b8;">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.8" style="display: block; margin: 0 auto 10px;"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                    <div style="font-weight: 600; color: #64748b; font-size: 0.95rem;">No orders found for this customer.</div>
                                    <p style="margin: 4px 0 14px; font-size: 0.80rem; color: #94a3b8;">Try clearing the filter or place a new order for this customer.</p>
                                    <a href="<?= BASE_URL ?>/admin/order-create?phone=<?= urlencode($customer['phone'] ?? '') ?>&name=<?= urlencode($customerFullName) ?>&city=<?= urlencode($customer['city'] ?? '') ?>" class="btn btn-sm btn-primary" style="display: inline-flex; align-items: center; gap: 5px;">
                                        + Create Order
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): 
                                $isPaid = ($order['status'] === 'delivered' || $order['payment_status'] === 'paid');
                                $payLabel = $isPaid ? 'Paid' : ucfirst($order['payment_status'] ?? 'unpaid');
                                $payBadge = $isPaid ? 'success' : 'warning';
                                $gatewayName = paymentDisplayName((string)($order['payment_method'] ?? 'COD'));
                                $stBadge = $statusMap[$order['status']]['badge'] ?? 'secondary';
                                $stLabel = $statusMap[$order['status']]['label'] ?? ucfirst($order['status']);
                            ?>
                            <tr>
                                <!-- Order # -->
                                <td>
                                    <a href="<?= BASE_URL ?>/admin/order/<?= $order['id'] ?>" style="font-weight: 700; color: #0f766e; text-decoration: none; font-size: 0.85rem; font-family: monospace; display: inline-flex; align-items: center; gap: 4px;">
                                        <span>#<?= htmlspecialchars($order['order_number'] ?: $order['id']) ?></span>
                                    </a>
                                </td>

                                <!-- Date & Time -->
                                <td>
                                    <div style="font-size: 0.80rem; font-weight: 500; color: #0f172a;">
                                        <?= date('M j, Y', strtotime($order['created_at'])) ?>
                                    </div>
                                    <div style="font-size: 0.72rem; color: #64748b;">
                                        <?= date('h:i A', strtotime($order['created_at'])) ?>
                                    </div>
                                </td>

                                <!-- Shipping & Contact -->
                                <td>
                                    <div style="font-weight: 500; color: #0f172a; font-size: 0.82rem;">
                                        <?= htmlspecialchars(trim(($order['shipping_first_name'] ?? '') . ' ' . ($order['shipping_last_name'] ?? ''))) ?: htmlspecialchars($customerFullName) ?>
                                    </div>
                                    <div style="font-size: 0.74rem; color: #64748b; margin-top: 1px;">
                                        <?= htmlspecialchars(implode(', ', array_filter([$order['shipping_address'] ?? '', $order['shipping_city'] ?? '']))) ?: 'Local Delivery' ?>
                                    </div>
                                    <?php if (!empty($order['shipping_phone'])): ?>
                                        <div style="font-size: 0.72rem; color: #64748b; margin-top: 1px;">
                                            📞 <?= htmlspecialchars($order['shipping_phone']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Total -->
                                <td style="text-align: right;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.88rem;">
                                        Tk <?= number_format((float)$order['total']) ?>
                                    </div>
                                    <?php if ((float)($order['advance_payment'] ?? 0) > 0 && !$isPaid): ?>
                                        <div style="font-size: 0.70rem; color: #059669; font-weight: 500;">
                                            Adv: Tk <?= number_format((float)$order['advance_payment']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Payment Status -->
                                <td style="text-align: center;">
                                    <span class="badge badge-<?= $payBadge ?>" style="font-weight: 500; font-size: 0.74rem; padding: 2px 8px;">
                                        <?= htmlspecialchars($payLabel) ?>
                                    </span>
                                </td>

                                <!-- Order Status (Read-Only) -->
                                <td style="text-align: center;">
                                    <span class="badge badge-<?= $stBadge ?>" style="font-weight: 600; font-size: 0.76rem; padding: 3px 9px;">
                                        <?= htmlspecialchars($stLabel) ?>
                                    </span>
                                </td>

                                <!-- Gateway -->
                                <td>
                                    <span style="font-size: 0.78rem; font-weight: 500; color: #334155;">
                                        <?= htmlspecialchars($gatewayName) ?>
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td style="text-align: right;">
                                    <div class="admin-actions-row">
                                        <a href="<?= BASE_URL ?>/admin/order/<?= $order['id'] ?>" class="btn-action-icon view" title="View Order Details">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </a>
                                        <a href="<?= BASE_URL ?>/invoice?order=<?= urlencode($order['order_number']) ?>" target="_blank" class="btn-action-icon invoice" title="Print / Download Invoice">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
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
            <?php renderAdminPagination($page, $totalFilteredOrders, $perPage, BASE_URL . '/admin/customer-orders', array_filter(['id' => $customerId, 'search' => $search, 'status' => $statusFilter, 'sort' => $sortBy])); ?>

        </main>
    </div>
    <script src="js/admin.js"></script>
</body>
</html>
