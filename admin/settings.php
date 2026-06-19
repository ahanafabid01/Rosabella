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
    header('Location: ../login.php');
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
    $settingKey = trim((string)($_POST['new_setting_key'] ?? ''));
    $settingType = trim((string)($_POST['new_setting_type'] ?? 'text'));
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
$pageTitle = 'Settings Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - KARTLY Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php renderAdminSidebar('settings'); ?>

    <main class="admin-content">
        <?php renderAdminTopbar($pageTitle ?? 'Admin Panel'); ?>
<h1 class="admin-page-title admin-title-spaced">Settings</h1>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="admin-card">
            <h2 class="admin-subtitle">Site Settings</h2>
            <form method="POST">
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                        <tr>
                            <th>Key</th>
                            <th>Type</th>
                            <th>Value</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($settings as $setting): ?>
                            <tr>
                                <td class="admin-cell-mono"><?= htmlspecialchars($setting['setting_key']) ?></td>
                                <td><?= htmlspecialchars($setting['setting_type']) ?></td>
                                <td>
                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                        <label class="admin-inline-field-label">
                                            <input type="checkbox" name="settings[<?= htmlspecialchars($setting['setting_key']) ?>]" value="true" <?= $setting['setting_value'] === 'true' ? 'checked' : '' ?>>
                                            Enabled
                                        </label>
                                    <?php elseif ($setting['setting_type'] === 'json'): ?>
                                        <textarea class="form-textarea" name="settings[<?= htmlspecialchars($setting['setting_key']) ?>]" rows="3"><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                                    <?php else: ?>
                                        <?php
                                        $settingKeyLower = strtolower((string)$setting['setting_key']);
                                        $isSensitiveSetting = strpos($settingKeyLower, 'password') !== false || strpos($settingKeyLower, 'secret') !== false;
                                        $inputType = $setting['setting_type'] === 'number' ? 'number' : ($isSensitiveSetting ? 'password' : 'text');
                                        ?>
                                        <input class="form-input" type="<?= $inputType ?>" name="settings[<?= htmlspecialchars($setting['setting_key']) ?>]" value="<?= htmlspecialchars($setting['setting_value']) ?>" <?= $isSensitiveSetting ? 'autocomplete="new-password"' : '' ?>>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" name="save_settings" value="1" class="btn btn-primary admin-mt-1">Save Settings</button>
            </form>
        </div>

        <div class="admin-card">
            <h2 class="admin-subtitle">Add New Setting</h2>
            <form method="POST" class="admin-settings-add-grid">
                <input class="form-input" type="text" name="new_setting_key" placeholder="setting_key" required>
                <select class="form-select" name="new_setting_type">
                    <option value="text">text</option>
                    <option value="number">number</option>
                    <option value="boolean">boolean</option>
                    <option value="json">json</option>
                </select>
                <input class="form-input" type="text" name="new_setting_value" placeholder="initial value">
                <button class="btn btn-secondary" type="submit" name="add_setting" value="1">Add Setting</button>
            </form>
        </div>
    </main>
</div>
    <script src="js/admin.js"></script>
</body>
</html>

