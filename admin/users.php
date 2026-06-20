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
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $userId = intval($_POST['user_id'] ?? 0);
    $role = sanitize($_POST['role'] ?? 'customer');
    $status = sanitize($_POST['status'] ?? 'active');

    if ($userId > 0) {
        $allowedRoles = ['customer', 'admin'];
        $allowedStatuses = ['active', 'inactive', 'banned'];
        $role = in_array($role, $allowedRoles, true) ? $role : 'customer';
        $status = in_array($status, $allowedStatuses, true) ? $status : 'active';

        $stmt = $db->prepare("UPDATE users SET role = ?, status = ? WHERE id = ?");
        if ($stmt->execute([$role, $status, $userId])) {
            $message = 'User updated successfully.';
        } else {
            $error = 'Unable to update user.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = intval($_POST['user_id'] ?? 0);
    if ($userId > 0 && $userId !== intval($_SESSION['user_id'])) {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$userId])) {
            $message = 'User deleted successfully.';
        } else {
            $error = 'Unable to delete user.';
        }
    } else {
        $error = 'You cannot delete the current admin account.';
    }
}

$search = sanitize($_GET['search'] ?? '');
$roleFilter = sanitize($_GET['role'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

$where = [];
$params = [];
if ($search) {
    $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($roleFilter) {
    $where[] = "role = ?";
    $params[] = $roleFilter;
}
if ($statusFilter) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("SELECT * FROM users $whereSql ORDER BY created_at DESC");
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
</head>
<body>
<div class="admin-layout">
    <?php renderAdminSidebar('users'); ?>

    <main class="admin-content">
        <?php renderAdminTopbar($pageTitle ?? 'Admin Panel'); ?>
<div class="admin-header">
            <h1 class="admin-page-title">Users</h1>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="admin-card">
            <form method="GET" class="admin-form-row">
                <input type="text" class="form-input admin-input-max-280" name="search" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                <select name="role" class="form-select admin-select-max-180">
                    <option value="">All Roles</option>
                    <option value="customer" <?= $roleFilter === 'customer' ? 'selected' : '' ?>>Customer</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
                <select name="status" class="form-select admin-select-max-180">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="banned" <?= $statusFilter === 'banned' ? 'selected' : '' ?>>Banned</option>
                </select>
                <button type="submit" class="btn btn-secondary">Filter</button>
            </form>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Created</th>
                    <th>Role / Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= intval($user['id']) ?></td>
                        <td><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars(date('M j, Y', strtotime($user['created_at']))) ?></td>
                        <td>
                            <form method="POST" class="admin-form-row-center">
                                <input type="hidden" name="user_id" value="<?= intval($user['id']) ?>">
                                <input type="hidden" name="update_user" value="1">
                                <select name="role" class="form-select admin-select-min-120">
                                    <option value="customer" <?= $user['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                                <select name="status" class="form-select admin-select-min-120">
                                    <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="banned" <?= $user['status'] === 'banned' ? 'selected' : '' ?>>Banned</option>
                                </select>
                                <button class="btn btn-sm btn-outline" type="submit">Save</button>
                            </form>
                        </td>
                        <td>
                            <?php if (intval($user['id']) !== intval($_SESSION['user_id'])): ?>
                                <form method="POST" onsubmit="return confirm('Delete this user?');">
                                    <input type="hidden" name="user_id" value="<?= intval($user['id']) ?>">
                                    <input type="hidden" name="delete_user" value="1">
                                    <button class="btn btn-sm btn-secondary" type="submit">Delete</button>
                                </form>
                            <?php else: ?>
                                <span class="admin-note-muted">Current user</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
    <script src="js/admin.js"></script>
</body>
</html>


