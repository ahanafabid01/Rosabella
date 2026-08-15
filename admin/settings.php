<?php
/**
 * Rosabella - Admin Panel & System Settings
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/image_helper.php';
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
    $removeAv  = ($_POST['remove_avatar'] ?? '0') === '1';

    if (empty($firstName) || empty($email)) {
        $error = 'First name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } else {
        // Fetch current user row
        $checkStmt = $db->prepare("SELECT id, password, avatar FROM users WHERE id = ?");
        $checkStmt->execute([$adminId]);
        $currUser = $checkStmt->fetch();

        // Verify email unique among other users
        $dupStmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $dupStmt->execute([$email, $adminId]);
        if ($dupStmt->fetch()) {
            $error = 'This email address is already in use by another account.';
        } else {
            $avatarPath = $currUser['avatar'] ?? null;

            // Handle Avatar Removal
            if ($removeAv && !empty($avatarPath)) {
                $oldFile = __DIR__ . '/../' . ltrim($avatarPath, '/');
                if (file_exists($oldFile) && is_file($oldFile)) {
                    @unlink($oldFile);
                }
                $avatarPath = null;
            }

            // Handle Avatar Upload
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../assets/uploads/avatars/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $newPath = optimizeAndSaveImage($_FILES['avatar'], $uploadDir, 400);
                if ($newPath) {
                    if (!empty($avatarPath) && $avatarPath !== $newPath) {
                        $oldFile = __DIR__ . '/../' . ltrim($avatarPath, '/');
                        if (file_exists($oldFile) && is_file($oldFile)) {
                            @unlink($oldFile);
                        }
                    }
                    $avatarPath = $newPath;
                } else {
                    $error = 'Failed to process avatar image.';
                }
            }

            if (!$error) {
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
                        $upd = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, password = ?, avatar = ? WHERE id = ?");
                        $upd->execute([$firstName, $lastName, $email, $hashed, $avatarPath, $adminId]);
                        $_SESSION['user_name'] = trim($firstName . ' ' . $lastName);
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_avatar'] = $avatarPath;
                        $message = 'Profile details, picture, and password updated successfully.';
                    }
                } else {
                    $upd = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, avatar = ? WHERE id = ?");
                    $upd->execute([$firstName, $lastName, $email, $avatarPath, $adminId]);
                    $_SESSION['user_name'] = trim($firstName . ' ' . $lastName);
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_avatar'] = $avatarPath;
                    $message = 'Profile details updated successfully.';
                }
            }
        }
    }
}

// ── 3. Handle System Actions (Purge Cache / Optimize DB) ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_optimize_db'])) {
    $activeTab = 'maintenance';
    try {
        // Dynamically fetch all existing tables in the database
        $stmt = $db->query("SHOW TABLES");
        $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $optimizedCount = 0;
        foreach ($allTables as $tbl) {
            $db->query("OPTIMIZE TABLE `$tbl`");
            $optimizedCount++;
        }
        $message = "Database optimization completed successfully ($optimizedCount tables defragmented and indexed).";
    } catch (Throwable $e) {
        $error = 'Database optimization failed: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_clear_cache'])) {
    $activeTab = 'maintenance';
    $clearedItems = 0;
    
    // 1. Clear asset cache directory
    $cacheDir = __DIR__ . '/../assets/cache/';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '*');
        foreach ($files as $f) {
            if (is_file($f)) {
                @unlink($f);
                $clearedItems++;
            }
        }
    } else {
        @mkdir($cacheDir, 0755, true);
    }

    // 2. Reset PHP OPcache if active
    if (function_exists('opcache_reset')) {
        @opcache_reset();
        $clearedItems++;
    }

    $message = "System cache and execution buffers purged successfully ($clearedItems objects/caches refreshed).";
}

// ── 4. Handle Custom Theme Palette Creation & Deletion ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_custom_palette'])) {
    $activeTab = 'theme';
    $paletteName = trim(sanitize($_POST['custom_palette_name'] ?? ''));
    if (empty($paletteName)) {
        $paletteName = 'My Custom Palette ' . date('M d');
    }

    $paletteData = [
        'id'   => 'custom_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 6),
        'name' => $paletteName,
        'sbg'  => sanitize($_POST['custom_sbg'] ?? '#f1f5f9'),
        'stxt' => sanitize($_POST['custom_stxt'] ?? '#1e293b'),
        'shov' => sanitize($_POST['custom_shov'] ?? '#ffffff'),
        'sact' => sanitize($_POST['custom_sact'] ?? '#e6fcf5'),
        'cbg'  => sanitize($_POST['custom_cbg'] ?? '#f8fafc'),
        'ctxt' => sanitize($_POST['custom_ctxt'] ?? '#0f172a'),
        'pri'  => sanitize($_POST['custom_pri'] ?? '#0f766e'),
    ];

    $existingJson = getSettingVal($allSettings ?? [], 'admin_custom_theme_palettes', '[]');
    $savedPalettes = json_decode($existingJson, true);
    if (!is_array($savedPalettes)) {
        $savedPalettes = [];
    }

    $savedPalettes[] = $paletteData;
    saveAdminSetting($db, 'admin_custom_theme_palettes', json_encode($savedPalettes), 'text');
    $allSettings['admin_custom_theme_palettes'] = json_encode($savedPalettes);
    $message = "Custom palette '{$paletteName}' created and saved successfully!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete_custom_palette'])) {
    $activeTab = 'theme';
    $paletteId = sanitize($_POST['palette_id'] ?? '');

    $existingJson = getSettingVal($allSettings ?? [], 'admin_custom_theme_palettes', '[]');
    $savedPalettes = json_decode($existingJson, true);
    if (!is_array($savedPalettes)) {
        $savedPalettes = [];
    }

    $filtered = array_values(array_filter($savedPalettes, fn($p) => ($p['id'] ?? '') !== $paletteId));
    saveAdminSetting($db, 'admin_custom_theme_palettes', json_encode($filtered), 'text');
    $allSettings['admin_custom_theme_palettes'] = json_encode($filtered);
    $message = 'Custom palette removed successfully.';
}

// Fetch current admin user info
$adminStmt = $db->prepare("SELECT first_name, last_name, email, avatar, role, created_at FROM users WHERE id = ?");
$adminStmt->execute([(int)$_SESSION['user_id']]);
$adminProfile = $adminStmt->fetch() ?: [
    'first_name' => 'Admin',
    'last_name' => '',
    'email' => 'admin@rosabella.com',
    'avatar' => null,
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
            gap: 0.75rem;
            min-width: 0;
        }

        .as-diag-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: #64748b;
            flex-shrink: 0;
        }

        .as-diag-val {
            font-size: 0.8rem;
            font-weight: 600;
            color: #0f172a;
            font-family: var(--admin-font);
            text-align: right;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        /* ── Tools Grid ── */
        .as-tools-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        /* ── Theme Color Search & Presets ── */
        .as-color-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            position: relative;
        }

        .as-color-swatch-picker {
            width: 38px;
            height: 38px;
            padding: 2px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            cursor: pointer;
            background: #ffffff;
            flex-shrink: 0;
        }

        .as-color-search-box {
            position: relative;
            flex: 1;
            min-width: 220px;
        }

        .as-color-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15);
            max-height: 220px;
            overflow-y: auto;
            z-index: 1050;
            display: none;
            margin-top: 4px;
        }

        .as-color-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 7px 12px;
            cursor: pointer;
            transition: background 0.12s ease;
        }

        .as-color-item:hover {
            background: #f0fdf4;
        }

        .as-theme-preset-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(185px, 1fr));
            gap: 0.85rem;
            margin-top: 0.85rem;
        }

        .as-preset-card {
            position: relative;
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.85rem 0.85rem 0.75rem;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .as-preset-card:hover {
            border-color: #0f766e;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(15, 118, 110, 0.09);
        }

        .as-preset-card.active-preset {
            border-color: #0f766e !important;
            background: #f0fdfa !important;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.18), 0 8px 18px rgba(15, 118, 110, 0.1) !important;
        }

        .as-preset-badge {
            display: none;
            position: absolute;
            top: 7px;
            right: 8px;
            background: #0f766e;
            color: #ffffff;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
            letter-spacing: 0.02em;
            box-shadow: 0 2px 4px rgba(15, 118, 110, 0.25);
            z-index: 2;
        }

        .as-preset-card.active-preset .as-preset-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .as-preset-delete-btn {
            position: absolute;
            top: 6px;
            right: 6px;
            background: #fee2e2;
            color: #ef4444;
            border: 1px solid #fca5a5;
            border-radius: 6px;
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
            transition: all 0.15s ease;
            z-index: 4;
            opacity: 0.85;
        }

        .as-preset-delete-btn:hover {
            background: #ef4444;
            color: #ffffff;
            border-color: #dc2626;
            transform: scale(1.1);
            opacity: 1;
        }

        .as-preset-card.active-preset .as-preset-delete-btn {
            right: auto;
            left: 6px;
        }

        .as-custom-creator-card {
            background: linear-gradient(135deg, #f0fdfa 0%, #f8fafc 100%);
            border: 1.5px dashed #0d9488;
            border-radius: 12px;
            padding: 1.15rem;
            margin-top: 1.5rem;
        }

        .as-preset-preview {
            display: flex;
            height: 44px;
            border-radius: 7px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);
        }

        .as-preset-sidebar {
            width: 38%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            border-right: 1px solid rgba(0,0,0,0.08);
            padding: 2px;
        }

        .as-preset-sidebar-text-bar {
            width: 60%;
            height: 4px;
            border-radius: 2px;
            opacity: 0.85;
        }

        .as-preset-content {
            flex: 1;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 8px;
        }

        .as-preset-content-text-bar {
            width: 45%;
            height: 4px;
            border-radius: 2px;
            opacity: 0.85;
        }

        .as-preset-accent {
            width: 14px;
            height: 14px;
            border-radius: 3px;
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
        }

        @media (max-width: 860px) {
            .as-layout-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .as-nav-card {
                position: static;
                display: flex;
                flex-direction: row;
                overflow-x: auto;
                padding: 0.4rem;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                gap: 0.35rem;
            }
            .as-nav-card::-webkit-scrollbar {
                display: none;
            }
            .as-nav-item {
                white-space: nowrap;
                padding: 0.55rem 0.85rem;
                font-size: 0.8rem;
                flex-shrink: 0;
                border-radius: 8px;
            }
            .as-row {
                grid-template-columns: 1fr;
                gap: 0.65rem;
            }
            .as-diag-grid {
                grid-template-columns: 1fr;
            }
            .as-tools-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 540px) {
            .as-diag-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            .as-diag-val {
                text-align: left;
            }
            .as-color-row {
                flex-direction: column;
                align-items: stretch;
            }
            .as-color-search-box {
                width: 100%;
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
                <button type="button" class="as-nav-item <?= $activeTab === 'theme' ? 'active' : '' ?>" onclick="switchAdminTab('theme', this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a7 7 0 0 0-7 7c0 2.38 1.19 4.47 3 5.74V17a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-2.26c1.81-1.27 3-3.36 3-5.74a7 7 0 0 0-7-7z"/><line x1="9" y1="21" x2="15" y2="21"/></svg>
                    <span>Admin Theme & Colors</span>
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
                    <form method="POST" enctype="multipart/form-data" id="adminProfileForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="remove_avatar" id="removeAvatarInput" value="0">
                        
                        <div class="as-section-card">
                            <div class="as-card-header">
                                <div>
                                    <h3 class="as-card-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0f766e" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                        Administrator Identity & Profile
                                    </h3>
                                    <div class="as-card-sub">Manage your profile photo, display identity, and administrative authentication.</div>
                                </div>
                            </div>

                            <!-- Executive Profile Identity Header Card -->
                            <?php 
                            $currAvatar = $adminProfile['avatar'] ?? '';
                            $hasAvatar = !empty($currAvatar);
                            $avatarSrc = resolveAdminImageSrc($currAvatar);
                            $profileInitials = strtoupper(substr(trim(($adminProfile['first_name'] ?? 'A') . ' ' . ($adminProfile['last_name'] ?? '')), 0, 2));
                            if (empty($profileInitials)) $profileInitials = 'AD';
                            ?>
                            <div style="background: linear-gradient(135deg, #f8fafc 0%, #f0fdfa 100%); border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem 1.4rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.25rem;">
                                <!-- Left: Avatar + Identity Info -->
                                <div style="display: flex; align-items: center; gap: 1.15rem;">
                                    <!-- Interactive Avatar Frame with Camera Edit Badge -->
                                    <div style="position: relative; width: 76px; height: 76px; flex-shrink: 0; cursor: pointer;" onclick="document.getElementById('avatarFileInput').click()" title="Click to upload new picture">
                                        <div id="avatarPreviewContainer" style="width: 100%; height: 100%; border-radius: 50%; border: 3px solid #ffffff; background: linear-gradient(135deg, #ccfbf1 0%, #99f6e4 100%); display: flex; align-items: center; justify-content: center; overflow: hidden; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);">
                                            <?php if ($hasAvatar): ?>
                                                <img id="avatarPreviewImg" src="<?= htmlspecialchars($avatarSrc) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                                <span id="avatarPreviewInitials" style="font-size: 1.5rem; font-weight: 700; color: #0f766e; display: none;"><?= $profileInitials ?></span>
                                            <?php else: ?>
                                                <img id="avatarPreviewImg" src="" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                                <span id="avatarPreviewInitials" style="font-size: 1.5rem; font-weight: 700; color: #0f766e;"><?= $profileInitials ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <!-- Floating Camera Icon Badge -->
                                        <div style="position: absolute; right: -2px; bottom: -2px; width: 26px; height: 26px; border-radius: 50%; background: #0f766e; color: #ffffff; border: 2.5px solid #ffffff; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.18);">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                        </div>
                                    </div>

                                    <!-- Name, Role & Email Details -->
                                    <div>
                                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                            <h3 style="margin: 0; font-size: 1.12rem; font-weight: 700; color: #0f172a; font-family: var(--admin-heading-font); letter-spacing: -0.01em;">
                                                <?= htmlspecialchars(trim(($adminProfile['first_name'] ?? 'Admin') . ' ' . ($adminProfile['last_name'] ?? ''))) ?>
                                            </h3>
                                            <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 20px; font-size: 0.68rem; font-weight: 600; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; text-transform: uppercase; letter-spacing: 0.04em;">
                                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                                <span><?= htmlspecialchars(ucfirst($adminProfile['role'] ?? 'Admin')) ?></span>
                                            </span>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; font-weight: 400;"><?= htmlspecialchars($adminProfile['email'] ?? '') ?></div>
                                        <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 2px;">Administrator since <?= date('M Y', strtotime($adminProfile['created_at'] ?? 'now')) ?></div>
                                    </div>
                                </div>

                                <!-- Right: Action Upload & Remove Controls -->
                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <label for="avatarFileInput" class="btn btn-primary" style="height: 35px; font-size: 0.81rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; padding: 0 1rem; border-radius: 8px; font-weight: 500;">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            <span>Upload Photo</span>
                                        </label>
                                        <input type="file" name="avatar" id="avatarFileInput" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="previewAvatarImage(this)">

                                        <button type="button" id="removeAvatarBtn" class="btn btn-outline" style="height: 35px; font-size: 0.81rem; color: #ef4444; border-color: #fecaca; background: #fff; border-radius: 8px; <?= $hasAvatar ? '' : 'display: none;' ?>" onclick="markAvatarForRemoval()" title="Remove current photo">
                                            Remove
                                        </button>
                                    </div>
                                    <div style="font-size: 0.72rem; color: #64748b; text-align: right;">
                                        JPG, PNG or WebP &bull; 400&times;400 auto-optimized
                                    </div>
                                </div>
                            </div>

                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Display Name</h4>
                                    <p>Your full administrator name displayed across admin topbar, activity logs, and receipts.</p>
                                </div>
                                <div class="as-input-wrap" style="gap: 10px;">
                                    <div style="width: 100%;">
                                        <label style="display: block; font-size: 0.72rem; font-weight: 500; color: #64748b; margin-bottom: 3px;">First Name</label>
                                        <input type="text" name="first_name" class="as-input" placeholder="First Name" value="<?= htmlspecialchars($adminProfile['first_name'] ?? '') ?>" required>
                                    </div>
                                    <div style="width: 100%;">
                                        <label style="display: block; font-size: 0.72rem; font-weight: 500; color: #64748b; margin-bottom: 3px;">Last Name</label>
                                        <input type="text" name="last_name" class="as-input" placeholder="Last Name" value="<?= htmlspecialchars($adminProfile['last_name'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Login Email Address</h4>
                                    <p>Your primary email address used for administrative login credentials and two-factor alerts.</p>
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

                <!-- ── TAB 5: Admin Theme & Appearance ── -->
                <div id="tab-theme" class="as-tab-panel <?= $activeTab === 'theme' ? 'active' : '' ?>">
                    <form method="POST" id="adminThemeForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="save_admin_settings" value="1">
                        <input type="hidden" name="active_tab" value="theme">

                        <div class="as-section-card">
                            <div class="as-card-header">
                                <div>
                                    <h3 class="as-card-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0f766e" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a7 7 0 0 0-7 7c0 2.38 1.19 4.47 3 5.74V17a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-2.26c1.81-1.27 3-3.36 3-5.74a7 7 0 0 0-7-7z"/><line x1="9" y1="21" x2="15" y2="21"/></svg>
                                        Admin Interface Theme & Color Customizer
                                    </h3>
                                    <div class="as-card-sub">Customize background, font, hover, and active highlight colors for the sidebar and main content canvas with live search across 120+ curated hex shades.</div>
                                </div>
                            </div>

                            <!-- 1. Sidebar Background Color -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Sidebar Background</h4>
                                    <p>Background color of the fixed admin navigation sidebar.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <div class="as-color-row">
                                        <input type="color" id="picker_sidebar_bg" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_sidebar_bg', '#f1f5f9')) ?>" class="as-color-swatch-picker" onchange="syncColorInput('sidebar_bg', this.value)">
                                        <input type="text" id="hex_sidebar_bg" name="settings[admin_sidebar_bg]" class="as-input" style="max-width: 105px; font-family: monospace; font-weight: 600;" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_sidebar_bg', '#f1f5f9')) ?>" oninput="syncColorInput('sidebar_bg', this.value)">
                                        <div class="as-color-search-box">
                                            <input type="text" class="as-input color-search-field" data-target="sidebar_bg" placeholder="Search 120+ colors (e.g. Slate, Black, Cream, Navy)...">
                                            <div class="as-color-dropdown"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Sidebar Font / Text Color -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Sidebar Font / Text Color</h4>
                                    <p>Text, labels, and icons color across the navigation sidebar.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <div class="as-color-row">
                                        <input type="color" id="picker_sidebar_text" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_sidebar_text', '#1e293b')) ?>" class="as-color-swatch-picker" onchange="syncColorInput('sidebar_text', this.value)">
                                        <input type="text" id="hex_sidebar_text" name="settings[admin_sidebar_text]" class="as-input" style="max-width: 105px; font-family: monospace; font-weight: 600;" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_sidebar_text', '#1e293b')) ?>" oninput="syncColorInput('sidebar_text', this.value)">
                                        <div class="as-color-search-box">
                                            <input type="text" class="as-input color-search-field" data-target="sidebar_text" placeholder="Search 120+ colors (e.g. Dark Charcoal, White, Silver)...">
                                            <div class="as-color-dropdown"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Sidebar Nav Item Hover Background -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Sidebar Hover Background</h4>
                                    <p>Background card color when hovering over navigation links and dropdown headers.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <div class="as-color-row">
                                        <input type="color" id="picker_sidebar_hover_bg" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_sidebar_hover_bg', '#ffffff')) ?>" class="as-color-swatch-picker" onchange="syncColorInput('sidebar_hover_bg', this.value)">
                                        <input type="text" id="hex_sidebar_hover_bg" name="settings[admin_sidebar_hover_bg]" class="as-input" style="max-width: 105px; font-family: monospace; font-weight: 600;" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_sidebar_hover_bg', '#ffffff')) ?>" oninput="syncColorInput('sidebar_hover_bg', this.value)">
                                        <div class="as-color-search-box">
                                            <input type="text" class="as-input color-search-field" data-target="sidebar_hover_bg" placeholder="Search 120+ colors (e.g. Pure White, Slate 800, Ice)...">
                                            <div class="as-color-dropdown"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 4. Sidebar Nav Active / Highlight Background -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Sidebar Active Highlight Background</h4>
                                    <p>Background pill color for the currently active page in sidebar navigation.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <div class="as-color-row">
                                        <input type="color" id="picker_sidebar_active_bg" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_sidebar_active_bg', '#e6fcf5')) ?>" class="as-color-swatch-picker" onchange="syncColorInput('sidebar_active_bg', this.value)">
                                        <input type="text" id="hex_sidebar_active_bg" name="settings[admin_sidebar_active_bg]" class="as-input" style="max-width: 105px; font-family: monospace; font-weight: 600;" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_sidebar_active_bg', '#e6fcf5')) ?>" oninput="syncColorInput('sidebar_active_bg', this.value)">
                                        <div class="as-color-search-box">
                                            <input type="text" class="as-input color-search-field" data-target="sidebar_active_bg" placeholder="Search 120+ colors (e.g. Teal 100, Sky Blue, Dark Slate)...">
                                            <div class="as-color-dropdown"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 5. Content Canvas Background Color -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Content Canvas Background</h4>
                                    <p>Background canvas color for tables, cards, dashboard charts, and main panels.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <div class="as-color-row">
                                        <input type="color" id="picker_content_bg" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_content_bg', '#f8fafc')) ?>" class="as-color-swatch-picker" onchange="syncColorInput('content_bg', this.value)">
                                        <input type="text" id="hex_content_bg" name="settings[admin_content_bg]" class="as-input" style="max-width: 105px; font-family: monospace; font-weight: 600;" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_content_bg', '#f8fafc')) ?>" oninput="syncColorInput('content_bg', this.value)">
                                        <div class="as-color-search-box">
                                            <input type="text" class="as-input color-search-field" data-target="content_bg" placeholder="Search 120+ colors (e.g. Ice, Off White, Gray, Snow)...">
                                            <div class="as-color-dropdown"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 6. Content Area Font / Text Color -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Content Area Font / Text Color</h4>
                                    <p>Primary typography font color for headings, card text, data tables, and metrics.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <div class="as-color-row">
                                        <input type="color" id="picker_content_text" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_content_text', '#0f172a')) ?>" class="as-color-swatch-picker" onchange="syncColorInput('content_text', this.value)">
                                        <input type="text" id="hex_content_text" name="settings[admin_content_text]" class="as-input" style="max-width: 105px; font-family: monospace; font-weight: 600;" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_content_text', '#0f172a')) ?>" oninput="syncColorInput('content_text', this.value)">
                                        <div class="as-color-search-box">
                                            <input type="text" class="as-input color-search-field" data-target="content_text" placeholder="Search 120+ colors (e.g. Charcoal, Jet Black, Gray)...">
                                            <div class="as-color-dropdown"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 7. Primary Brand Accent Color -->
                            <div class="as-row">
                                <div class="as-row-info">
                                    <h4>Primary Accent Color</h4>
                                    <p>Accent highlight color used for active navigation pills, primary action buttons, and icons.</p>
                                </div>
                                <div class="as-input-wrap">
                                    <div class="as-color-row">
                                        <input type="color" id="picker_primary_color" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_primary_color', '#0f766e')) ?>" class="as-color-swatch-picker" onchange="syncColorInput('primary_color', this.value)">
                                        <input type="text" id="hex_primary_color" name="settings[admin_primary_color]" class="as-input" style="max-width: 105px; font-family: monospace; font-weight: 600;" value="<?= htmlspecialchars(getSettingVal($allSettings, 'admin_primary_color', '#0f766e')) ?>" oninput="syncColorInput('primary_color', this.value)">
                                        <div class="as-color-search-box">
                                            <input type="text" class="as-input color-search-field" data-target="primary_color" placeholder="Search 120+ colors (e.g. Teal, Emerald, Blue, Rose)...">
                                            <div class="as-color-dropdown"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Create Custom Color Palette Section -->
                            <div class="as-custom-creator-card">
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.65rem;">
                                    <div>
                                        <h4 style="margin: 0; font-size: 0.90rem; font-weight: 700; color: #0f766e; display: flex; align-items: center; gap: 6px;">
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v20M2 12h20"/></svg>
                                            Create Custom Color Palette
                                        </h4>
                                        <p style="margin: 2px 0 0; font-size: 0.76rem; color: #475569;">Save your configured background, text, hover, and highlight colors as a reusable 1-click theme palette.</p>
                                    </div>
                                    <button type="button" class="btn btn-secondary" onclick="toggleCustomPaletteForm()" id="btnToggleCustomPalette" style="height: 32px; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 5px;">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                        <span>Save Current as Custom Palette</span>
                                    </button>
                                </div>

                                <div id="customPaletteFormWrapper" style="display: none; margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px dashed #cbd5e1;">
                                    <form method="POST" id="saveCustomPaletteForm" style="margin: 0;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action_save_custom_palette" value="1">
                                        <input type="hidden" name="custom_sbg" id="cp_sbg">
                                        <input type="hidden" name="custom_stxt" id="cp_stxt">
                                        <input type="hidden" name="custom_shov" id="cp_shov">
                                        <input type="hidden" name="custom_sact" id="cp_sact">
                                        <input type="hidden" name="custom_cbg" id="cp_cbg">
                                        <input type="hidden" name="custom_ctxt" id="cp_ctxt">
                                        <input type="hidden" name="custom_pri" id="cp_pri">

                                        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                                            <div style="flex: 1; min-width: 220px;">
                                                <label style="font-size: 0.78rem; font-weight: 600; color: #0f172a; margin-bottom: 4px; display: block;">Custom Palette Name</label>
                                                <input type="text" name="custom_palette_name" id="custom_palette_name_input" class="as-input" placeholder="e.g. Sapphire Executive, Rosabella Boutique, Dark Velvet..." required style="height: 38px;">
                                            </div>
                                            <div style="display: flex; gap: 0.5rem; align-items: flex-end; margin-top: auto;">
                                                <button type="submit" class="btn btn-primary" onclick="prepareCustomPaletteSave()" style="height: 38px; font-size: 0.82rem; padding: 0 1.25rem;">
                                                    Save Palette
                                                </button>
                                                <button type="button" class="btn btn-secondary" onclick="toggleCustomPaletteForm()" style="height: 38px; font-size: 0.82rem;">
                                                    Cancel
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <?php
                            $customPalettesJson = getSettingVal($allSettings, 'admin_custom_theme_palettes', '[]');
                            $customPalettes = json_decode($customPalettesJson, true) ?: [];
                            if (!empty($customPalettes)):
                            ?>
                            <!-- Saved Custom Palettes Section -->
                            <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9;">
                                <h4 style="margin: 0 0 0.75rem; font-size: 0.875rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0f766e" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                    Your Saved Custom Palettes (<?= count($customPalettes) ?>)
                                </h4>
                                <div class="as-theme-preset-grid">
                                    <?php foreach ($customPalettes as $cp): ?>
                                    <div class="as-preset-card" 
                                         id="preset_<?= htmlspecialchars($cp['id']) ?>" 
                                         data-sbg="<?= htmlspecialchars($cp['sbg']) ?>" 
                                         data-stxt="<?= htmlspecialchars($cp['stxt']) ?>" 
                                         data-shov="<?= htmlspecialchars($cp['shov']) ?>" 
                                         data-sact="<?= htmlspecialchars($cp['sact']) ?>" 
                                         data-cbg="<?= htmlspecialchars($cp['cbg']) ?>" 
                                         data-ctxt="<?= htmlspecialchars($cp['ctxt']) ?>" 
                                         data-pri="<?= htmlspecialchars($cp['pri']) ?>" 
                                         onclick="applyThemePreset('<?= htmlspecialchars($cp['sbg']) ?>', '<?= htmlspecialchars($cp['stxt']) ?>', '<?= htmlspecialchars($cp['shov']) ?>', '<?= htmlspecialchars($cp['sact']) ?>', '<?= htmlspecialchars($cp['cbg']) ?>', '<?= htmlspecialchars($cp['ctxt']) ?>', '<?= htmlspecialchars($cp['pri']) ?>', 'preset_<?= htmlspecialchars($cp['id']) ?>')">
                                        
                                        <span class="as-preset-badge"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Active</span>

                                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Delete this custom palette permanently?');" onclick="event.stopPropagation();">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action_delete_custom_palette" value="1">
                                            <input type="hidden" name="palette_id" value="<?= htmlspecialchars($cp['id']) ?>">
                                            <button type="submit" class="as-preset-delete-btn" title="Delete custom palette">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </form>

                                        <div class="as-preset-preview">
                                            <div class="as-preset-sidebar" style="background: <?= htmlspecialchars($cp['sbg']) ?>;">
                                                <div style="background: <?= htmlspecialchars($cp['sact']) ?>; border-left: 2px solid <?= htmlspecialchars($cp['pri']) ?>; border-radius: 2px; padding: 2px 4px; width: 85%;">
                                                    <div class="as-preset-sidebar-text-bar" style="background: <?= htmlspecialchars($cp['pri']) ?>; width: 100%;"></div>
                                                </div>
                                            </div>
                                            <div class="as-preset-content" style="background: <?= htmlspecialchars($cp['cbg']) ?>;">
                                                <div class="as-preset-content-text-bar" style="background: <?= htmlspecialchars($cp['ctxt']) ?>;"></div>
                                                <div class="as-preset-accent" style="background: <?= htmlspecialchars($cp['pri']) ?>;"></div>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.80rem; font-weight: 600; color: #0f172a; padding-right: 20px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;"><?= htmlspecialchars($cp['name']) ?></div>
                                        <div style="font-size: 0.70rem; color: #64748b;"><?= htmlspecialchars($cp['sbg']) ?> &bull; <?= htmlspecialchars($cp['cbg']) ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- One-Click Preset Palettes -->
                            <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                                    <h4 style="margin: 0; font-size: 0.875rem; font-weight: 700; color: #0f172a;">One-Click Executive Preset Palettes</h4>
                                    <button type="button" class="btn btn-secondary" onclick="applyThemePreset('#f1f5f9', '#1e293b', '#ffffff', '#e6fcf5', '#f8fafc', '#0f172a', '#0f766e', 'preset_slate')" style="height: 30px; font-size: 0.75rem; padding: 0 10px;">
                                        Reset to Default
                                    </button>
                                </div>
                                <div class="as-theme-preset-grid">
                                    <!-- 1. Executive Slate -->
                                    <div class="as-preset-card" id="preset_slate" data-sbg="#f1f5f9" data-stxt="#1e293b" data-shov="#ffffff" data-sact="#e6fcf5" data-cbg="#f8fafc" data-ctxt="#0f172a" data-pri="#0f766e" onclick="applyThemePreset('#f1f5f9', '#1e293b', '#ffffff', '#e6fcf5', '#f8fafc', '#0f172a', '#0f766e', 'preset_slate')">
                                        <span class="as-preset-badge"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Active</span>
                                        <div class="as-preset-preview">
                                            <div class="as-preset-sidebar" style="background: #f1f5f9;">
                                                <div style="background: #e6fcf5; border-left: 2px solid #0f766e; border-radius: 2px; padding: 2px 4px; width: 85%;">
                                                    <div class="as-preset-sidebar-text-bar" style="background: #0f766e; width: 100%;"></div>
                                                </div>
                                            </div>
                                            <div class="as-preset-content" style="background: #f8fafc;">
                                                <div class="as-preset-content-text-bar" style="background: #0f172a;"></div>
                                                <div class="as-preset-accent" style="background: #0f766e;"></div>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.80rem; font-weight: 600; color: #0f172a;">Executive Slate (Default)</div>
                                        <div style="font-size: 0.70rem; color: #64748b;">Slate #f1f5f9 &bull; Canvas #f8fafc</div>
                                    </div>

                                    <!-- 2. Pure Minimal White -->
                                    <div class="as-preset-card" id="preset_white" data-sbg="#ffffff" data-stxt="#334155" data-shov="#f8fafc" data-sact="#f0fdfa" data-cbg="#fafaf9" data-ctxt="#1e293b" data-pri="#0f766e" onclick="applyThemePreset('#ffffff', '#334155', '#f8fafc', '#f0fdfa', '#fafaf9', '#1e293b', '#0f766e', 'preset_white')">
                                        <span class="as-preset-badge"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Active</span>
                                        <div class="as-preset-preview">
                                            <div class="as-preset-sidebar" style="background: #ffffff;">
                                                <div style="background: #f0fdfa; border-left: 2px solid #0f766e; border-radius: 2px; padding: 2px 4px; width: 85%;">
                                                    <div class="as-preset-sidebar-text-bar" style="background: #0f766e; width: 100%;"></div>
                                                </div>
                                            </div>
                                            <div class="as-preset-content" style="background: #fafaf9;">
                                                <div class="as-preset-content-text-bar" style="background: #1e293b;"></div>
                                                <div class="as-preset-accent" style="background: #0f766e;"></div>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.80rem; font-weight: 600; color: #0f172a;">Pure Minimal White</div>
                                        <div style="font-size: 0.70rem; color: #64748b;">White #ffffff &bull; Canvas #fafaf9</div>
                                    </div>

                                    <!-- 3. Cool Gray Studio -->
                                    <div class="as-preset-card" id="preset_cool" data-sbg="#e2e8f0" data-stxt="#1e293b" data-shov="#ffffff" data-sact="#dbeafe" data-cbg="#f1f5f9" data-ctxt="#0f172a" data-pri="#2563eb" onclick="applyThemePreset('#e2e8f0', '#1e293b', '#ffffff', '#dbeafe', '#f1f5f9', '#0f172a', '#2563eb', 'preset_cool')">
                                        <span class="as-preset-badge"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Active</span>
                                        <div class="as-preset-preview">
                                            <div class="as-preset-sidebar" style="background: #e2e8f0;">
                                                <div style="background: #dbeafe; border-left: 2px solid #2563eb; border-radius: 2px; padding: 2px 4px; width: 85%;">
                                                    <div class="as-preset-sidebar-text-bar" style="background: #2563eb; width: 100%;"></div>
                                                </div>
                                            </div>
                                            <div class="as-preset-content" style="background: #f1f5f9;">
                                                <div class="as-preset-content-text-bar" style="background: #0f172a;"></div>
                                                <div class="as-preset-accent" style="background: #2563eb;"></div>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.80rem; font-weight: 600; color: #0f172a;">Cool Gray Studio</div>
                                        <div style="font-size: 0.70rem; color: #64748b;">Gray #e2e8f0 &bull; Canvas #f1f5f9</div>
                                    </div>

                                    <!-- 4. Dark Executive Slate -->
                                    <div class="as-preset-card" id="preset_dark" data-sbg="#0f172a" data-stxt="#f1f5f9" data-shov="#1e293b" data-sact="#134e4a" data-cbg="#f8fafc" data-ctxt="#0f172a" data-pri="#0d9488" onclick="applyThemePreset('#0f172a', '#f1f5f9', '#1e293b', '#134e4a', '#f8fafc', '#0f172a', '#0d9488', 'preset_dark')">
                                        <span class="as-preset-badge"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Active</span>
                                        <div class="as-preset-preview">
                                            <div class="as-preset-sidebar" style="background: #0f172a;">
                                                <div style="background: #134e4a; border-left: 2px solid #0d9488; border-radius: 2px; padding: 2px 4px; width: 85%;">
                                                    <div class="as-preset-sidebar-text-bar" style="background: #5eead4; width: 100%;"></div>
                                                </div>
                                            </div>
                                            <div class="as-preset-content" style="background: #f8fafc;">
                                                <div class="as-preset-content-text-bar" style="background: #0f172a;"></div>
                                                <div class="as-preset-accent" style="background: #0d9488;"></div>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.80rem; font-weight: 600; color: #0f172a;">Dark Executive Slate</div>
                                        <div style="font-size: 0.70rem; color: #64748b;">Dark #0f172a &bull; Canvas #f8fafc</div>
                                    </div>

                                    <!-- 5. Midnight Azure (Full Dark) -->
                                    <div class="as-preset-card" id="preset_midnight" data-sbg="#1e293b" data-stxt="#f8fafc" data-shov="#334155" data-sact="#312e81" data-cbg="#0f172a" data-ctxt="#f1f5f9" data-pri="#6366f1" onclick="applyThemePreset('#1e293b', '#f8fafc', '#334155', '#312e81', '#0f172a', '#f1f5f9', '#6366f1', 'preset_midnight')">
                                        <span class="as-preset-badge"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Active</span>
                                        <div class="as-preset-preview">
                                            <div class="as-preset-sidebar" style="background: #1e293b;">
                                                <div style="background: #312e81; border-left: 2px solid #6366f1; border-radius: 2px; padding: 2px 4px; width: 85%;">
                                                    <div class="as-preset-sidebar-text-bar" style="background: #a5b4fc; width: 100%;"></div>
                                                </div>
                                            </div>
                                            <div class="as-preset-content" style="background: #0f172a;">
                                                <div class="as-preset-content-text-bar" style="background: #f1f5f9;"></div>
                                                <div class="as-preset-accent" style="background: #6366f1;"></div>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.80rem; font-weight: 600; color: #0f172a;">Midnight Azure (Dark)</div>
                                        <div style="font-size: 0.70rem; color: #64748b;">Midnight #1e293b &bull; Canvas #0f172a</div>
                                    </div>

                                    <!-- 6. Soft Warm Cream -->
                                    <div class="as-preset-card" id="preset_cream" data-sbg="#faf9f6" data-stxt="#451a03" data-shov="#ffffff" data-sact="#fef3c7" data-cbg="#fefdfb" data-ctxt="#292524" data-pri="#d97706" onclick="applyThemePreset('#faf9f6', '#451a03', '#ffffff', '#fef3c7', '#fefdfb', '#292524', '#d97706', 'preset_cream')">
                                        <span class="as-preset-badge"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Active</span>
                                        <div class="as-preset-preview">
                                            <div class="as-preset-sidebar" style="background: #faf9f6;">
                                                <div style="background: #fef3c7; border-left: 2px solid #d97706; border-radius: 2px; padding: 2px 4px; width: 85%;">
                                                    <div class="as-preset-sidebar-text-bar" style="background: #d97706; width: 100%;"></div>
                                                </div>
                                            </div>
                                            <div class="as-preset-content" style="background: #fefdfb;">
                                                <div class="as-preset-content-text-bar" style="background: #292524;"></div>
                                                <div class="as-preset-accent" style="background: #d97706;"></div>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.80rem; font-weight: 600; color: #0f172a;">Soft Warm Cream</div>
                                        <div style="font-size: 0.70rem; color: #64748b;">Cream #faf9f6 &bull; Canvas #fefdfb</div>
                                    </div>

                                    <!-- 7. Emerald Mint -->
                                    <div class="as-preset-card" id="preset_emerald" data-sbg="#f0fdfa" data-stxt="#064e3b" data-shov="#ffffff" data-sact="#d1fae5" data-cbg="#f8fafc" data-ctxt="#0f172a" data-pri="#059669" onclick="applyThemePreset('#f0fdfa', '#064e3b', '#ffffff', '#d1fae5', '#f8fafc', '#0f172a', '#059669', 'preset_emerald')">
                                        <span class="as-preset-badge"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Active</span>
                                        <div class="as-preset-preview">
                                            <div class="as-preset-sidebar" style="background: #f0fdfa;">
                                                <div style="background: #d1fae5; border-left: 2px solid #059669; border-radius: 2px; padding: 2px 4px; width: 85%;">
                                                    <div class="as-preset-sidebar-text-bar" style="background: #059669; width: 100%;"></div>
                                                </div>
                                            </div>
                                            <div class="as-preset-content" style="background: #f8fafc;">
                                                <div class="as-preset-content-text-bar" style="background: #0f172a;"></div>
                                                <div class="as-preset-accent" style="background: #059669;"></div>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.80rem; font-weight: 600; color: #0f172a;">Emerald Mint</div>
                                        <div style="font-size: 0.70rem; color: #64748b;">Mint #f0fdfa &bull; Canvas #f8fafc</div>
                                    </div>

                                    <!-- 8. Nordic Frost -->
                                    <div class="as-preset-card" id="preset_nordic" data-sbg="#eef2f6" data-stxt="#0c4a6e" data-shov="#ffffff" data-sact="#e0f2fe" data-cbg="#f8fafc" data-ctxt="#0f172a" data-pri="#0284c7" onclick="applyThemePreset('#eef2f6', '#0c4a6e', '#ffffff', '#e0f2fe', '#f8fafc', '#0f172a', '#0284c7', 'preset_nordic')">
                                        <span class="as-preset-badge"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Active</span>
                                        <div class="as-preset-preview">
                                            <div class="as-preset-sidebar" style="background: #eef2f6;">
                                                <div style="background: #e0f2fe; border-left: 2px solid #0284c7; border-radius: 2px; padding: 2px 4px; width: 85%;">
                                                    <div class="as-preset-sidebar-text-bar" style="background: #0284c7; width: 100%;"></div>
                                                </div>
                                            </div>
                                            <div class="as-preset-content" style="background: #f8fafc;">
                                                <div class="as-preset-content-text-bar" style="background: #0f172a;"></div>
                                                <div class="as-preset-accent" style="background: #0284c7;"></div>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.80rem; font-weight: 600; color: #0f172a;">Nordic Frost</div>
                                        <div style="font-size: 0.70rem; color: #64748b;">Ice #eef2f6 &bull; Canvas #f8fafc</div>
                                    </div>
                                </div>
                            </div>

                            <div class="as-actions-bar">
                                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    <span>Save Theme Settings</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ── TAB 6: Maintenance & System Tools ── -->
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

                        <div class="as-tools-grid">
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

function previewAvatarImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('avatarPreviewImg');
            const initials = document.getElementById('avatarPreviewInitials');
            const removeInput = document.getElementById('removeAvatarInput');
            const removeBtn = document.getElementById('removeAvatarBtn');
            if (removeInput) removeInput.value = '0';
            if (removeBtn) removeBtn.style.display = 'inline-block';
            if (img) {
                img.src = e.target.result;
                img.style.display = 'block';
            }
            if (initials) {
                initials.style.display = 'none';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function markAvatarForRemoval() {
    const removeInput = document.getElementById('removeAvatarInput');
    const img = document.getElementById('avatarPreviewImg');
    const initials = document.getElementById('avatarPreviewInitials');
    const fileInput = document.getElementById('avatarFileInput');
    const removeBtn = document.getElementById('removeAvatarBtn');

    if (removeInput) removeInput.value = '1';
    if (fileInput) fileInput.value = '';
    if (img) {
        img.src = '';
        img.style.display = 'none';
    }
    if (initials) {
        initials.style.display = 'block';
    }
    if (removeBtn) {
        removeBtn.style.display = 'none';
    }
}

/* ─── 120+ Curated Color Database & Live Theme Customizer ─── */
const ROSABELLA_THEME_COLORS = [
    // Monochrome & Basics
    { name: "Slate Gray", hex: "#708090" },
    { name: "Light Slate", hex: "#f1f5f9" },
    { name: "Cool Gray", hex: "#e2e8f0" },
    { name: "Ice White", hex: "#f8fafc" },
    { name: "Pure White", hex: "#FFFFFF" },
    { name: "Off White", hex: "#FAF9F6" },
    { name: "Snow White", hex: "#FFFAFA" },
    { name: "Ivory", hex: "#FFFFF0" },
    { name: "Cream", hex: "#FFFDD0" },
    { name: "Warm Alabaster", hex: "#F2F0EB" },
    { name: "Jet Black", hex: "#0A0A0A" },
    { name: "Midnight Black", hex: "#121212" },
    { name: "Matte Black", hex: "#222222" },
    { name: "Charcoal", hex: "#36454F" },
    { name: "Dark Charcoal", hex: "#212529" },
    { name: "Slate 900", hex: "#0f172a" },
    { name: "Slate 800", hex: "#1e293b" },
    { name: "Slate 700", hex: "#334155" },
    { name: "Gray", hex: "#808080" },
    { name: "Dark Gray", hex: "#555555" },
    { name: "Light Gray", hex: "#D3D3D3" },
    { name: "Silver", hex: "#C0C0C0" },
    { name: "Metallic Silver", hex: "#AAA9AD" },
    { name: "Space Gray", hex: "#4B4B4D" },
    { name: "Pebble Gray", hex: "#8B8C7A" },
    { name: "Ash Gray", hex: "#B2BEB5" },

    // Teals & Emeralds
    { name: "Deep Teal", hex: "#0f766e" },
    { name: "Teal", hex: "#008080" },
    { name: "Light Teal", hex: "#0d9488" },
    { name: "Teal 50", hex: "#f0fdfa" },
    { name: "Teal 100", hex: "#ccfbf1" },
    { name: "Emerald Green", hex: "#50C878" },
    { name: "Emerald Dark", hex: "#047857" },
    { name: "Emerald Light", hex: "#10b981" },
    { name: "Mint Green", hex: "#98FF98" },
    { name: "Seafoam Green", hex: "#9FE2BF" },
    { name: "Turquoise", hex: "#40E0D0" },
    { name: "Jade Green", hex: "#00A86B" },
    { name: "Sage Green", hex: "#9DC183" },
    { name: "Forest Green", hex: "#228B22" },
    { name: "Dark Green", hex: "#006400" },
    { name: "Olive Green", hex: "#808000" },
    { name: "Lime Green", hex: "#32CD32" },
    { name: "Pistachio Green", hex: "#93C572" },
    { name: "Army Green", hex: "#4B5320" },

    // Blues
    { name: "Navy Blue", hex: "#000080" },
    { name: "Midnight Blue", hex: "#191970" },
    { name: "Royal Blue", hex: "#4169E1" },
    { name: "Sky Blue", hex: "#87CEEB" },
    { name: "Baby Blue", hex: "#89CFF0" },
    { name: "Denim Blue", hex: "#1560BD" },
    { name: "Steel Blue", hex: "#4682B4" },
    { name: "Ocean Blue", hex: "#006994" },
    { name: "Sapphire Blue", hex: "#0F52BA" },
    { name: "Cobalt Blue", hex: "#0047AB" },
    { name: "Electric Blue", hex: "#7DF9FF" },
    { name: "Powder Blue", hex: "#B0E0E6" },
    { name: "Cyan", hex: "#00FFFF" },
    { name: "Aquamarine", hex: "#7FFFD4" },
    { name: "Sierra Blue", hex: "#9BB5CE" },
    { name: "Pacific Blue", hex: "#284A5C" },
    { name: "Ice Blue", hex: "#D4F1F4" },

    // Purples & Indigos
    { name: "Indigo", hex: "#4B0082" },
    { name: "Royal Purple", hex: "#7851A9" },
    { name: "Lavender", hex: "#E6E6FA" },
    { name: "Lavender Mist", hex: "#F5F3FF" },
    { name: "Violet", hex: "#8F00FF" },
    { name: "Plum", hex: "#8E4585" },
    { name: "Mauve", hex: "#E0B0FF" },
    { name: "Lilac", hex: "#C8A2C8" },
    { name: "Deep Amethyst", hex: "#9966CC" },
    { name: "Grape", hex: "#6F2DA8" },

    // Reds & Pinks
    { name: "Red", hex: "#FF0000" },
    { name: "Crimson Red", hex: "#DC143C" },
    { name: "Scarlet", hex: "#FF2400" },
    { name: "Burgundy", hex: "#800020" },
    { name: "Wine Red", hex: "#722F37" },
    { name: "Maroon", hex: "#800000" },
    { name: "Cherry Red", hex: "#D2042D" },
    { name: "Ruby Red", hex: "#9B111E" },
    { name: "Pink", hex: "#FFC0CB" },
    { name: "Rose Pink", hex: "#FF66CC" },
    { name: "Baby Pink", hex: "#F4C2C2" },
    { name: "Hot Pink", hex: "#FF69B4" },
    { name: "Blush Pink", hex: "#DE5D83" },
    { name: "Magenta", hex: "#FF00FF" },
    { name: "Coral Pink", hex: "#F88379" },
    { name: "Dusty Rose", hex: "#DCAE96" },
    { name: "Rose Gold", hex: "#B76E79" },
    { name: "Fuchsia", hex: "#FF00FF" },
    { name: "Salmon", hex: "#FA8072" },

    // Earth Tones & Yellows
    { name: "Gold", hex: "#FFD700" },
    { name: "Metallic Gold", hex: "#D4AF37" },
    { name: "Champagne", hex: "#F7E7CE" },
    { name: "Yellow", hex: "#FFFF00" },
    { name: "Mustard Yellow", hex: "#FFDB58" },
    { name: "Lemon Yellow", hex: "#FFF700" },
    { name: "Amber", hex: "#FFBF00" },
    { name: "Orange", hex: "#FFA500" },
    { name: "Burnt Orange", hex: "#CC5500" },
    { name: "Coral", hex: "#FF7F50" },
    { name: "Peach", hex: "#FFDAB9" },
    { name: "Tangerine", hex: "#F28500" },
    { name: "Rust", hex: "#B7410E" },
    { name: "Terracotta", hex: "#E2725B" },
    { name: "Bronze", hex: "#CD7F32" },
    { name: "Copper", hex: "#B87333" },
    { name: "Mocha Brown", hex: "#3B2F2F" },
    { name: "Chocolate Brown", hex: "#7B3F00" },
    { name: "Coffee Brown", hex: "#4A2C2A" },
    { name: "Caramel", hex: "#AF6E4D" },
    { name: "Beige", hex: "#F5F5DC" },
    { name: "Sand", hex: "#C2B280" },
    { name: "Khaki", hex: "#C3B091" },
    { name: "Tan", hex: "#D2B48C" }
];

function syncColorInput(target, hexVal) {
    if (!hexVal) return;
    if (!hexVal.startsWith('#')) hexVal = '#' + hexVal;
    
    // Update color picker swatch
    const picker = document.getElementById('picker_' + target);
    if (picker && /^#[0-9A-F]{6}$/i.test(hexVal)) picker.value = hexVal;

    // Update hex text box
    const hexInput = document.getElementById('hex_' + target);
    if (hexInput && hexInput.value.toUpperCase() !== hexVal.toUpperCase()) {
        hexInput.value = hexVal;
    }

    updateLiveThemePreview();
}

function applyThemePreset(sidebarBg, sidebarText, sidebarHoverBg, sidebarActiveBg, contentBg, contentText, primaryColor, presetId) {
    syncColorInput('sidebar_bg', sidebarBg);
    syncColorInput('sidebar_text', sidebarText);
    syncColorInput('sidebar_hover_bg', sidebarHoverBg);
    syncColorInput('sidebar_active_bg', sidebarActiveBg);
    syncColorInput('content_bg', contentBg);
    syncColorInput('content_text', contentText);
    syncColorInput('primary_color', primaryColor);

    // Update search field texts with matched preset names
    const matchName = (hex) => {
        const found = ROSABELLA_THEME_COLORS.find(c => c.hex.toLowerCase() === hex.toLowerCase());
        return found ? `${found.name} (${found.hex})` : hex;
    };
    
    const sInput = document.querySelector('.color-search-field[data-target="sidebar_bg"]');
    const stInput = document.querySelector('.color-search-field[data-target="sidebar_text"]');
    const shInput = document.querySelector('.color-search-field[data-target="sidebar_hover_bg"]');
    const saInput = document.querySelector('.color-search-field[data-target="sidebar_active_bg"]');
    const cInput = document.querySelector('.color-search-field[data-target="content_bg"]');
    const ctInput = document.querySelector('.color-search-field[data-target="content_text"]');
    const pInput = document.querySelector('.color-search-field[data-target="primary_color"]');

    if (sInput) sInput.value = matchName(sidebarBg);
    if (stInput) stInput.value = matchName(sidebarText);
    if (shInput) shInput.value = matchName(sidebarHoverBg);
    if (saInput) saInput.value = matchName(sidebarActiveBg);
    if (cInput) cInput.value = matchName(contentBg);
    if (ctInput) ctInput.value = matchName(contentText);
    if (pInput) pInput.value = matchName(primaryColor);

    checkActivePreset();
}

function toggleCustomPaletteForm() {
    const wrap = document.getElementById('customPaletteFormWrapper');
    if (!wrap) return;
    if (wrap.style.display === 'none' || wrap.style.display === '') {
        wrap.style.display = 'block';
        document.getElementById('custom_palette_name_input')?.focus();
    } else {
        wrap.style.display = 'none';
    }
}

function prepareCustomPaletteSave() {
    document.getElementById('cp_sbg').value = document.getElementById('hex_sidebar_bg')?.value || '#f1f5f9';
    document.getElementById('cp_stxt').value = document.getElementById('hex_sidebar_text')?.value || '#1e293b';
    document.getElementById('cp_shov').value = document.getElementById('hex_sidebar_hover_bg')?.value || '#ffffff';
    document.getElementById('cp_sact').value = document.getElementById('hex_sidebar_active_bg')?.value || '#e6fcf5';
    document.getElementById('cp_cbg').value = document.getElementById('hex_content_bg')?.value || '#f8fafc';
    document.getElementById('cp_ctxt').value = document.getElementById('hex_content_text')?.value || '#0f172a';
    document.getElementById('cp_pri').value = document.getElementById('hex_primary_color')?.value || '#0f766e';
}

function updateLiveThemePreview() {
    const sidebarBg = document.getElementById('hex_sidebar_bg')?.value || '#f1f5f9';
    const sidebarText = document.getElementById('hex_sidebar_text')?.value || '#1e293b';
    const sidebarHoverBg = document.getElementById('hex_sidebar_hover_bg')?.value || '#ffffff';
    const sidebarActiveBg = document.getElementById('hex_sidebar_active_bg')?.value || '#e6fcf5';
    const contentBg = document.getElementById('hex_content_bg')?.value || '#f8fafc';
    const contentText = document.getElementById('hex_content_text')?.value || '#0f172a';
    const primaryColor = document.getElementById('hex_primary_color')?.value || '#0f766e';

    document.documentElement.style.setProperty('--admin-sidebar-bg', sidebarBg);
    document.documentElement.style.setProperty('--admin-sidebar-text', sidebarText);
    document.documentElement.style.setProperty('--admin-sidebar-hover-bg', sidebarHoverBg);
    document.documentElement.style.setProperty('--admin-sidebar-active-bg', sidebarActiveBg);
    document.documentElement.style.setProperty('--admin-content-bg', contentBg);
    document.documentElement.style.setProperty('--admin-content-text', contentText);
    document.documentElement.style.setProperty('--admin-theme-primary', primaryColor);

    const sidebar = document.querySelector('.admin-sidebar');
    const content = document.querySelector('.admin-content');
    if (sidebar) {
        sidebar.style.backgroundColor = sidebarBg;
        sidebar.style.color = sidebarText;
    }
    if (content) {
        content.style.backgroundColor = contentBg;
        content.style.color = contentText;
    }

    checkActivePreset();
}

function checkActivePreset() {
    const sBg = document.getElementById('hex_sidebar_bg')?.value.toLowerCase() || '';
    const sTxt = document.getElementById('hex_sidebar_text')?.value.toLowerCase() || '';
    const sHov = document.getElementById('hex_sidebar_hover_bg')?.value.toLowerCase() || '';
    const sAct = document.getElementById('hex_sidebar_active_bg')?.value.toLowerCase() || '';
    const cBg = document.getElementById('hex_content_bg')?.value.toLowerCase() || '';
    const cTxt = document.getElementById('hex_content_text')?.value.toLowerCase() || '';
    const pri = document.getElementById('hex_primary_color')?.value.toLowerCase() || '';

    document.querySelectorAll('.as-preset-card').forEach(card => {
        const cSBg = (card.dataset.sbg || '').toLowerCase();
        const cSTxt = (card.dataset.stxt || '').toLowerCase();
        const cSHov = (card.dataset.shov || '').toLowerCase();
        const cSAct = (card.dataset.sact || '').toLowerCase();
        const cCBg = (card.dataset.cbg || '').toLowerCase();
        const cCTxt = (card.dataset.ctxt || '').toLowerCase();
        const cPri = (card.dataset.pri || '').toLowerCase();

        const matchBg = sBg === cSBg && cBg === cCBg && pri === cPri;
        const matchTxt = (!cSTxt || sTxt === cSTxt) && (!cCTxt || cTxt === cCTxt);
        const matchStates = (!cSHov || sHov === cSHov) && (!cSAct || sAct === cSAct);

        if (matchBg && matchTxt && matchStates) {
            card.classList.add('active-preset');
        } else {
            card.classList.remove('active-preset');
        }
    });
}

// Attach Search Autocomplete to all 7 color search boxes
document.addEventListener('DOMContentLoaded', () => {
    const colorDB = (typeof ROSABELLA_COLOR_DATABASE !== 'undefined' && ROSABELLA_COLOR_DATABASE.length) 
        ? ROSABELLA_COLOR_DATABASE 
        : ROSABELLA_THEME_COLORS;

    document.querySelectorAll('.color-search-field').forEach(input => {
        const target = input.dataset.target;
        const dropdown = input.parentElement.querySelector('.as-color-dropdown');
        if (!dropdown) return;

        function renderMatches(query) {
            let matches = [];
            if (!query) {
                // Show top recommended colors if search is empty
                matches = colorDB.slice(0, 18);
            } else {
                matches = colorDB.filter(c => 
                    c.name.toLowerCase().includes(query) || 
                    c.hex.toLowerCase().includes(query)
                );
            }

            if (matches.length === 0) {
                dropdown.innerHTML = `<div style="padding: 10px 14px; font-size: 0.78rem; color: #94a3b8; text-align: center;">No matching standard color. You can enter a custom Hex code directly.</div>`;
            } else {
                dropdown.innerHTML = matches.map(c => `
                    <div class="as-color-item" data-hex="${c.hex}" data-name="${c.name}">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="as-color-item-swatch" style="background-color: ${c.hex};"></span>
                            <span style="font-size: 0.8125rem; font-weight: 600; color: #0f172a;">${c.name}</span>
                        </div>
                        <span style="font-size: 0.72rem; font-family: monospace; font-weight: 700; color: #0f766e; background: #f0fdfa; padding: 2px 6px; border-radius: 4px; border: 1px solid #ccfbf1;">${c.hex}</span>
                    </div>
                `).join('');

                dropdown.querySelectorAll('.as-color-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const hex = item.dataset.hex;
                        const name = item.dataset.name;
                        syncColorInput(target, hex);
                        input.value = name + ' (' + hex + ')';
                        dropdown.style.display = 'none';
                    });
                });
            }
            dropdown.style.display = 'block';
        }

        input.addEventListener('input', () => {
            renderMatches(input.value.trim().toLowerCase());
        });

        input.addEventListener('focus', () => {
            renderMatches(input.value.trim().toLowerCase());
        });

        document.addEventListener('click', (e) => {
            if (!input.parentElement.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    });

    // Populate initial search box labels with friendly names if matching hex
    const setInitialName = (target, hexId) => {
        const hex = document.getElementById(hexId)?.value;
        if (!hex) return;
        const found = colorDB.find(c => c.hex.toLowerCase() === hex.toLowerCase());
        const sInput = document.querySelector(`.color-search-field[data-target="${target}"]`);
        if (sInput && found) {
            sInput.value = `${found.name} (${found.hex})`;
        }
    };

    setInitialName('sidebar_bg', 'hex_sidebar_bg');
    setInitialName('sidebar_text', 'hex_sidebar_text');
    setInitialName('sidebar_hover_bg', 'hex_sidebar_hover_bg');
    setInitialName('sidebar_active_bg', 'hex_sidebar_active_bg');
    setInitialName('content_bg', 'hex_content_bg');
    setInitialName('content_text', 'hex_content_text');
    setInitialName('primary_color', 'hex_primary_color');

    // Run active preset indicator on initial load
    checkActivePreset();
});
</script>
</body>
</html>
