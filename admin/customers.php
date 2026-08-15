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
    $orderFilter  = sanitize($_GET['order_filter'] ?? '');
    
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

    $sql = "
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.city, u.upazila, u.address, u.status, u.created_at,
               COUNT(o.id) as total_orders,
               COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total ELSE 0 END), 0) as total_spent,
               MAX(o.created_at) as last_order_date
        FROM users u
        LEFT JOIN orders o ON o.user_id = u.id
        WHERE $whereSql
        GROUP BY u.id
        $havingSql
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
$totalCustomers  = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$activeCustomers = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM orders WHERE user_id IS NOT NULL")->fetchColumn();
$newThisMonth    = (int)$db->query("
    SELECT COUNT(*) FROM users WHERE role = 'customer' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
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
$joinedFilter = sanitize($_GET['joined'] ?? '');
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

if ($joinedFilter === 'month') {
    $where[] = "u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
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

// Active filter counter for badge
$activeFilterCount = 0;
if ($statusFilter !== '') $activeFilterCount++;
if ($orderFilter !== '') $activeFilterCount++;
if ($joinedFilter !== '') $activeFilterCount++;
if ($sortBy !== 'newest') $activeFilterCount++;

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

$pageTitle = 'Customers';
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        /* ── Typography & Header Refinements ── */
        .cust-header-wrap {
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .cust-page-title {
            font-size: 1.35rem;
            font-weight: 600;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin: 0;
        }
        .cust-page-sub {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 400;
            margin-top: 2px;
        }

        /* ── Modern Sleek KPI Cards ── */
        .cust-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.85rem;
            margin-bottom: 1.25rem;
        }
        .cust-kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.85rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            min-width: 0;
        }
        .cust-kpi-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 2px 8px rgba(15,23,42,0.04);
        }
        .cust-kpi-card.active-border {
            border-color: var(--color-primary, #0f766e) !important;
            background: #fcfefe;
        }
        .cust-kpi-text {
            min-width: 0;
            flex: 1;
            padding-right: 6px;
        }
        .cust-kpi-val {
            font-size: 1.18rem;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.2;
            letter-spacing: -0.01em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .cust-kpi-label {
            font-size: 0.74rem;
            font-weight: 450;
            color: #64748b;
            margin-top: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .cust-kpi-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .cust-kpi-icon.teal   { background: #f0fdfa; color: #0f766e; }
        .cust-kpi-icon.blue   { background: #eff6ff; color: #2563eb; }
        .cust-kpi-icon.purple { background: #f5f3ff; color: #7c3aed; }
        .cust-kpi-icon.amber  { background: #fffbeb; color: #d97706; }
        .cust-kpi-icon svg    { width: 17px; height: 17px; }

        /* ── Multi-Filter Toolbar ── */
        .cust-filter-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 1.25rem;
        }
        .cust-filter-form {
            display: flex;
            flex-direction: column;
            width: 100%;
        }
        .cust-filter-top-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
        }
        .cust-filter-search {
            position: relative;
            flex: 1 1 auto;
            min-width: 180px;
        }
        .filter-toggle-btn {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 5px;
            height: 36px;
            padding: 0 12px;
            font-size: 0.82rem;
            font-weight: 500;
            border-radius: 7px;
            white-space: nowrap;
            flex-shrink: 0;
            cursor: pointer;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #334155;
        }
        .filter-toggle-btn:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }
        .cust-filter-drawer {
            display: none;
            width: 100%;
            padding-top: 10px;
            border-top: 1px dashed #e2e8f0;
            margin-top: 8px;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        .cust-filter-drawer.active {
            display: flex !important;
        }
        .cust-filter-select {
            height: 36px;
            font-size: 0.82rem;
            font-weight: 400;
            padding: 0 0.65rem;
            border-radius: 7px;
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            color: #334155;
            flex: 1 1 130px;
            min-width: 120px;
        }
        .cust-filter-actions {
            display: flex;
            gap: 6px;
            margin-left: auto;
            flex-shrink: 0;
        }

        /* ── Customer Table & Avatars ── */
        .cust-avatar {
            width: 34px;
            height: 34px;
            border-radius: 7px;
            background: #f0fdfa;
            color: #0f766e;
            border: 1px solid #ccfbf1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.78rem;
            flex-shrink: 0;
            letter-spacing: 0.3px;
        }
        .cust-name {
            font-weight: 500;
            color: #0f172a;
            font-size: 0.88rem;
            line-height: 1.3;
        }
        .cust-email {
            font-size: 0.76rem;
            color: #64748b;
            font-weight: 400;
        }

        /* ── Modals ── */
        .cust-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(3px);
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .cust-modal-backdrop.show { display: flex; }
        .cust-modal-window {
            background: #ffffff;
            border-radius: 12px;
            width: 100%;
            max-width: 540px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
            animation: modalPop 0.15s ease-out;
        }
        @keyframes modalPop {
            from { transform: scale(0.97); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }

        @media (max-width: 860px) {
            .cust-kpi-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.65rem !important;
            }
            .cust-header-wrap {
                flex-direction: column;
                align-items: stretch;
            }
            .cust-header-actions {
                display: flex;
                width: 100%;
                gap: 8px;
            }
            .cust-header-actions .btn {
                flex: 1;
                justify-content: center;
            }
        }
        @media (max-width: 640px) {
            .cust-kpi-card {
                padding: 0.7rem 0.8rem;
            }
            .cust-kpi-val {
                font-size: 1.05rem;
            }
            .cust-filter-drawer.active {
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 6px !important;
            }
            .cust-filter-select {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                flex: none !important;
                height: 36px !important;
                font-size: 0.8rem !important;
                padding: 0 6px !important;
            }
            .cust-filter-actions {
                grid-column: span 2 !important;
                margin-left: 0 !important;
                width: 100% !important;
                display: flex !important;
                gap: 6px !important;
            }
            .cust-filter-actions button, .cust-filter-actions a {
                flex: 1 !important;
                height: 36px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                text-align: center !important;
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

            <!-- ── Top Header Toolbar ── -->
            <div class="cust-header-wrap">
                <div>
                    <h1 class="cust-page-title">Customers</h1>
                    <div class="cust-page-sub">Manage registered shoppers, monitor lifetime spend, and track orders.</div>
                </div>
                <div class="cust-header-actions" style="display: flex; align-items: center; gap: 8px;">
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline" style="height: 36px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; font-size: 0.82rem; border-radius: 7px; padding: 0 12px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <span>Export CSV</span>
                    </a>
                    <button type="button" class="btn btn-primary" onclick="openAddCustomerModal()" style="height: 36px; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; font-size: 0.82rem; border-radius: 7px; padding: 0 14px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span>Add Customer</span>
                    </button>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($message): ?>
            <div class="alert alert-success" style="margin-bottom: 1.25rem; display: flex; align-items: center; gap: 8px; font-size: 0.84rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.25rem; display: flex; align-items: center; gap: 8px; font-size: 0.84rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <!-- ── 1. Sleek KPI Cards ── -->
            <div class="cust-kpi-grid">
                <!-- All Shoppers -->
                <a href="<?= BASE_URL ?>/admin/customers" class="cust-kpi-card <?= (!$statusFilter && !$orderFilter && !$joinedFilter) ? 'active-border' : '' ?>">
                    <div class="cust-kpi-text">
                        <div class="cust-kpi-val"><?= number_format($totalCustomers) ?></div>
                        <div class="cust-kpi-label">Total Shoppers</div>
                    </div>
                    <div class="cust-kpi-icon teal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                </a>

                <!-- Active Buyers -->
                <a href="<?= BASE_URL ?>/admin/customers?order_filter=with_orders" class="cust-kpi-card <?= $orderFilter === 'with_orders' ? 'active-border' : '' ?>">
                    <div class="cust-kpi-text">
                        <div class="cust-kpi-val"><?= number_format($activeCustomers) ?></div>
                        <div class="cust-kpi-label">Active Buyers</div>
                    </div>
                    <div class="cust-kpi-icon blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    </div>
                </a>

                <!-- New Signups (Last 30 Days) -->
                <a href="<?= BASE_URL ?>/admin/customers?joined=month" class="cust-kpi-card <?= $joinedFilter === 'month' ? 'active-border' : '' ?>">
                    <div class="cust-kpi-text">
                        <div class="cust-kpi-val"><?= number_format($newThisMonth) ?></div>
                        <div class="cust-kpi-label">New This Month</div>
                    </div>
                    <div class="cust-kpi-icon purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    </div>
                </a>

                <!-- Repeat Shoppers -->
                <a href="<?= BASE_URL ?>/admin/customers?order_filter=repeat" class="cust-kpi-card <?= $orderFilter === 'repeat' ? 'active-border' : '' ?>">
                    <div class="cust-kpi-text">
                        <div class="cust-kpi-val"><?= number_format($repeatCustomers) ?></div>
                        <div class="cust-kpi-label">Repeat Shoppers</div>
                    </div>
                    <div class="cust-kpi-icon amber">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                    </div>
                </a>
            </div>

            <!-- ── 2. Multi-Filter Toolbar ── -->
            <div class="cust-filter-card">
                <form method="GET" action="<?= BASE_URL ?>/admin/customers" class="cust-filter-form">
                    <!-- Search Bar & Filter Toggle Button -->
                    <div class="cust-filter-top-bar">
                        <div class="cust-filter-search">
                            <input type="text" name="search" class="form-input" placeholder="Search customer name, email, phone, city..." value="<?= htmlspecialchars($search) ?>" style="padding-left: 2.2rem; height: 36px; font-size: 0.82rem; width: 100%; border-radius: 7px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </div>

                        <button type="button" class="filter-toggle-btn" onclick="document.getElementById('cust-filter-drawer').classList.toggle('active')" title="Toggle Filter Options">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                            <span>Filter<?= $activeFilterCount > 0 ? " ($activeFilterCount)" : "" ?></span>
                        </button>
                    </div>

                    <!-- Filter Options Drawer -->
                    <div id="cust-filter-drawer" class="cust-filter-drawer <?= ($activeFilterCount > 0) ? 'active' : '' ?>">
                        <!-- Status Filter -->
                        <select name="status" class="form-select cust-filter-select" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="banned" <?= $statusFilter === 'banned' ? 'selected' : '' ?>>Banned</option>
                        </select>

                        <!-- Order Activity Filter -->
                        <select name="order_filter" class="form-select cust-filter-select" onchange="this.form.submit()">
                            <option value="">All Order Activity</option>
                            <option value="with_orders" <?= $orderFilter === 'with_orders' ? 'selected' : '' ?>>With Orders (1+)</option>
                            <option value="repeat" <?= $orderFilter === 'repeat' ? 'selected' : '' ?>>Repeat Buyers (2+)</option>
                            <option value="no_orders" <?= $orderFilter === 'no_orders' ? 'selected' : '' ?>>No Orders Placed</option>
                        </select>

                        <!-- Joined Filter -->
                        <select name="joined" class="form-select cust-filter-select" onchange="this.form.submit()">
                            <option value="">All Join Dates</option>
                            <option value="month" <?= $joinedFilter === 'month' ? 'selected' : '' ?>>New This Month (30d)</option>
                        </select>

                        <!-- Sort By -->
                        <select name="sort" class="form-select cust-filter-select" onchange="this.form.submit()">
                            <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Sort: Newest</option>
                            <option value="ltv_desc" <?= $sortBy === 'ltv_desc' ? 'selected' : '' ?>>Sort: Highest Spend</option>
                            <option value="orders_desc" <?= $sortBy === 'orders_desc' ? 'selected' : '' ?>>Sort: Most Orders</option>
                            <option value="name_asc" <?= $sortBy === 'name_asc' ? 'selected' : '' ?>>Sort: Name (A-Z)</option>
                            <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Sort: Oldest</option>
                        </select>

                        <div class="cust-filter-actions">
                            <button type="submit" class="btn btn-primary" style="height: 36px; font-size: 0.82rem; padding: 0 1rem; border-radius: 7px;">Filter</button>
                            <?php if ($search || $statusFilter || $orderFilter || $joinedFilter || $sortBy !== 'newest'): ?>
                                <a href="<?= BASE_URL ?>/admin/customers" class="btn btn-secondary" style="height: 36px; font-size: 0.82rem; padding: 0 0.75rem; border-radius: 7px; display: inline-flex; align-items: center;">Clear</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- ── 3. Customers Data Table ── -->
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th style="min-width: 200px;">Customer</th>
                            <th>Contact & Location</th>
                            <th style="text-align: center;">Orders</th>
                            <th style="text-align: right;">Total Spend</th>
                            <th style="text-align: center;">Status</th>
                            <th>Joined Date</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #94a3b8; padding: 3rem 1rem;">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.8" style="display: block; margin: 0 auto 8px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                    No customers found matching the selected filters.
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
                                <td style="font-weight: 500; color: #64748b; font-size: 0.8rem;">#<?= $c['id'] ?></td>
                                
                                <!-- Customer Name & Email -->
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="cust-avatar"><?= $initials ?></div>
                                        <div style="display: flex; flex-direction: column; gap: 1px;">
                                            <div class="cust-name"><?= htmlspecialchars($fullName) ?></div>
                                            <div class="cust-email"><?= htmlspecialchars($c['email']) ?></div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Contact & Location -->
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 2px;">
                                        <?php if (!empty($c['phone'])): ?>
                                            <div style="font-size: 0.8rem; font-weight: 500; color: #334155; display: inline-flex; align-items: center; gap: 4px;">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                                <a href="tel:<?= htmlspecialchars($c['phone']) ?>" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($c['phone']) ?></a>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-size: 0.74rem; color: #94a3b8;">No phone</span>
                                        <?php endif; ?>
                                        
                                        <div style="font-size: 0.74rem; color: #64748b; display: inline-flex; align-items: center; gap: 3px;">
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                            <span><?= htmlspecialchars(implode(', ', array_filter([$c['upazila'], $c['city']]))) ?: 'Bangladesh' ?></span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Orders Count -->
                                <td style="text-align: center;">
                                    <?php if ($ordersCnt > 0): ?>
                                        <span class="badge badge-info" style="font-weight: 500; padding: 2px 7px; font-size: 0.75rem;">
                                            <?= $ordersCnt ?> <?= $ordersCnt === 1 ? 'Order' : 'Orders' ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.76rem;">0 Orders</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Lifetime Value -->
                                <td style="text-align: right; font-weight: 600; color: #0f172a; font-size: 0.88rem;">
                                    Tk <?= number_format($spent) ?>
                                </td>

                                <!-- Live Status Dropdown -->
                                <td style="text-align: center;">
                                    <form method="POST" style="margin: 0; display: inline-block;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                        <select name="status" class="form-select admin-status-select" style="font-weight: 500; font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 6px;" onchange="this.form.submit()">
                                            <option value="active" <?= $st === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= $st === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                            <option value="banned" <?= $st === 'banned' ? 'selected' : '' ?>>Banned</option>
                                        </select>
                                    </form>
                                </td>

                                <!-- Joined Date -->
                                <td>
                                    <span style="font-size: 0.76rem; color: #64748b; font-weight: 450;">
                                        <?= date('M j, Y', strtotime($c['created_at'])) ?>
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td style="text-align: right;">
                                    <div class="admin-actions-row" style="justify-content: flex-end; gap: 5px;">
                                        <!-- Create Order for Customer -->
                                        <a href="<?= BASE_URL ?>/admin/order-create?phone=<?= urlencode($c['phone'] ?? '') ?>&name=<?= urlencode($fullName) ?>&city=<?= urlencode($c['city'] ?? '') ?>" class="btn btn-sm btn-outline" style="width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;" title="Create Order for Customer">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/><path d="M12 8v6M9 11h6"/></svg>
                                        </a>

                                        <!-- Edit Customer Trigger -->
                                        <button type="button" class="btn btn-sm btn-primary" style="width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;" onclick="openEditCustomerModal(<?= htmlspecialchars(json_encode($c)) ?>)" title="Edit Customer">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>

                                        <!-- Delete / Ban Customer -->
                                        <form method="POST" style="margin: 0; display: inline-block;" onsubmit="return confirm('Are you sure you want to delete or deactivate this customer?');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete_customer">
                                            <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary" style="width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; color: #ef4444;" title="Delete / Deactivate Customer">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
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

            <!-- Standard Admin Pagination -->
            <?php renderAdminPagination($page, $totalFiltered, $perPage, BASE_URL . '/admin/customers', array_filter(['search' => $search, 'status' => $statusFilter, 'order_filter' => $orderFilter, 'sort' => $sortBy])); ?>

        </main>
    </div>

    <!-- ── 4. Add Customer Modal ── -->
    <div id="addCustomerModal" class="cust-modal-backdrop">
        <div class="cust-modal-window">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between;">
                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 600; color: #0f172a;">Add New Customer</h3>
                <button type="button" onclick="closeModal('addCustomerModal')" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #64748b;">&times;</button>
            </div>
            <form method="POST" style="padding: 1.25rem 1.5rem;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_customer">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">First Name *</label>
                        <input type="text" name="first_name" class="form-input" required placeholder="e.g. John" style="font-size: 0.82rem; height: 36px;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">Last Name</label>
                        <input type="text" name="last_name" class="form-input" placeholder="e.g. Doe" style="font-size: 0.82rem; height: 36px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">Email Address *</label>
                        <input type="email" name="email" class="form-input" required placeholder="john@example.com" style="font-size: 0.82rem; height: 36px;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">Phone Number</label>
                        <input type="text" name="phone" class="form-input" placeholder="017XXXXXXXX" style="font-size: 0.82rem; height: 36px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">City / District</label>
                        <input type="text" name="city" class="form-input" placeholder="e.g. Dhaka" style="font-size: 0.82rem; height: 36px;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">Upazila / Area</label>
                        <input type="text" name="upazila" class="form-input" placeholder="e.g. Dhanmondi" style="font-size: 0.82rem; height: 36px;">
                    </div>
                </div>

                <div style="margin-bottom: 0.85rem;">
                    <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">Delivery Address</label>
                    <textarea name="address" class="form-input" rows="2" placeholder="House #, Road #, Sector..." style="font-size: 0.82rem;"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 1.25rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">Account Password</label>
                        <input type="password" name="password" class="form-input" placeholder="Leave blank for auto-generate" style="font-size: 0.82rem; height: 36px;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">Account Status</label>
                        <select name="status" class="form-select" style="font-size: 0.82rem; height: 36px;">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="banned">Banned</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 8px; border-top: 1px solid #f1f5f9; padding-top: 1rem;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addCustomerModal')" style="font-size: 0.82rem;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="font-size: 0.82rem;">Create Customer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── 5. Edit Customer Modal ── -->
    <div id="editCustomerModal" class="cust-modal-backdrop">
        <div class="cust-modal-window">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between;">
                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 600; color: #0f172a;">Edit Customer Profile</h3>
                <button type="button" onclick="closeModal('editCustomerModal')" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #64748b;">&times;</button>
            </div>
            <form method="POST" style="padding: 1.25rem 1.5rem;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="edit_customer">
                <input type="hidden" name="customer_id" id="edit_customer_id">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">First Name *</label>
                        <input type="text" name="first_name" id="edit_first_name" class="form-input" required style="font-size: 0.82rem; height: 36px;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">Last Name</label>
                        <input type="text" name="last_name" id="edit_last_name" class="form-input" style="font-size: 0.82rem; height: 36px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">Email Address *</label>
                        <input type="email" name="email" id="edit_email" class="form-input" required style="font-size: 0.82rem; height: 36px;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">Phone Number</label>
                        <input type="text" name="phone" id="edit_phone" class="form-input" style="font-size: 0.82rem; height: 36px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">City / District</label>
                        <input type="text" name="city" id="edit_city" class="form-input" style="font-size: 0.82rem; height: 36px;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">Upazila / Area</label>
                        <input type="text" name="upazila" id="edit_upazila" class="form-input" style="font-size: 0.82rem; height: 36px;">
                    </div>
                </div>

                <div style="margin-bottom: 0.85rem;">
                    <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">Delivery Address</label>
                    <textarea name="address" id="edit_address" class="form-input" rows="2" style="font-size: 0.82rem;"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 1.25rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">New Password (Optional)</label>
                        <input type="password" name="new_password" class="form-input" placeholder="Leave empty to keep current" style="font-size: 0.82rem; height: 36px;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.76rem; font-weight: 500;">Status</label>
                        <select name="status" id="edit_status" class="form-select" style="font-size: 0.82rem; height: 36px;">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="banned">Banned</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 8px; border-top: 1px solid #f1f5f9; padding-top: 1rem;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editCustomerModal')" style="font-size: 0.82rem;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="font-size: 0.82rem;">Save Changes</button>
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
