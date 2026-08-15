<?php
/**
 * Rosabella – Admin Notification Sync Helper
 * Shared between the API and the notifications page.
 * Syncs live data (orders, stock, reviews, users) into the admin_notifications table.
 * Zero inline CREATE TABLE / IF NOT EXISTS — table must exist (see database migration).
 */
if (!function_exists('syncAdminNotifications')) {

    function syncAdminNotifications(PDO $db): void
    {
        $events = _gatherAdminNotificationEvents($db);

        $stmt = $db->prepare("
            INSERT INTO admin_notifications (type, icon, title, body, priority, url, ref_id)
            SELECT ?, ?, ?, ?, ?, ?, ?
            WHERE NOT EXISTS (
                SELECT 1 FROM admin_notifications
                WHERE ref_id = ? AND DATE(created_at) = CURDATE()
            )
        ");

        foreach ($events as $e) {
            $stmt->execute([
                $e['type'], $e['icon'], $e['title'], $e['body'],
                $e['priority'], $e['url'], $e['ref_id'],
                $e['ref_id'],   // WHERE NOT EXISTS check
            ]);
        }
    }

    function _gatherAdminNotificationEvents(PDO $db): array
    {
        $events = [];

        // 1. Pending orders
        $pendingCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
        if ($pendingCount > 0) {
            $latest = $db->query("SELECT id, customer_name, total FROM orders WHERE status='pending' ORDER BY created_at DESC LIMIT 1")->fetch();
            $events[] = [
                'type' => 'order', 'icon' => 'order', 'priority' => 'high',
                'title' => $pendingCount === 1 ? '1 Pending Order' : "{$pendingCount} Pending Orders",
                'body'  => $latest ? "Latest: #{$latest['id']} — " . htmlspecialchars($latest['customer_name']) . " · Tk " . number_format($latest['total'], 2) : '',
                'url'   => BASE_URL . '/admin/orders?status=pending',
                'ref_id'=> 'pending_orders_' . $pendingCount,
            ];
        }

        // 2. New orders last 24h (non-pending)
        $newCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND status != 'pending'")->fetchColumn();
        if ($newCount > 0) {
            $latest = $db->query("SELECT id, customer_name FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND status != 'pending' ORDER BY created_at DESC LIMIT 1")->fetch();
            $events[] = [
                'type' => 'order', 'icon' => 'order', 'priority' => 'medium',
                'title' => $newCount === 1 ? '1 New Order (24h)' : "{$newCount} New Orders (24h)",
                'body'  => $latest ? "Latest: #{$latest['id']} — " . htmlspecialchars($latest['customer_name']) : '',
                'url'   => BASE_URL . '/admin/orders',
                'ref_id'=> 'new_orders_24h_' . $newCount,
            ];
        }

        // 3. Pending reviews
        try {
            $rCount = (int)$db->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();
            if ($rCount > 0) {
                $latestR = $db->query("SELECT id, rating, comment FROM reviews WHERE status='pending' ORDER BY created_at DESC LIMIT 1")->fetch();
                $events[] = [
                    'type' => 'review', 'icon' => 'review', 'priority' => 'medium',
                    'title' => $rCount === 1 ? '1 Pending Review' : "{$rCount} Pending Reviews",
                    'body'  => $latestR ? "★{$latestR['rating']} — " . mb_strimwidth($latestR['comment'] ?? '', 0, 60, '…') : '',
                    'url'   => BASE_URL . '/admin/reviews',
                    'ref_id'=> 'pending_reviews_' . $rCount,
                ];
            }
        } catch (Throwable $e) {}

        // 4. Low stock (≤5 units, still active)
        $lowCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 5 AND stock_quantity > 0 AND status='active'")->fetchColumn();
        if ($lowCount > 0) {
            $crit = $db->query("SELECT name, stock_quantity FROM products WHERE stock_quantity <= 5 AND stock_quantity > 0 AND status='active' ORDER BY stock_quantity ASC LIMIT 1")->fetch();
            $events[] = [
                'type' => 'stock', 'icon' => 'stock', 'priority' => 'high',
                'title' => $lowCount === 1 ? '1 Product Low on Stock' : "{$lowCount} Products Low on Stock",
                'body'  => $crit ? "\"{$crit['name']}\" — only {$crit['stock_quantity']} left" : '',
                'url'   => BASE_URL . '/admin/products?stock=low_stock',
                'ref_id'=> 'low_stock_' . $lowCount,
            ];
        }

        // 5. Out of stock
        $ooStock = (int)$db->query("SELECT COUNT(*) FROM products WHERE stock_quantity = 0 AND status='active'")->fetchColumn();
        if ($ooStock > 0) {
            $events[] = [
                'type' => 'stock', 'icon' => 'stock', 'priority' => 'high',
                'title' => $ooStock === 1 ? '1 Product Out of Stock' : "{$ooStock} Products Out of Stock",
                'body'  => 'These products are unavailable to customers',
                'url'   => BASE_URL . '/admin/products?stock=out_of_stock',
                'ref_id'=> 'out_of_stock_' . $ooStock,
            ];
        }

        // 6. New customers last 48h
        $newUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='customer' AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)")->fetchColumn();
        if ($newUsers > 0) {
            $latestU = $db->query("SELECT name, email FROM users WHERE role='customer' AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR) ORDER BY created_at DESC LIMIT 1")->fetch();
            $events[] = [
                'type' => 'user', 'icon' => 'user', 'priority' => 'low',
                'title' => $newUsers === 1 ? '1 New Customer' : "{$newUsers} New Customers",
                'body'  => $latestU ? htmlspecialchars($latestU['name']) . ' · ' . htmlspecialchars($latestU['email']) : '',
                'url'   => BASE_URL . '/admin/users',
                'ref_id'=> 'new_users_48h_' . $newUsers,
            ];
        }

        // 7. Problem orders last 7 days
        $probCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status IN ('cancelled','returned','fake','unreachable') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        if ($probCount > 0) {
            $events[] = [
                'type' => 'alert', 'icon' => 'alert', 'priority' => 'medium',
                'title' => $probCount === 1 ? '1 Problem Order (7d)' : "{$probCount} Problem Orders (7d)",
                'body'  => 'Cancelled, returned, fake, or unreachable orders need attention',
                'url'   => BASE_URL . '/admin/orders?status=cancelled',
                'ref_id'=> 'problem_orders_7d_' . $probCount,
            ];
        }

        return $events;
    }
}
