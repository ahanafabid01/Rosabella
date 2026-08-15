<?php
/**
 * Rosabella - Admin Dashboard
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
$totalProducts = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalOrders = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$totalRevenue = (float)$db->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();

// Status breakdown counts
$pendingCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$processingCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();
$deliveredCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn();

// Get recent orders
$recentOrders = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 6")->fetchAll();

// Get low stock products
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
    <title>Admin Dashboard - Rosabella</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .dash-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .dash-kpi-card {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.15rem 1.35rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            transition: all 0.2s ease;
        }
        .dash-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.05);
            border-color: #cbd5e1;
        }
        .dash-quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 1.5rem;
        }
        .dash-main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        @media (max-width: 1100px) {
            .dash-main-grid {
                grid-template-columns: 1fr !important;
            }
        }
        @media (max-width: 900px) {
            .dash-kpi-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.75rem !important;
                margin-bottom: 1.25rem !important;
            }
            .dash-kpi-card {
                padding: 0.95rem 1.05rem !important;
                border-radius: 12px !important;
            }
            .dash-kpi-card > div:first-child > div:nth-child(2) {
                font-size: 1.25rem !important;
            }
            .dash-quick-actions {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 8px !important;
                margin-bottom: 1.25rem !important;
            }
            .dash-quick-actions .btn {
                width: 100% !important;
                justify-content: center !important;
                padding: 0.55rem 0.65rem !important;
                font-size: 0.78rem !important;
                box-sizing: border-box !important;
                white-space: nowrap !important;
            }
        }
        @media (max-width: 480px) {
            .dash-kpi-card {
                padding: 0.85rem 0.9rem !important;
            }
            .dash-kpi-card > div:first-child > div:first-child {
                font-size: 0.68rem !important;
            }
            .dash-kpi-card > div:first-child > div:nth-child(2) {
                font-size: 1.15rem !important;
            }
            .dash-kpi-card > div:first-child > div:nth-child(3) {
                font-size: 0.68rem !important;
            }
            .dash-kpi-card > div:last-child {
                width: 36px !important;
                height: 36px !important;
                border-radius: 8px !important;
            }
            .dash-kpi-card > div:last-child svg {
                width: 18px !important;
                height: 18px !important;
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
            <?php renderAdminTopbar($pageTitle ?? 'Admin Panel'); ?>
            
            <div class="admin-header" style="margin-bottom: 1.25rem;">
                <div>
                    <h1 class="admin-title">Dashboard</h1>
                    <p style="font-size: 0.85rem; color: #64748b; margin-top: 2px;">Welcome back, <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></strong>! Here is an overview of your store today.</p>
                </div>
            </div>

            <!-- Top Metric KPI Cards -->
            <div class="dash-kpi-grid">
                <!-- Total Revenue -->
                <div class="dash-kpi-card">
                    <div>
                        <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Total Revenue</div>
                        <div style="font-size: 1.45rem; font-weight: 800; color: #0f766e; line-height: 1.2; margin-top: 4px;"><?= formatPrice($totalRevenue) ?></div>
                        <div style="font-size: 0.75rem; font-weight: 600; color: #10b981; margin-top: 4px; display: inline-flex; align-items: center; gap: 4px;">
                            <span>✓ Net Paid Revenue</span>
                        </div>
                    </div>
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: #ccfbf1; color: #0f766e; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                </div>

                <!-- Total Orders -->
                <a href="<?= BASE_URL ?>/admin/orders" style="text-decoration: none;" class="dash-kpi-card">
                    <div>
                        <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Total Orders</div>
                        <div style="font-size: 1.45rem; font-weight: 800; color: #1e293b; line-height: 1.2; margin-top: 4px;"><?= number_format($totalOrders) ?></div>
                        <div style="font-size: 0.75rem; font-weight: 600; color: #3b82f6; margin-top: 4px; display: inline-flex; align-items: center; gap: 4px;">
                            <span>📦 <?= $pendingCount ?> Pending Orders</span>
                        </div>
                    </div>
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    </div>
                </a>

                <!-- Total Products -->
                <a href="<?= BASE_URL ?>/admin/products" style="text-decoration: none;" class="dash-kpi-card">
                    <div>
                        <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Catalog Items</div>
                        <div style="font-size: 1.45rem; font-weight: 800; color: #1e293b; line-height: 1.2; margin-top: 4px;"><?= number_format($totalProducts) ?></div>
                        <div style="font-size: 0.75rem; font-weight: 600; color: #8b5cf6; margin-top: 4px; display: inline-flex; align-items: center; gap: 4px;">
                            <span>🛍️ Active Products</span>
                        </div>
                    </div>
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: #f3e8ff; color: #7c3aed; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    </div>
                </a>

                <!-- Total Customers -->
                <a href="<?= BASE_URL ?>/admin/users" style="text-decoration: none;" class="dash-kpi-card">
                    <div>
                        <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Registered Users</div>
                        <div style="font-size: 1.45rem; font-weight: 800; color: #1e293b; line-height: 1.2; margin-top: 4px;"><?= number_format($totalUsers) ?></div>
                        <div style="font-size: 0.75rem; font-weight: 600; color: #d97706; margin-top: 4px; display: inline-flex; align-items: center; gap: 4px;">
                            <span>👥 Registered Accounts</span>
                        </div>
                    </div>
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                </a>
            </div>

            <!-- Quick Action Toolbar -->
            <div class="dash-quick-actions">
                <a href="<?= BASE_URL ?>/admin/order-create.php" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Create New Order
                </a>
                <a href="<?= BASE_URL ?>/admin/products?action=add" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.85rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    Add Product
                </a>
                <a href="<?= BASE_URL ?>/admin/coupons" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.85rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    Manage Coupons
                </a>
                <a href="<?= BASE_URL ?>/admin/orders" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    All Orders (<?= $totalOrders ?>)
                </a>
            </div>

            <!-- Main Dashboard Tables Grid -->
            <div class="dash-main-grid">
                <!-- Recent Orders Card -->
                <div class="admin-card" style="margin-bottom: 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                        <h3 class="admin-section-heading" style="margin: 0; font-size: 1.05rem;">Recent Orders</h3>
                        <a href="<?= BASE_URL ?>/admin/orders" style="font-size: 0.8rem; font-weight: 700; color: #0f766e; text-decoration: none;">View All →</a>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-table-sm">
                            <thead>
                                <tr>
                                    <th>Order Reference</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Total</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentOrders)): ?>
                                    <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 2rem;">No recent orders found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentOrders as $order): ?>
                                    <?php
                                        $statusClass = 'secondary';
                                        if ($order['status'] === 'delivered') $statusClass = 'success';
                                        elseif ($order['status'] === 'processing') $statusClass = 'info';
                                        elseif ($order['status'] === 'pending') $statusClass = 'warning';
                                        elseif ($order['status'] === 'cancelled' || $order['status'] === 'returned') $statusClass = 'error';
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 700; color: #0f172a; font-size: 0.85rem;"><?= htmlspecialchars($order['order_number']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $statusClass ?>" style="font-size: 0.75rem; padding: 3px 8px; font-weight: 700;">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right; font-weight: 700; color: #1e293b; font-size: 0.88rem;">
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
                        <a href="<?= BASE_URL ?>/admin/products?stock=low_stock" style="font-size: 0.8rem; font-weight: 700; color: #ef4444; text-decoration: none;">Manage Inventory →</a>
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
        </main>
    </div>
    <script src="js/admin.js"></script>
</body>
</html>
