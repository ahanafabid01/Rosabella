<?php
/**
 * Rosabella - Admin Manual Order Create
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../includes/payment_gateway.php';

if (!isLoggedIn() || !isAdmin()) {
    if (isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$db = getDB();

if (!function_exists('generateUniqueOrderNumber')) {
    function generateUniqueOrderNumber(PDO $db): string
    {
        do {
            $candidate = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
            $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE order_number = ?");
            $stmt->execute([$candidate]);
            $exists = ((int)$stmt->fetchColumn()) > 0;
        } while ($exists);

        return $candidate;
    }
}

// ── AJAX Endpoint: Live Customer Search Checking BOTH Users & Orders ──────────
if (isset($_GET['action']) && $_GET['action'] === 'lookup_customer') {
    header('Content-Type: application/json');
    $q = trim($_GET['query'] ?? '');
    if (strlen($q) < 2) {
        echo json_encode(['results' => []]);
        exit;
    }

    $results = [];
    $seenPhones = [];
    $searchTerm = "%$q%";

    // 1. Search in Registered USERS Table
    try {
        $stmtUsers = $db->prepare("
            SELECT u.id, u.first_name, u.last_name, u.phone, u.email, u.address, u.city, u.upazila, u.postal_code,
                   (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id OR (u.phone IS NOT NULL AND u.phone != '' AND o.shipping_phone = u.phone)) as total_orders
            FROM users u
            WHERE (u.phone LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)
              AND ((u.phone IS NOT NULL AND u.phone != '') OR (u.email IS NOT NULL AND u.email != ''))
            ORDER BY total_orders DESC, u.id DESC
            LIMIT 8
        ");
        $stmtUsers->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        while ($user = $stmtUsers->fetch(PDO::FETCH_ASSOC)) {
            $phoneKey = trim($user['phone'] ?? '');
            if ($phoneKey) $seenPhones[$phoneKey] = true;

            $displayEmail = (strpos($user['email'], '@rosabella.local') === false) ? $user['email'] : '';

            $results[] = [
                'first_name'   => $user['first_name'] ?? '',
                'last_name'    => $user['last_name'] ?? '',
                'phone'        => $user['phone'] ?? '',
                'email'        => $displayEmail,
                'address'      => $user['address'] ?? '',
                'city'         => $user['city'] ?? '',
                'zone'         => $user['upazila'] ?? '',
                'area'         => $user['postal_code'] ?? '',
                'total_orders' => (int)$user['total_orders'],
                'source'       => 'User Account'
            ];
        }
    } catch (Exception $e) {}

    // 2. Search in ORDERS Table (Previous Customer Orders)
    try {
        $stmtOrders = $db->prepare("
            SELECT 
                shipping_first_name as first_name,
                shipping_last_name as last_name,
                shipping_phone as phone,
                shipping_email as email,
                shipping_address as address,
                shipping_city as city,
                shipping_upazila as upazila,
                shipping_postal_code as postal_code,
                COUNT(*) as total_orders
            FROM orders 
            WHERE (shipping_phone LIKE ? OR shipping_first_name LIKE ? OR shipping_last_name LIKE ? OR shipping_email LIKE ?) 
              AND shipping_phone IS NOT NULL AND shipping_phone != ''
            GROUP BY shipping_phone
            ORDER BY MAX(id) DESC
            LIMIT 8
        ");
        $stmtOrders->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        while ($order = $stmtOrders->fetch(PDO::FETCH_ASSOC)) {
            $phoneKey = trim($order['phone']);
            if (!empty($phoneKey) && !isset($seenPhones[$phoneKey])) {
                $seenPhones[$phoneKey] = true;
                $displayEmail = (strpos($order['email'] ?? '', '@rosabella.local') === false) ? ($order['email'] ?? '') : '';

                $results[] = [
                    'first_name'   => $order['first_name'] ?? '',
                    'last_name'    => $order['last_name'] ?? '',
                    'phone'        => $phoneKey,
                    'email'        => $displayEmail,
                    'address'      => $order['address'] ?? '',
                    'city'         => $order['city'] ?? '',
                    'zone'         => $order['upazila'] ?? '',
                    'area'         => $order['postal_code'] ?? '',
                    'total_orders' => (int)$order['total_orders'],
                    'source'       => 'Customer History'
                ];
            }
        }
    } catch (Exception $e) {}

    echo json_encode(['results' => $results]);
    exit;
}

$message = '';
$error = '';

// ── Form Submission Handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    $customerName   = sanitize($_POST['customer_name'] ?? '');
    $lastName       = sanitize($_POST['last_name'] ?? '');
    $phone          = sanitize($_POST['phone'] ?? '');
    $email          = sanitize($_POST['email'] ?? '');
    $city           = sanitize($_POST['city'] ?? '');
    $zone           = sanitize($_POST['zone'] ?? '');
    $area           = sanitize($_POST['area'] ?? '');
    $orderDate      = sanitize($_POST['order_date'] ?? date('Y-m-d'));
    $address        = sanitize($_POST['address'] ?? '');

    $orderNumber    = sanitize($_POST['order_number'] ?? '');
    $chkStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE order_number = ?");
    $chkStmt->execute([$orderNumber]);
    if (empty($orderNumber) || (int)$chkStmt->fetchColumn() > 0) {
        $orderNumber = generateUniqueOrderNumber($db);
    }

    $rawStatus      = sanitize($_POST['order_status'] ?? 'pending');
    $paymentMethod  = sanitize($_POST['payment_method'] ?? 'Cash on Delivery');

    $orderNote      = sanitize($_POST['order_notes'] ?? '');
    $staffNote      = sanitize($_POST['staff_note'] ?? '');
    $salesNote      = sanitize($_POST['sales_note'] ?? '');

    $notesParts = [];
    if (!empty($orderNote)) {
        $notesParts[] = "Delivery Note: " . $orderNote;
    }
    if (!empty($staffNote)) {
        $notesParts[] = "Staff Note: " . $staffNote;
    }
    if (!empty($salesNote)) {
        $notesParts[] = "Packing/Sales Note: " . $salesNote;
    }
    $combinedNotes = implode("\n", $notesParts);

    $tax            = floatval($_POST['tax'] ?? 0);
    $shippingCost   = floatval($_POST['shipping_cost'] ?? 0);
    $advancePayment = floatval($_POST['advance_payment'] ?? 0);
    $courierCharge  = floatval($_POST['courier_charge'] ?? 0);
    $discountVal    = floatval($_POST['discount_val'] ?? 0);
    $discountType   = sanitize($_POST['discount_type'] ?? 'fixed');
    $couponCode     = strtoupper(sanitize($_POST['applied_coupon_code'] ?? ''));

    $items          = $_POST['items'] ?? [];

    if (empty($customerName)) {
        $error = 'Customer First Name is required.';
    } elseif (empty($phone)) {
        $error = 'Phone Number is required.';
    } elseif (empty($address)) {
        $error = 'Full Address is required.';
    } elseif (empty($items) || !is_array($items)) {
        $error = 'Please add at least one product to the order.';
    } else {
        // Calculate items and totals
        $subtotal = 0;
        $validItems = [];

        foreach ($items as $item) {
            $productId = intval($item['product_id'] ?? 0);
            $qty = max(1, intval($item['quantity'] ?? 1));
            $price = max(0, floatval($item['price'] ?? 0));
            $itemTotal = $price * $qty;

            $subtotal += $itemTotal;

            $validItems[] = [
                'product_id'   => $productId,
                'product_name' => sanitize($item['product_name'] ?? 'Product'),
                'product_sku'  => sanitize($item['product_sku'] ?? ''),
                'size'         => sanitize($item['size'] ?? ''),
                'color'        => sanitize($item['color'] ?? ''),
                'variant'      => sanitize($item['variant'] ?? ''),
                'quantity'     => $qty,
                'price'        => $price,
                'total'        => $itemTotal,
            ];
        }

        // Calculate discount
        if ($discountType === 'percent') {
            $discount = round(($subtotal * ($discountVal / 100)), 2);
        } else {
            $discount = min($subtotal, $discountVal);
        }

        $afterDiscount = max(0, $subtotal - $discount);
        $grandTotal = $afterDiscount + $tax + $shippingCost;

        // Check if coupon exists in DB
        $couponId = null;
        if (!empty($couponCode)) {
            $cStmt = $db->prepare("SELECT id FROM coupons WHERE code = ? LIMIT 1");
            $cStmt->execute([$couponCode]);
            $couponId = $cStmt->fetchColumn() ?: null;
        }

        // Status mapping
        $orderStatus = 'pending';
        $paymentStatus = 'pending';

        if ($rawStatus === 'paid') {
            $orderStatus = 'processing';
            $paymentStatus = 'paid';
        } elseif (in_array($rawStatus, ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'], true)) {
            $orderStatus = $rawStatus;
            if ($rawStatus === 'delivered' || ($advancePayment >= $grandTotal && $grandTotal > 0)) {
                $paymentStatus = 'paid';
            }
        }

        try {
            $db->beginTransaction();

            // ── Auto-Save or Update Customer in Users Table ───────────────────
            $userId = null;
            $findUserStmt = $db->prepare("
                SELECT id FROM users 
                WHERE (phone IS NOT NULL AND phone != '' AND phone = ?) 
                   OR (email IS NOT NULL AND email != '' AND email = ?)
                LIMIT 1
            ");
            $findUserStmt->execute([$phone, ($email ?: '---')]);
            $existingUserId = $findUserStmt->fetchColumn();

            if ($existingUserId) {
                $userId = (int)$existingUserId;
                $updateUserStmt = $db->prepare("
                    UPDATE users SET 
                        first_name = COALESCE(NULLIF(first_name, ''), ?),
                        last_name = COALESCE(NULLIF(last_name, ''), ?),
                        address = COALESCE(NULLIF(address, ''), ?),
                        city = COALESCE(NULLIF(city, ''), ?),
                        upazila = COALESCE(NULLIF(upazila, ''), ?),
                        postal_code = COALESCE(NULLIF(postal_code, ''), ?)
                    WHERE id = ?
                ");
                $updateUserStmt->execute([
                    $customerName,
                    $lastName,
                    $address,
                    $city ?: ($zone ?: 'Dhaka'),
                    $zone ?: $area,
                    $area,
                    $userId
                ]);
            } else {
                $customerEmail = !empty($email) ? $email : ('cust_' . preg_replace('/[^0-9]/', '', $phone) . '@rosabella.local');
                $checkEmailStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $checkEmailStmt->execute([$customerEmail]);
                if ($checkEmailStmt->fetchColumn()) {
                    $customerEmail = 'cust_' . preg_replace('/[^0-9]/', '', $phone) . '_' . time() . '@rosabella.local';
                }

                $randomPass = bin2hex(random_bytes(8));
                $hashedPass = password_hash($randomPass, PASSWORD_DEFAULT);

                $insertUserStmt = $db->prepare("
                    INSERT INTO users (
                        first_name, last_name, email, password, phone, address, city, upazila, postal_code, country, role, status, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Bangladesh', 'customer', 'active', NOW()
                    )
                ");
                $insertUserStmt->execute([
                    $customerName,
                    $lastName,
                    $customerEmail,
                    $hashedPass,
                    $phone,
                    $address,
                    $city ?: ($zone ?: 'Dhaka'),
                    $zone ?: $area,
                    $area
                ]);
                $userId = (int)$db->lastInsertId();
            }

            // ── Insert Order Record ──────────────────────────────────────────
            $insertOrderStmt = $db->prepare("
                INSERT INTO orders (
                    user_id, order_number, status, subtotal, discount, coupon_id, shipping_cost, tax, advance_payment, total, 
                    payment_method, payment_status, shipping_first_name, shipping_last_name, shipping_email, 
                    shipping_phone, shipping_address, shipping_city, shipping_upazila, shipping_postal_code, 
                    shipping_country, order_notes, notes, delivery_method, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, 
                    'Bangladesh', ?, ?, 'Courier', ?
                )
            ");

            $createdAt = $orderDate . ' ' . date('H:i:s');

            $insertOrderStmt->execute([
                $userId,
                $orderNumber,
                $orderStatus,
                $subtotal,
                $discount,
                $couponId,
                $shippingCost,
                $tax,
                $advancePayment,
                $grandTotal,
                $paymentMethod,
                $paymentStatus,
                $customerName,
                $lastName,
                $email,
                $phone,
                $address,
                $city ?: ($zone ?: 'Dhaka'),
                $zone ?: $area,
                $area,
                $combinedNotes,
                $combinedNotes,
                $createdAt
            ]);

            $newOrderId = (int)$db->lastInsertId();

            $insertItemStmt = $db->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, product_sku, size, color, variant, quantity, price, total)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $updateStockStmt = $db->prepare("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?");

            foreach ($validItems as $vItem) {
                $insertItemStmt->execute([
                    $newOrderId,
                    $vItem['product_id'],
                    $vItem['product_name'],
                    $vItem['product_sku'],
                    $vItem['size'] ?: NULL,
                    $vItem['color'] ?: NULL,
                    $vItem['variant'] ?: NULL,
                    $vItem['quantity'],
                    $vItem['price'],
                    $vItem['total']
                ]);

                if ($vItem['product_id'] > 0) {
                    $updateStockStmt->execute([$vItem['quantity'], $vItem['product_id']]);
                }
            }

            $db->commit();
            header("Location: " . BASE_URL . "/admin/order/" . $newOrderId . "?created=1");
            exit;

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch all active products
$productsStmt = $db->query("
    SELECT id, name, sku, price, sale_price, stock_quantity, sizes, colors, variants, main_image 
    FROM products 
    WHERE status != 'inactive' 
    ORDER BY name ASC
");
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// Normalize products for JavaScript client
$productsJsonData = [];
foreach ($products as $p) {
    $effPrice = !empty($p['sale_price']) && $p['sale_price'] > 0 ? (float)$p['sale_price'] : (float)$p['price'];
    $imgSrc = !empty($p['main_image']) ? resolveAdminImageSrc($p['main_image']) : '';
    $productsJsonData[] = [
        'id'          => (int)$p['id'],
        'name'        => $p['name'],
        'sku'         => $p['sku'] ?? '',
        'price'       => $effPrice,
        'orig_price'  => (float)$p['price'],
        'has_sale'    => (!empty($p['sale_price']) && $p['sale_price'] > 0 && $p['sale_price'] < $p['price']),
        'stock'       => (int)$p['stock_quantity'],
        'image'       => $imgSrc,
        'sizes'       => $p['sizes'] ?? '',
        'colors'      => $p['colors'] ?? '',
        'variants'    => $p['variants'] ?? ''
    ];
}

// Fetch active coupons
$couponsStmt = $db->query("SELECT code, type, value, min_order_amount FROM coupons WHERE status = 'active'");
$coupons = $couponsStmt->fetchAll(PDO::FETCH_ASSOC);

$districts = ["Bagerhat", "Bandarban", "Barguna", "Barisal", "Bhola", "Bogura", "Brahmanbaria", "Chandpur", "Chapai Nawabganj", "Chattogram - City", "Chattogram - Suburb", "Chuadanga", "Cox's Bazar", "Cumilla", "Dhaka - City", "Dhaka - Suburb", "Dinajpur", "Faridpur", "Feni", "Gaibandha", "Gazipur - City", "Gazipur - Suburb", "Gopalganj", "Habiganj", "Jamalpur", "Jashore", "Jhalokati", "Jhenaidah", "Joypurhat", "Khagrachari", "Khulna - City", "Khulna - Suburb", "Kishoreganj", "Kurigram", "Kushtia", "Lakshmipur", "Lalmonirhat", "Madaripur", "Magura", "Manikganj", "Meherpur", "Moulvibazar", "Munshiganj", "Mymensingh", "Naogaon", "Narail", "Narayanganj", "Narsingdi", "Natore", "Netrokona", "Nilphamari", "Noakhali", "Pabna", "Panchagarh", "Patuakhali", "Pirojpur", "Rajbari", "Rajshahi - Suburb", "Rajshahi City", "Rangamati", "Rangpur - Suburb", "Rangpur City", "Satkhira", "Shariatpur", "Sherpur", "Sirajganj", "Sunamganj", "Sylhet", "Tangail", "Thakurgaon"];

$autoOrderNumber = generateUniqueOrderNumber($db);
$pageTitle = 'Order Create';
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
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        /* Table Cell Padding Override - Counteracts admin-table padding!important */
        #order-items-table th,
        #order-items-table td,
        .oc-table th,
        .oc-table td {
            padding: 10px 12px !important;
            white-space: normal !important;
            max-width: none !important;
            box-sizing: border-box !important;
        }
        #order-items-table thead th {
            white-space: nowrap !important;
        }

        /* Flex & Input Min-Width Containment */
        .oc-card *, .admin-card * {
            box-sizing: border-box !important;
        }
        .oc-form-group, .customer-search-box, .oc-prod-search-row {
            min-width: 0 !important;
            max-width: 100% !important;
        }
        .oc-input, .oc-select, .oc-textarea, .form-input, .form-select, .form-textarea {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            box-sizing: border-box !important;
        }
        .oc-prod-search-row > div {
            min-width: 0 !important;
            max-width: 100% !important;
        }

        /* Card Containment & Horizontal Scroll */
        .oc-card, .admin-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            margin-bottom: 1.5rem;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
        }

        .oc-table-wrap {
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch !important;
            display: block !important;
            box-sizing: border-box !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 10px !important;
            margin-bottom: 20px !important;
        }
        
        .oc-table-wrap::-webkit-scrollbar {
            height: 6px;
        }
        .oc-table-wrap::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        .oc-table-wrap::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .oc-table-wrap::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .oc-table, #order-items-table {
            min-width: 620px !important;
            width: 100% !important;
        }

        .oc-row-4 {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 16px !important;
            margin-bottom: 16px !important;
        }
        @media (max-width: 1024px) {
            .oc-row-4 { grid-template-columns: repeat(2, 1fr) !important; }
        }
        @media (max-width: 640px) {
            .oc-row-4 { grid-template-columns: 1fr !important; gap: 12px !important; margin-bottom: 12px !important; }
        }
        .oc-two-col-layout {
            display: grid !important;
            grid-template-columns: 1fr 370px !important;
            gap: 20px !important;
            align-items: start !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        .oc-two-col-layout > div {
            min-width: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        @media (max-width: 1100px) {
            .oc-two-col-layout { 
                grid-template-columns: 100% !important; 
                display: flex !important;
                flex-direction: column !important;
                gap: 16px !important;
                width: 100% !important;
                max-width: 100% !important;
                align-items: stretch !important;
            }
            .oc-summary-card {
                position: static !important;
                top: auto !important;
            }
        }
        .customer-search-box {
            background: #f8fafc;
            border: 1.5px dashed #cbd5e1;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            box-sizing: border-box;
            width: 100%;
        }
        .customer-search-box:focus-within {
            border-color: #0f766e;
            background: #f0fdfa;
        }
        .customer-search-input {
            flex: 1;
            border: none;
            background: transparent;
            font-size: 0.95rem;
            outline: none;
            color: #0f172a;
            min-width: 0;
        }
        .customer-search-input::placeholder {
            color: #94a3b8;
        }
        .oc-summary-row {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 12px !important;
            padding: 0.65rem 0 !important;
            border-bottom: 1px solid #f1f5f9 !important;
            font-size: 0.88rem !important;
            color: #475569 !important;
        }
        .oc-summary-row > span,
        .oc-summary-row > div:first-child {
            flex-shrink: 0 !important;
            white-space: nowrap !important;
        }
        .oc-summary-input {
            width: 100px !important;
            min-width: 100px !important;
            max-width: 100px !important;
            flex-shrink: 0 !important;
            flex-grow: 0 !important;
            text-align: right !important;
            padding: 0.35rem 0.55rem !important;
            font-size: 0.88rem !important;
            box-sizing: border-box !important;
        }
        @media (max-width: 768px) {
            .admin-content {
                padding: 0.75rem 0.5rem !important;
                box-sizing: border-box !important;
            }
            .oc-card, .admin-card {
                padding: 0.85rem 0.75rem !important;
                border-radius: 10px !important;
                margin-bottom: 1rem !important;
            }
            .customer-search-box {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 10px !important;
                padding: 10px 12px !important;
            }
            .customer-search-box button {
                width: 100% !important;
                justify-content: center !important;
                height: 38px !important;
            }
            .oc-prod-search-row {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 8px !important;
            }
            .oc-prod-search-row button {
                width: 100% !important;
                justify-content: center !important;
                height: 40px !important;
            }
            .oc-grid-2 {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }
            .oc-summary-row {
                padding: 0.55rem 0 !important;
                font-size: 0.84rem !important;
                gap: 8px !important;
            }
            .oc-summary-input {
                width: 90px !important;
                min-width: 90px !important;
                max-width: 90px !important;
                padding: 0.35rem 0.45rem !important;
                font-size: 0.85rem !important;
            }
            .oc-product-dropdown {
                max-height: 280px !important;
            }
            .oc-prod-item {
                padding: 8px 10px !important;
                gap: 8px !important;
            }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php renderAdminSidebar('order-create'); ?>

    <main class="admin-content">
        <?php renderAdminTopbar($pageTitle); ?>

        <div class="admin-header" style="margin-bottom: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <h1 class="admin-page-title" style="margin: 0; font-size: 1.35rem; font-weight: 700; color: #0f172a;">Order Create</h1>
            </div>
            <a href="<?= BASE_URL ?>/admin/orders" class="btn btn-outline" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back to Orders
            </a>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST" id="order-create-form" autocomplete="off">
            <?= csrfField() ?>
            <input type="hidden" name="applied_coupon_code" id="input-applied-coupon" value="">

            <!-- Card 1: Add New Order (Customer & Delivery Information) -->
            <div class="oc-card">
                <div class="oc-card-title" style="margin-bottom: 1rem;">
                    <span>Add New Order</span>
                </div>

                <!-- Search Customer by Phone/Name -->
                <div class="customer-search-box">
                    <div class="customer-search-input-wrap" style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; width: 100%;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0f766e" stroke-width="2.2" style="flex-shrink: 0;">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" id="top-customer-search" class="customer-search-input" placeholder="Search Customer / User by Phone or Name to auto-fill..." autocomplete="off">
                    </div>
                    <button type="button" id="btn-top-search" class="btn btn-primary btn-sm" style="padding: 0.4rem 1rem; border-radius: 6px; font-size: 0.85rem;">
                        Search Customer
                    </button>
                    
                    <!-- Top Search Dropdown -->
                    <div id="top-search-dropdown" class="oc-autocomplete-dropdown" style="display: none; top: calc(100% + 4px);"></div>
                </div>

                <!-- Autofill Success Badge Notice -->
                <div id="autofill-notice" class="oc-autofill-alert" style="display: none; margin-bottom: 1.25rem;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span id="autofill-text" style="font-weight: 500;">Customer loaded — details auto-filled!</span>
                    </div>
                    <button type="button" onclick="clearCustomerFields()" style="background: none; border: none; color: #065f46; font-weight: 700; cursor: pointer; text-decoration: underline; font-size: 0.82rem;">Clear / Reset</button>
                </div>
                
                <!-- Row 1: Customer Name, Last Name, Phone Number, Email Address -->
                <div class="oc-row-4">
                    <div class="oc-form-group">
                        <label class="oc-label">Customer Name <span class="req">*</span></label>
                        <input type="text" name="customer_name" id="cust-first-name" class="oc-input" placeholder="Enter First Name" required value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
                    </div>
                    <div class="oc-form-group">
                        <label class="oc-label">Last Name</label>
                        <input type="text" name="last_name" id="cust-last-name" class="oc-input" placeholder="Enter Last Name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                    </div>
                    <div class="oc-form-group" style="position: relative;">
                        <label class="oc-label">Phone Number <span class="req">*</span></label>
                        <input type="tel" name="phone" id="cust-phone" class="oc-input" placeholder="Enter Phone Number" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" autocomplete="off">
                        <div id="phone-autocomplete-dropdown" class="oc-autocomplete-dropdown" style="display: none;"></div>
                    </div>
                    <div class="oc-form-group">
                        <label class="oc-label">Email Address</label>
                        <input type="email" name="email" id="cust-email" class="oc-input" placeholder="Enter Email Address" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <!-- Row 2: Recipient City (District), Recipient Zone, Recipient Area, Date -->
                <div class="oc-row-4" style="margin-top: 16px;">
                    <div class="oc-form-group">
                        <label class="oc-label">Recipient City (District)</label>
                        <select name="city" id="cust-city" class="oc-select">
                            <option value="">Select District</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= htmlspecialchars($d) ?>" <?= (isset($_POST['city']) && $_POST['city'] === $d) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="oc-form-group">
                        <label class="oc-label">Recipient Zone</label>
                        <input type="text" name="zone" id="cust-zone" class="oc-input" placeholder="Enter Zone" value="<?= htmlspecialchars($_POST['zone'] ?? '') ?>">
                    </div>
                    <div class="oc-form-group">
                        <label class="oc-label">Recipient Area</label>
                        <input type="text" name="area" id="cust-area" class="oc-input" placeholder="Enter Area" value="<?= htmlspecialchars($_POST['area'] ?? '') ?>">
                    </div>
                    <div class="oc-form-group">
                        <label class="oc-label">Date</label>
                        <input type="date" name="order_date" class="oc-input" value="<?= htmlspecialchars($_POST['order_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>

                <!-- Row 3: Address -->
                <div class="oc-form-group" style="margin-top: 16px;">
                    <label class="oc-label">Address <span class="req">*</span></label>
                    <input type="text" name="address" id="cust-address" class="oc-input" placeholder="Enter Full Address" required value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                </div>
            </div>

            <!-- Two-Column Section: Listed Products & Summary -->
            <div class="oc-two-col-layout">
                
                <!-- Left Column: Listed Product & Order Notes -->
                <div>
                    <div class="oc-card">
                        <div class="oc-card-title">
                            <span>Listed Product</span>
                        </div>

                        <!-- Professional Live Product Search Bar -->
                        <div class="oc-form-group" style="margin-bottom: 16px; position: relative;">
                            <label class="oc-label">Search & Add Product</label>
                            
                            <div class="oc-prod-search-row" style="display: flex; gap: 8px; align-items: center;">
                                <div style="position: relative; flex: 1; min-width: 0;">
                                    <div style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #64748b; pointer-events: none;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                    </div>
                                    <input type="text" id="product-live-search" class="oc-input" placeholder="Search product by title, SKU, or keyword..." style="padding-left: 38px; height: 44px; font-size: 0.92rem;" autocomplete="off">
                                    <button type="button" id="btn-clear-prod-input" style="display: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 1.2rem; color: #94a3b8; cursor: pointer;">&times;</button>
                                </div>
                                
                                <button type="button" id="btn-show-all-prods" class="btn btn-outline" style="height: 44px; padding: 0 16px; white-space: nowrap; font-size: 0.85rem; font-weight: 600; border-radius: 8px;">
                                    Browse All (<?= count($products) ?>)
                                </button>
                            </div>

                            <!-- Live Product Search Results Floating Dropdown -->
                            <div id="product-search-dropdown" class="oc-product-dropdown" style="display: none;"></div>
                        </div>

                        <!-- Dynamic Listed Products Table -->
                        <div class="oc-table-wrap">
                            <table class="oc-table" id="order-items-table">
                                <thead id="order-items-head">
                                    <tr>
                                        <th style="min-width: 200px;">PRODUCT</th>
                                        <th style="width: 110px;" class="th-right">PRICE</th>
                                        <th style="width: 80px;" class="th-center">QUANTITY</th>
                                        <th style="width: 120px;" class="th-right">SUB TOTAL</th>
                                        <th style="width: 40px;" class="th-center"></th>
                                    </tr>
                                </thead>
                                <tbody id="order-items-body">
                                    <tr id="empty-products-row">
                                        <td colspan="5" class="oc-empty-state">
                                            <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                                <span>No products selected. Search or browse products above to add items.</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Order Status & Payment Method -->
                        <div class="oc-grid-2">
                            <div class="oc-form-group">
                                <label class="oc-label">Order Status</label>
                                <select name="order_status" class="oc-select">
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="paid">Paid</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="oc-form-group">
                                <label class="oc-label">Payment Method</label>
                                <select name="payment_method" class="oc-select oc-highlight-border">
                                    <option value="Cash on Delivery">Cash on Delivery</option>
                                    <option value="bKash">bKash</option>
                                    <option value="Nagad">Nagad</option>
                                    <option value="Rocket">Rocket</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="POS / Card">POS / Card</option>
                                </select>
                            </div>
                        </div>

                        <!-- Order Notes Grid -->
                        <div class="oc-grid-2" style="margin-top: 16px;">
                            <div class="oc-form-group">
                                <label class="oc-label">Order Note/Delivery Note</label>
                                <textarea name="order_notes" class="oc-textarea" rows="3" placeholder="Enter Order/Delivery Note"><?= htmlspecialchars($_POST['order_notes'] ?? '') ?></textarea>
                            </div>
                            <div class="oc-form-group">
                                <label class="oc-label">Staff Note</label>
                                <textarea name="staff_note" class="oc-textarea" rows="3" placeholder="Enter Staff Note"><?= htmlspecialchars($_POST['staff_note'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <!-- Packing/Sales Note -->
                        <div class="oc-form-group" style="margin-top: 16px;">
                            <label class="oc-label">Packing Note /Sales Note</label>
                            <textarea name="sales_note" class="oc-textarea" rows="2" placeholder="Enter Packing/Sales Note"><?= htmlspecialchars($_POST['sales_note'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Summary Card -->
                <div>
                    <div class="oc-card oc-summary-card">
                        <div class="oc-card-title">
                            <span>Summary</span>
                        </div>

                        <div class="oc-form-group" style="margin-bottom: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <label class="oc-label" style="margin-bottom: 0;">Reference no</label>
                                <span style="font-size: 0.72rem; color: #64748b; background: #f1f5f9; padding: 2px 7px; border-radius: 4px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    Auto
                                </span>
                            </div>
                            <input type="text" name="order_number" class="oc-input" readonly value="<?= htmlspecialchars($_POST['order_number'] ?? $autoOrderNumber) ?>" style="background: #f8fafc; color: #334155; font-weight: 700; cursor: not-allowed; border: 1.5px solid #e2e8f0; font-family: monospace; font-size: 0.95rem; letter-spacing: 0.02em;" title="Auto-generated reference number (Unchangeable)">
                        </div>

                        <!-- Sub Total -->
                        <div class="oc-summary-row">
                            <span>Sub Total (TK) :</span>
                            <strong id="display-subtotal" style="font-size: 1rem; color: #0f172a;">$0</strong>
                        </div>

                        <!-- Discount / Less -->
                        <div class="oc-summary-row">
                            <div style="display: flex; align-items: center;">
                                <span>Discount/ Less (Tk) :</span>
                                <div class="oc-toggle-pill">
                                    <button type="button" id="btn-toggle-fixed" class="oc-toggle-btn active" onclick="setDiscountType('fixed')">TK</button>
                                    <button type="button" id="btn-toggle-percent" class="oc-toggle-btn" onclick="setDiscountType('percent')">%</button>
                                </div>
                                <input type="hidden" name="discount_type" id="input-discount-type" value="fixed">
                            </div>
                            <input type="number" step="0.01" min="0" name="discount_val" id="input-discount" class="oc-input oc-summary-input" value="0">
                        </div>

                        <!-- After discount -->
                        <div class="oc-summary-row">
                            <span>After discount (Tk) :</span>
                            <span id="display-after-discount" style="font-weight: 600;">$0</span>
                        </div>

                        <!-- Tax -->
                        <div class="oc-summary-row">
                            <span>Tax (Tk) :</span>
                            <input type="number" step="0.01" min="0" name="tax" id="input-tax" class="oc-input oc-summary-input" value="0">
                        </div>

                        <!-- Grand Total -->
                        <div class="oc-summary-row total-row">
                            <span>Grand Total (Tk) :</span>
                            <span id="display-grand-total" style="color: #10b981;">$0</span>
                        </div>

                        <!-- Shipping -->
                        <div class="oc-summary-row">
                            <span>Shipping (Tk) :</span>
                            <input type="number" step="0.01" min="0" name="shipping_cost" id="input-shipping" class="oc-input oc-summary-input" value="0">
                        </div>

                        <!-- Advance Payment -->
                        <div class="oc-summary-row">
                            <span>Advance Payment (Tk) :</span>
                            <input type="number" step="0.01" min="0" name="advance_payment" id="input-advance" class="oc-input oc-summary-input" value="0">
                        </div>

                        <!-- Due -->
                        <div class="oc-summary-row due-row">
                            <span>Due (Tk) :</span>
                            <span id="display-due">$0</span>
                        </div>

                        <!-- Courier Charged to me -->
                        <div class="oc-summary-row" style="border-bottom: none;">
                            <span>Courier Charged to me :</span>
                            <input type="number" step="0.01" min="0" name="courier_charge" id="input-courier" class="oc-input oc-summary-input" value="0">
                        </div>

                        <!-- Apply Coupon Trigger -->
                        <div style="text-align: right; margin-top: 8px; margin-bottom: 20px;">
                            <a href="javascript:void(0)" class="coupon-link-toggle" onclick="toggleCouponInput()" style="color: #10b981; font-weight: 600; font-size: 0.85rem; text-decoration: none;">Apply Coupon</a>
                            <div id="coupon-field-wrap" style="display: none; margin-top: 10px;">
                                <div style="display: flex; gap: 6px;">
                                    <input type="text" id="coupon-code-input" class="oc-input" placeholder="Coupon Code" style="text-transform: uppercase; font-size: 0.85rem; padding: 0.4rem 0.6rem;">
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="applyCouponCode()">Apply</button>
                                </div>
                                <div id="coupon-message" style="font-size: 0.8rem; margin-top: 4px; text-align: left;"></div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.85rem; font-size: 1rem; font-weight: 600; border-radius: 8px; box-shadow: 0 4px 12px rgba(15, 118, 110, 0.2);">
                            Create Order
                        </button>
                    </div>
                </div>

            </div>
        </form>
    </main>
</div>

<script src="js/admin.js"></script>
<script>
    var allProducts = <?= json_encode($productsJsonData) ?>;
    var availableCoupons = <?= json_encode($coupons) ?>;
    var itemCount = 0;
    var currentDiscountType = 'fixed';
    var lookupTimer = null;
    var prodSearchTimer = null;

    document.addEventListener('DOMContentLoaded', function() {
        // Summary Input recalculation events
        ['input-discount', 'input-tax', 'input-shipping', 'input-advance', 'input-courier'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', recalculateTotals);
            }
        });

        // Form submit validation
        document.getElementById('order-create-form').addEventListener('submit', function(e) {
            var rows = document.querySelectorAll('#order-items-body tr:not(#empty-products-row)');
            if (rows.length === 0) {
                e.preventDefault();
                alert('Please select and add at least one product to the order.');
            }
        });

        // ── Professional Live Product Search Setup ─────────────────────────────
        var prodSearchInput = document.getElementById('product-live-search');
        var prodDropdown = document.getElementById('product-search-dropdown');
        var btnBrowseAll = document.getElementById('btn-show-all-prods');
        var btnClearProd = document.getElementById('btn-clear-prod-input');

        prodSearchInput.addEventListener('input', function() {
            var val = this.value.trim();
            btnClearProd.style.display = val.length > 0 ? 'block' : 'none';
            clearTimeout(prodSearchTimer);
            if (val.length === 0) {
                prodDropdown.style.display = 'none';
                return;
            }
            prodSearchTimer = setTimeout(function() {
                filterAndRenderProducts(val);
            }, 100);
        });

        prodSearchInput.addEventListener('focus', function() {
            if (this.value.trim().length > 0) {
                filterAndRenderProducts(this.value.trim());
            }
        });

        btnBrowseAll.addEventListener('click', function(e) {
            e.stopPropagation();
            if (prodDropdown.style.display === 'block') {
                prodDropdown.style.display = 'none';
            } else {
                renderProductDropdownItems(allProducts);
                prodSearchInput.focus();
            }
        });

        btnClearProd.addEventListener('click', function() {
            prodSearchInput.value = '';
            btnClearProd.style.display = 'none';
            prodDropdown.style.display = 'none';
            prodSearchInput.focus();
        });

        // ── Customer Lookup & Search Setup ─────────────────────────────────────
        var topSearchInput = document.getElementById('top-customer-search');
        var topDropdown = document.getElementById('top-search-dropdown');
        var btnTopSearch = document.getElementById('btn-top-search');
        var phoneInput = document.getElementById('cust-phone');
        var phoneDropdown = document.getElementById('phone-autocomplete-dropdown');

        // Top Search Bar Events
        topSearchInput.addEventListener('input', function() {
            var val = this.value.trim();
            clearTimeout(lookupTimer);
            if (val.length < 2) {
                topDropdown.style.display = 'none';
                return;
            }
            lookupTimer = setTimeout(function() {
                performCustomerSearch(val, topDropdown);
            }, 200);
        });

        btnTopSearch.addEventListener('click', function() {
            var val = topSearchInput.value.trim();
            if (!val) {
                alert('Please type a phone number or name to search.');
                topSearchInput.focus();
                return;
            }
            performCustomerSearch(val, topDropdown);
        });

        // Phone Input Events
        phoneInput.addEventListener('input', function() {
            var val = this.value.trim();
            clearTimeout(lookupTimer);
            if (val.length < 3) {
                phoneDropdown.style.display = 'none';
                return;
            }
            lookupTimer = setTimeout(function() {
                performCustomerSearch(val, phoneDropdown);
            }, 200);
        });

        // Dismiss dropdowns on outside click
        document.addEventListener('click', function(e) {
            if (!topSearchInput.contains(e.target) && !topDropdown.contains(e.target) && e.target !== btnTopSearch) {
                topDropdown.style.display = 'none';
            }
            if (!phoneInput.contains(e.target) && !phoneDropdown.contains(e.target)) {
                phoneDropdown.style.display = 'none';
            }
            if (!prodSearchInput.contains(e.target) && !prodDropdown.contains(e.target) && e.target !== btnBrowseAll) {
                prodDropdown.style.display = 'none';
            }
        });
    });

    // ── Product Live Search Functions ──────────────────────────────────────────
    function filterAndRenderProducts(query) {
        var q = query.toLowerCase();
        var matches = allProducts.filter(function(p) {
            return p.name.toLowerCase().includes(q) || 
                   (p.sku && p.sku.toLowerCase().includes(q)) ||
                   p.price.toString().includes(q);
        });
        renderProductDropdownItems(matches, query);
    }

    function renderProductDropdownItems(items, query) {
        var dropdown = document.getElementById('product-search-dropdown');
        dropdown.innerHTML = '';

        if (!items || items.length === 0) {
            dropdown.innerHTML = '<div style="padding: 2rem; text-align: center; color: #94a3b8; font-size: 0.9rem;">No products found matching "<strong>' + escapeHtml(query || '') + '</strong>"</div>';
            dropdown.style.display = 'block';
            return;
        }

        // Header bar
        var headerEl = document.createElement('div');
        headerEl.style.cssText = 'padding: 8px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 0.78rem; font-weight: 600; color: #64748b; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10;';
        headerEl.innerHTML = '<span>Available Products (' + items.length + ')</span><span style="font-weight:400; color:#94a3b8;">Click item to add</span>';
        dropdown.appendChild(headerEl);

        items.forEach(function(p) {
            var el = document.createElement('div');
            el.className = 'oc-prod-item';
            el.style.cssText = 'padding: 10px 14px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; cursor: pointer; gap: 14px; background: #ffffff; transition: background 0.15s;';

            var imgTag = p.image 
                ? '<img src="' + escapeHtml(p.image) + '" alt="" style="width: 48px !important; height: 48px !important; min-width: 48px !important; max-width: 48px !important; object-fit: contain !important; border-radius: 8px !important; border: 1px solid #e2e8f0 !important; background: #f8fafc !important; padding: 2px !important; flex-shrink: 0 !important;">' 
                : '<div style="width: 48px !important; height: 48px !important; min-width: 48px !important; max-width: 48px !important; background: #f1f5f9; border-radius: 8px; border: 1px solid #e2e8f0; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:0.75rem; flex-shrink: 0 !important;">No Img</div>';

            var stockBadge = p.stock > 10 
                ? '<span style="color:#059669; font-weight:600; font-size: 0.78rem;">✓ In Stock: ' + p.stock + '</span>'
                : (p.stock > 0 ? '<span style="color:#d97706; font-weight:600; font-size: 0.78rem;">Low Stock: ' + p.stock + '</span>' : '<span style="color:#dc2626; font-weight:600; font-size: 0.78rem;">Out of Stock</span>');

            var priceDisplay = 'Tk ' + p.price.toFixed(2);
            if (p.has_sale) {
                priceDisplay += ' <span style="font-size:0.8rem; color:#94a3b8; text-decoration:line-through; font-weight:400;">Tk ' + p.orig_price.toFixed(2) + '</span>';
            }

            el.innerHTML = 
                '<div style="display:flex; align-items:center; gap:12px; flex:1; min-width:0;">' +
                    imgTag +
                    '<div style="flex:1; min-width:0;">' +
                        '<div style="font-weight:600; color:#0f172a; font-size:0.92rem; line-height:1.3; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' + escapeHtml(p.name) + '</div>' +
                        '<div style="font-size:0.78rem; color:#64748b; margin-top:3px; display:flex; gap:8px; align-items:center;">' +
                            (p.sku ? '<span>SKU: <strong>' + escapeHtml(p.sku) + '</strong></span> • ' : '') +
                            stockBadge +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div style="display:flex; align-items:center; gap:12px; flex-shrink:0;">' +
                    '<span style="font-weight:700; color:#0f766e; font-size:0.95rem;">' + priceDisplay + '</span>' +
                    '<button type="button" class="oc-prod-add-btn" style="background:#ff5722; color:#fff; border:none; border-radius:6px; padding:6px 12px; font-size:0.8rem; font-weight:600; cursor:pointer; box-shadow: 0 2px 4px rgba(255, 87, 34, 0.25);">+ Add</button>' +
                '</div>';

            el.addEventListener('mouseenter', function() {
                this.style.background = '#f0fdfa';
            });
            el.addEventListener('mouseleave', function() {
                this.style.background = '#ffffff';
            });

            el.addEventListener('click', function(e) {
                e.preventDefault();
                addProductRow(p);
                dropdown.style.display = 'none';
                document.getElementById('product-live-search').value = '';
                document.getElementById('btn-clear-prod-input').style.display = 'none';
            });

            dropdown.appendChild(el);
        });

        dropdown.style.display = 'block';
    }

    // ── Customer Search Functions ──────────────────────────────────────────────
    function performCustomerSearch(query, dropdownEl) {
        fetch('order-create.php?action=lookup_customer&query=' + encodeURIComponent(query))
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.results && data.results.length > 0) {
                    renderSearchResults(data.results, dropdownEl);
                } else {
                    dropdownEl.innerHTML = '<div style="padding: 1rem; color: #94a3b8; text-align: center; font-size: 0.85rem;">No previous customer/user found for "<strong>' + escapeHtml(query) + '</strong>"</div>';
                    dropdownEl.style.display = 'block';
                }
            })
            .catch(function(err) {
                dropdownEl.style.display = 'none';
            });
    }

    function renderSearchResults(results, dropdownEl) {
        dropdownEl.innerHTML = '';

        results.forEach(function(item) {
            var el = document.createElement('div');
            el.className = 'oc-autocomplete-item';
            
            var fullName = (item.first_name + ' ' + (item.last_name || '')).trim() || 'Customer';
            var location = (item.city ? item.city : '') + (item.zone ? ', ' + item.zone : '');
            var sourceTag = (item.source === 'User Account') 
                ? '<span class="oc-badge-orders" style="background:#fef3c7; color:#b45309; margin-right:4px;">User Account</span>' 
                : '';
            var orderBadge = item.total_orders > 0 
                ? '<span class="oc-badge-orders">' + item.total_orders + ' past orders</span>' 
                : '';

            el.innerHTML = 
                '<div>' +
                    '<div class="oc-autocomplete-name">' + escapeHtml(fullName) + ' <span style="font-weight:600; color:#0f766e;">(' + escapeHtml(item.phone) + ')</span></div>' +
                    '<div class="oc-autocomplete-sub">' + (location ? escapeHtml(location) + ' • ' : '') + escapeHtml(item.address || 'No saved address') + '</div>' +
                '</div>' +
                '<div style="display:flex; gap:4px; align-items:center;">' + sourceTag + orderBadge + '</div>';

            el.addEventListener('mousedown', function(e) {
                e.preventDefault();
                autofillCustomer(item);
                dropdownEl.style.display = 'none';
                var topInput = document.getElementById('top-customer-search');
                if (topInput) topInput.value = item.phone + ' - ' + fullName;
            });

            dropdownEl.appendChild(el);
        });

        dropdownEl.style.display = 'block';
    }

    function autofillCustomer(data) {
        if (data.first_name) document.getElementById('cust-first-name').value = data.first_name;
        if (data.last_name) document.getElementById('cust-last-name').value = data.last_name;
        if (data.phone) document.getElementById('cust-phone').value = data.phone;
        if (data.email) document.getElementById('cust-email').value = data.email;
        if (data.address) document.getElementById('cust-address').value = data.address;
        if (data.zone) document.getElementById('cust-zone').value = data.zone;
        if (data.area) document.getElementById('cust-area').value = data.area;

        // Select city/district if matches
        if (data.city) {
            var citySelect = document.getElementById('cust-city');
            for (var i = 0; i < citySelect.options.length; i++) {
                if (citySelect.options[i].value.toLowerCase() === data.city.toLowerCase() ||
                    citySelect.options[i].value.toLowerCase().includes(data.city.toLowerCase())) {
                    citySelect.selectedIndex = i;
                    break;
                }
            }
        }

        // Show alert badge
        var notice = document.getElementById('autofill-notice');
        var noticeText = document.getElementById('autofill-text');
        var fullName = (data.first_name + ' ' + (data.last_name || '')).trim();
        var orderCount = data.total_orders || 0;
        var src = data.source || 'Database';

        noticeText.textContent = 'Customer loaded from ' + src + ': ' + fullName + ' (' + data.phone + ')' + (orderCount > 0 ? ' • ' + orderCount + ' past orders' : '') + ' — details auto-filled!';
        notice.style.display = 'flex';
    }

    function clearCustomerFields() {
        document.getElementById('cust-first-name').value = '';
        document.getElementById('cust-last-name').value = '';
        document.getElementById('cust-phone').value = '';
        document.getElementById('cust-email').value = '';
        document.getElementById('cust-city').selectedIndex = 0;
        document.getElementById('cust-zone').value = '';
        document.getElementById('cust-area').value = '';
        document.getElementById('cust-address').value = '';
        var topInput = document.getElementById('top-customer-search');
        if (topInput) topInput.value = '';
        document.getElementById('autofill-notice').style.display = 'none';
    }

    // ── Listed Products Table Operations ───────────────────────────────────────
    var selectedProducts = [];

    function updateTableColumns() {
        var anySize = selectedProducts.some(function(item) {
            return item.data.sizes && item.data.sizes.trim().length > 0;
        });
        var anyColor = selectedProducts.some(function(item) {
            return item.data.colors && item.data.colors.trim().length > 0;
        });
        var anyVariant = selectedProducts.some(function(item) {
            return item.data.variants && item.data.variants.trim().length > 0;
        });

        // Update Header
        var headRow = document.querySelector('#order-items-head tr');
        if (headRow) {
            var cols = '<th style="min-width: 200px;">PRODUCT</th>';
            if (anySize) cols += '<th style="width: 115px;">SIZE</th>';
            if (anyColor) cols += '<th style="width: 115px;">COLOR</th>';
            if (anyVariant) cols += '<th style="width: 115px;">VARIANT</th>';
            cols += '<th style="width: 110px;" class="th-right">PRICE</th>';
            cols += '<th style="width: 80px;" class="th-center">QUANTITY</th>';
            cols += '<th style="width: 120px;" class="th-right">SUB TOTAL</th>';
            cols += '<th style="width: 40px;" class="th-center"></th>';
            headRow.innerHTML = cols;
        }

        var totalCols = 4 + (anySize ? 1 : 0) + (anyColor ? 1 : 0) + (anyVariant ? 1 : 0);
        var emptyTd = document.querySelector('#empty-products-row td');
        if (emptyTd) {
            emptyTd.colSpan = totalCols;
        }

        // Update Rows
        selectedProducts.forEach(function(item) {
            var row = document.getElementById('item-row-' + item.index);
            if (!row) return;

            // Size cell
            var tdSize = row.querySelector('.col-size');
            if (anySize) {
                if (!tdSize) {
                    tdSize = document.createElement('td');
                    tdSize.className = 'col-size';
                    row.insertBefore(tdSize, row.children[1]);
                }
                tdSize.innerHTML = renderSizeSelect(item.index, item.data);
            } else if (tdSize) {
                tdSize.remove();
            }

            // Color cell
            var tdColor = row.querySelector('.col-color');
            if (anyColor) {
                if (!tdColor) {
                    tdColor = document.createElement('td');
                    tdColor.className = 'col-color';
                    var priceTd = row.querySelector('.col-price');
                    row.insertBefore(tdColor, priceTd);
                }
                tdColor.innerHTML = renderColorSelect(item.index, item.data);
            } else if (tdColor) {
                tdColor.remove();
            }

            // Variant cell
            var tdVariant = row.querySelector('.col-variant');
            if (anyVariant) {
                if (!tdVariant) {
                    tdVariant = document.createElement('td');
                    tdVariant.className = 'col-variant';
                    var priceTd = row.querySelector('.col-price');
                    row.insertBefore(tdVariant, priceTd);
                }
                tdVariant.innerHTML = renderVariantSelect(item.index, item.data);
            } else if (tdVariant) {
                tdVariant.remove();
            }
        });
    }

    function renderSizeSelect(index, data) {
        if (!data.sizes || !data.sizes.trim()) {
            return '<span style="color:#cbd5e1; font-size:0.85rem;">-</span>';
        }
        var sizeList = data.sizes.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
        if (sizeList.length === 0) return '<span style="color:#cbd5e1; font-size:0.85rem;">-</span>';
        
        var html = '<select name="items[' + index + '][size]" class="oc-select" style="font-size:0.82rem; padding: 0.3rem 0.5rem; min-height: 32px;">';
        html += '<option value="">Size</option>';
        sizeList.forEach(function(sz) {
            html += '<option value="' + escapeHtml(sz) + '">' + escapeHtml(sz) + '</option>';
        });
        html += '</select>';
        return html;
    }

    function renderColorSelect(index, data) {
        if (!data.colors || !data.colors.trim()) {
            return '<span style="color:#cbd5e1; font-size:0.85rem;">-</span>';
        }
        var colorList = [];
        try {
            var parsed = JSON.parse(data.colors);
            if (typeof parsed === 'object') {
                colorList = Object.keys(parsed);
            }
        } catch(e) {
            colorList = data.colors.split(',').map(function(c) { return c.split(':')[0].trim(); }).filter(Boolean);
        }
        if (colorList.length === 0) return '<span style="color:#cbd5e1; font-size:0.85rem;">-</span>';

        var html = '<select name="items[' + index + '][color]" class="oc-select" style="font-size:0.82rem; padding: 0.3rem 0.5rem; min-height: 32px;">';
        html += '<option value="">Color</option>';
        colorList.forEach(function(cl) {
            html += '<option value="' + escapeHtml(cl) + '">' + escapeHtml(cl) + '</option>';
        });
        html += '</select>';
        return html;
    }

    function renderVariantSelect(index, data) {
        if (!data.variants || !data.variants.trim()) {
            return '<span style="color:#cbd5e1; font-size:0.85rem;">-</span>';
        }
        var varList = data.variants.split(',').map(function(v) { return v.trim(); }).filter(Boolean);
        if (varList.length === 0) return '<span style="color:#cbd5e1; font-size:0.85rem;">-</span>';

        var html = '<select name="items[' + index + '][variant]" class="oc-select" style="font-size:0.82rem; padding: 0.3rem 0.5rem; min-height: 32px;">';
        html += '<option value="">Variant</option>';
        varList.forEach(function(vt) {
            html += '<option value="' + escapeHtml(vt) + '">' + escapeHtml(vt) + '</option>';
        });
        html += '</select>';
        return html;
    }

    function addProductRow(data) {
        var emptyRow = document.getElementById('empty-products-row');
        if (emptyRow) {
            emptyRow.style.display = 'none';
        }

        itemCount++;
        var index = itemCount;
        selectedProducts.push({ index: index, data: data });
        var tbody = document.getElementById('order-items-body');

        var tr = document.createElement('tr');
        tr.id = 'item-row-' + index;

        var imgTag = data.image 
            ? '<img src="' + escapeHtml(data.image) + '" alt="" style="width: 44px !important; height: 44px !important; min-width: 44px !important; max-width: 44px !important; object-fit: contain !important; border-radius: 6px !important; border: 1px solid #e2e8f0 !important; background: #f8fafc !important; padding: 2px !important; flex-shrink: 0 !important;">' 
            : '<div style="width: 44px !important; height: 44px !important; min-width: 44px !important; max-width: 44px !important; background: #f1f5f9; border-radius: 6px; border: 1px solid #e2e8f0; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:0.7rem; flex-shrink: 0 !important;">Item</div>';

        var priceVal = typeof data.price === 'number' ? data.price : parseFloat(data.price);

        tr.innerHTML = 
            '<td>' +
                '<div style="display: flex; align-items: center; gap: 10px;">' +
                    imgTag +
                    '<div>' +
                        '<div style="font-weight: 600; color: #0f172a; line-height: 1.25;">' + escapeHtml(data.name) + '</div>' +
                        (data.sku ? '<div style="font-size: 0.75rem; color: #64748b;">SKU: ' + escapeHtml(data.sku) + '</div>' : '') +
                    '</div>' +
                '</div>' +
                '<input type="hidden" name="items[' + index + '][product_id]" value="' + data.id + '">' +
                '<input type="hidden" name="items[' + index + '][product_name]" value="' + escapeHtml(data.name) + '">' +
                '<input type="hidden" name="items[' + index + '][product_sku]" value="' + escapeHtml(data.sku) + '">' +
            '</td>' +
            '<td style="text-align: right;" class="col-price">' +
                '<span class="row-unit-price" style="font-weight: 600; color: #334155; font-size: 0.9rem; white-space: nowrap;">Tk ' + priceVal.toFixed(2) + '</span>' +
                '<input type="hidden" name="items[' + index + '][price]" class="row-price-input" value="' + priceVal.toFixed(2) + '">' +
            '</td>' +
            '<td style="text-align: center;" class="col-qty">' +
                '<input type="number" min="1" max="' + (data.stock || 100) + '" name="items[' + index + '][quantity]" class="oc-table-input row-qty" value="1" style="width: 70px; text-align: center;" oninput="recalculateRow(' + index + ')">' +
            '</td>' +
            '<td style="text-align: right;" class="col-subtotal">' +
                '<div class="row-subtotal" style="font-weight: 700; color: #0f766e; font-size: 0.95rem; white-space: nowrap;">Tk ' + priceVal.toFixed(2) + '</div>' +
            '</td>' +
            '<td style="text-align: center;" class="col-action">' +
                '<button type="button" class="oc-btn-remove" onclick="removeProductRow(' + index + ')" title="Remove item">&times;</button>' +
            '</td>';

        tbody.appendChild(tr);
        updateTableColumns();
        recalculateTotals();
    }

    function removeProductRow(index) {
        var tr = document.getElementById('item-row-' + index);
        if (tr) {
            tr.remove();
        }

        selectedProducts = selectedProducts.filter(function(item) {
            return item.index !== index;
        });

        updateTableColumns();

        if (selectedProducts.length === 0) {
            var emptyRow = document.getElementById('empty-products-row');
            if (emptyRow) {
                emptyRow.style.display = '';
            }
        }
        recalculateTotals();
    }

    function recalculateRow(index) {
        var tr = document.getElementById('item-row-' + index);
        if (!tr) return;

        var price = parseFloat(tr.querySelector('.row-price-input').value) || 0;
        var qty = parseInt(tr.querySelector('.row-qty').value) || 1;
        var subtotal = price * qty;

        tr.querySelector('.row-subtotal').textContent = 'Tk ' + subtotal.toFixed(2);
        recalculateTotals();
    }

    function recalculateTotals() {
        var subtotal = 0;
        var rows = document.querySelectorAll('#order-items-body tr:not(#empty-products-row)');
        
        rows.forEach(function(row) {
            var priceEl = row.querySelector('.row-price-input');
            var price = priceEl ? (parseFloat(priceEl.value) || 0) : 0;
            var qtyEl = row.querySelector('.row-qty');
            var qty = qtyEl ? (parseInt(qtyEl.value) || 1) : 1;
            subtotal += (price * qty);
        });

        var discountVal = parseFloat(document.getElementById('input-discount').value) || 0;
        var discountAmount = 0;

        if (currentDiscountType === 'percent') {
            discountAmount = subtotal * (discountVal / 100);
        } else {
            discountAmount = Math.min(subtotal, discountVal);
        }

        var afterDiscount = Math.max(0, subtotal - discountAmount);
        var tax = parseFloat(document.getElementById('input-tax').value) || 0;
        var shipping = parseFloat(document.getElementById('input-shipping').value) || 0;
        var advance = parseFloat(document.getElementById('input-advance').value) || 0;

        var grandTotal = afterDiscount + tax + shipping;
        var due = Math.max(0, grandTotal - advance);

        document.getElementById('display-subtotal').textContent = 'Tk ' + subtotal.toFixed(2);
        document.getElementById('display-after-discount').textContent = 'Tk ' + afterDiscount.toFixed(2);
        document.getElementById('display-grand-total').textContent = 'Tk ' + grandTotal.toFixed(2);
        document.getElementById('display-due').textContent = 'Tk ' + due.toFixed(2);
    }

    function setDiscountType(type) {
        currentDiscountType = type;
        document.getElementById('input-discount-type').value = type;

        var btnFixed = document.getElementById('btn-toggle-fixed');
        var btnPercent = document.getElementById('btn-toggle-percent');

        if (type === 'percent') {
            btnPercent.classList.add('active');
            btnFixed.classList.remove('active');
        } else {
            btnFixed.classList.add('active');
            btnPercent.classList.remove('active');
        }
        recalculateTotals();
    }

    function toggleCouponInput() {
        var wrap = document.getElementById('coupon-field-wrap');
        wrap.style.display = (wrap.style.display === 'none' || wrap.style.display === '') ? 'block' : 'none';
    }

    function applyCouponCode() {
        var code = document.getElementById('coupon-code-input').value.trim().toUpperCase();
        var msgEl = document.getElementById('coupon-message');
        if (!code) {
            msgEl.textContent = 'Please enter a coupon code.';
            msgEl.style.color = '#ef4444';
            return;
        }

        var found = availableCoupons.find(function(c) {
            return c.code.toUpperCase() === code;
        });

        if (!found) {
            msgEl.textContent = 'Invalid or expired coupon.';
            msgEl.style.color = '#ef4444';
            return;
        }

        var subtotal = parseFloat(document.getElementById('display-subtotal').textContent.replace(/[^\d.]/g, '')) || 0;
        if (found.min_order_amount > 0 && subtotal < parseFloat(found.min_order_amount)) {
            msgEl.textContent = 'Minimum order of Tk ' + found.min_order_amount + ' required.';
            msgEl.style.color = '#ef4444';
            return;
        }

        document.getElementById('input-applied-coupon').value = found.code;

        if (found.type === 'percentage') {
            setDiscountType('percent');
            document.getElementById('input-discount').value = found.value;
        } else {
            setDiscountType('fixed');
            document.getElementById('input-discount').value = found.value;
        }

        msgEl.textContent = 'Coupon ' + found.code + ' applied successfully!';
        msgEl.style.color = '#10b981';
        recalculateTotals();
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>
</body>
</html>
