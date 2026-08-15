<?php
/**
 * Rosabella - Admin Settings
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once __DIR__ . '/includes/layout.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$db = getDB();

// \u2500\u2500 Security: Verify CSRF on all admin POST requests \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
}

$message = '';
$error = '';

// ---- Handle Branding uploads ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_branding'])) {
    $uploadDir = __DIR__ . '/../assets/uploads/branding/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $upsert = $db->prepare(
        "INSERT INTO settings (setting_key, setting_value, setting_type)
         VALUES (?, ?, 'text')
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );

    $fields = ['site_logo' => 'logo', 'site_icon' => 'icon'];

    // Handle clear requests
    if (!empty($_POST['clear_branding_key'])) {
        $clearKey = $_POST['clear_branding_key'];
        if (in_array($clearKey, array_keys($fields), true)) {
            $db->prepare("UPDATE settings SET setting_value = '' WHERE setting_key = ?")->execute([$clearKey]);
            $message = 'Image removed successfully.';
        }
    }

    foreach ($fields as $dbKey => $fileField) {
        if (!empty($_FILES[$fileField]['tmp_name']) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES[$fileField]['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','svg','webp','ico'];
            if (!in_array($ext, $allowed)) {
                $error = 'Invalid file type for ' . $fileField . '. Allowed: ' . implode(', ', $allowed);
                continue;
            }
            $filename = $dbKey . '_' . time() . '.' . $ext;
            $dest = $uploadDir . $filename;
            if (move_uploaded_file($_FILES[$fileField]['tmp_name'], $dest)) {
                $upsert->execute([$dbKey, 'assets/uploads/branding/' . $filename]);
            } else {
                $error = 'Failed to upload ' . $fileField . '.';
            }
        }
    }
    if (!$error) $message = 'Branding updated successfully.';
}

// ---- Handle Homepage Theme selection ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_homepage_theme'])) {
    $selectedTheme = trim((string)($_POST['homepage_theme'] ?? 'default_theme'));
    $allowedThemes = ['default_theme', 'compact_layout', 'showcase_layout', 'clothing_brand'];
    
    if (!in_array($selectedTheme, $allowedThemes)) {
        $error = 'Invalid theme selection.';
    } else {
        try {
            $db->prepare(
                "INSERT INTO settings (setting_key, setting_value, setting_type)
                 VALUES ('homepage_theme', ?, 'text')
                 ON DUPLICATE KEY UPDATE setting_value = ?"
            )->execute([$selectedTheme, $selectedTheme]);
            $message = 'Homepage theme updated successfully.';
        } catch (Throwable $e) {
            $error = 'Failed to update theme: ' . $e->getMessage();
        }
    }
}

// ---- Handle Typography settings ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_typography'])) {
    $typoFields = $_POST['typo'] ?? [];
    $upsertTypo = $db->prepare(
        "INSERT INTO settings (setting_key, setting_value, setting_type)
         VALUES (?, ?, 'text')
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    foreach ($typoFields as $key => $value) {
        if (preg_match('/^typo_[a-zA-Z0-9_]+$/', $key)) {
            $upsertTypo->execute([$key, trim((string)$value)]);
        }
    }
    $message = 'Typography settings saved successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $postedSettings = $_POST['settings'] ?? [];

    try {
        $stmt = $db->query("SELECT setting_key, setting_type FROM settings");
        $allSettings = $stmt->fetchAll();

        $updateStmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        foreach ($allSettings as $setting) {
            $key = $setting['setting_key'];
            $type = $setting['setting_type'];

            if ($type === 'boolean') {
                $value = array_key_exists($key, $postedSettings) ? 'true' : 'false';
            } else {
                $value = trim((string)($postedSettings[$key] ?? ''));
            }
            $updateStmt->execute([$value, $key]);
        }

        $message = 'Settings updated successfully.';
    } catch (Throwable $e) {
        $error = 'Unable to update settings.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_setting'])) {
    $settingKey   = trim((string)($_POST['new_setting_key']   ?? ''));
    $settingType  = trim((string)($_POST['new_setting_type']  ?? 'text'));
    $settingValue = trim((string)($_POST['new_setting_value'] ?? ''));
    $allowedTypes = ['text', 'number', 'boolean', 'json'];

    if (!$settingKey) {
        $error = 'Setting key is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $settingKey)) {
        $error = 'Setting key can only contain letters, numbers, and underscores.';
    } elseif (!in_array($settingType, $allowedTypes, true)) {
        $error = 'Invalid setting type.';
    } else {
        try {
            if ($settingType === 'boolean') {
                $settingValue = in_array(strtolower($settingValue), ['1', 'true', 'yes', 'on'], true) ? 'true' : 'false';
            }
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, ?)");
            $stmt->execute([$settingKey, $settingValue, $settingType]);
            $message = 'Setting created successfully.';
        } catch (Throwable $e) {
            $error = 'Unable to create setting. Key must be unique.';
        }
    }
}
// ---- Handle Colors settings ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_colors'])) {
    $colorFields = $_POST['color'] ?? [];
    $upsertColor = $db->prepare(
        "INSERT INTO settings (setting_key, setting_value, setting_type)
         VALUES (?, ?, 'text')
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    foreach ($colorFields as $key => $value) {
        if (preg_match('/^color_[a-zA-Z0-9_-]+$/', $key)) {
            $upsertColor->execute([$key, trim((string)$value)]);
        }
    }
    $message = 'Color palette saved successfully.';
}

$settings = $db->query("SELECT * FROM settings ORDER BY setting_key ASC")->fetchAll();

// Group settings by prefix
$groups = [
    'branding'   => ['label' => 'Branding',   'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z', 'items' => []],
    'homepage_theme' => ['label' => 'Homepage Theme', 'icon' => 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01', 'items' => []],
    'typography' => ['label' => 'Typography', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'items' => []],
    'colors'     => ['label' => 'Colors',     'icon' => 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01', 'items' => []],
    'general'    => ['label' => 'General',    'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'items' => []],
    'payment'    => ['label' => 'Payment',    'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'items' => []],
    'shipping'   => ['label' => 'Shipping',   'icon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4', 'items' => []],
    'email'      => ['label' => 'Email',      'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'items' => []],
    'security'   => ['label' => 'Security',   'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'items' => []],
    'advanced'   => ['label' => 'Advanced',   'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z', 'items' => []],
];

// Current branding values
$siteLogo = getSetting('site_logo') ?? '';
$siteIcon = getSetting('site_icon') ?? '';

// Typography — per-tag font family only
$typoTags = ['body', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'a', 'small', 'label', 'button', 'input', 'nav', 'logo_text'];
$typoFonts = [];
foreach ($typoTags as $tag) {
    $typoFonts[$tag] = getSetting('typo_font_' . $tag) ?: '';
}

// Color Palette
$colorVars = [
    'primary' => '#0f766e',
    'primary_hover' => '#0b5b55',
    'secondary' => '#f8f9fa',
    'secondary_hover' => '#e9ecef',
    'success' => '#198754',
    'danger' => '#c0392b',
    'warning' => '#f39c12',
    'info' => '#0ea5e9',
    'text' => '#102133',
    'text_light' => '#5f7083',
    'text_muted' => '#9aa8b5',
    'bg' => '#ffffff',
    'bg_secondary' => '#f6f8fb',
    'bg_tertiary' => '#edf1f5',
    'border' => '#d8dee6',
    'border_light' => '#e8edf3',
    'topbar_bg' => '#ffffff',
    'navbar_bg' => '#0f766e',
    'footer_bg' => '#f6f8fb',
];
$colors = [];
foreach ($colorVars as $key => $defaultHex) {
    $val = getSetting('color_' . $key);
    $colors[$key] = ($val !== null && $val !== '') ? $val : $defaultHex;
}

foreach ($settings as $s) {
    $key = strtolower($s['setting_key']);
    if (str_contains($key, 'payment') || str_contains($key, 'stripe') || str_contains($key, 'paypal') || str_contains($key, 'currency')) {
        $groups['payment']['items'][] = $s;
    } elseif (str_contains($key, 'ship') || str_contains($key, 'delivery') || str_contains($key, 'free_ship')) {
        $groups['shipping']['items'][] = $s;
    } elseif (str_contains($key, 'email') || str_contains($key, 'smtp') || str_contains($key, 'mail')) {
        $groups['email']['items'][] = $s;
    } elseif (str_contains($key, 'password') || str_contains($key, 'secret') || str_contains($key, 'token') || str_contains($key, 'api_key') || str_contains($key, 'security') || str_contains($key, 'captcha')) {
        $groups['security']['items'][] = $s;
    } elseif (str_contains($key, 'debug') || str_contains($key, 'cache') || str_contains($key, 'log') || str_contains($key, 'maintenance') || str_contains($key, 'version')) {
        $groups['advanced']['items'][] = $s;
    } elseif (str_contains($key, 'site') || str_contains($key, 'store') || str_contains($key, 'contact') || str_contains($key, 'address') || str_contains($key, 'phone') || str_contains($key, 'name') || str_contains($key, 'logo') || str_contains($key, 'description')) {
        $groups['general']['items'][] = $s;
    } else {
        $groups['general']['items'][] = $s;
    }
}

$pageTitle = 'Website Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $siteFavicon = getSetting('site_favicon'); if ($siteFavicon): ?>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . '/' . htmlspecialchars($siteFavicon) ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Rosabella Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        /* ---- Settings Tabs ---- */
        .settings-tabs-nav {
            display: flex;
            gap: 0;
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 0.4rem;
            margin-bottom: 1.5rem;
            overflow-x: auto;
            flex-wrap: nowrap;
        }
        .settings-tab-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.1rem;
            border: none;
            background: transparent;
            border-radius: calc(var(--radius-lg) - 4px);
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--color-text-light);
            white-space: nowrap;
            transition: all 0.18s ease;
        }
        .settings-tab-btn svg {
            width: 16px; height: 16px; flex-shrink: 0;
        }
        .settings-tab-btn:hover {
            background: var(--color-bg-secondary);
            color: var(--color-text);
        }
        .settings-tab-btn.active {
            background: var(--color-primary);
            color: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .settings-tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 700;
            background: rgba(0,0,0,0.12);
            color: inherit;
        }
        .settings-tab-btn.active .settings-tab-badge {
            background: rgba(255,255,255,0.25);
        }
        .settings-tab-panel { display: none; }
        .settings-tab-panel.active { display: block; }

        /* ---- Setting Row ---- */
        .setting-row {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1.5rem;
            align-items: start;
            padding: 1.25rem 0;
            border-bottom: 1px solid var(--color-border);
        }
        .setting-row:last-child { border-bottom: none; }
        .setting-label-col {}
        .setting-key-text {
            font-size: 0.8rem;
            font-weight: 700;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            color: var(--color-text);
            margin-bottom: 0.15rem;
        }
        .setting-type-badge {
            display: inline-block;
            font-size: 0.68rem;
            font-weight: 600;
            padding: 0.1rem 0.45rem;
            border-radius: 999px;
            background: var(--color-bg-secondary);
            color: var(--color-text-light);
            border: 1px solid var(--color-border);
        }
        .setting-input-col {}
        .setting-boolean-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .toggle-switch {
            position: relative;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute;
            inset: 0;
            background: var(--color-border);
            border-radius: 999px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 18px; height: 18px;
            left: 3px; top: 3px;
            background: #fff;
            border-radius: 50%;
            transition: transform 0.2s;
        }
        .toggle-switch input:checked + .toggle-slider { background: var(--color-primary); }
        .toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
        .toggle-label { font-size: 0.83rem; color: var(--color-text-light); }

        /* ---- Empty tab state ---- */
        .settings-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 3rem;
            color: var(--color-text-light);
            text-align: center;
        }
        .settings-empty-state svg { width: 40px; height: 40px; opacity: 0.4; }
        .settings-empty-state p { font-size: 0.875rem; margin: 0; }

        /* ---- Add Setting Card ---- */
        .add-setting-grid {
            display: grid;
            grid-template-columns: 1fr 160px 1fr auto;
            gap: 0.75rem;
            align-items: end;
        }
        @media (max-width: 900px) {
            .add-setting-grid { grid-template-columns: 1fr 1fr; }
            .setting-row { grid-template-columns: 1fr; gap: 0.5rem; }
        }
        @media (max-width: 600px) {
            .add-setting-grid { grid-template-columns: 1fr; }
        }
        /* ---- Branding Upload ---- */
        .branding-upload-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        @media (max-width: 700px) { .branding-upload-grid { grid-template-columns: 1fr; } }
        .branding-upload-card {
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            background: var(--color-bg-secondary);
        }
        .branding-upload-card h3 {
            margin: 0 0 0.25rem;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--color-text);
        }
        .branding-upload-card p {
            margin: 0 0 1rem;
            font-size: 0.78rem;
            color: var(--color-text-light);
        }
        .branding-preview-wrap {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .branding-preview-box {
            width: 80px;
            height: 80px;
            border-radius: var(--radius-md);
            border: 2px dashed var(--color-border);
            background: var(--color-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .branding-preview-box.icon-box {
            width: 56px;
            height: 56px;
            border-radius: 10px;
        }
        .branding-preview-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .branding-preview-box .no-img {
            color: var(--color-text-light);
            font-size: 0.7rem;
            text-align: center;
        }
        .branding-file-input-wrap {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .branding-file-input-wrap input[type=file] {
            font-size: 0.8rem;
        }
        .branding-clear-btn {
            background: none;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            color: var(--color-text-light);
            font-size: 0.75rem;
            padding: 0.3rem 0.7rem;
            cursor: pointer;
            transition: all 0.15s;
        }
        .branding-clear-btn:hover { border-color: var(--color-danger); color: var(--color-danger); }

    </style>
</head>
<body>
<div class="admin-layout">
    <?php renderAdminSidebar('website'); ?>

    <main class="admin-content">
        <?php renderAdminTopbar($pageTitle ?? 'Admin Panel'); ?>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- Tabs Nav -->
        <div class="settings-tabs-nav" role="tablist">
            <?php $first = true; foreach ($groups as $groupKey => $group): ?>
            <button
                class="settings-tab-btn <?= $first ? 'active' : '' ?>"
                data-tab="<?= $groupKey ?>"
                role="tab"
                aria-selected="<?= $first ? 'true' : 'false' ?>"
                type="button"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="<?= htmlspecialchars($group['icon']) ?>"/>
                </svg>
                <?= htmlspecialchars($group['label']) ?>
                <span class="settings-tab-badge"><?= count($group['items']) ?></span>
            </button>
            <?php $first = false; endforeach; ?>
        </div>

        <!-- Tab Panels -->

        <!-- === Branding Tab (special - file upload) === -->
        <div class="settings-tab-panel active" id="tab-branding">
            <div class="admin-card">
                <h2 class="admin-section-heading">Branding Settings</h2>
                <p style="font-size:0.82rem;color:var(--color-text-light);margin:-0.25rem 0 1.5rem;">Upload your site logo and favicon icon. Stored in <code>assets/uploads/branding/</code> and saved to the database.</p>

                <form method="POST" enctype="multipart/form-data">
                        <!-- Security: CSRF token -->
                        <?= csrfField() ?>
                    <div class="branding-upload-grid">

                        <!-- Site Logo -->
                        <div class="branding-upload-card">
                            <h3>Site Logo</h3>
                            <p>Displayed in the header, emails, and receipts. Recommended: PNG/SVG, transparent background, min 200px wide.</p>
                            <div class="branding-preview-wrap">
                                <div class="branding-preview-box" id="logo-preview-box">
                                    <?php if ($siteLogo): ?>
                                        <img src="<?= htmlspecialchars(BASE_URL . '/' . $siteLogo) ?>" alt="Site Logo" id="logo-preview-img">
                                    <?php else: ?>
                                        <span class="no-img" id="logo-preview-img">No logo<br>uploaded</span>
                                    <?php endif; ?>
                                </div>
                                <div class="branding-file-input-wrap">
                                    <input type="file" name="logo" id="logo-file" accept="image/*" onchange="previewImage(this,'logo-preview-box','logo-preview-img')">
                                    <?php if ($siteLogo): ?>
                                        <button type="button" class="branding-clear-btn" onclick="clearBranding('site_logo','logo-preview-box','logo-preview-img','logo-file')">✕ Remove Logo</button>
                                    <?php endif; ?>
                                    <?php if ($siteLogo): ?>
                                        <span style="font-size:0.72rem;color:var(--color-text-light);word-break:break-all;"><?= htmlspecialchars($siteLogo) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Site Icon (Favicon) -->
                        <div class="branding-upload-card">
                            <h3>Site Icon / Favicon</h3>
                            <p>Shown in browser tabs and bookmarks. Recommended: PNG or ICO, square, 512×512px.</p>
                            <div class="branding-preview-wrap">
                                <div class="branding-preview-box icon-box" id="icon-preview-box">
                                    <?php if ($siteIcon): ?>
                                        <img src="<?= htmlspecialchars(BASE_URL . '/' . $siteIcon) ?>" alt="Site Icon" id="icon-preview-img">
                                    <?php else: ?>
                                        <span class="no-img" id="icon-preview-img">No icon</span>
                                    <?php endif; ?>
                                </div>
                                <div class="branding-file-input-wrap">
                                    <input type="file" name="icon" id="icon-file" accept="image/*,.ico" onchange="previewImage(this,'icon-preview-box','icon-preview-img')">
                                    <?php if ($siteIcon): ?>
                                        <button type="button" class="branding-clear-btn" onclick="clearBranding('site_icon','icon-preview-box','icon-preview-img','icon-file')">✕ Remove Icon</button>
                                    <?php endif; ?>
                                    <?php if ($siteIcon): ?>
                                        <span style="font-size:0.72rem;color:var(--color-text-light);word-break:break-all;"><?= htmlspecialchars($siteIcon) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div style="margin-top:1.5rem;display:flex;justify-content:flex-end;">
                        <button type="submit" name="save_branding" value="1" class="btn btn-primary">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:4px;"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Save Branding
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- === Homepage Theme Tab === -->
        <div class="settings-tab-panel" id="tab-homepage_theme">
            <div class="admin-card">
                <h2 class="admin-section-heading">Homepage Theme</h2>
                <p style="font-size:0.82rem;color:var(--color-text-light);margin:-0.25rem 0 1.5rem;">Select your preferred homepage layout and design. Each theme provides a different visual presentation of your featured products, categories, and promotional content.</p>

                <form method="POST">
                    <?= csrfField() ?>
                    <?php
                    // Get current theme selection
                    $currentTheme = getSetting('homepage_theme') ?: 'default_theme';
                    
                    // Define available themes
                    $themes = [
                        'default_theme' => [
                            'name' => 'Default Theme',
                            'description' => 'The current classic layout featuring a hero section with bento grid layout, category showcase, featured products grid, and hot deals section.',
                            'features' => ['Hero Bento Grid', 'Categories Grid', 'Featured Products', 'Hot Deals Banner', 'New Arrivals'],
                        ],
                        'clothing_brand' => [
                            'name' => 'Clothing Brand',
                            'description' => 'Professional fashion e-commerce layout optimized for clothing brands. Features full-width hero with overlay, prominent category showcase, product-focused grid, and promotional banners for maximum impact.',
                            'features' => ['Full-Width Hero', 'Category Showcase', 'Promotional Sections', 'Fashion Optimized', 'Modern Grid Layout'],
                        ],
                    ];
                    ?>
                    
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem;margin-bottom:2rem;">
                        <?php foreach ($themes as $themeKey => $theme): ?>
                        <div style="border:2px solid <?= $currentTheme === $themeKey ? 'var(--color-primary)' : 'var(--color-border)' ?>;border-radius:var(--radius-lg);padding:1.25rem;background:<?= $currentTheme === $themeKey ? 'rgba(15,118,110,0.05)' : 'var(--color-bg-secondary)' ?>;transition:all 0.2s ease;cursor:pointer;" onclick="selectTheme('<?= $themeKey ?>')">
                            <label style="display:flex;align-items:flex-start;gap:0.75rem;cursor:pointer;">
                                <input type="radio" name="homepage_theme" value="<?= $themeKey ?>" <?= $currentTheme === $themeKey ? 'checked' : '' ?> style="flex-shrink:0;width:18px;height:18px;margin-top:2px;cursor:pointer;">
                                <div style="flex:1;">
                                    <h3 style="margin:0 0 0.5rem;font-size:0.95rem;font-weight:700;color:var(--color-text);"><?= htmlspecialchars($theme['name']) ?></h3>
                                    <p style="margin:0 0 1rem;font-size:0.82rem;color:var(--color-text-light);line-height:1.5;"><?= htmlspecialchars($theme['description']) ?></p>
                                    <div style="display:flex;flex-wrap:wrap;gap:0.4rem;">
                                        <?php foreach ($theme['features'] as $feature): ?>
                                        <span style="display:inline-block;font-size:0.7rem;font-weight:600;padding:0.25rem 0.5rem;background:var(--color-bg);border:1px solid var(--color-border);border-radius:4px;color:var(--color-text-light);"><?= htmlspecialchars($feature) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="background:var(--color-bg-secondary);border:1px solid var(--color-border);border-radius:var(--radius-lg);padding:1.25rem;margin-bottom:1.5rem;">
                        <h3 style="margin:0 0 0.5rem;font-size:0.88rem;font-weight:700;color:var(--color-text);">📋 Theme Details</h3>
                        <div id="theme-details" style="font-size:0.82rem;color:var(--color-text-light);line-height:1.6;">
                            <p><?= htmlspecialchars($themes[$currentTheme]['description']) ?></p>
                        </div>
                    </div>

                    <div style="margin-top:1.5rem;display:flex;justify-content:flex-end;gap:0.75rem;">
                        <button type="button" class="btn btn-secondary" onclick="previewTheme()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Preview Theme
                        </button>
                        <button type="submit" name="save_homepage_theme" value="1" class="btn btn-primary">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:4px;"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Save Theme
                        </button>
                    </div>
                </form>

                <script>
                function selectTheme(themeKey) {
                    document.querySelector('input[name="homepage_theme"][value="' + themeKey + '"]').checked = true;
                    const themes = <?= json_encode($themes) ?>;
                    document.getElementById('theme-details').innerHTML = '<p>' + themes[themeKey].description + '</p>';
                    // Update card styling
                    document.querySelectorAll('[onclick^="selectTheme"]').forEach(el => {
                        el.style.borderColor = 'var(--color-border)';
                        el.style.background = 'var(--color-bg-secondary)';
                    });
                    event.currentTarget.style.borderColor = 'var(--color-primary)';
                    event.currentTarget.style.background = 'rgba(15,118,110,0.05)';
                }

                function previewTheme() {
                    const selectedTheme = document.querySelector('input[name="homepage_theme"]:checked').value;
                    const previewUrl = '<?= BASE_URL ?>/?theme_preview=' + selectedTheme;
                    window.open(previewUrl, '_blank', 'width=1200,height=800');
                }
                </script>
            </div>
        </div>

        <!-- === Typography Tab === -->
        <div class="settings-tab-panel" id="tab-typography">
            <div class="admin-card">
                <h2 class="admin-section-heading">Typography — Font Style</h2>
                <p style="font-size:0.82rem;color:var(--color-text-light);margin:-0.25rem 0 1.5rem;">
                    Set a Google Font for each HTML tag. Leave blank to inherit the parent font.
                    Font names must match exactly on <a href="https://fonts.google.com" target="_blank">fonts.google.com</a>.
                </p>

                <form method="POST">
                        <!-- Security: CSRF token -->
                        <?= csrfField() ?>
                    <?php
                    $typoTagLabels = [
                        'body'   => ['label' => 'Body',           'desc' => 'Base font for the entire page'],
                        'h1'     => ['label' => 'Heading 1',      'desc' => 'Main page titles'],
                        'h2'     => ['label' => 'Heading 2',      'desc' => 'Section headings'],
                        'h3'     => ['label' => 'Heading 3',      'desc' => 'Sub-section headings'],
                        'h4'     => ['label' => 'Heading 4',      'desc' => 'Card / panel headings'],
                        'h5'     => ['label' => 'Heading 5',      'desc' => 'Small headings'],
                        'h6'     => ['label' => 'Heading 6',      'desc' => 'Tiny headings'],
                        'p'      => ['label' => 'Paragraph',      'desc' => 'Body text and descriptions'],
                        'a'      => ['label' => 'Links',          'desc' => 'Anchor / hyperlink text'],
                        'small'  => ['label' => 'Small Text',     'desc' => 'Fine print, badges, meta text'],
                        'label'  => ['label' => 'Labels',         'desc' => 'Form labels and captions'],
                        'button' => ['label' => 'Buttons',        'desc' => 'All button text'],
                        'input'  => ['label' => 'Inputs & Fields','desc' => 'Form inputs, selects, textareas'],
                        'nav'    => ['label' => 'Navigation / Sidebar','desc' => 'Mobile sidebar & nav menu links'],
                        'logo_text' => ['label' => 'Text Logo','desc' => 'Site name font when no image logo is uploaded'],
                    ];
                    ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;">
                    <?php foreach ($typoTagLabels as $tag => $info): ?>
                        <div style="border:1px solid var(--color-border);border-radius:var(--radius-md);padding:1rem;background:var(--color-bg-secondary);">
                            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
                                <code style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:4px;padding:0.1rem 0.4rem;font-size:0.75rem;color:var(--color-primary);font-weight:700;">&lt;<?= $tag ?>&gt;</code>
                                <div>
                                    <div style="font-size:0.82rem;font-weight:600;color:var(--color-text);"><?= $info['label'] ?></div>
                                    <div style="font-size:0.72rem;color:var(--color-text-light);"><?= $info['desc'] ?></div>
                                </div>
                            </div>
                            <input
                                class="form-input"
                                type="text"
                                name="typo[typo_font_<?= $tag ?>]"
                                value="<?= htmlspecialchars($typoFonts[$tag] ?? '') ?>"
                                placeholder="e.g. Inter, Roboto, Poppins"
                                style="font-size:0.82rem;"
                                data-tag="<?= $tag ?>"
                            >
                            <?php if (!empty($typoFonts[$tag])): ?>
                            <div style="margin-top:0.5rem;padding:0.4rem 0.6rem;background:var(--color-bg);border-radius:4px;border:1px solid var(--color-border);">
                                <span style="font-size:0.75rem;color:var(--color-text-light);">Preview: </span>
                                <span class="font-preview-span" data-font="<?= htmlspecialchars($typoFonts[$tag]) ?>" style="font-size:0.88rem;"><?= htmlspecialchars($typoFonts[$tag]) ?> — Aa Bb Cc</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>

                    <div style="margin-top:1.5rem;display:flex;justify-content:flex-end;">
                        <button type="submit" name="save_typography" value="1" class="btn btn-primary">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:4px;"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Save Typography
                        </button>
                    </div>
                </form>
            </div>
        </div>



        <!-- === Colors Tab === -->
        <div class="settings-tab-panel" id="tab-colors">
            <div class="admin-card">
                <h2 class="admin-section-heading">Color Palette</h2>
                <p style="font-size:0.82rem;color:var(--color-text-light);margin:-0.25rem 0 1.5rem;">
                    Customize the global colors used throughout the store. Changes affect buttons, text, backgrounds, and accents.
                </p>

                <form method="POST">
                        <!-- Security: CSRF token -->
                        <?= csrfField() ?>
                    <?php
                    $colorGroups = [
                        'Brand & Accent' => [
                            'primary' => 'Primary (Buttons, Links)',
                            'primary_hover' => 'Primary Hover',
                            'logo_text' => 'Text Logo Color',
                        ],
                        'Backgrounds' => [
                            'bg' => 'Main Background',
                            'bg_secondary' => 'Secondary Background (Cards)',
                            'bg_tertiary' => 'Tertiary Background (Hover states)',
                            'topbar_bg' => 'Topbar Background',
                            'navbar_bg' => 'Navbar Background',
                            'footer_bg' => 'Footer Background',
                        ],
                        'Text' => [
                            'text' => 'Main Text',
                            'text_light' => 'Light Text',
                            'text_muted' => 'Muted / Placeholder Text',
                        ],
                        'Borders' => [
                            'border' => 'Main Border',
                            'border_light' => 'Light Border (Dividers)',
                        ],
                        'Status & Alerts' => [
                            'success' => 'Success (Green)',
                            'danger' => 'Danger (Red)',
                            'warning' => 'Warning (Yellow)',
                            'info' => 'Info (Blue)',
                        ],
                    ];
                    ?>

                    <?php foreach ($colorGroups as $groupName => $groupVars): ?>
                        <div style="background:var(--color-bg-secondary);border-radius:var(--radius-lg);padding:1.25rem;margin-bottom:1.25rem;">
                            <h3 style="margin:0 0 1rem;font-size:0.88rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--color-text-light);"><?= $groupName ?></h3>
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;">
                                <?php foreach ($groupVars as $k => $label): ?>
                                    <div>
                                        <label style="font-size:0.75rem;font-weight:600;color:var(--color-text);display:block;margin-bottom:0.4rem;"><?= htmlspecialchars($label) ?></label>
                                        <div style="display:flex;gap:0.5rem;align-items:center;">
                                            <input type="color" name="color[color_<?= $k ?>]" value="<?= htmlspecialchars($colors[$k] ?? '#000000') ?>" style="width:40px;height:36px;padding:2px;border:1px solid var(--color-border);border-radius:6px;cursor:pointer;">
                                            <input class="form-input" type="text" name="color[color_<?= $k ?>_hex]" value="<?= htmlspecialchars($colors[$k] ?? '#000000') ?>" style="font-family:monospace;font-size:0.8rem;" placeholder="#000000">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div style="margin-top:1.5rem;display:flex;justify-content:flex-end;">
                        <button type="submit" name="save_colors" value="1" class="btn btn-primary">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:4px;"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Save Colors
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- === Dynamic Settings Tabs === -->
        <form method="POST" id="settings-form">
                        <!-- Security: CSRF token -->
                        <?= csrfField() ?>
            <?php $first = false; foreach ($groups as $groupKey => $group):
                  if ($groupKey === 'branding' || $groupKey === 'typography' || $groupKey === 'colors') { continue; } ?>
            <div class="settings-tab-panel" id="tab-<?= $groupKey ?>">
                <div class="admin-card">
                    <h2 class="admin-section-heading"><?= htmlspecialchars($group['label']) ?> Settings</h2>
                    <?php if (empty($group['items'])): ?>
                        <div class="settings-empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                            <p>No settings in this category yet.<br>Add one below using the "Advanced Settings" panel.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($group['items'] as $setting): ?>
                            <?php
                            $sKey = $setting['setting_key'];
                            $sType = $setting['setting_type'];
                            $sVal  = $setting['setting_value'];
                            $sKeyLower = strtolower($sKey);
                            $isSensitive = str_contains($sKeyLower, 'password') || str_contains($sKeyLower, 'secret') || str_contains($sKeyLower, 'token');
                            $inputType = $sType === 'number' ? 'number' : ($isSensitive ? 'password' : 'text');
                            ?>
                            <div class="setting-row">
                                <div class="setting-label-col">
                                    <div class="setting-key-text"><?= htmlspecialchars($sKey) ?></div>
                                    <span class="setting-type-badge"><?= htmlspecialchars($sType) ?></span>
                                </div>
                                <div class="setting-input-col">
                                    <?php if ($sType === 'boolean'): ?>
                                        <div class="setting-boolean-wrap">
                                            <label class="toggle-switch">
                                                <input type="checkbox"
                                                    name="settings[<?= htmlspecialchars($sKey) ?>]"
                                                    value="true"
                                                    <?= $sVal === 'true' ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                            <span class="toggle-label"><?= $sVal === 'true' ? 'Enabled' : 'Disabled' ?></span>
                                        </div>
                                    <?php elseif ($sType === 'json'): ?>
                                        <textarea class="form-textarea"
                                            name="settings[<?= htmlspecialchars($sKey) ?>]"
                                            rows="4"
                                            style="font-family: monospace; font-size: 0.82rem;"><?= htmlspecialchars($sVal) ?></textarea>
                                    <?php else: ?>
                                        <input class="form-input"
                                            type="<?= $inputType ?>"
                                            name="settings[<?= htmlspecialchars($sKey) ?>]"
                                            value="<?= htmlspecialchars($sVal) ?>"
                                            <?= $isSensitive ? 'autocomplete="new-password"' : '' ?>>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div style="margin-top: 1.25rem; display: flex; justify-content: flex-end;">
                            <button type="submit" name="save_settings" value="1" class="btn btn-primary">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:4px;"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                Save <?= htmlspecialchars($group['label']) ?> Settings
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php $first = false; endforeach; ?>
        </form>


    </main>
</div>

<script src="js/admin.js"></script>
<script>
// Tab switching
document.querySelectorAll('.settings-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const target = btn.dataset.tab;
        document.querySelectorAll('.settings-tab-btn').forEach(b => {
            b.classList.remove('active');
            b.setAttribute('aria-selected', 'false');
        });
        document.querySelectorAll('.settings-tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');
        document.getElementById('tab-' + target)?.classList.add('active');
    });
});

// Update toggle labels live
document.querySelectorAll('.toggle-switch input[type="checkbox"]').forEach(cb => {
    const label = cb.closest('.setting-boolean-wrap')?.querySelector('.toggle-label');
    cb.addEventListener('change', () => {
        if (label) label.textContent = cb.checked ? 'Enabled' : 'Disabled';
    });
});

// Live image preview on file pick
function previewImage(input, boxId, imgId) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    const box = document.getElementById(boxId);
    reader.onload = e => {
        box.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="max-width:100%;max-height:100%;object-fit:contain;">';
    };
    reader.readAsDataURL(input.files[0]);
}

// Remove / clear branding (sends a POST to clear the DB value)
function clearBranding(key, boxId, imgId, fileId) {
    if (!confirm('Remove this image?')) return;
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '<?= generateCSRFToken() ?>';
    const form = document.createElement('form');
    form.method = 'POST';
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = csrfToken;
    form.appendChild(csrfInput);

    const k = document.createElement('input');
    k.type = 'hidden'; k.name = 'clear_branding_key'; k.value = key;
    form.appendChild(k);

    const b = document.createElement('input');
    b.type = 'hidden'; b.name = 'save_branding'; b.value = '1';
    form.appendChild(b);

    document.body.appendChild(form);
    form.submit();
}

// Live Google Font preview for each tag
function loadFontPreview(fontName, previewSpan) {
    if (!fontName) {
        if (previewSpan) previewSpan.style.fontFamily = 'inherit';
        return;
    }
    const encoded = encodeURIComponent(fontName.trim());
    const id = 'gf-preview-' + encoded;
    if (!document.getElementById(id)) {
        const link = document.createElement('link');
        link.id = id;
        link.rel = 'stylesheet';
        link.href = 'https://fonts.googleapis.com/css2?family=' + encoded + ':wght@400;700&display=swap';
        document.head.appendChild(link);
    }
    if (previewSpan) {
        previewSpan.style.fontFamily = '"' + fontName.trim() + '", sans-serif';
        previewSpan.innerHTML = fontName.trim() + ' — Aa Bb Cc';
    }
}

document.querySelectorAll('input[data-tag]').forEach(input => {
    const parent = input.closest('div');
    let previewSpan = parent.querySelector('.font-preview-span');
    
    // Initial load
    if (input.value) loadFontPreview(input.value, previewSpan);

    input.addEventListener('input', () => {
        if (input.value.trim() && !previewSpan) {
            // Create preview if it didn't exist
            const pWrap = document.createElement('div');
            pWrap.style = "margin-top:0.5rem;padding:0.4rem 0.6rem;background:var(--color-bg);border-radius:4px;border:1px solid var(--color-border);";
            pWrap.innerHTML = '<span style="font-size:0.75rem;color:var(--color-text-light);">Preview: </span> <span class="font-preview-span" style="font-size:0.88rem;"></span>';
            parent.appendChild(pWrap);
            previewSpan = pWrap.querySelector('.font-preview-span');
        }
        if (!input.value.trim() && previewSpan) {
            // Remove preview if cleared
            previewSpan.parentElement.remove();
            previewSpan = null;
        }
        loadFontPreview(input.value, previewSpan);
    });
});

// Color picker <-> hex text sync
document.querySelectorAll('input[type="color"]').forEach(picker => {
    const name = picker.name; // e.g. color[color_primary]
    const hexKey = name.replace(']', '_hex]');
    const hexInput = document.querySelector('input[name="' + hexKey + '"]');
    if (!hexInput) return;
    
    // picker -> text
    picker.addEventListener('input', () => { 
        hexInput.value = picker.value; 
    });
    
    // text -> picker
    hexInput.addEventListener('input', () => {
        const val = hexInput.value.trim();
        if (/^#[0-9a-fA-F]{6}$/.test(val)) {
            picker.value = val;
        } else if (/^#[0-9a-fA-F]{3}$/.test(val)) {
            // Expand 3-digit hex to 6-digit
            const r = val[1], g = val[2], b = val[3];
            picker.value = '#' + r + r + g + g + b + b;
        }
    });
});
</script>
</body>
</html>

