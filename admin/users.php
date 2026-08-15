<?php
/**
 * Rosabella - Executive Staff & Administrator Management Center
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

// ── AJAX: Update staff field (status) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    $userId = intval($_POST['user_id'] ?? 0);
    $field  = $_POST['field'] ?? '';
    $value  = sanitize($_POST['value'] ?? '');

    $allowed = [
        'status' => ['active', 'inactive', 'banned'],
    ];

    if ($userId > 0 && isset($allowed[$field]) && in_array($value, $allowed[$field], true)) {
        $stmt = $db->prepare("UPDATE users SET $field = ? WHERE id = ? AND role = 'admin'");
        echo $stmt->execute([$value, $userId])
            ? json_encode(['success' => true])
            : json_encode(['success' => false, 'message' => 'Database update failed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    }
    exit;
}

// ── Handle Create New Staff Member ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_staff'])) {
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName  = sanitize($_POST['last_name'] ?? '');
    $email     = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password  = $_POST['password'] ?? '';
    $status    = in_array($_POST['status'] ?? '', ['active', 'inactive', 'banned'], true) ? $_POST['status'] : 'active';

    if (!$firstName || !$email || !$password) {
        $error = 'First name, valid email, and temporary password are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ((string)getSetting('admin_require_strong_password') === '1' && (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password))) {
        $error = 'Password must include uppercase, lowercase, and numeric characters as enforced by system security policy.';
    } else {
        $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'An account with this email address already exists.';
        } else {
            $pwdHash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $db->prepare("
                INSERT INTO users (first_name, last_name, email, password, role, status, created_at)
                VALUES (?, ?, ?, ?, 'admin', ?, NOW())
            ");
            if ($ins->execute([$firstName, $lastName, $email, $pwdHash, $status])) {
                $message = "Staff member '{$firstName} {$lastName}' added successfully.";
            } else {
                $error = 'Failed to create staff account.';
            }
        }
    }
}

// ── Delete staff member ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = intval($_POST['user_id'] ?? 0);
    if ($userId > 0 && $userId !== intval($_SESSION['user_id'])) {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
        $message = $stmt->execute([$userId]) ? 'Staff account deleted.' : '';
        if (!$message) $error = 'Unable to delete staff account.';
    } else {
        $error = 'You cannot delete your own active administrator account.';
    }
}

// ── Fetch staff users (role = 'admin' only) ──────────────────────────────────
$search       = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

$where  = ["role = 'admin'"];
$params = [];
if ($search) {
    $where[]  = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusFilter) { 
    $where[] = "status = ?"; 
    $params[] = $statusFilter; 
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

// Pagination Setup
$perPage = max(1, min(100, intval($_GET['per_page'] ?? 15)));
$page = max(1, intval($_GET['page'] ?? 1));

$countStmt = $db->prepare("SELECT COUNT(*) FROM users $whereSql");
$countStmt->execute($params);
$totalStaff = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalStaff / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT id, first_name, last_name, avatar, email, role, status, created_at FROM users $whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$staffMembers = $stmt->fetchAll();

$pageTitle = 'Staff Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $siteFavicon = getSetting('site_favicon'); if ($siteFavicon): ?>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . '/' . htmlspecialchars($siteFavicon) ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
    <title><?= $pageTitle ?> - Rosabella Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .staff-avatar-img {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            object-fit: cover;
            border: 1.5px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            flex-shrink: 0;
            margin-right: 0.65rem;
        }
        .staff-avatar-initials {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%);
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-right: 0.65rem;
            border: 1.5px solid #ffffff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
        }
        .staff-name-wrap {
            display: flex;
            align-items: center;
        }
        .u-select {
            appearance: auto;
            border: 1.5px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.3rem 0.55rem;
            font-size: 0.8125rem;
            font-family: inherit;
            font-weight: 500;
            background: #fff;
            color: #1e293b;
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s, opacity .15s;
            min-width: 100px;
        }
        .u-select:focus {
            outline: none;
            border-color: #0f766e;
            box-shadow: 0 0 0 2px rgba(15,118,110,.15);
        }
        .u-select[data-val="active"]   { border-color: #16a34a; color: #16a34a; }
        .u-select[data-val="inactive"] { border-color: #d97706; color: #d97706; }
        .u-select[data-val="banned"]   { border-color: #dc2626; color: #dc2626; }

        @keyframes u-spin { to { transform: rotate(360deg); } }
        .u-saving-ring {
            width: 13px; height: 13px; border-radius: 50%;
            border: 2px solid #cbd5e1;
            border-top-color: #0f766e;
            animation: u-spin .55s linear infinite;
            display: none; vertical-align: middle; margin-left: 4px;
        }
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

        /* Modal Styles */
        .staff-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1200;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(2px);
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .staff-modal.open {
            display: flex;
        }
        .staff-modal-dialog {
            background: #ffffff;
            border-radius: 12px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            animation: u-pop 0.2s ease;
        }
        .staff-modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .staff-modal-body {
            padding: 1.5rem;
        }
        .staff-modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php renderAdminSidebar('users'); ?>

    <main class="admin-content">
        <?php renderAdminTopbar($pageTitle); ?>

        <div class="admin-header" style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <div>
                <h1 class="admin-page-title" style="margin-bottom: 0.25rem;">Staff & Administrators</h1>
                <p style="color: #64748b; font-size: 0.875rem; margin: 0;">Manage administrative staff members with access to the Rosabella control panel.</p>
            </div>
            <button type="button" class="btn btn-primary" onclick="openStaffModal()" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Add New Staff</span>
            </button>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- Customer Redirect Banner -->
        <div style="background: #f0fdfa; border: 1px solid #ccfbf1; border-radius: 10px; padding: 0.85rem 1.15rem; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.65rem; color: #0f766e; font-size: 0.85rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>Looking for customer buyer accounts? Manage registered shoppers in the dedicated Customers section.</span>
            </div>
            <a href="<?= BASE_URL ?>/admin/customers" class="btn btn-secondary" style="font-size: 0.80rem; padding: 0.35rem 0.75rem; color: #0f766e; border-color: #99f6e4; background: #ffffff;">
                Go to Customers &rarr;
            </a>
        </div>

        <!-- Filter bar -->
        <div class="admin-card" style="margin-bottom: 1.25rem;">
            <form method="GET" class="admin-form-row">
                <input type="text" class="form-input" style="max-width: 280px;" name="search"
                    placeholder="Search staff by name or email…" value="<?= htmlspecialchars($search) ?>">
                <select name="status" class="form-select" style="max-width: 160px;">
                    <option value="">All Statuses</option>
                    <option value="active"   <?= $statusFilter === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="banned"   <?= $statusFilter === 'banned'   ? 'selected' : '' ?>>Banned</option>
                </select>
                <button type="submit" class="btn btn-secondary">Filter Staff</button>
                <?php if ($search || $statusFilter): ?>
                    <a href="<?= BASE_URL ?>/admin/users" class="btn btn-text" style="font-size: 0.835rem; color: #64748b;">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Staff Table -->
        <div class="admin-card" style="padding: 0; overflow: hidden;">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>Staff Member</th>
                            <th>Email Address</th>
                            <th>Role</th>
                            <th style="width: 140px;">Status</th>
                            <th>Joined Date</th>
                            <th style="width: 90px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($staffMembers)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 2.5rem; color: #94a3b8;">No staff members found matching criteria.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($staffMembers as $user):
                        $full   = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                        $ini    = strtoupper(substr($user['first_name'] ?? 'A', 0, 1) . substr($user['last_name'] ?? 'D', 0, 1));
                        $isSelf = intval($user['id']) === intval($_SESSION['user_id']);
                        $avatarSrc = !empty($user['avatar']) ? resolveAdminImageSrc($user['avatar']) : '';
                    ?>
                    <tr>
                        <!-- ID -->
                        <td style="color: #94a3b8; font-size: 0.78rem; font-weight: 600;">#<?= intval($user['id']) ?></td>

                        <!-- Staff Member Name & Avatar -->
                        <td>
                            <div class="staff-name-wrap">
                                <?php if ($avatarSrc): ?>
                                    <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="Avatar" class="staff-avatar-img">
                                <?php else: ?>
                                    <span class="staff-avatar-initials"><?= htmlspecialchars($ini) ?></span>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight: 600; color: #0f172a; font-size: 0.875rem;">
                                        <?= htmlspecialchars($full ?: 'Administrator') ?>
                                        <?php if ($isSelf): ?>
                                            <span style="font-size: 0.70rem; background: #e0f2fe; color: #0369a1; padding: 1px 6px; border-radius: 4px; font-weight: 600; margin-left: 4px;">You</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Email -->
                        <td style="color: #475569; font-size: 0.85rem;"><?= htmlspecialchars($user['email']) ?></td>

                        <!-- Role Badge -->
                        <td>
                            <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.75rem; font-weight: 600; background: #ede9fe; color: #6d28d9; padding: 3px 8px; border-radius: 6px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                Administrator
                            </span>
                        </td>

                        <!-- Status (auto-save via AJAX) -->
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

                        <!-- Joined Date -->
                        <td style="color: #64748b; font-size: 0.8125rem;"><?= date('M j, Y', strtotime($user['created_at'])) ?></td>

                        <!-- Actions -->
                        <td style="text-align: center;">
                            <?php if (!$isSelf): ?>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to remove this staff member?');" style="display: inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="user_id" value="<?= intval($user['id']) ?>">
                                    <input type="hidden" name="delete_user" value="1">
                                    <button type="submit" class="btn-action-icon delete" title="Remove Staff Member">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="font-size: 0.76rem; color: #94a3b8; font-style: italic;">Protected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php renderAdminPagination($page, $totalStaff, $perPage, BASE_URL . '/admin/users', array_filter(['search' => $search, 'status' => $statusFilter])); ?>
        </div>
    </main>
</div>

<!-- Modal: Add New Staff Member -->
<div id="staffModal" class="staff-modal">
    <div class="staff-modal-dialog">
        <div class="staff-modal-header">
            <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a;">Add Administrator / Staff</h3>
            <button type="button" onclick="closeStaffModal()" style="background: transparent; border: none; font-size: 1.25rem; color: #94a3b8; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="create_staff" value="1">
            <div class="staff-modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="form-label" style="font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">First Name *</label>
                        <input type="text" name="first_name" class="form-input" required placeholder="e.g. John">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Last Name</label>
                        <input type="text" name="last_name" class="form-input" placeholder="e.g. Doe">
                    </div>
                </div>

                <div style="margin-bottom: 0.85rem;">
                    <label class="form-label" style="font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Email Address *</label>
                    <input type="email" name="email" class="form-input" required placeholder="admin@rosabella.com">
                </div>

                <div style="margin-bottom: 0.85rem;">
                    <label class="form-label" style="font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Temporary Password *</label>
                    <input type="password" name="password" class="form-input" required placeholder="Minimum 6 characters" minlength="6">
                </div>

                <div>
                    <label class="form-label" style="font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Initial Status</label>
                    <select name="status" class="form-select">
                        <option value="active">Active (Access Granted)</option>
                        <option value="inactive">Inactive (Pending)</option>
                    </select>
                </div>
            </div>
            <div class="staff-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeStaffModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Staff Member</button>
            </div>
        </form>
    </div>
</div>

<div id="u-toast"></div>

<script src="js/admin.js"></script>
<script>
(function () {
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

    /* auto-save status on change */
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
            
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) fd.append('csrf_token', csrfMeta.content);

            try {
                const res  = await fetch(location.pathname, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    recolour(this);
                    showToast('Staff status set to "' + value + '"', 'ok');
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

    window.openStaffModal = function() {
        document.getElementById('staffModal').classList.add('open');
    };
    window.closeStaffModal = function() {
        document.getElementById('staffModal').classList.remove('open');
    };
})();
</script>
</body>
</html>
