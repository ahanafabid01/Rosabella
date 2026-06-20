<?php
/**
 * KARTLY - Admin Settings
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
$message = '';
$error = '';

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

$settings = $db->query("SELECT * FROM settings ORDER BY setting_key ASC")->fetchAll();

// Group settings by prefix
$groups = [
    'general'  => ['label' => 'General',   'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'items' => []],
    'payment'  => ['label' => 'Payment',   'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'items' => []],
    'shipping' => ['label' => 'Shipping',  'icon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4', 'items' => []],
    'email'    => ['label' => 'Email',     'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'items' => []],
    'security' => ['label' => 'Security',  'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'items' => []],
    'advanced' => ['label' => 'Advanced',  'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z', 'items' => []],
];

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

$pageTitle = 'Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - KARTLY Admin</title>
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


    </style>
</head>
<body>
<div class="admin-layout">
    <?php renderAdminSidebar('settings'); ?>

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
        <form method="POST" id="settings-form">
            <?php $first = true; foreach ($groups as $groupKey => $group): ?>
            <div class="settings-tab-panel <?= $first ? 'active' : '' ?>" id="tab-<?= $groupKey ?>">
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

        <!-- Advanced: Add New Setting -->
        <div class="admin-card" style="margin-top:0.5rem;">
            <h2 class="admin-section-heading">Advanced — Add New Setting</h2>
            <p style="font-size:0.82rem;color:var(--color-text-light);margin-bottom:1rem;">Manually register a new configuration key into the database.</p>
            <form method="POST">
                <div class="add-setting-grid">
                    <div>
                        <label style="font-size:0.75rem;font-weight:600;color:var(--color-text-light);display:block;margin-bottom:0.4rem;">KEY</label>
                        <input class="form-input" type="text" name="new_setting_key" placeholder="e.g. site_name" required>
                    </div>
                    <div>
                        <label style="font-size:0.75rem;font-weight:600;color:var(--color-text-light);display:block;margin-bottom:0.4rem;">TYPE</label>
                        <select class="form-select" name="new_setting_type">
                            <option value="text">text</option>
                            <option value="number">number</option>
                            <option value="boolean">boolean</option>
                            <option value="json">json</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.75rem;font-weight:600;color:var(--color-text-light);display:block;margin-bottom:0.4rem;">INITIAL VALUE</label>
                        <input class="form-input" type="text" name="new_setting_value" placeholder="Initial value">
                    </div>
                    <div>
                        <label style="font-size:0.75rem;display:block;margin-bottom:0.4rem;">&nbsp;</label>
                        <button class="btn btn-secondary" type="submit" name="add_setting" value="1" style="width:100%;">Add Setting</button>
                    </div>
                </div>
            </form>
        </div>

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
</script>
</body>
</html>
