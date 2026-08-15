<?php
/**
 * Rosabella - Executive Admin Dashboard & Analytics Center
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once __DIR__ . '/includes/layout.php';

// Auth Guard
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$pageTitle = 'Dashboard Analytics';
$db = getDB();

// Status Mapping
$statusMap = [
    'pending'      => ['label' => 'Pending',           'badge' => 'warning'],
    'confirmed'    => ['label' => 'Confirmed',         'badge' => 'info'],
    'processing'   => ['label' => 'Processing',        'badge' => 'info'],
    'shipped'      => ['label' => 'Shipped',           'badge' => 'primary'],
    'delivered'    => ['label' => 'Delivered',         'badge' => 'success'],
    'on_hold'      => ['label' => 'Hold',              'badge' => 'warning'],
    'unreachable'  => ['label' => 'Unreachable',       'badge' => 'danger'],
    'not_received' => ['label' => "Didn't Receive",    'badge' => 'danger'],
    'returned'     => ['label' => 'Returned',          'badge' => 'purple'],
    'cancelled'    => ['label' => 'Cancelled',         'badge' => 'secondary'],
    'refunded'     => ['label' => 'Refunded',          'badge' => 'pink'],
    'fake'         => ['label' => 'Fake Order',        'badge' => 'dark-red'],
];

$message = '';
// ── Handle Inline Order Status Update ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = intval($_POST['order_id'] ?? 0);
    $newStatus = sanitize($_POST['status'] ?? 'pending');

    if ($orderId > 0 && isset($statusMap[$newStatus])) {
        $oldStmt = $db->prepare("SELECT status, total FROM orders WHERE id = ?");
        $oldStmt->execute([$orderId]);
        $oldRow = $oldStmt->fetch();
        $oldStatus = $oldRow['status'] ?? '';
        $orderTotal = floatval($oldRow['total'] ?? 0);

        if ($newStatus === 'delivered') {
            $stmt = $db->prepare("UPDATE orders SET status = ?, payment_status = 'paid', advance_payment = ? WHERE id = ?");
            $saved = $stmt->execute([$newStatus, $orderTotal, $orderId]);
        } else {
            $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $saved = $stmt->execute([$newStatus, $orderId]);
        }

        if ($saved) {
            $message = 'Order #' . $orderId . ' status updated to ' . htmlspecialchars($statusMap[$newStatus]['label']) . '.';
            
            // Audit Log
            $changedBy = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
            $histStmt  = $db->prepare("INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)");
            $histStmt->execute([$orderId, $newStatus, 'Status updated from admin dashboard', $changedBy]);

            // Stock Synchronization
            if ($oldStatus && $oldStatus !== $newStatus) {
                $isOldInactive = in_array($oldStatus, ['cancelled', 'refunded', 'fake'], true);
                $isNewInactive = in_array($newStatus, ['cancelled', 'refunded', 'fake'], true);
                
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
        }
    }
}

// ── 1. Core KPIs ─────────────────────────────────────────────────────────────
$totalProducts = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalOrders   = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalUsers    = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$totalRevenue  = (float)$db->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
$grossSales    = (float)$db->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status NOT IN ('cancelled', 'fake')")->fetchColumn();
$paidOrdersCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
$avgOrderValue = $paidOrdersCount > 0 ? ($totalRevenue / $paidOrdersCount) : 0;

// Status counts
$statusRows = $db->query("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$pendingCount    = (int)($statusRows['pending'] ?? 0);
$processingCount = (int)($statusRows['processing'] ?? 0);
$deliveredCount  = (int)($statusRows['delivered'] ?? 0);
$cancelledCount  = (int)($statusRows['cancelled'] ?? 0);
$returnedCount   = (int)($statusRows['returned'] ?? 0);
$onHoldCount     = (int)($statusRows['on_hold'] ?? 0);

$fulfillmentRate = $totalOrders > 0 ? round(($deliveredCount / $totalOrders) * 100, 1) : 0;

// ── 2. Time-Series Chart Data ────────────────────────────────────────────────
// Last 14 Days Daily Data
$dailyRows = $db->query("
    SELECT DATE(created_at) as o_date,
           COUNT(*) as o_count,
           SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as o_paid,
           SUM(total) as o_total
    FROM orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    GROUP BY DATE(created_at)
")->fetchAll(PDO::FETCH_ASSOC);

$dailyMap = [];
foreach ($dailyRows as $r) {
    $dailyMap[$r['o_date']] = [
        'count'   => (int)$r['o_count'],
        'paid'    => (float)$r['o_paid'],
        'total'   => (float)$r['o_total']
    ];
}

$dailyLabels  = [];
$dailyRevenue = [];
$dailyOrders  = [];
for ($i = 13; $i >= 0; $i--) {
    $dateStr = date('Y-m-d', strtotime("-$i days"));
    $dailyLabels[]  = date('M j', strtotime($dateStr));
    $dailyRevenue[] = $dailyMap[$dateStr]['paid'] ?? ($dailyMap[$dateStr]['total'] ?? 0);
    $dailyOrders[]  = $dailyMap[$dateStr]['count'] ?? 0;
}

// Monthly Data (Last 6 Months)
$monthlyRows = $db->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as o_month,
           COUNT(*) as o_count,
           SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as o_paid
    FROM orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY o_month ASC
")->fetchAll(PDO::FETCH_ASSOC);

$monthlyMap = [];
foreach ($monthlyRows as $r) {
    $monthlyMap[$r['o_month']] = [
        'count' => (int)$r['o_count'],
        'paid'  => (float)$r['o_paid']
    ];
}

$monthlyLabels  = [];
$monthlyRevenue = [];
$monthlyOrders  = [];
for ($i = 5; $i >= 0; $i--) {
    $mStr = date('Y-m', strtotime("-$i months"));
    $monthlyLabels[]  = date('M Y', strtotime($mStr . '-01'));
    $monthlyRevenue[] = $monthlyMap[$mStr]['paid'] ?? 0;
    $monthlyOrders[]  = $monthlyMap[$mStr]['count'] ?? 0;
}

// Status Doughnut Data
$doughnutLabels = ['Delivered', 'Processing', 'Pending', 'Cancelled', 'Other'];
$doughnutData   = [
    $deliveredCount,
    $processingCount,
    $pendingCount,
    $cancelledCount,
    $returnedCount + $onHoldCount
];
$doughnutColors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6'];

// ── 3. Payment Methods Breakdown (Normalized & Merged) ─────────────────────────
$paymentRows = $db->query("
    SELECT payment_method, COUNT(*) as cnt, SUM(total) as rev
    FROM orders
    GROUP BY payment_method
")->fetchAll(PDO::FETCH_ASSOC);

$mergedPayments = [];
foreach ($paymentRows as $pr) {
    $m = strtolower(trim((string)$pr['payment_method']));
    $key  = 'cod';
    $name = 'Cash on Delivery';
    $type = 'cod';

    if (str_contains($m, 'bkash')) {
        $key = 'bkash'; $name = 'bKash'; $type = 'bkash';
    } elseif (str_contains($m, 'nagad')) {
        $key = 'nagad'; $name = 'Nagad'; $type = 'nagad';
    } elseif (str_contains($m, 'rocket')) {
        $key = 'rocket'; $name = 'Rocket'; $type = 'rocket';
    } elseif (str_contains($m, 'card') || str_contains($m, 'ssl')) {
        $key = 'card'; $name = 'Card / Digital'; $type = 'card';
    }

    if (!isset($mergedPayments[$key])) {
        $mergedPayments[$key] = [
            'name'    => $name,
            'type'    => $type,
            'count'   => 0,
            'revenue' => 0.0,
        ];
    }
    $mergedPayments[$key]['count']   += (int)$pr['cnt'];
    $mergedPayments[$key]['revenue'] += (float)$pr['rev'];
}

// Sort by count descending
uasort($mergedPayments, fn($a, $b) => $b['count'] <=> $a['count']);

$paymentBreakdown = [];
foreach ($mergedPayments as $item) {
    $item['percentage'] = $totalOrders > 0 ? round(($item['count'] / $totalOrders) * 100) : 0;
    $paymentBreakdown[] = $item;
}

// ── 4. Tables Data ───────────────────────────────────────────────────────────
$recentOrders = $db->query("
    SELECT o.*, 
           TRIM(CONCAT(COALESCE(o.shipping_first_name,''), ' ', COALESCE(o.shipping_last_name,''))) AS customer_full_name,
           (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
    FROM orders o 
    ORDER BY o.created_at DESC 
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$lowStockProducts = $db->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.stock_quantity <= 10 AND p.status = 'active' 
    ORDER BY p.stock_quantity ASC 
    LIMIT 6
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $siteFavicon = getSetting('site_favicon'); if ($siteFavicon): ?>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . '/' . htmlspecialchars($siteFavicon) ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Dashboard — Rosabella</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <style>
        /* =========================================================
           EXECUTIVE DASHBOARD PROFESSIONAL STYLES
           ========================================================= */

        /* ── Welcome Banner ── */
        .dash-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.25rem;
            flex-wrap: wrap;
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.02);
        }
        .dash-hero-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 0.2rem;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dash-hero-sub {
            font-size: 0.82rem;
            color: #64748b;
            margin: 0;
        }
        .dash-hero-badges {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .dash-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.74rem;
            font-weight: 600;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #475569;
            white-space: nowrap;
        }
        .dash-live-pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
            animation: dashPulse 2s infinite ease-in-out;
        }
        @keyframes dashPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.75; }
        }

        /* ── 6-Card Metric Grid ── */
        .dash-kpi-grid-6 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        @media (min-width: 1400px) {
            .dash-kpi-grid-6 {
                grid-template-columns: repeat(6, 1fr);
            }
        }
        .dash-kpi-card {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.1rem 1.2rem;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.02);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 112px;
            text-decoration: none;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }
        .dash-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
            border-color: #cbd5e1;
        }
        .dash-kpi-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.35rem;
        }
        .dash-kpi-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-top: 2px;
        }
        .dash-kpi-bubble {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: transform 0.2s ease;
        }
        .dash-kpi-card:hover .dash-kpi-bubble {
            transform: scale(1.06);
        }
        .dash-kpi-bubble svg { width: 17px; height: 17px; }
        .dash-kpi-bubble.teal   { background: #ccfbf1; color: #0f766e; }
        .dash-kpi-bubble.blue   { background: #dbeafe; color: #1d4ed8; }
        .dash-kpi-bubble.emerald{ background: #d1fae5; color: #047857; }
        .dash-kpi-bubble.amber  { background: #fef3c7; color: #b45309; }
        .dash-kpi-bubble.purple { background: #f3e8ff; color: #7c3aed; }
        .dash-kpi-bubble.indigo { background: #e0e7ff; color: #4338ca; }

        .dash-kpi-val {
            font-size: clamp(1.2rem, 2vw, 1.45rem);
            font-weight: 800;
            color: #0f172a;
            line-height: 1.15;
            letter-spacing: -0.02em;
            margin-bottom: 0.35rem;
            word-break: break-word;
        }
        .dash-kpi-val.text-teal { color: #0f766e; }

        .dash-kpi-meta {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 5px;
            width: fit-content;
        }
        .dash-kpi-meta.teal    { background: #f0fdf4; color: #15803d; }
        .dash-kpi-meta.blue    { background: #eff6ff; color: #1d4ed8; }
        .dash-kpi-meta.amber   { background: #fffbeb; color: #b45309; }
        .dash-kpi-meta.purple  { background: #faf5ff; color: #7e22ce; }
        .dash-kpi-meta.neutral { background: #f8fafc; color: #64748b; }

        /* ── Operational Toolbar ── */
        .dash-actions-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        .dash-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            padding: 0.65rem 0.9rem;
            border-radius: 11px;
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
            border: 1.5px solid transparent;
            transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
            min-height: 42px;
            box-sizing: border-box;
            white-space: nowrap;
        }
        .dash-action-btn svg { width: 15px; height: 15px; flex-shrink: 0; }
        .dash-action-btn.btn-main {
            background: var(--color-primary, #0f766e);
            color: #ffffff;
            border-color: var(--color-primary, #0f766e);
            box-shadow: 0 2px 6px rgba(15, 118, 110, 0.25);
        }
        .dash-action-btn.btn-main:hover {
            background: #0d655e;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(15, 118, 110, 0.35);
        }
        .dash-action-btn.btn-sub {
            background: #ffffff;
            color: #334155;
            border-color: #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .dash-action-btn.btn-sub:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }
        .dash-action-badge {
            background: #f1f5f9;
            color: #475569;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 0.68rem;
            font-weight: 800;
        }

        /* ── Charts Grid ── */
        .dash-charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }
        .dash-chart-card {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.02);
            display: flex;
            flex-direction: column;
        }
        .dash-chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        .dash-chart-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }
        .dash-chart-pills {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            background: #f8fafc;
            padding: 3px;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
        }
        .dash-chart-pill {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px 9px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            color: #64748b;
            transition: all 0.15s ease;
        }
        .dash-chart-pill.active {
            background: #ffffff;
            color: var(--color-primary, #0f766e);
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .dash-canvas-container {
            position: relative;
            width: 100%;
            height: 280px;
            flex: 1;
        }

        /* ── Payment Distribution Card ── */
        .dash-breakdown-list {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            margin-top: 0.5rem;
        }
        .dash-breakdown-item {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .dash-breakdown-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.78rem;
            font-weight: 700;
            color: #1e293b;
        }
        .dash-pay-title {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dash-pay-icon-bubble {
            width: 26px;
            height: 26px;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .dash-pay-icon-bubble.cod    { background: #ccfbf1; color: #0f766e; }
        .dash-pay-icon-bubble.bkash  { background: #ffe4e6; color: #e11d48; }
        .dash-pay-icon-bubble.nagad  { background: #ffedd5; color: #ea580c; }
        .dash-pay-icon-bubble.rocket { background: #f3e8ff; color: #9333ea; }
        .dash-pay-icon-bubble.card   { background: #dbeafe; color: #2563eb; }
        .dash-pay-icon-bubble svg    { width: 14px; height: 14px; }

        .dash-pay-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .dash-pay-badge.cod   { background: #ccfbf1; color: #0f766e; }
        .dash-pay-badge.bkash { background: #ffe4e6; color: #e11d48; }
        .dash-pay-badge.nagad { background: #ffedd5; color: #ea580c; }
        .dash-pay-badge.card  { background: #dbeafe; color: #2563eb; }
        .dash-pay-badge svg   { width: 12px; height: 12px; }

        .dash-breakdown-bar {
            height: 7px;
            background: #f1f5f9;
            border-radius: 4px;
            overflow: hidden;
        }
        .dash-breakdown-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* ── Responsive Adaptations ── */
        @media (max-width: 1100px) {
            .dash-charts-grid {
                grid-template-columns: 1fr !important;
            }
            .dash-kpi-grid-6 {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }
        @media (max-width: 900px) {
            .dash-kpi-grid-6 {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.75rem !important;
            }
            .dash-actions-bar {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.6rem !important;
            }
        }
        @media (max-width: 480px) {
            .dash-hero {
                padding: 0.9rem 1.1rem;
            }
            .dash-hero-title {
                font-size: 1.18rem;
            }
            .dash-kpi-card {
                padding: 0.85rem 0.95rem;
                min-height: 102px;
            }
            .dash-kpi-val {
                font-size: 1.12rem;
            }
            .dash-actions-bar {
                grid-template-columns: 1fr 1fr !important;
                gap: 0.5rem !important;
            }
            .dash-action-btn {
                padding: 0.55rem 0.65rem;
                font-size: 0.75rem;
                min-height: 38px;
            }
            .dash-canvas-container {
                height: 230px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <?php renderAdminSidebar('dashboard'); ?>

        <!-- Main Content -->
        <main class="admin-content">
            <?php renderAdminTopbar($pageTitle); ?>
            
            <div style="padding-top: 0.25rem;">
                
                <!-- ── 1. Executive Welcome Header ── -->
                <div class="dash-hero">
                    <div class="dash-hero-left">
                        <h1 class="dash-hero-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            <span>Store Analytics & Overview</span>
                        </h1>
                        <p class="dash-hero-sub">Welcome back, <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></strong>! Live summary of orders, sales performance, and operations.</p>
                    </div>
                    <div class="dash-hero-badges">
                        <div class="dash-chip">
                            <span class="dash-live-pulse"></span>
                            <span>Live Store Active</span>
                        </div>
                        <div class="dash-chip">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span><?= date('D, j M Y') ?></span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                <div class="alert alert-success" style="margin-bottom: 1.25rem; display: flex; align-items: center; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
                <?php endif; ?>

                <!-- ── 2. 6 Executive Metric KPI Cards ── -->
                <div class="dash-kpi-grid-6">
                    <!-- Total Revenue -->
                    <div class="dash-kpi-card">
                        <div class="dash-kpi-top">
                            <span class="dash-kpi-label">Total Revenue</span>
                            <div class="dash-kpi-bubble teal">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            </div>
                        </div>
                        <div class="dash-kpi-val text-teal"><?= formatPrice($totalRevenue) ?></div>
                        <div class="dash-kpi-meta teal">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            <span>Net Paid</span>
                        </div>
                    </div>

                    <!-- Total Orders -->
                    <a href="<?= BASE_URL ?>/admin/orders" class="dash-kpi-card">
                        <div class="dash-kpi-top">
                            <span class="dash-kpi-label">Total Orders</span>
                            <div class="dash-kpi-bubble blue">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                            </div>
                        </div>
                        <div class="dash-kpi-val"><?= number_format($totalOrders) ?></div>
                        <div class="dash-kpi-meta blue">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span><?= $pendingCount > 0 ? "{$pendingCount} Pending" : "Up to Date" ?></span>
                        </div>
                    </a>

                    <!-- Average Order Value (AOV) -->
                    <div class="dash-kpi-card">
                        <div class="dash-kpi-top">
                            <span class="dash-kpi-label">Avg Order Value</span>
                            <div class="dash-kpi-bubble emerald">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><line x1="12" y1="6" x2="12" y2="8"/><line x1="12" y1="16" x2="12" y2="18"/></svg>
                            </div>
                        </div>
                        <div class="dash-kpi-val"><?= formatPrice($avgOrderValue) ?></div>
                        <div class="dash-kpi-meta teal">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                            <span>Per Paid Order</span>
                        </div>
                    </div>

                    <!-- Fulfillment Rate -->
                    <div class="dash-kpi-card">
                        <div class="dash-kpi-top">
                            <span class="dash-kpi-label">Fulfillment</span>
                            <div class="dash-kpi-bubble amber">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                        </div>
                        <div class="dash-kpi-val"><?= $fulfillmentRate ?>%</div>
                        <div class="dash-kpi-meta amber">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            <span><?= $deliveredCount ?> of <?= $totalOrders ?> Delivered</span>
                        </div>
                    </div>

                    <!-- Catalog Items -->
                    <a href="<?= BASE_URL ?>/admin/products" class="dash-kpi-card">
                        <div class="dash-kpi-top">
                            <span class="dash-kpi-label">Active Catalog</span>
                            <div class="dash-kpi-bubble purple">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                            </div>
                        </div>
                        <div class="dash-kpi-val"><?= number_format($totalProducts) ?></div>
                        <div class="dash-kpi-meta purple">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
                            <span>In Catalog</span>
                        </div>
                    </a>

                    <!-- Registered Users -->
                    <a href="<?= BASE_URL ?>/admin/users" class="dash-kpi-card">
                        <div class="dash-kpi-top">
                            <span class="dash-kpi-label">Customers</span>
                            <div class="dash-kpi-bubble indigo">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                        </div>
                        <div class="dash-kpi-val"><?= number_format($totalUsers) ?></div>
                        <div class="dash-kpi-meta neutral">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            <span>Registered</span>
                        </div>
                    </a>
                </div>

                <!-- ── 3. Operational Quick Action Toolbar ── -->
                <div class="dash-actions-bar">
                    <a href="<?= BASE_URL ?>/admin/order-create" class="dash-action-btn btn-main">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span>Create Order</span>
                    </a>
                    <a href="<?= BASE_URL ?>/admin/products?action=add" class="dash-action-btn btn-sub">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        <span>Add Product</span>
                    </a>
                    <a href="<?= BASE_URL ?>/admin/coupons" class="dash-action-btn btn-sub">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        <span>Coupons</span>
                    </a>
                    <a href="<?= BASE_URL ?>/admin/orders" class="dash-action-btn btn-sub">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span>All Orders</span>
                        <span class="dash-action-badge"><?= $totalOrders ?></span>
                    </a>
                </div>

                <!-- ── 4. Interactive Charts Section ── -->
                <div class="dash-charts-grid">
                    <!-- Chart 1: Revenue & Order Trajectory -->
                    <div class="dash-chart-card">
                        <div class="dash-chart-header">
                            <h3 class="dash-chart-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                                Sales & Orders Performance
                            </h3>
                            <div class="dash-chart-pills">
                                <button type="button" class="dash-chart-pill active" id="btnChartDaily">Daily (14d)</button>
                                <button type="button" class="dash-chart-pill" id="btnChartMonthly">Monthly</button>
                            </div>
                        </div>
                        <div class="dash-canvas-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>

                    <!-- Chart 2: Order Status Pipeline Doughnut -->
                    <div class="dash-chart-card">
                        <div class="dash-chart-header">
                            <h3 class="dash-chart-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 10 10"/></svg>
                                Order Status Pipeline
                            </h3>
                            <span style="font-size: 0.72rem; color: #64748b; font-weight: 700;"><?= $totalOrders ?> Total</span>
                        </div>
                        <div class="dash-canvas-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- ── 5. Payment Methods & Tables Grid ── -->
                <div class="dash-main-grid">
                    <!-- Recent Orders Card -->
                    <div class="admin-card" style="margin-bottom: 0;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                            <h3 class="admin-section-heading" style="margin: 0; font-size: 1.05rem;">Recent Orders</h3>
                            <a href="<?= BASE_URL ?>/admin/orders" style="font-size: 0.8rem; font-weight: 700; color: #0f766e; text-decoration: none;">View All &rarr;</a>
                        </div>
                        <div class="admin-table-wrap">
                            <table class="admin-table admin-table-sm">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th style="text-align: right;">Total</th>
                                        <th style="text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentOrders)): ?>
                                        <tr><td colspan="7" style="text-align: center; color: #94a3b8; padding: 2rem;">No recent orders found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recentOrders as $order): ?>
                                        <?php
                                            $customerName = trim((string)($order['customer_full_name'] ?? ''));
                                            $phone = trim((string)($order['shipping_phone'] ?? ''));
                                            $rawMethod = strtolower((string)($order['payment_method'] ?? 'cod'));
                                            $badgeType = 'cod';
                                            $badgeLabel = 'COD';
                                            if (str_contains($rawMethod, 'bkash')) { $badgeType = 'bkash'; $badgeLabel = 'bKash'; }
                                            elseif (str_contains($rawMethod, 'nagad')) { $badgeType = 'nagad'; $badgeLabel = 'Nagad'; }
                                            elseif (str_contains($rawMethod, 'card') || str_contains($rawMethod, 'ssl')) { $badgeType = 'card'; $badgeLabel = 'Card'; }
                                        ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 700; color: #0f172a; font-size: 0.85rem;"><?= htmlspecialchars($order['order_number']) ?></div>
                                            </td>
                                            <td>
                                                <?php if ($customerName): ?>
                                                    <div style="font-weight: 600; color: #1e293b; font-size: 0.82rem; line-height: 1.2;"><?= htmlspecialchars($customerName) ?></div>
                                                    <?php if ($phone): ?><div style="font-size: 0.72rem; color: #64748b;"><?= htmlspecialchars($phone) ?></div><?php endif; ?>
                                                <?php elseif ($phone): ?>
                                                    <div style="font-weight: 600; color: #1e293b; font-size: 0.82rem;"><?= htmlspecialchars($phone) ?></div>
                                                <?php else: ?>
                                                    <span style="color: #94a3b8; font-size: 0.78rem;">Direct Customer</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span style="font-size: 0.75rem; color: #64748b; font-weight: 500; white-space: nowrap;">
                                                    <?= date('d M, h:i A', strtotime($order['created_at'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="dash-pay-badge <?= $badgeType ?>">
                                                    <?php if ($badgeType === 'bkash'): ?>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                                                    <?php elseif ($badgeType === 'nagad'): ?>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                                                    <?php elseif ($badgeType === 'card'): ?>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                                    <?php else: ?>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg>
                                                    <?php endif; ?>
                                                    <span><?= htmlspecialchars($badgeLabel) ?></span>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" class="admin-form-row-center" style="margin: 0;">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <select name="status" class="form-select admin-status-select" style="font-weight: 700; font-size: 0.74rem; padding: 0.28rem 0.5rem; border-radius: 7px;" onchange="this.form.submit()">
                                                        <?php foreach ($statusMap as $sKey => $sVal): ?>
                                                            <option value="<?= $sKey ?>" <?= $order['status'] === $sKey ? 'selected' : '' ?>><?= htmlspecialchars($sVal['label']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </form>
                                            </td>
                                            <td style="text-align: right; font-weight: 700; color: #0f172a; font-size: 0.86rem; white-space: nowrap;">
                                                <?= formatPrice($order['total']) ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <a href="<?= BASE_URL ?>/admin/order-detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline" style="width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;" title="View Order Details">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Low Stock Alert Card -->
                    <div class="admin-card" style="margin-bottom: 0;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                            <h3 class="admin-section-heading" style="margin: 0; font-size: 1.05rem; color: #b91c1c; display: flex; align-items: center; gap: 6px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                Low Stock Alerts
                            </h3>
                            <a href="<?= BASE_URL ?>/admin/products?stock=low_stock" style="font-size: 0.8rem; font-weight: 700; color: #ef4444; text-decoration: none;">Manage Inventory &rarr;</a>
                        </div>
                        <div class="admin-table-wrap">
                            <table class="admin-table admin-table-sm">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th style="text-align: center;">Stock Left</th>
                                        <th style="text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($lowStockProducts)): ?>
                                        <tr><td colspan="3" style="text-align: center; color: #10b981; padding: 2rem; font-weight: 600;">✅ All products are well stocked!</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($lowStockProducts as $product): ?>
                                        <?php
                                            $imgSrc = !empty($product['main_image']) ? resolveAdminImageSrc($product['main_image']) : '';
                                            $qty = intval($product['stock_quantity']);
                                        ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <?php if ($imgSrc): ?>
                                                        <img src="<?= htmlspecialchars($imgSrc) ?>" alt="" style="width: 36px; height: 36px; border-radius: 6px; object-fit: cover; border: 1px solid #e2e8f0; flex-shrink: 0;">
                                                    <?php else: ?>
                                                        <div style="width: 36px; height: 36px; border-radius: 6px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 0.65rem; font-weight: 700; flex-shrink: 0;">IMG</div>
                                                    <?php endif; ?>
                                                    <div style="display: flex; flex-direction: column; gap: 1px;">
                                                        <div style="font-weight: 700; color: #0f172a; font-size: 0.85rem; line-height: 1.2;"><?= htmlspecialchars($product['name']) ?></div>
                                                        <div style="font-size: 0.74rem; color: #64748b;"><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if ($qty === 0): ?>
                                                    <span class="badge badge-error" style="font-size: 0.75rem; padding: 3px 8px; font-weight: 700;">Out of Stock (0)</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning" style="font-size: 0.75rem; padding: 3px 8px; font-weight: 700;"><?= $qty ?> left</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <a href="<?= BASE_URL ?>/admin/products?action=edit&id=<?= $product['id'] ?>" class="btn btn-sm btn-primary" style="width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;" title="Edit / Restock Product">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ── 6. Payment Method Channel Share ── -->
                <div class="dash-chart-card" style="margin-top: 1.25rem;">
                    <div class="dash-chart-header" style="margin-bottom: 0.5rem;">
                        <h3 class="dash-chart-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            Payment Gateways & Channel Share
                        </h3>
                        <span style="font-size: 0.72rem; color: #64748b; font-weight: 600;"><?= count($paymentBreakdown) ?> Active Methods</span>
                    </div>
                    <div class="dash-breakdown-list">
                        <?php 
                        $fillColors = ['#0f766e', '#3b82f6', '#ec4899', '#f59e0b'];
                        foreach ($paymentBreakdown as $idx => $pb):
                            $c = $fillColors[$idx % count($fillColors)];
                            $type = $pb['type'] ?? 'cod';
                        ?>
                        <div class="dash-breakdown-item">
                            <div class="dash-breakdown-header">
                                <div class="dash-pay-title">
                                    <span class="dash-pay-icon-bubble <?= $type ?>">
                                        <?php if ($type === 'bkash'): ?>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                                        <?php elseif ($type === 'nagad'): ?>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                                        <?php elseif ($type === 'rocket'): ?>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                        <?php elseif ($type === 'card'): ?>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                        <?php else: ?>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                                        <?php endif; ?>
                                    </span>
                                    <span><?= htmlspecialchars($pb['name']) ?> <span style="font-weight: 500; color: #64748b;">(<?= $pb['count'] ?> <?= $pb['count'] === 1 ? 'order' : 'orders' ?>)</span></span>
                                </div>
                                <span>Tk <?= number_format($pb['revenue'], 2) ?> &middot; <strong><?= $pb['percentage'] ?>%</strong></span>
                            </div>
                            <div class="dash-breakdown-bar">
                                <div class="dash-breakdown-fill" style="width: <?= $pb['percentage'] ?>%; background: <?= $c ?>;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Chart Configuration Script -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof Chart === 'undefined') return;

        // ── Data Packages ──────────────────────────────────────
        const dailyData = {
            labels: <?= json_encode($dailyLabels) ?>,
            revenue: <?= json_encode($dailyRevenue) ?>,
            orders: <?= json_encode($dailyOrders) ?>
        };

        const monthlyData = {
            labels: <?= json_encode($monthlyLabels) ?>,
            revenue: <?= json_encode($monthlyRevenue) ?>,
            orders: <?= json_encode($monthlyOrders) ?>
        };

        // ── Chart 1: Sales Area Chart ──────────────────────────
        const ctxSales = document.getElementById('salesChart');
        if (ctxSales) {
            const chartSales = new Chart(ctxSales, {
                type: 'line',
                data: {
                    labels: dailyData.labels,
                    datasets: [
                        {
                            label: 'Revenue (Tk)',
                            data: dailyData.revenue,
                            borderColor: '#0f766e',
                            backgroundColor: 'rgba(15, 118, 110, 0.08)',
                            borderWidth: 2.5,
                            fill: true,
                            tension: 0.35,
                            pointBackgroundColor: '#0f766e',
                            pointHoverRadius: 6,
                            pointRadius: 3,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Orders',
                            data: dailyData.orders,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.04)',
                            borderWidth: 2,
                            borderDash: [4, 4],
                            fill: false,
                            tension: 0.35,
                            pointBackgroundColor: '#3b82f6',
                            pointHoverRadius: 5,
                            pointRadius: 2.5,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'end',
                            labels: {
                                boxWidth: 12,
                                font: { size: 11, weight: '600' },
                                color: '#475569'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            padding: 10,
                            titleFont: { size: 12, weight: '700' },
                            bodyFont: { size: 11 },
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.label.includes('Revenue')) {
                                        return ' Revenue: Tk ' + Number(context.parsed.y).toLocaleString();
                                    }
                                    return ' Orders: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 10 }, color: '#94a3b8' }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            grid: { color: '#f1f5f9' },
                            ticks: {
                                font: { size: 10 },
                                color: '#94a3b8',
                                callback: val => 'Tk ' + (val >= 1000 ? (val/1000).toFixed(0) + 'k' : val)
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: {
                                font: { size: 10 },
                                color: '#94a3b8',
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            // Toggle daily / monthly
            const btnDaily   = document.getElementById('btnChartDaily');
            const btnMonthly = document.getElementById('btnChartMonthly');

            if (btnDaily && btnMonthly) {
                btnDaily.addEventListener('click', () => {
                    btnDaily.classList.add('active');
                    btnMonthly.classList.remove('active');
                    chartSales.data.labels = dailyData.labels;
                    chartSales.data.datasets[0].data = dailyData.revenue;
                    chartSales.data.datasets[1].data = dailyData.orders;
                    chartSales.update();
                });

                btnMonthly.addEventListener('click', () => {
                    btnMonthly.classList.add('active');
                    btnDaily.classList.remove('active');
                    chartSales.data.labels = monthlyData.labels;
                    chartSales.data.datasets[0].data = monthlyData.revenue;
                    chartSales.data.datasets[1].data = monthlyData.orders;
                    chartSales.update();
                });
            }
        }

        // ── Chart 2: Order Status Doughnut Chart ───────────────
        const ctxStatus = document.getElementById('statusChart');
        if (ctxStatus) {
            new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($doughnutLabels) ?>,
                    datasets: [{
                        data: <?= json_encode($doughnutData) ?>,
                        backgroundColor: <?= json_encode($doughnutColors) ?>,
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 10,
                                font: { size: 10, weight: '600' },
                                color: '#475569',
                                padding: 12
                            }
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            padding: 8,
                            callbacks: {
                                label: function(context) {
                                    const val = context.parsed;
                                    const total = context.dataset.data.reduce((a,b) => a + b, 0);
                                    const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                    return ` ${context.label}: ${val} (${pct}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
    });
    </script>
</body>
</html>
