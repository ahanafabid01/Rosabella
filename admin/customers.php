<?php
/**
 * Rosabella – Executive Customers Intelligence & Management Center
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

$db = getDB();
$message = '';
$error = '';

// ── Export CSV Handler ────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $search       = sanitize($_GET['search'] ?? '');
    $statusFilter = sanitize($_GET['status'] ?? '');
    
    $where = ["u.role = 'customer'"];
    $params = [];
    if ($search) {
        $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.city LIKE ?)";
        $term = "%$search%";
        $params = array_merge($params, [$term, $term, $term, $term, $term]);
    }
    if ($statusFilter) {
        $where[] = "u.status = ?";
        $params[] = $statusFilter;
    }
    $whereSql = implode(' AND ', $where);

    $sql = "
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.city, u.upazila, u.address, u.status, u.created_at,
               COUNT(o.id) as total_orders,
               COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total ELSE 0 END), 0) as total_spent,
               MAX(o.created_at) as last_order_date
        FROM users u
        LEFT JOIN orders o ON o.user_id = u.id
        WHERE $whereSql
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rosabella_customers_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Customer ID', 'First Name', 'Last Name', 'Email', 'Phone', 'City', 'Upazila', 'Address', 'Status', 'Total Orders', 'Total Spend (Tk)', 'Last Order Date', 'Joined Date']);

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['id'],
            $r['first_name'],
            $r['last_name'],
            $r['email'],
            $r['phone'] ?? '',
            $r['city'] ?? '',
            $r['upazila'] ?? '',
            $r['address'] ?? '',
            ucfirst($r['status']),
            $r['total_orders'],
            number_format((float)$r['total_spent'], 2, '.', ''),
            $r['last_order_date'] ?? 'Never',
            date('Y-m-d H:i', strtotime($r['created_at']))
        ]);
    }
    fclose($output);
    exit;
}

// ── Handle Customer POST Actions (Add / Edit / Delete / Status) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';

    // 1. Add New Customer
    if ($action === 'create_customer') {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName  = sanitize($_POST['last_name'] ?? '');
        $email     = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone     = sanitize($_POST['phone'] ?? '');
        $password  = $_POST['password'] ?? '';
        $city      = sanitize($_POST['city'] ?? '');
        $upazila   = sanitize($_POST['upazila'] ?? '');
        $address   = sanitize($_POST['address'] ?? '');
        $status    = in_array($_POST['status'] ?? '', ['active', 'inactive', 'banned']) ? $_POST['status'] : 'active';

        if (!$firstName || !$email) {
            $error = 'First name and a valid email address are required.';
        } else {
            $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $error = 'A customer with this email address already exists.';
            } else {
                $pwdHash = password_hash($password ?: 'Rosabella@' . rand(1000, 9999), PASSWORD_DEFAULT);
                $ins = $db->prepare("
                    INSERT INTO users (first_name, last_name, email, password, phone, city, upazila, address, role, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'customer', ?, NOW())
                ");
                if ($ins->execute([$firstName, $lastName, $email, $pwdHash, $phone, $city, $upazila, $address, $status])) {
                    $message = "Customer '{$firstName} {$lastName}' registered successfully.";
                } else {
                    $error = 'Failed to create customer account.';
                }
            }
        }
    }

    // 2. Edit Customer
    if ($action === 'edit_customer') {
        $customerId = intval($_POST['customer_id'] ?? 0);
        $firstName  = sanitize($_POST['first_name'] ?? '');
        $lastName   = sanitize($_POST['last_name'] ?? '');
        $email      = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone      = sanitize($_POST['phone'] ?? '');
        $city       = sanitize($_POST['city'] ?? '');
        $upazila    = sanitize($_POST['upazila'] ?? '');
        $address    = sanitize($_POST['address'] ?? '');
        $status     = in_array($_POST['status'] ?? '', ['active', 'inactive', 'banned']) ? $_POST['status'] : 'active';
        $newPass    = $_POST['new_password'] ?? '';

        if ($customerId <= 0 || !$firstName || !$email) {
            $error = 'Invalid customer details provided.';
        } else {
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->execute([$email, $customerId]);
            if ($chk->fetch()) {
                $error = 'Another account is already using this email address.';
            } else {
                if ($newPass !== '') {
                    $pwdHash = password_hash($newPass, PASSWORD_DEFAULT);
                    $upd = $db->prepare("
                        UPDATE users SET first_name=?, last_name=?, email=?, phone=?, city=?, upazila=?, address=?, status=?, password=? WHERE id=?
                    ");
                    $upd->execute([$firstName, $lastName, $email, $phone, $city, $upazila, $address, $status, $pwdHash, $customerId]);
                } else {
                    $upd = $db->prepare("
                        UPDATE users SET first_name=?, last_name=?, email=?, phone=?, city=?, upazila=?, address=?, status=? WHERE id=?
                    ");
                    $upd->execute([$firstName, $lastName, $email, $phone, $city, $upazila, $address, $status, $customerId]);
                }
                $message = 'Customer profile updated successfully.';
            }
        }
    }

    // 3. Quick Status Toggle
    if ($action === 'update_status') {
        $customerId = intval($_POST['customer_id'] ?? 0);
        $status     = in_array($_POST['status'] ?? '', ['active', 'inactive', 'banned']) ? $_POST['status'] : 'active';
        if ($customerId > 0) {
            $upd = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
            $upd->execute([$status, $customerId]);
            $message = 'Customer status updated to ' . ucfirst($status) . '.';
        }
    }

    // 4. Delete Customer
    if ($action === 'delete_customer') {
        $customerId = intval($_POST['customer_id'] ?? 0);
        if ($customerId > 0) {
            $orderCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE user_id = $customerId")->fetchColumn();
            if ($orderCount > 0) {
                $db->prepare("UPDATE users SET status = 'banned' WHERE id = ?")->execute([$customerId]);
                $message = "Customer has $orderCount associated orders and has been deactivated/banned rather than deleted.";
            } else {
                $db->prepare("DELETE FROM users WHERE id = ? AND role = 'customer'")->execute([$customerId]);
                $message = 'Customer account deleted successfully.';
            }
        }
    }
}

// ── Overall Customer KPIs ─────────────────────────────────────────────────────
$totalCustomers = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$activeCustomers = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM orders WHERE user_id IS NOT NULL")->fetchColumn();
$totalCustomerLTV = (float)$db->query("
    SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'paid' AND user_id IS NOT NULL
")->fetchColumn();
$repeatCustomers = (int)$db->query("
    SELECT COUNT(*) FROM (
        SELECT user_id FROM orders WHERE user_id IS NOT NULL GROUP BY user_id HAVING COUNT(id) >= 2
    ) as repeats
")->fetchColumn();

// ── Filter & Search Query ─────────────────────────────────────────────────────
$search       = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$orderFilter  = sanitize($_GET['order_filter'] ?? '');
$sortBy       = sanitize($_GET['sort'] ?? 'newest');

$where = ["u.role = 'customer'"];
$params = [];

if ($search !== '') {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.city LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if ($statusFilter !== '') {
    $where[] = "u.status = ?";
    $params[] = $statusFilter;
}

$having = [];
if ($orderFilter === 'with_orders') {
    $having[] = "COUNT(o.id) > 0";
} elseif ($orderFilter === 'repeat') {
    $having[] = "COUNT(o.id) >= 2";
} elseif ($orderFilter === 'no_orders') {
    $having[] = "COUNT(o.id) = 0";
}

$whereSql = implode(' AND ', $where);
$havingSql = !empty($having) ? 'HAVING ' . implode(' AND ', $having) : '';

// Sorting Map
$sortSql = match ($sortBy) {
    'oldest'      => 'u.created_at ASC',
    'ltv_desc'    => 'total_spent DESC',
    'orders_desc' => 'total_orders DESC',
    'name_asc'    => 'u.first_name ASC, u.last_name ASC',
    default       => 'u.created_at DESC',
};

// Pagination
$perPage = max(10, min(100, intval($_GET['per_page'] ?? 15)));
$page    = max(1, intval($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Count Total Filtered Rows
$countQuery = "
    SELECT COUNT(*) FROM (
        SELECT u.id
        FROM users u
        LEFT JOIN orders o ON o.user_id = u.id
        WHERE $whereSql
        GROUP BY u.id
        $havingSql
    ) as filtered_count
";
$cntStmt = $db->prepare($countQuery);
$cntStmt->execute($params);
$totalFiltered = (int)$cntStmt->fetchColumn();
$totalPages = max(1, ceil($totalFiltered / $perPage));
if ($page > $totalPages) $page = $totalPages;

// Fetch Customers Page
$dataQuery = "
    SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.city, u.upazila, u.address, u.status, u.created_at,
           COUNT(o.id) as total_orders,
           COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total ELSE 0 END), 0) as total_spent,
           MAX(o.created_at) as last_order_date
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    WHERE $whereSql
    GROUP BY u.id
    $havingSql
    ORDER BY $sortSql
    LIMIT $perPage OFFSET $offset
";
$stmt = $db->prepare($dataQuery);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getInitials(string $first, string $last): string {
    $f = mb_substr(trim($first), 0, 1);
    $l = mb_substr(trim($last), 0, 1);
    return strtoupper(($f . $l) ?: 'C');
}

$pageTitle = 'Customers Intelligence';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $fav = getSetting('site_favicon'); if ($fav): ?>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . '/' . htmlspecialchars($fav) ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> &mdash; Rosabella Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .cust-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .cust-kpi-card {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.15rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }
        .cust-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(15,23,42,0.05);
            border-color: #cbd5e1;
        }
        .cust-kpi-info { display: flex; flex-direction: column; gap: 0.25rem; }
        .cust-kpi-label { font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .cust-kpi-val { font-size: 1.45rem; font-weight: 800; color: #0f172a; line-height: 1.15; }
        .cust-kpi-sub { font-size: 0.72rem; color: #94a3b8; font-weight: 600; }
        .cust-kpi-icon {
            width: 42px;
            height: 42px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .cust-kpi-icon.teal   { background: #ccfbf1; color: #0f766e; }
        .cust-kpi-icon.blue   { background: #dbeafe; color: #1d4ed8; }
        .cust-kpi-icon.purple { background: #f3e8ff; color: #7e22ce; }
        .cust-kpi-icon.amber  { background: #fef3c7; color: #b45309; }
        .cust-kpi-icon svg    { width: 20px; height: 20px; }

        .cust-avatar {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: linear-gradient(135deg, #0f766e, #14b8a6);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.8rem;
            flex-shrink: 0;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 5px rgba(15, 118, 110, 0.2);
        }

        .cust-filter-bar {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.85rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .cust-filter-inputs {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.65rem;
            flex: 1;
        }

        .cust-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .cust-modal-backdrop.show { display: flex; }
        .cust-modal-window {
            background: #ffffff;
            border-radius: 16px;
            width: 100%;
            max-width: 580px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes modalPop {
            from { transform: scale(0.95); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }

        @media (max-width: 1024px) {
            .cust-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .cust-kpi-grid { grid-template-columns: 1fr; }
            .cust-filter-bar { flex-direction: column; align-items: stretch; }
            .cust-filter-inputs { flex-direction: column; align-items: stretch; }
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

            <div style="padding-top: 0.25rem;">
                
                <!-- Alerts -->
                <?php if ($message): ?>
                <div class="alert alert-success" style="margin-bottom: 1.25rem; display: flex; align-items: center; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom: 1.25rem; display: flex; align-items: center; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <!-- ── 1. KPI Summary Cards ── -->
                <div class="cust-kpi-grid">
                    <!-- Total Customers -->
                    <div class="cust-kpi-card">
                        <div class="cust-kpi-info">
                            <span class="cust-kpi-label">Registered Shoppers</span>
                            <div class="cust-kpi-val"><?= number_format($totalCustomers) ?></div>
                            <span class="cust-kpi-sub">Total Customer Accounts</span>
                        </div>
                        <div class="cust-kpi-icon teal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                    </div>

                    <!-- Active Buyers -->
                    <div class="cust-kpi-card">
                        <div class="cust-kpi-info">
                            <span class="cust-kpi-label">Active Buyers</span>
                            <div class="cust-kpi-val"><?= number_format($activeCustomers) ?></div>
                            <span class="cust-kpi-sub"><?= $totalCustomers > 0 ? round(($activeCustomers / $totalCustomers) * 100, 1) : 0 ?>% Conversion Rate</span>
                        </div>
                        <div class="cust-kpi-icon blue">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        </div>
                    </div>

                    <!-- Total Customer LTV -->
                    <div class="cust-kpi-card">
                        <div class="cust-kpi-info">
                            <span class="cust-kpi-label">Total Customer LTV</span>
                            <div class="cust-kpi-val"><?= formatPrice($totalCustomerLTV) ?></div>
                            <span class="cust-kpi-sub">Net Paid Order Spend</span>
                        </div>
                        <div class="cust-kpi-icon purple">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                    </div>

                    <!-- Repeat Customers -->
                    <div class="cust-kpi-card">
                        <div class="cust-kpi-info">
                            <span class="cust-kpi-label">Repeat Buyers</span>
                            <div class="cust-kpi-val"><?= number_format($repeatCustomers) ?></div>
                            <span class="cust-kpi-sub"><?= $activeCustomers > 0 ? round(($repeatCustomers / $activeCustomers) * 100, 1) : 0 ?>% of Active Shoppers</span>
                        </div>
                        <div class="cust-kpi-icon amber">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                        </div>
                    </div>
                </div>

                <!-- ── 2. Filter & Actions Toolbar ── -->
                <div class="cust-filter-bar">
                    <form method="GET" class="cust-filter-inputs">
                        <!-- Search Box -->
                        <div style="position: relative; min-width: 220px; flex: 1;">
                            <input type="text" name="search" class="form-input" style="padding-left: 32px; font-size: 0.82rem;" placeholder="Search customer name, email, phone, city..." value="<?= htmlspecialchars($search) ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </div>

                        <!-- Status Filter -->
                        <select name="status" class="form-select" style="font-size: 0.82rem; min-width: 130px;" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="banned" <?= $statusFilter === 'banned' ? 'selected' : '' ?>>Banned</option>
                        </select>

                        <!-- Order Activity Filter -->
                        <select name="order_filter" class="form-select" style="font-size: 0.82rem; min-width: 140px;" onchange="this.form.submit()">
                            <option value="">All Customers</option>
                            <option value="with_orders" <?= $orderFilter === 'with_orders' ? 'selected' : '' ?>>With Orders (1+)</option>
                            <option value="repeat" <?= $orderFilter === 'repeat' ? 'selected' : '' ?>>Repeat Buyers (2+)</option>
                            <option value="no_orders" <?= $orderFilter === 'no_orders' ? 'selected' : '' ?>>No Orders Placed</option>
                        </select>

                        <!-- Sort By -->
                        <select name="sort" class="form-select" style="font-size: 0.82rem; min-width: 140px;" onchange="this.form.submit()">
                            <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest Joined</option>
                            <option value="ltv_desc" <?= $sortBy === 'ltv_desc' ? 'selected' : '' ?>>Highest Spend (LTV)</option>
                            <option value="orders_desc" <?= $sortBy === 'orders_desc' ? 'selected' : '' ?>>Most Orders</option>
                            <option value="name_asc" <?= $sortBy === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                            <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest Joined</option>
                        </select>

                        <button type="submit" class="btn btn-secondary" style="font-size: 0.82rem; padding: 0.45rem 0.85rem;">Filter</button>
                        <?php if ($search || $statusFilter || $orderFilter || $sortBy !== 'newest'): ?>
                        <a href="<?= BASE_URL ?>/admin/customers" class="btn btn-outline" style="font-size: 0.82rem; padding: 0.45rem 0.85rem;" title="Reset filters">Reset</a>
                        <?php endif; ?>
                    </form>

                    <div style="display: flex; align-items: center; gap: 8px;">
                        <!-- Export CSV -->
                        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline" style="font-size: 0.82rem; padding: 0.5rem 0.85rem; display: inline-flex; align-items: center; gap: 5px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <span>Export CSV</span>
                        </a>

                        <!-- Add Customer Modal Trigger -->
                        <button type="button" class="btn btn-primary" onclick="openAddCustomerModal()" style="font-size: 0.82rem; padding: 0.5rem 0.95rem; display: inline-flex; align-items: center; gap: 5px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            <span>Add Customer</span>
                        </button>
                    </div>
                </div>

                <!-- ── 3. Customers Data Table ── -->
                <div class="admin-card">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                        <h3 class="admin-section-heading" style="margin: 0; font-size: 1.05rem;">
                            Customer Directory <span style="font-size: 0.76rem; color: #64748b; font-weight: 600; margin-left: 6px;">(Showing <?= count($customers) ?> of <?= $totalFiltered ?>)</span>
                        </h3>
                    </div>

                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Contact & Location</th>
                                    <th style="text-align: center;">Orders</th>
                                    <th style="text-align: right;">Total Spend (LTV)</th>
                                    <th style="text-align: center;">Status</th>
                                    <th>Joined Date</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($customers)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: #94a3b8; padding: 3rem 1rem;">
                                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.8" style="display: block; margin: 0 auto 8px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                            No customers found matching your search and filter criteria.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($customers as $c): 
                                        $initials = getInitials($c['first_name'], $c['last_name']);
                                        $fullName = trim($c['first_name'] . ' ' . $c['last_name']);
                                        $ordersCnt = (int)$c['total_orders'];
                                        $spent = (float)$c['total_spent'];
                                        $st = $c['status'];
                                    ?>
                                    <tr>
                                        <!-- Customer Name & Email -->
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div class="cust-avatar"><?= $initials ?></div>
                                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.86rem;"><?= htmlspecialchars($fullName) ?></div>
                                                    <div style="font-size: 0.74rem; color: #64748b;"><?= htmlspecialchars($c['email']) ?></div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Contact & Location -->
                                        <td>
                                            <div style="display: flex; flex-direction: column; gap: 2px;">
                                                <?php if (!empty($c['phone'])): ?>
                                                    <div style="font-size: 0.8rem; font-weight: 600; color: #334155; display: inline-flex; align-items: center; gap: 4px;">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                                        <a href="tel:<?= htmlspecialchars($c['phone']) ?>" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($c['phone']) ?></a>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="font-size: 0.74rem; color: #94a3b8;">No phone added</span>
                                                <?php endif; ?>
                                                
                                                <div style="font-size: 0.72rem; color: #64748b; display: inline-flex; align-items: center; gap: 4px;">
                                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                                    <span><?= htmlspecialchars(implode(', ', array_filter([$c['upazila'], $c['city']]))) ?: 'Bangladesh' ?></span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Orders Count -->
                                        <td style="text-align: center;">
                                            <?php if ($ordersCnt > 0): ?>
                                                <span class="badge badge-info" style="font-weight: 700; padding: 3px 8px; font-size: 0.74rem;">
                                                    <?= $ordersCnt ?> <?= $ordersCnt === 1 ? 'Order' : 'Orders' ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-size: 0.76rem;">0 Orders</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Lifetime Value -->
                                        <td style="text-align: right; font-weight: 800; color: #0f766e; font-size: 0.88rem;">
                                            <?= formatPrice($spent) ?>
                                        </td>

                                        <!-- Live Status Dropdown -->
                                        <td style="text-align: center;">
                                            <form method="POST" style="margin: 0; display: inline-block;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                                <select name="status" class="form-select admin-status-select" style="font-weight: 700; font-size: 0.74rem; padding: 0.25rem 0.5rem; border-radius: 6px;" onchange="this.form.submit()">
                                                    <option value="active" <?= $st === 'active' ? 'selected' : '' ?>>Active</option>
                                                    <option value="inactive" <?= $st === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                    <option value="banned" <?= $st === 'banned' ? 'selected' : '' ?>>Banned</option>
                                                </select>
                                            </form>
                                        </td>

                                        <!-- Joined Date -->
                                        <td>
                                            <span style="font-size: 0.76rem; color: #64748b; font-weight: 500;">
                                                <?= date('M j, Y', strtotime($c['created_at'])) ?>
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        <td style="text-align: right;">
                                            <div style="display: inline-flex; align-items: center; gap: 5px;">
                                                <!-- Create Order for Customer -->
                                                <a href="<?= BASE_URL ?>/admin/order-create?phone=<?= urlencode($c['phone'] ?? '') ?>&name=<?= urlencode($fullName) ?>&city=<?= urlencode($c['city'] ?? '') ?>" class="btn btn-sm btn-outline" style="width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;" title="Create Order for Customer">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/><path d="M12 8v6M9 11h6"/></svg>
                                                </a>

                                                <!-- Edit Customer Trigger -->
                                                <button type="button" class="btn btn-sm btn-outline" style="width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;" onclick="openEditCustomerModal(<?= htmlspecialchars(json_encode($c)) ?>)" title="Edit Customer">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </button>

                                                <!-- Delete / Ban Customer -->
                                                <form method="POST" style="margin: 0; display: inline-block;" onsubmit="return confirm('Are you sure you want to delete or deactivate this customer?');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="delete_customer">
                                                    <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline" style="width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; color: #ef4444; border-color: #fca5a5;" title="Delete / Deactivate Customer">
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Bar -->
                    <?php if ($totalPages > 1): ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #f1f5f9; flex-wrap: wrap; gap: 0.75rem;">
                        <span style="font-size: 0.78rem; color: #64748b;">
                            Showing page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (<?= $totalFiltered ?> customers)
                        </span>
                        <div style="display: inline-flex; gap: 4px;">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-sm btn-outline">&laquo; Prev</a>
                            <?php endif; ?>

                            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>" style="min-width: 32px; text-align: center;">
                                    <?= $p ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-sm btn-outline">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <!-- ── 4. Add Customer Modal ── -->
    <div id="addCustomerModal" class="cust-modal-backdrop">
        <div class="cust-modal-window">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between;">
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #0f172a;">Add New Customer</h3>
                <button type="button" onclick="closeModal('addCustomerModal')" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #64748b;">&times;</button>
            </div>
            <form method="POST" style="padding: 1.25rem 1.5rem;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_customer">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">First Name *</label>
                        <input type="text" name="first_name" class="form-input" required placeholder="e.g. John">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Last Name</label>
                        <input type="text" name="last_name" class="form-input" placeholder="e.g. Doe">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Email Address *</label>
                        <input type="email" name="email" class="form-input" required placeholder="john@example.com">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Phone Number</label>
                        <input type="text" name="phone" class="form-input" placeholder="017XXXXXXXX">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">City / District</label>
                        <input type="text" name="city" class="form-input" placeholder="e.g. Dhaka">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Upazila / Area</label>
                        <input type="text" name="upazila" class="form-input" placeholder="e.g. Dhanmondi">
                    </div>
                </div>

                <div style="margin-bottom: 0.85rem;">
                    <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Delivery Address</label>
                    <textarea name="address" class="form-input" rows="2" placeholder="House #, Road #, Sector..."></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 1.25rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Account Password</label>
                        <input type="password" name="password" class="form-input" placeholder="Leave blank for auto-generate">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Account Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="banned">Banned</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 8px; border-top: 1px solid #f1f5f9; padding-top: 1rem;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addCustomerModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Customer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── 5. Edit Customer Modal ── -->
    <div id="editCustomerModal" class="cust-modal-backdrop">
        <div class="cust-modal-window">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between;">
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #0f172a;">Edit Customer Profile</h3>
                <button type="button" onclick="closeModal('editCustomerModal')" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #64748b;">&times;</button>
            </div>
            <form method="POST" style="padding: 1.25rem 1.5rem;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="edit_customer">
                <input type="hidden" name="customer_id" id="edit_customer_id">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">First Name *</label>
                        <input type="text" name="first_name" id="edit_first_name" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Last Name</label>
                        <input type="text" name="last_name" id="edit_last_name" class="form-input">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Email Address *</label>
                        <input type="email" name="email" id="edit_email" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Phone Number</label>
                        <input type="text" name="phone" id="edit_phone" class="form-input">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">City / District</label>
                        <input type="text" name="city" id="edit_city" class="form-input">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Upazila / Area</label>
                        <input type="text" name="upazila" id="edit_upazila" class="form-input">
                    </div>
                </div>

                <div style="margin-bottom: 0.85rem;">
                    <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Delivery Address</label>
                    <textarea name="address" id="edit_address" class="form-input" rows="2"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 1.25rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">New Password (Optional)</label>
                        <input type="password" name="new_password" class="form-input" placeholder="Leave empty to keep current">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="banned">Banned</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 8px; border-top: 1px solid #f1f5f9; padding-top: 1rem;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editCustomerModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <?php renderAdminScripts(); ?>
    <script>
    function openAddCustomerModal() {
        document.getElementById('addCustomerModal').classList.add('show');
    }
    function openEditCustomerModal(customer) {
        document.getElementById('edit_customer_id').value = customer.id;
        document.getElementById('edit_first_name').value = customer.first_name || '';
        document.getElementById('edit_last_name').value = customer.last_name || '';
        document.getElementById('edit_email').value = customer.email || '';
        document.getElementById('edit_phone').value = customer.phone || '';
        document.getElementById('edit_city').value = customer.city || '';
        document.getElementById('edit_upazila').value = customer.upazila || '';
        document.getElementById('edit_address').value = customer.address || '';
        document.getElementById('edit_status').value = customer.status || 'active';
        document.getElementById('editCustomerModal').classList.add('show');
    }
    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }
    document.querySelectorAll('.cust-modal-backdrop').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.classList.remove('show');
        });
    });
    </script>
</body>
</html>
