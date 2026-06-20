<?php
/**
 * KARTLY - Admin Dashboard
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once __DIR__ . '/includes/layout.php';

// Check if admin is logged in
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$pageTitle = 'Admin Dashboard';
$db = getDB();

// Get stats
$totalProducts = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalOrders = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$totalRevenue = $db->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();

// Get recent orders
$recentOrders = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get low stock products
$lowStockProducts = $db->query("SELECT * FROM products WHERE stock_quantity < 10 AND status = 'active' LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $siteFavicon = getSetting('site_favicon'); if ($siteFavicon): ?>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . '/' . htmlspecialchars($siteFavicon) ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - KARTLY</title>
    <link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <?php renderAdminSidebar('dashboard'); ?>

        <!-- Main Content -->
        <main class="admin-content">
        <?php renderAdminTopbar($pageTitle ?? 'Admin Panel'); ?>
<div class="admin-header">
                <h1 class="admin-title">Dashboard</h1>
                <div class="admin-inline-flex-center-gap">
                    <span class="admin-text-muted">Welcome, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="admin-stats-grid">
                <div class="stat-card">
                    <div class="stat-value admin-stat-primary"><?= number_format($totalProducts) ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value admin-stat-success"><?= number_format($totalOrders) ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value admin-stat-info"><?= number_format($totalUsers) ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value admin-stat-warning"><?= formatPrice($totalRevenue) ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>

            <div class="admin-dashboard-grid">
                <!-- Recent Orders -->
                <div class="stat-card">
                    <h3 class="admin-section-heading">Recent Orders</h3>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-table-sm">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><?= htmlspecialchars($order['order_number']) ?></td>
                                    <td><span class="badge badge-<?= $order['status'] === 'delivered' ? 'success' : 'warning' ?>"><?= ucfirst($order['status']) ?></span></td>
                                    <td><?= formatPrice($order['total']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Low Stock -->
                <div class="stat-card">
                    <h3 class="admin-section-heading">Low Stock Alert</h3>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Stock</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><span class="badge badge-danger"><?= $product['stock_quantity'] ?></span></td>
                                    <td><a href="<?= BASE_URL ?>/admin/products?action=edit&id=<?= $product['id'] ?>" class="btn btn-sm btn-outline">Edit</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="js/admin.js"></script>
</body>
</html>


