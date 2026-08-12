<?php
/**
 * KARTLY - Admin Users Management
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

// -- Security: Verify CSRF on all admin POST requests --------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
}

$message = '';
$error = '';

// ── AJAX: Update user field (role or status) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    $userId = intval($_POST['user_id'] ?? 0);
    $field  = $_POST['field'] ?? '';
    $value  = sanitize($_POST['value'] ?? '');

    $allowed = [
        'role'   => ['customer', 'admin'],
        'status' => ['active', 'inactive', 'banned'],
    ];

    if ($userId > 0 && isset($allowed[$field]) && in_array($value, $allowed[$field], true)) {
        $stmt = $db->prepare("UPDATE users SET $field = ? WHERE id = ?");
        echo $stmt->execute([$value, $userId])
            ? json_encode(['success' => true])
            : json_encode(['success' => false, 'message' => 'DB error']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
    }
    exit;
}

// ── Delete user ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = intval($_POST['user_id'] ?? 0);
    if ($userId > 0 && $userId !== intval($_SESSION['user_id'])) {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $message = $stmt->execute([$userId]) ? 'User deleted.' : '';
        if (!$message) $error = 'Unable to delete user.';
    } else {
        $error = 'You cannot delete your own account.';
    }
}

// ── Fetch users ──────────────────────────────────────────────────────
$search       = sanitize($_GET['search'] ?? '');
$roleFilter   = sanitize($_GET['role']   ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

$where  = [];
$params = [];
if ($search) {
    $where[]  = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($roleFilter)   { $where[] = "role = ?";   $params[] = $roleFilter; }
if ($statusFilter) { $where[] = "status = ?"; $params[] = $statusFilter; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("SELECT id, first_name, last_name, email, role, status, created_at FROM users $whereSql ORDER BY created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Users Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $siteFavicon = getSetting('site_favicon'); if ($siteFavicon): ?>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . '/' . htmlspecialchars($siteFavicon) ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - KARTLY Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        /* ─── Inline select pill ─────────────────────── */
        .u-select {
            appearance: auto;
            border: 1.5px solid var(--color-border, #e2e8f0);
            border-radius: 6px;
            padding: 0.3rem 0.55rem;
            font-size: 0.8125rem;
            font-family: inherit;
            font-weight: 500;
            background: var(--color-bg, #fff);
            color: var(--color-text, #1e293b);
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s, opacity .15s;
            min-width: 110px;
        }
        .u-select:focus {
            outline: none;
            border-color: var(--color-primary, #0f766e);
            box-shadow: 0 0 0 2px rgba(15,118,110,.15);
        }
        .u-select:disabled { opacity: .45; cursor: not-allowed; }

        /* colour coding for role */
        .u-select[data-field="role"][data-val="admin"]    { border-color: #6366f1; color: #6366f1; }
        .u-select[data-field="role"][data-val="customer"] { border-color: var(--color-primary,#0f766e); color: var(--color-primary,#0f766e); }

        /* colour coding for status */
        .u-select[data-field="status"][data-val="active"]   { border-color: #16a34a; color: #16a34a; }
        .u-select[data-field="status"][data-val="inactive"] { border-color: #d97706; color: #d97706; }
        .u-select[data-field="status"][data-val="banned"]   { border-color: #dc2626; color: #dc2626; }

        /* saving spinner */
        @keyframes u-spin { to { transform: rotate(360deg); } }
        .u-saving-ring {
            width: 13px; height: 13px; border-radius: 50%;
            border: 2px solid #cbd5e1;
            border-top-color: var(--color-primary,#0f766e);
            animation: u-spin .55s linear infinite;
            display: none; vertical-align: middle; margin-left: 4px;
        }
        .u-saving .u-saving-ring { display: inline-block; }

        /* toast stack */
        #u-toast {
            position: fixed; bottom: 1.5rem; right: 1.5rem;
            display: flex; flex-direction: column; gap: .4rem;
            z-index: 9999; pointer-events: none;
        }
        .u-toast {
            display: flex; align-items: center; gap: .45rem;
            padding: .6rem 1rem; border-radius: 8px;
            font-size: .83rem; font-weight: 500; color: #fff;
            box-shadow: 0 4px 18px rgba(0,0,0,.18);
            animation: u-pop .2s ease;
            pointer-events: auto;
        }
        @keyframes u-pop { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
        .u-toast.ok  { background: #16a34a; }
        .u-toast.err { background: #dc2626; }

        /* avatar circle */
        .u-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, var(--color-primary,#0f766e), #0d9488);
            color: #fff; font-size: .72rem; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center;
            flex-shrink: 0; margin-right: .55rem;
        }
        .u-name-wrap { display: flex; align-items: center; }
        .admin-table td { vertical-align: middle; }
        .admin-table th, .admin-table td { white-space: nowrap; }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php renderAdminSidebar('users'); ?>

    <main class="admin-content">
        <?php renderAdminTopbar($pageTitle); ?>

        <div class="admin-header">
            <h1 class="admin-page-title">Users</h1>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- Filter bar -->
        <div class="admin-card" style="margin-bottom:1.5rem;">
            <form method="GET" class="admin-form-row">
                <input type="text" class="form-input" style="max-width:260px;" name="search"
                    placeholder="Search users…" value="<?= htmlspecialchars($search) ?>">
                <select name="role" class="form-select" style="max-width:150px;">
                    <option value="">All Roles</option>
                    <option value="customer" <?= $roleFilter === 'customer' ? 'selected' : '' ?>>Customer</option>
                    <option value="admin"    <?= $roleFilter === 'admin'    ? 'selected' : '' ?>>Admin</option>
                </select>
                <select name="status" class="form-select" style="max-width:150px;">
                    <option value="">All Statuses</option>
                    <option value="active"   <?= $statusFilter === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="banned"   <?= $statusFilter === 'banned'   ? 'selected' : '' ?>>Banned</option>
                </select>
                <button type="submit" class="btn btn-secondary">Filter</button>
            </form>
        </div>

        <!-- Table -->
        <div class="admin-card" style="padding:0; overflow:hidden;">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Joined</th>
                            <th style="width:130px;">Role</th>
                            <th style="width:140px;">Status</th>
                            <th style="width:90px; text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8;">No users found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($users as $user):
                        $full    = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                        $ini     = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
                        $isSelf  = intval($user['id']) === intval($_SESSION['user_id']);
                    ?>
                    <tr>
                        <!-- ID -->
                        <td style="color:#94a3b8;font-size:.78rem;">#<?= intval($user['id']) ?></td>

                        <!-- Name -->
                        <td>
                            <div class="u-name-wrap">
                                <span class="u-avatar"><?= htmlspecialchars($ini) ?></span>
                                <span><?= htmlspecialchars($full ?: 'Unknown') ?></span>
                            </div>
                        </td>

                        <!-- Email -->
                        <td style="color:#64748b;font-size:.875rem;"><?= htmlspecialchars($user['email']) ?></td>

                        <!-- Joined -->
                        <td style="color:#94a3b8;font-size:.8rem;"><?= date('M j, Y', strtotime($user['created_at'])) ?></td>

                        <!-- Role (auto-save) -->
                        <td>
                            <span class="u-save-wrap">
                                <select class="u-select" data-field="role" data-user-id="<?= intval($user['id']) ?>"
                                    data-val="<?= htmlspecialchars($user['role']) ?>"
                                    <?= $isSelf ? 'disabled title="Cannot change your own role"' : '' ?>>
                                    <option value="customer" <?= $user['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                                    <option value="admin"    <?= $user['role'] === 'admin'    ? 'selected' : '' ?>>Admin</option>
                                </select>
                                <span class="u-saving-ring"></span>
                            </span>
                        </td>

                        <!-- Status (auto-save) -->
                        <td>
                            <span class="u-save-wrap">
                                <select class="u-select" data-field="status" data-user-id="<?= intval($user['id']) ?>"
                                    data-val="<?= htmlspecialchars($user['status']) ?>">
                                    <option value="active"   <?= $user['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="banned"   <?= $user['status'] === 'banned'   ? 'selected' : '' ?>>Banned</option>
                                </select>
                                <span class="u-saving-ring"></span>
                            </span>
                        </td>

                        <!-- Actions -->
                        <td style="text-align:center;">
                            <?php if (!$isSelf): ?>
                                <form method="POST" onsubmit="return confirm('Delete this user permanently?');" style="display:inline;">
                        <!-- Security: CSRF token -->
                        <?= csrfField() ?>
                                    <input type="hidden" name="user_id"     value="<?= intval($user['id']) ?>">
                                    <input type="hidden" name="delete_user" value="1">
                                    <button type="submit"
                                        style="background:transparent;border:1.5px solid #fca5a5;color:#dc2626;padding:.25rem .65rem;border-radius:6px;font-size:.8rem;cursor:pointer;font-weight:500;">
                                        Delete
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="font-size:.78rem;color:#94a3b8;font-style:italic;">You</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<div id="u-toast"></div>

<script src="js/admin.js"></script>
<script>
(function () {
    const ENDPOINT = location.pathname + location.search.split('&').filter(p => !p.startsWith('search=') && !p.startsWith('role=') && !p.startsWith('status=')).join('&');

    /* colour each select on load */
    function recolour(sel) { sel.dataset.val = sel.value; }
    document.querySelectorAll('.u-select').forEach(recolour);

    /* toast helper */
    function showToast(msg, type) {
        const wrap = document.getElementById('u-toast');
        const el   = document.createElement('div');
        el.className = 'u-toast ' + type;
        const icon = type === 'ok'
            ? '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>'
            : '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        el.innerHTML = icon + ' ' + msg;
        wrap.appendChild(el);
        setTimeout(() => el.remove(), 2800);
    }

    /* auto-save on change */
    document.querySelectorAll('.u-select').forEach(sel => {
        sel.addEventListener('change', async function () {
            const prev   = this.dataset.val;
            const field  = this.dataset.field;
            const value  = this.value;
            const userId = this.dataset.userId;
            const ring   = this.closest('.u-save-wrap').querySelector('.u-saving-ring');

            this.disabled = true;
            if (ring) ring.style.display = 'inline-block';

            const fd = new FormData();
            fd.append('ajax_update', '1');
            fd.append('user_id', userId);
            fd.append('field',   field);
            fd.append('value',   value);
            // Security: include CSRF token
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) fd.append('csrf_token', csrfMeta.content);

            try {
                const res  = await fetch(location.pathname, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    recolour(this);
                    showToast((field === 'role' ? 'Role' : 'Status') + ' set to "' + value + '"', 'ok');
                } else {
                    this.value = prev;
                    recolour(this);
                    showToast(data.message || 'Save failed', 'err');
                }
            } catch (e) {
                this.value = prev;
                recolour(this);
                showToast('Network error', 'err');
            } finally {
                this.disabled = false;
                if (ring) ring.style.display = 'none';
            }
        });
    });
})();
</script>
</body>
</html>
