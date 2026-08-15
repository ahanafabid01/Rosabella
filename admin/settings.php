<?php
/**
 * Rosabella - Admin Panel & System Settings
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/layout.php';

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
$activeTab = $_GET['tab'] ?? 'general';

// Helper to save a single setting key
function saveAdminSetting(PDO $db, string $key, string $value, string $type = 'text'): void {
    $stmt = $db->prepare("
        INSERT INTO settings (setting_key, setting_value, setting_type)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type)
    ");
    $stmt->execute([$key, $value, $type]);
}

// ── 1. Handle General & Operational Settings Save ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_admin_settings'])) {
    $activeTab = sanitize($_POST['active_tab'] ?? 'general');
    $settingsToSave = $_POST['settings'] ?? [];

    try {
        $db->beginTransaction();
        
        // Save text / number inputs
        foreach ($settingsToSave as $k => $v) {
            $key = sanitize($k);
            $val = sanitize($v);
            saveAdminSetting($db, $key, $val, 'text');
        }

        // Handle boolean checkboxes that might be unchecked
        $booleanKeys = [
            'admin_ip_session_binding',
            'admin_require_strong_password',
            'auto_confirm_cod_orders',
            'notify_low_stock_email',
            'notify_new_customer_email',
            'admin_sound_notifications',
            'maintenance_mode'
        ];

        foreach ($booleanKeys as $bKey) {
            if (isset($_POST['declared_booleans'][$bKey])) {
                $bVal = isset($settingsToSave[$bKey]) ? '1' : '0';
                saveAdminSetting($db, $bKey, $bVal, 'boolean');
            }
        }

        $db->commit();
        $message = 'Admin settings saved successfully.';
    } catch (Throwable $e) {
        $db->rollBack();
        $error = 'Failed to save settings: ' . $e->getMessage();
    }
}

// ── 2. Handle Admin Profile & Password Update ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin_profile'])) {
    $activeTab = 'profile';
    $adminId   = (int)$_SESSION['user_id'];
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName  = sanitize($_POST['last_name'] ?? '');
    $email     = sanitize($_POST['email'] ?? '');
    $currentPw = $_POST['current_password'] ?? '';
    $newPw     = $_POST['new_password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    if (empty($firstName) || empty($email)) {
        $error = 'First name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } else {
        // Verify email unique among other users
        $checkStmt = $db->prepare("SELECT id, password FROM users WHERE id = ?");
        $checkStmt->execute([$adminId]);
        $currUser = $checkStmt->fetch();

        $dupStmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $dupStmt->execute([$email, $adminId]);
        if ($dupStmt->fetch()) {
            $error = 'This email address is already in use by another account.';
        } else {
            // Check if password change requested
            if (!empty($newPw)) {
                if (empty($currentPw) || !password_verify($currentPw, $currUser['password'])) {
                    $error = 'Current password is incorrect.';
                } elseif (strlen($newPw) < 6) {
                    $error = 'New password must be at least 6 characters long.';
                } elseif ($newPw !== $confirmPw) {
                    $error = 'New password and confirmation do not match.';
                } else {
                    $hashed = password_hash($newPw, PASSWORD_DEFAULT);
                    $upd = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, password = ? WHERE id = ?");
                    $upd->execute([$firstName, $lastName, $email, $hashed, $adminId]);
                    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                    $_SESSION['user_email'] = $email;
                    $message = 'Profile and password updated successfully.';
                }
            } else {
                $upd = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
                $upd->execute([$firstName, $lastName, $email, $adminId]);
                $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                $_SESSION['user_email'] = $email;
                $message = 'Profile details updated successfully.';
            }
        }
    }
}

// ── 3. Handle System Actions (Purge Cache / Optimize DB) ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_optimize_db'])) {
    $activeTab = 'maintenance';
    try {
        $tables = ['products', 'orders', 'order_items', 'users', 'categories', 'cart_items', 'reviews', 'coupons', 'settings', 'hero_slides', 'global_attributes'];
        foreach ($tables as $tbl) {
            $db->query("OPTIMIZE TABLE `$tbl`");
        }
        $message = 'Database tables optimized successfully.';
    } catch (Throwable $e) {
        $error = 'Database optimization failed: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_clear_cache'])) {
    $activeTab = 'maintenance';
    $clearedItems = 0;
    // Clear temporary directory in cache if exists
    $cacheDir = __DIR__ . '/../assets/cache/';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '*');
        foreach ($files as $f) {
            if (is_file($f)) {
                unlink($f);
                $clearedItems++;
            }
        }
    }
    $message = "System cache purged successfully. ($clearedItems cached objects removed)";
}

// Fetch current admin user info
$adminStmt = $db->prepare("SELECT first_name, last_name, email, role, created_at FROM users WHERE id = ?");
$adminStmt->execute([(int)$_SESSION['user_id']]);
$adminProfile = $adminStmt->fetch() ?: [
    'first_name' => 'Admin',
    'last_name' => '',
    'email' => 'admin@rosabella.com',
    'role' => 'admin',
    'created_at' => date('Y-m-d H:i:s')
];

// Fetch current settings map
$allSettings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

function getSettingVal(array $map, string $key, string $default = ''): string {
    return $map[$key] ?? $default;
}

$pageTitle = 'Admin Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $siteFavicon = getSetting('site_favicon'); if ($siteFavicon): ?>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . '/' . htmlspecialchars($siteFavicon) ?>">
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
        /* ── Modern Settings Layout & Nav ── */
        .as-layout-grid {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 1.25rem;
            align-items: start;
        }

        .as-nav-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.65rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            position: sticky;
            top: 1rem;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .as-nav-item {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.65rem 0.85rem;
            border-radius: 7px;
            font-size: 0.835rem;
            font-weight: 500;
            color: #475569;
            text-decoration: none;
            background: transparent;
            border: none;
            cursor: pointer;
            width: 100%;
            text-align: left;
            transition: all 0.15s ease;
        }

        .as-nav-item:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        .as-nav-item.active {
            background: #f0fdfa;
            color: #0f766e;
            font-weight: 600;
        }

        .as-nav-item svg {
            width: 17px;
            height: 17px;
            flex-shrink: 0;
        }

        .as-section-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.25rem 1.4rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            margin-bottom: 1.25rem;
        }

        .as-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 0.85rem;
            margin-bottom: 1.15rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .as-card-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: #0f172a;
            letter-spacing: -0.01em;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .as-card-sub {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 2px;
            font-weight: 400;
        }

        .as-tab-panel {
            display: none;
        }

        .as-tab-panel.active {
            display: block;
            animation: asFadeIn 0.18s ease-in-out;
        }

        @keyframes asFadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Setting Rows & Form Elements ── */
        .as-row {
            display: grid;
            grid-template-columns: 1.2fr 2fr;
            gap: 1.5rem;
            padding: 0.95rem 0;
            border-bottom: 1px solid #f8fafc;
            align-items: center;
        }

        .as-row:last-child {
            border-bottom: none;
        }

        .as-row-info h4 {
            margin: 0 0 2px;
            font-size: 0.86rem;
            font-weight: 500;
            color: #0f172a;
        }

        .as-row-info p {
            margin: 0;
            font-size: 0.76rem;
            color: #64748b;
            line-height: 1.35;
        }

        .as-input-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .as-input {
            width: 100%;
            height: 36px;
            padding: 0 0.75rem;
            font-size: 0.835rem;
            font-weight: 400;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            background: #ffffff;
            color: #0f172a;
            outline: none;
            transition: border-color 0.15s ease;
        }

        .as-input:focus {
            border-color: var(--color-primary, #0f766e);
            box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.12);
        }

        .as-textarea {
            width: 100%;
            padding: 0.65rem 0.75rem;
            font-size: 0.835rem;
            font-weight: 400;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            background: #ffffff;
            color: #0f172a;
            outline: none;
            resize: vertical;
            min-height: 70px;
        }

        .as-textarea:focus {
            border-color: var(--color-primary, #0f766e);
            box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.12);
        }

        /* ── Modern Switch Toggle ── */
        .as-switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 22px;
            flex-shrink: 0;
        }

        .as-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .as-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background-color: #cbd5e1;
            transition: 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            border-radius: 22px;
        }

        .as-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        }

        .as-switch input:checked + .as-slider {
            background-color: var(--color-primary, #0f766e);
        }

        .as-switch input:checked + .as-slider:before {
            transform: translateX(18px);
        }

        /* ── System Diagnostics Table ── */
        .as-diag-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .as-diag-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .as-diag-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: #64748b;
        }

        .as-diag-val {
            font-size: 0.8rem;
            font-weight: 600;
            color: #0f172a;
            font-family: var(--admin-font);
        }

        @media (max-width: 860px) {
            .as-layout-grid {
                grid-template-columns: 1fr;
            }
            .as-nav-card {
                position: static;
                flex-direction: row;
                overflow-x: auto;
                padding: 0.4rem;
            }
            .as-nav-item {
                white-space: nowrap;
                padding: 0.5rem 0.75rem;
            }
            .as-row {
                grid-template-columns: 1fr;
                gap: 0.65rem;
            }
            .as-diag-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php renderAdminSidebar('settings'); ?>

    <main class="admin-content">
        <?php renderAdminTopbar($pageTitle); ?>

        <!-- Header -->
        <div class="admin-header">
            <div>
                <h1 class="admin-title">Admin & System Settings</h1>
                <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;">Configure core administrative operations, security rules, notifications, and diagnostics.</div>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/website-settings" class="btn btn-secondary" style="height: 36px; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <span>Storefront & Website Settings</span>
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success" style="display: flex; align-items: center; gap: 8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error" style="display: flex; align-items: center; gap: 8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <div class="as-layout-grid">
            <!-- Left Navigation Tabs -->
            <div class="as-nav-card">
                <button type="button" class="as-nav-item <?= $activeTab === 'general' ? 'active' : '' ?>" onclick="switchAdminTab('general', this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <span>General & Store Operations</span>
                </button>
                <button type="button" class="as-nav-item <?= $activeTab === 'security' ? 'active' : '' ?>" onclick="switchAdminTab('security', this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>Security & Sessions</span>
                </button>
                <button type="button" class="as-nav-item <?= $activeTab === 'notifications' ? 'active' : '' ?>" onclick="switchAdminTab('notifications', this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <span>Alerts & Notifications</span>
                </button>
                <button type="button" class="as-nav-item <?= $activeTab === 'profile' ? 'active' : '' ?>" onclick="switchAdminTab('profile', this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>Admin Profile & Password</span>
                </button>
                <button type="button" class="as-nav-item <?= $activeTab === 'maintenance' ? 'active' : '' ?>" onclick="switchAdminTab('maintenance', this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    <span>Maintenance & Tools</span>
                </button>
            </div>

            <!-- Right Content Panels -->
            <div class="as-content-area">

                <!-- ── TAB 1: General & Operations ── -->
                <div id="tab-general" class="as-tab-panel <?= $activeTab === 'general' ? 'active' : '' ?>">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="active_tab" value="general">
                        
                        <div class="as-section-card">
                            <div class="as-card-header">
                                <div>
                                    <h3 class="as-card-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0f766e" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                        Order & Inventory Automation
                                    </h3>
                                    <div class="as-card-sub">Operational rules governing inventory monitoring, invoice numbering, and order defaults.</div>
                                </div>
                            </div>

                            <!-- Low Stock Threshold -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Low Stock Warning Threshold</h4>
                                    <p>Products with stock less than or equal to this number will trigger low-stock alerts on the dashboard.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <input type="number" min="0" max="999" name="settings[low_stock_threshold]" class="as-input" style="max-width: 140px;" value="<?= htmlspecialchars(getSettingVal($allSettings, 'low_stock_threshold', '5')) ?>">
                                    <span style="font-size: 0.78rem; color: #64748b;">units</span>
                                </div>
                            </div>

                            <!-- Order Invoice Prefix -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Order Number Prefix</h4>
                                    <p>Prefix attached to all auto-generated invoice and order numbers.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <input type="text" name="settings[order_id_prefix]" class="as-input" style="max-width: 180px;" value="<?= htmlspecialchars(getSettingVal($allSettings, 'order_id_prefix', 'ORD-')) ?>">
                                </div>
                            </div>

                            <!-- Default Order Status Filter -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Default Orders Filter</h4>
                                    <p>Default status view when opening the orders list page.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <select name="settings[default_order_status_view]" class="as-input" style="max-width: 200px;">
                                        <?php $defSt = getSettingVal($allSettings, 'default_order_status_view', 'all'); ?>
                                        <option value="all" <?= $defSt === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                        <option value="pending" <?= $defSt === 'pending' ? 'selected' : '' ?>>Pending Confirmation</option>
                                        <option value="confirmed" <?= $defSt === 'confirmed' ? 'selected' : '' ?>>Confirmed Orders</option>
                                        <option value="processing" <?= $defSt === 'processing' ? 'selected' : '' ?>>Processing Orders</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Auto-Confirm COD Orders -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Auto-Confirm COD Orders</h4>
                                    <p>Automatically transition Cash on Delivery orders to Confirmed state upon creation.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <input type="hidden" name="declared_booleans[auto_confirm_cod_orders]" value="1">
                                    <label class="as-switch">
                                        <input type="checkbox" name="settings[auto_confirm_cod_orders]" value="1" <?= getSettingVal($allSettings, 'auto_confirm_cod_orders', '0') === '1' ? 'checked' : '' ?>>
                                        <span class="as-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" name="save_admin_settings" class="btn btn-primary" style="height: 38px; font-size: 0.84rem; padding: 0 1.25rem;">
                                Save Operational Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── TAB 2: Security & Sessions ── -->
                <div id="tab-security" class="as-tab-panel <?= $activeTab === 'security' ? 'active' : '' ?>">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="active_tab" value="security">
                        
                        <div class="as-section-card">
                            <div class="as-card-header">
                                <div>
                                    <h3 class="as-card-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                        Admin Security & Session Policies
                                    </h3>
                                    <div class="as-card-sub">Session timeout limits, brute-force rate limit protection, and session IP binding.</div>
                                </div>
                            </div>

                            <!-- Session Inactivity Timeout -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Session Inactivity Timeout</h4>
                                    <p>Automatically logs out idle admin sessions after the specified duration.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <input type="number" min="15" max="1440" name="settings[admin_session_timeout]" class="as-input" style="max-width: 140px;" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_session_timeout', '120')) ?>">
                                    <span style="font-size: 0.78rem; color: #64748b;">minutes</span>
                                </div>
                            </div>

                            <!-- Max Failed Login Attempts -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Max Login Attempts</h4>
                                    <p>Number of incorrect password attempts permitted before temporary IP rate limit triggers.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <input type="number" min="3" max="20" name="settings[admin_max_login_attempts]" class="as-input" style="max-width: 140px;" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_max_login_attempts', '5')) ?>">
                                    <span style="font-size: 0.78rem; color: #64748b;">attempts</span>
                                </div>
                            </div>

                            <!-- Session IP Binding -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Session IP Verification</h4>
                                    <p>Enforce strict session binding to the login IP address to protect against session hijacking.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <input type="hidden" name="declared_booleans[admin_ip_session_binding]" value="1">
                                    <label class="as-switch">
                                        <input type="checkbox" name="settings[admin_ip_session_binding]" value="1" <?= getSettingVal($allSettings, 'admin_ip_session_binding', '1') === '1' ? 'checked' : '' ?>>
                                        <span class="as-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Require Strong Password -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Enforce Strong Passwords</h4>
                                    <p>Require staff and admin accounts to use mixed uppercase, lowercase, and numbers.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <input type="hidden" name="declared_booleans[admin_require_strong_password]" value="1">
                                    <label class="as-switch">
                                        <input type="checkbox" name="settings[admin_require_strong_password]" value="1" <?= getSettingVal($allSettings, 'admin_require_strong_password', '0') === '1' ? 'checked' : '' ?>>
                                        <span class="as-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" name="save_admin_settings" class="btn btn-primary" style="height: 38px; font-size: 0.84rem; padding: 0 1.25rem;">
                                Save Security Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── TAB 3: Notifications & Alerts ── -->
                <div id="tab-notifications" class="as-tab-panel <?= $activeTab === 'notifications' ? 'active' : '' ?>">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="active_tab" value="notifications">
                        
                        <div class="as-section-card">
                            <div class="as-card-header">
                                <div>
                                    <h3 class="as-card-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                                        Admin Alerts & System Notifications
                                    </h3>
                                    <div class="as-card-sub">Routing rules for operational alerts, low-stock warnings, and order events.</div>
                                </div>
                            </div>

                            <!-- Notification Email -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>System Alert Recipient Email</h4>
                                    <p>Email address where urgent notifications and order summaries are routed.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <input type="email" name="settings[admin_alert_email]" class="as-input" placeholder="admin@rosabella.com" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_alert_email', $adminProfile['email'])) ?>">
                                </div>
                            </div>

                            <!-- Low Stock Email Alert -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Email on Low Stock</h4>
                                    <p>Send an immediate email digest when any product stock drops to the alert threshold.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <input type="hidden" name="declared_booleans[notify_low_stock_email]" value="1">
                                    <label class="as-switch">
                                        <input type="checkbox" name="settings[notify_low_stock_email]" value="1" <?= getSettingVal($allSettings, 'notify_low_stock_email', '1') === '1' ? 'checked' : '' ?>>
                                        <span class="as-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- New Registered Customer Email -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Email on New Customer Signup</h4>
                                    <p>Receive an alert whenever a new customer creates an account on the storefront.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <input type="hidden" name="declared_booleans[notify_new_customer_email]" value="1">
                                    <label class="as-switch">
                                        <input type="checkbox" name="settings[notify_new_customer_email]" value="1" <?= getSettingVal($allSettings, 'notify_new_customer_email', '0') === '1' ? 'checked' : '' ?>>
                                        <span class="as-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Sound & Audio Alerts in Admin -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Admin Chime Audio Alerts</h4>
                                    <p>Play a soft chime when live orders arrive while the dashboard is open in browser.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <input type="hidden" name="declared_booleans[admin_sound_notifications]" value="1">
                                    <label class="as-switch">
                                        <input type="checkbox" name="settings[admin_sound_notifications]" value="1" <?= getSettingVal($allSettings, 'admin_sound_notifications', '1') === '1' ? 'checked' : '' ?>>
                                        <span class="as-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" name="save_admin_settings" class="btn btn-primary" style="height: 38px; font-size: 0.84rem; padding: 0 1.25rem;">
                                Save Notification Preferences
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── TAB 4: Admin Profile & Credentials ── -->
                <div id="tab-profile" class="as-tab-panel <?= $activeTab === 'profile' ? 'active' : '' ?>">
                    <form method="POST">
                        <?= csrfField() ?>
                        
                        <div class="as-section-card">
                            <div class="as-card-header">
                                <div>
                                    <h3 class="as-card-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0f766e" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                        Current Administrator Account
                                    </h3>
                                    <div class="as-card-sub">Manage your active admin display name, contact email, and login credentials.</div>
                                </div>
                            </div>

                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Admin Name</h4>
                                    <p>Your full display name across admin logs and audit trails.</p>
                                </div>
                                <div class="as-input-wrap" style="gap: 8px;">
                                    <input type="text" name="first_name" class="as-input" placeholder="First Name" value="<?= htmlspecialchars($adminProfile['first_name'] ?? '') ?>" required>
                                    <input type="text" name="last_name" class="as-input" placeholder="Last Name" value="<?= htmlspecialchars($adminProfile['last_name'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Account Email</h4>
                                    <p>Your login email address for administrative authentication.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <input type="email" name="email" class="as-input" value="<?= htmlspecialchars($adminProfile['email'] ?? '') ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Change Password Card -->
                        <div class="as-section-card">
                            <div class="as-card-header">
                                <div>
                                    <h3 class="as-card-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                        Change Password
                                    </h3>
                                    <div class="as-card-sub">Leave blank if you do not wish to change your current password.</div>
                                </div>
                            </div>

                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Current Password</h4>
                                    <p>Required to authorize any password change.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <input type="password" name="current_password" class="as-input" placeholder="Enter current password">
                                </div>
                            </div>

                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>New Password</h4>
                                    <p>Minimum 6 characters with mixed letters and numbers.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <input type="password" name="new_password" class="as-input" placeholder="New secure password">
                                </div>
                            </div>

                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Confirm New Password</h4>
                                    <p>Repeat the new password exactly.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <input type="password" name="confirm_password" class="as-input" placeholder="Repeat new password">
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" name="update_admin_profile" class="btn btn-primary" style="height: 38px; font-size: 0.84rem; padding: 0 1.25rem;">
                                Update Profile & Credentials
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── TAB 5: Maintenance & System Tools ── -->
                <div id="tab-maintenance" class="as-tab-panel <?= $activeTab === 'maintenance' ? 'active' : '' ?>">
                    <!-- Maintenance Mode Toggle Form -->
                    <form method="POST" style="margin-bottom: 1.25rem;">
                        <?= csrfField() ?>
                        <input type="hidden" name="active_tab" value="maintenance">
                        
                        <div class="as-section-card">
                            <div class="as-card-header">
                                <div>
                                    <h3 class="as-card-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                        Storefront Maintenance Mode
                                    </h3>
                                    <div class="as-card-sub">When active, public customers will see a temporary maintenance splash screen while admins retain full access.</div>
                                </div>
                            </div>

                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Enable Maintenance Mode</h4>
                                    <p>Lock storefront for public visitors during major upgrades.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <input type="hidden" name="declared_booleans[maintenance_mode]" value="1">
                                    <label class="as-switch">
                                        <input type="checkbox" name="settings[maintenance_mode]" value="1" <?= getSettingVal($allSettings, 'maintenance_mode', '0') === '1' ? 'checked' : '' ?>>
                                        <span class="as-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Maintenance Notice</h4>
                                    <p>Custom message displayed on the storefront maintenance page.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <textarea name="settings[maintenance_message]" class="as-textarea" placeholder="We are currently upgrading our store experience. We'll be right back!"><?= htmlspecialchars(getSettingVal($allSettings, 'maintenance_message', 'We are currently upgrading our store experience to serve you better. We will be back online shortly!')) ?></textarea>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
                                <button type="submit" name="save_admin_settings" class="btn btn-primary" style="height: 38px; font-size: 0.84rem; padding: 0 1.25rem;">
                                    Save Maintenance Settings
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- System Diagnostics & Maintenance Operations -->
                    <div class="as-section-card">
                        <div class="as-card-header">
                            <div>
                                <h3 class="as-card-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0f766e" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                                    System Optimization & Cache Tools
                                </h3>
                                <div class="as-card-sub">Run routine server maintenance, defragment indexes, and flush temporary cache buffers.</div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                            <!-- Cache Purge Card -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;">
                                <h4 style="margin: 0 0 4px; font-size: 0.88rem; font-weight: 600; color: #0f172a;">Purge Temporary Cache</h4>
                                <p style="font-size: 0.76rem; color: #64748b; margin: 0 0 12px; line-height: 1.35;">Flushes cached objects and temporary files to ensure all recent changes reflect immediately.</p>
                                <form method="POST" style="margin: 0;">
                                    <?= csrfField() ?>
                                    <button type="submit" name="action_clear_cache" class="btn btn-secondary" style="height: 34px; font-size: 0.78rem;">
                                        Flush Cache Now
                                    </button>
                                </form>
                            </div>

                            <!-- Database Optimize Card -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;">
                                <h4 style="margin: 0 0 4px; font-size: 0.88rem; font-weight: 600; color: #0f172a;">Defragment & Optimize Tables</h4>
                                <p style="font-size: 0.76rem; color: #64748b; margin: 0 0 12px; line-height: 1.35;">Optimizes MySQL index trees and reclaims unused space across core catalog tables.</p>
                                <form method="POST" style="margin: 0;">
                                    <?= csrfField() ?>
                                    <button type="submit" name="action_optimize_db" class="btn btn-secondary" style="height: 34px; font-size: 0.78rem;">
                                        Optimize Database
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- System Environment Snapshot -->
                        <h4 style="margin: 0 0 0.65rem; font-size: 0.84rem; font-weight: 600; color: #334155;">Server & Runtime Snapshot</h4>
                        <div class="as-diag-grid">
                            <div class="as-diag-item">
                                <span class="as-diag-label">PHP Version</span>
                                <span class="as-diag-val"><?= PHP_VERSION ?></span>
                            </div>
                            <div class="as-diag-item">
                                <span class="as-diag-label">Database Server</span>
                                <span class="as-diag-val">MySQL <?= htmlspecialchars($db->getAttribute(PDO::ATTR_SERVER_VERSION)) ?></span>
                            </div>
                            <div class="as-diag-item">
                                <span class="as-diag-label">Memory Limit</span>
                                <span class="as-diag-val"><?= ini_get('memory_limit') ?></span>
                            </div>
                            <div class="as-diag-item">
                                <span class="as-diag-label">Max Upload Size</span>
                                <span class="as-diag-val"><?= ini_get('upload_max_filesize') ?> (POST: <?= ini_get('post_max_size') ?>)</span>
                            </div>
                            <div class="as-diag-item">
                                <span class="as-diag-label">Server Timezone</span>
                                <span class="as-diag-val"><?= date_default_timezone_get() ?> (<?= date('H:i:s') ?>)</span>
                            </div>
                            <div class="as-diag-item">
                                <span class="as-diag-label">Server Software</span>
                                <span class="as-diag-val"><?= htmlspecialchars(substr($_SERVER['SERVER_SOFTWARE'] ?? 'Apache', 0, 24)) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

<script>
function switchAdminTab(tabKey, btnEl) {
    document.querySelectorAll('.as-nav-item').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.as-tab-panel').forEach(p => p.classList.remove('active'));

    if (btnEl) btnEl.classList.add('active');
    const targetPanel = document.getElementById('tab-' + tabKey);
    if (targetPanel) {
        targetPanel.classList.add('active');
    }

    // Sync URL hash
    if (history.replaceState) {
        history.replaceState(null, null, '?tab=' + tabKey);
    }
}

// Auto-switch tab if hash or query param present on load
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const initialTab = urlParams.get('tab');
    if (initialTab) {
        const btn = document.querySelector(`.as-nav-item[onclick*="'${initialTab}'"]`);
        if (btn) switchAdminTab(initialTab, btn);
    }
});
</script>
</body>
</html>
