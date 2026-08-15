<?php
/**
 * Rosabella – Admin Notifications Centre
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/notification_sync.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . BASE_URL . '/login'); exit;
}

$db = getDB();

// ── POST actions ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'mark_read':
            $db->prepare("UPDATE admin_notifications SET is_read = 1, read_at = NOW() WHERE id = ?")
               ->execute([(int)($_POST['id'] ?? 0)]);
            break;
        case 'mark_all_read':
            $db->exec("UPDATE admin_notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0");
            break;
        case 'delete':
            $db->prepare("DELETE FROM admin_notifications WHERE id = ?")
               ->execute([(int)($_POST['id'] ?? 0)]);
            break;
        case 'delete_all_read':
            $db->exec("DELETE FROM admin_notifications WHERE is_read = 1");
            break;
    }
    header('Location: ' . BASE_URL . '/admin/notifications'); exit;
}

// ── Sync live data into DB ─────────────────────────────────────────────────────
try { syncAdminNotifications($db); } catch (Throwable $e) {}

// ── Filters & pagination ───────────────────────────────────────────────────────
$filterType   = in_array($_GET['type']   ?? '', ['order','stock','review','user','alert']) ? $_GET['type']   : '';
$filterStatus = in_array($_GET['status'] ?? '', ['unread','read'])                         ? $_GET['status'] : '';
$perPage      = 20;
$page         = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($page - 1) * $perPage;

$where = ['1=1']; $params = [];
if ($filterType)             { $where[] = 'type = ?';    $params[] = $filterType; }
if ($filterStatus === 'unread') $where[] = 'is_read = 0';
if ($filterStatus === 'read')   $where[] = 'is_read = 1';
$whereSQL = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM admin_notifications WHERE $whereSQL");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$listStmt = $db->prepare(
    "SELECT * FROM admin_notifications WHERE $whereSQL
     ORDER BY is_read ASC, (priority='high') DESC, (priority='medium') DESC, created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$listStmt->execute($params);
$notifications = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$unreadTotal = (int)$db->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0")->fetchColumn();
$totalPages  = max(1, (int)ceil($total / $perPage));

// Per-type unread counts for tabs
$tabCounts = [];
foreach (['','order','stock','review','user','alert'] as $t) {
    $s = $db->prepare("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0" . ($t ? " AND type = '$t'" : ''));
    $s->execute(); $tabCounts[$t] = (int)$s->fetchColumn();
}

// Stats
$highPrioUnread  = (int)$db->query("SELECT COUNT(*) FROM admin_notifications WHERE priority='high' AND is_read=0")->fetchColumn();
$orderUnread     = $tabCounts['order'];
$stockUnread     = $tabCounts['stock'];
$pageTitle = 'Notifications';

// ── Helpers ────────────────────────────────────────────────────────────────────
function npIcon(string $type): string {
    return match ($type) {
        'order'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
        'stock'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        'review' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
        'user'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        default  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    };
}
function npTimeAgo(?string $dt): string {
    if (!$dt) return '—';
    $diff = time() - strtotime($dt);
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('d M Y', strtotime($dt));
}
function npBuildUrl(array $merge): string {
    return '?' . http_build_query(array_merge(array_filter($_GET, fn($v) => $v !== ''), $merge));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $fav = getSetting('site_favicon'); if ($fav): ?>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . '/' . htmlspecialchars($fav) ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — Rosabella Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
    /* ── Notification Page Styles ─────────────────────────────── */
    .np-wrap { padding-top: 1.5rem; }

    /* Header */
    .np-header { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:1.5rem; }
    .np-header h1 { font-size:1.35rem; font-weight:800; color:#0f172a; margin:0 0 .2rem; letter-spacing:-.02em; }
    .np-header-sub { font-size:.8rem; color:#64748b; }
    .np-header-btns { display:flex; gap:.5rem; flex-wrap:wrap; }

    .np-btn { display:inline-flex; align-items:center; gap:.4rem; padding:.5rem .9rem; border-radius:9px; font-size:.76rem; font-weight:600; border:1.5px solid transparent; cursor:pointer; text-decoration:none; transition:all .15s; white-space:nowrap; }
    .np-btn svg { width:13px; height:13px; flex-shrink:0; }
    .np-btn-ghost  { background:#fff; border-color:#e2e8f0; color:#475569; }
    .np-btn-ghost:hover  { background:#f8fafc; border-color:#cbd5e1; color:#0f172a; }
    .np-btn-danger { background:#fff; border-color:#fca5a5; color:#dc2626; }
    .np-btn-danger:hover { background:#fef2f2; }

    /* Stats */
    .np-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.5rem; }
    @media(max-width:860px){ .np-stats{grid-template-columns:repeat(2,1fr);} }
    @media(max-width:480px){ .np-stats{grid-template-columns:1fr 1fr;} }
    .np-stat { background:#fff; border:1.5px solid #e2e8f0; border-radius:13px; padding:1rem 1.15rem; display:flex; align-items:center; gap:.85rem; }
    .np-stat-ico { width:40px; height:40px; border-radius:11px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .np-stat-ico svg { width:18px; height:18px; }
    .np-stat-ico.red   { background:#fee2e2; color:#b91c1c; }
    .np-stat-ico.blue  { background:#dbeafe; color:#1d4ed8; }
    .np-stat-ico.amber { background:#fef3c7; color:#92400e; }
    .np-stat-ico.green { background:#d1fae5; color:#065f46; }
    .np-stat-val   { font-size:1.5rem; font-weight:800; color:#0f172a; line-height:1; }
    .np-stat-label { font-size:.71rem; color:#64748b; margin-top:2px; }

    /* Filter panel */
    .np-filter-panel { background:#fff; border:1.5px solid #e2e8f0; border-radius:13px; overflow:hidden; margin-bottom:1rem; }
    .np-tabs { display:flex; overflow-x:auto; scrollbar-width:none; border-bottom:1px solid #f1f5f9; }
    .np-tabs::-webkit-scrollbar{display:none;}
    .np-tab { display:inline-flex; align-items:center; gap:.35rem; padding:.8rem 1rem; font-size:.76rem; font-weight:600; color:#64748b; text-decoration:none; border-bottom:2px solid transparent; white-space:nowrap; transition:color .15s,border-color .15s; }
    .np-tab:hover{color:#334155;}
    .np-tab.active{color:var(--color-primary,#0f766e);border-bottom-color:var(--color-primary,#0f766e);}
    .np-tab-badge { min-width:16px; height:16px; padding:0 4px; background:#fee2e2; color:#b91c1c; font-size:.58rem; font-weight:800; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; }
    .np-tab:not(.active) .np-tab-badge{background:#f1f5f9;color:#475569;}
    .np-status-row { display:flex; align-items:center; gap:.4rem; padding:.55rem 1rem; flex-wrap:wrap; }
    .np-status-lbl { font-size:.72rem; color:#94a3b8; }
    .np-status-pill { display:inline-flex; align-items:center; gap:.25rem; padding:3px 9px; border-radius:20px; font-size:.7rem; font-weight:600; text-decoration:none; border:1.5px solid #e2e8f0; color:#475569; background:#fff; transition:all .12s; }
    .np-status-pill:hover{border-color:#cbd5e1;}
    .np-status-pill.active{background:#0f172a;color:#fff;border-color:#0f172a;}

    /* Items */
    .np-item { display:flex; align-items:flex-start; gap:.9rem; padding:1rem 1.1rem; background:#fff; border:1.5px solid #e2e8f0; border-radius:13px; margin-bottom:.55rem; transition:box-shadow .15s,transform .1s; position:relative; overflow:hidden; }
    .np-item:hover{box-shadow:0 4px 18px rgba(15,23,42,.07);transform:translateY(-1px);}

    /* Unread left border */
    .np-item.unread{border-left-width:3.5px;}
    .np-item.unread.p-high   {border-left-color:#ef4444;background:#fff8f8;}
    .np-item.unread.p-medium {border-left-color:#f59e0b;background:#fffdf0;}
    .np-item.unread.p-low    {border-left-color:#10b981;background:#f0fdf8;}

    /* Top gradient accent */
    .np-item::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;opacity:0;}
    .np-item.unread.p-high::before   {background:linear-gradient(90deg,#ef4444,#f97316);opacity:1;}
    .np-item.unread.p-medium::before {background:linear-gradient(90deg,#f59e0b,#eab308);opacity:1;}
    .np-item.unread.p-low::before    {background:linear-gradient(90deg,#10b981,#06b6d4);opacity:1;}

    .np-icon { flex-shrink:0; width:42px; height:42px; border-radius:11px; display:flex; align-items:center; justify-content:center; }
    .np-icon svg{width:18px;height:18px;}
    .np-icon.t-order  {background:#dbeafe;color:#1d4ed8;}
    .np-icon.t-stock  {background:#fee2e2;color:#b91c1c;}
    .np-icon.t-review {background:#fef3c7;color:#92400e;}
    .np-icon.t-user   {background:#d1fae5;color:#065f46;}
    .np-icon.t-alert  {background:#ede9fe;color:#6d28d9;}

    .np-body{flex:1;min-width:0;}
    .np-title{font-size:.855rem;font-weight:700;color:#0f172a;margin:0 0 .2rem;line-height:1.3;}
    .np-item.read .np-title{color:#64748b;font-weight:600;}
    .np-desc{font-size:.77rem;color:#475569;margin:0 0 .4rem;line-height:1.5;}
    .np-meta{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;}
    .np-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;}
    .p-high   .np-dot{background:#ef4444;}
    .p-medium .np-dot{background:#f59e0b;}
    .p-low    .np-dot{background:#10b981;}
    .np-time{font-size:.68rem;color:#94a3b8;}
    .np-pill{font-size:.6rem;font-weight:700;padding:2px 6px;border-radius:4px;text-transform:uppercase;letter-spacing:.04em;}
    .p-high   .np-pill{background:#fee2e2;color:#b91c1c;}
    .p-medium .np-pill{background:#fef9c3;color:#92400e;}
    .p-low    .np-pill{background:#d1fae5;color:#065f46;}
    .np-type-pill{font-size:.6rem;font-weight:600;padding:2px 6px;border-radius:4px;background:#f1f5f9;color:#475569;text-transform:capitalize;}

    .np-actions{display:flex;flex-direction:column;gap:.35rem;align-items:flex-end;flex-shrink:0;}
    .np-act{display:inline-flex;align-items:center;gap:.2rem;padding:4px 8px;border-radius:7px;font-size:.68rem;font-weight:600;border:1.5px solid #e2e8f0;background:#fff;cursor:pointer;color:#475569;text-decoration:none;transition:all .12s;white-space:nowrap;}
    .np-act:hover{background:#f8fafc;border-color:#cbd5e1;color:#0f172a;}
    .np-act.del{border-color:#fca5a5;color:#dc2626;}
    .np-act.del:hover{background:#fef2f2;}
    .np-act svg{width:11px;height:11px;}

    /* Empty */
    .np-empty{text-align:center;padding:5rem 2rem;background:#fff;border:1.5px solid #e2e8f0;border-radius:13px;}
    .np-empty-ico{width:68px;height:68px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;margin:0 auto 1.1rem;}
    .np-empty-ico svg{width:30px;height:30px;color:#94a3b8;}
    .np-empty h3{font-size:1rem;font-weight:700;color:#334155;margin:0 0 .35rem;}
    .np-empty p{font-size:.79rem;color:#94a3b8;margin:0;}

    /* Pagination */
    .np-pager{display:flex;align-items:center;justify-content:space-between;padding:1rem 0 0;flex-wrap:wrap;gap:.6rem;}
    .np-pager-info{font-size:.73rem;color:#94a3b8;}
    .np-pager-links{display:flex;gap:.25rem;}
    .np-pager-a{min-width:32px;height:32px;padding:0 7px;border:1.5px solid #e2e8f0;border-radius:7px;background:#fff;color:#475569;font-size:.75rem;font-weight:600;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;transition:all .12s;}
    .np-pager-a:hover{background:#f8fafc;border-color:#cbd5e1;}
    .np-pager-a.active{background:var(--color-primary,#0f766e);border-color:var(--color-primary,#0f766e);color:#fff;}
    .np-pager-a.off{opacity:.4;pointer-events:none;}
    </style>
</head>
<body>
<div class="admin-layout">
    <?php renderAdminSidebar('notifications'); ?>
    <div class="admin-content">
        <?php renderAdminTopbar($pageTitle); ?>
        <div class="np-wrap">

            <!-- ── Header ────────────────────────────────────────── -->
            <div class="np-header">
                <div>
                    <h1>🔔 Notification Centre</h1>
                    <div class="np-header-sub">
                        <strong><?= $unreadTotal ?></strong> unread &middot; <strong><?= $total ?></strong> total
                        <?php if ($filterType || $filterStatus): ?> &mdash; filtered<?php endif; ?>
                    </div>
                </div>
                <div class="np-header-btns">
                    <?php if ($unreadTotal > 0): ?>
                    <form method="POST" style="display:contents;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="np-btn np-btn-ghost">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Mark all read
                        </button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" style="display:contents;" onsubmit="return confirm('Delete all read notifications?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_all_read">
                        <button type="submit" class="np-btn np-btn-danger">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            Clear read
                        </button>
                    </form>
                </div>
            </div>

            <!-- ── Stats ─────────────────────────────────────────── -->
            <div class="np-stats">
                <div class="np-stat">
                    <div class="np-stat-ico red">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    </div>
                    <div><div class="np-stat-val"><?= $unreadTotal ?></div><div class="np-stat-label">Unread</div></div>
                </div>
                <div class="np-stat">
                    <div class="np-stat-ico red">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>
                    </div>
                    <div><div class="np-stat-val"><?= $highPrioUnread ?></div><div class="np-stat-label">High Priority</div></div>
                </div>
                <div class="np-stat">
                    <div class="np-stat-ico blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    </div>
                    <div><div class="np-stat-val"><?= $orderUnread ?></div><div class="np-stat-label">Order Alerts</div></div>
                </div>
                <div class="np-stat">
                    <div class="np-stat-ico amber">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>
                    </div>
                    <div><div class="np-stat-val"><?= $stockUnread ?></div><div class="np-stat-label">Stock Alerts</div></div>
                </div>
            </div>

            <!-- ── Filters ────────────────────────────────────────── -->
            <div class="np-filter-panel">
                <div class="np-tabs">
                    <?php
                    $tabDefs = [
                        '' => 'All', 'order' => 'Orders', 'stock' => 'Stock',
                        'review' => 'Reviews', 'user' => 'Users', 'alert' => 'Alerts'
                    ];
                    foreach ($tabDefs as $key => $label):
                        $count  = $tabCounts[$key] ?? 0;
                        $active = ($filterType === $key);
                        $href   = npBuildUrl(array_merge($key ? ['type' => $key] : [], ['page' => 1]));
                        if ($key === '' && $filterType === '') $active = true;
                    ?>
                    <a href="<?= htmlspecialchars($href) ?>" class="np-tab <?= $active ? 'active' : '' ?>">
                        <?= htmlspecialchars($label) ?>
                        <?php if ($count > 0): ?><span class="np-tab-badge"><?= $count ?></span><?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="np-status-row">
                    <span class="np-status-lbl">Show:</span>
                    <a href="<?= htmlspecialchars(npBuildUrl(['page' => 1, 'status' => ''])) ?>" class="np-status-pill <?= !$filterStatus ? 'active' : '' ?>">All</a>
                    <a href="<?= htmlspecialchars(npBuildUrl(['status' => 'unread', 'page' => 1])) ?>" class="np-status-pill <?= $filterStatus === 'unread' ? 'active' : '' ?>">
                        <span style="width:6px;height:6px;border-radius:50%;background:#ef4444;display:inline-block;"></span>Unread
                    </a>
                    <a href="<?= htmlspecialchars(npBuildUrl(['status' => 'read', 'page' => 1])) ?>" class="np-status-pill <?= $filterStatus === 'read' ? 'active' : '' ?>">Read</a>
                </div>
            </div>

            <!-- ── Notification List ──────────────────────────────── -->
            <?php if (empty($notifications)): ?>
            <div class="np-empty">
                <div class="np-empty-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                </div>
                <h3>All clear!</h3>
                <p>No notifications match your current filters.</p>
            </div>
            <?php else: ?>

            <?php foreach ($notifications as $n):
                $isUnread = !(bool)$n['is_read'];
                $p = htmlspecialchars($n['priority']);
                $t = htmlspecialchars($n['type']);
            ?>
            <div class="np-item <?= $isUnread ? 'unread' : 'read' ?> p-<?= $p ?>">
                <div class="np-icon t-<?= $t ?>"><?= npIcon($t) ?></div>
                <div class="np-body">
                    <div class="np-title"><?= htmlspecialchars($n['title']) ?></div>
                    <?php if ($n['body']): ?><div class="np-desc"><?= htmlspecialchars($n['body']) ?></div><?php endif; ?>
                    <div class="np-meta">
                        <?php if ($isUnread): ?><span class="np-dot"></span><?php endif; ?>
                        <span class="np-time" title="<?= htmlspecialchars($n['created_at']) ?>"><?= npTimeAgo($n['created_at']) ?></span>
                        <span class="np-pill"><?= $p ?></span>
                        <span class="np-type-pill"><?= $t ?></span>
                    </div>
                </div>
                <div class="np-actions">
                    <?php if ($n['url']): ?>
                    <a href="<?= htmlspecialchars($n['url']) ?>" class="np-act" target="_blank">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        View
                    </a>
                    <?php endif; ?>
                    <?php if ($isUnread): ?>
                    <form method="POST" style="display:contents;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="mark_read">
                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                        <button type="submit" class="np-act">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Read
                        </button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" style="display:contents;" onsubmit="return confirm('Delete this notification?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                        <button type="submit" class="np-act del">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- ── Pagination ───────────────────────────────── -->
            <?php if ($totalPages > 1): ?>
            <div class="np-pager">
                <span class="np-pager-info">
                    Showing <?= $offset + 1 ?>–<?= min($total, $offset + $perPage) ?> of <?= $total ?>
                </span>
                <div class="np-pager-links">
                    <a href="<?= htmlspecialchars(npBuildUrl(['page' => $page - 1])) ?>" class="np-pager-a <?= $page <= 1 ? 'off' : '' ?>">‹</a>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="<?= htmlspecialchars(npBuildUrl(['page' => $i])) ?>" class="np-pager-a <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <a href="<?= htmlspecialchars(npBuildUrl(['page' => $page + 1])) ?>" class="np-pager-a <?= $page >= $totalPages ? 'off' : '' ?>">›</a>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

        </div><!-- /np-wrap -->
    </div><!-- /admin-content -->
</div>
</body>
</html>
