<?php
/**
 * KARTLY - Track Order
 */
$pageTitle = 'Track Order';
require_once __DIR__ . '/../includes/header.php';

$error = '';
$order = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderNumber = sanitize($_POST['order_number'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    
    if (!empty($orderNumber) && !empty($email)) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? AND shipping_email = ?");
        $stmt->execute([$orderNumber, $email]);
        $order = $stmt->fetch();
        
        if (!$order) {
            $error = 'Order not found. Please check your order number and email.';
        }
    } else {
        $error = 'Please enter both order number and email.';
    }
}
?>

    <!-- Page Header -->
    <section class="section section-bg">
        <div class="container">
            <nav style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem;">
                <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light);">Home</a>
                <span> / </span>
                <a href="<?= BASE_URL ?>/help" style="color: var(--color-text-light);">Help</a>
                <span> / </span>
                <span style="color: var(--color-text);">Track Order</span>
            </nav>
            <h1 style="font-size: 2rem; font-weight: 700;">Track Your Order</h1>
        </div>
    </section>

    <!-- Track Order Section -->
    <section class="section">
        <div class="container" style="max-width: 800px;">
            
            <?php if (!$order): ?>
            <!-- Search Form -->
            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem; margin-bottom: 2rem;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Enter Your Order Details</h2>
                
                <?php if ($error): ?>
                    <div style="background: rgba(220, 53, 69, 0.1); border: 1px solid var(--color-danger); color: var(--color-danger); padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; font-size: 0.875rem;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="order_number">Order Number</label>
                        <input type="text" id="order_number" name="order_number" class="form-input" placeholder="e.g., KAR-2024-12345" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="Enter the email used for your order" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">Track Order</button>
                </form>
            </div>
            
            <div style="text-align: center;">
                <p style="color: var(--color-text-light); font-size: 0.875rem;">
                    Your order number can be found in your confirmation email.<br>
                    Check your spam folder if you can't find it.
                </p>
            </div>
            <?php else: ?>
            
            <!-- Order Found -->
            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; margin-bottom: 2rem;">
                <!-- Header -->
                <div style="background: var(--color-bg-secondary); padding: 1.5rem; border-bottom: 1px solid var(--color-border);">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <p style="font-size: 0.75rem; color: var(--color-text-light); text-transform: uppercase;">Order Number</p>
                            <h2 style="font-size: 1.25rem; font-weight: 700;"><?= htmlspecialchars($order['order_number']) ?></h2>
                        </div>
                        <div style="text-align: right;">
                            <span class="badge badge-<?= $order['status'] === 'delivered' ? 'success' : ($order['status'] === 'shipped' ? 'primary' : 'warning') ?>"><?= ucfirst($order['status']) ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Progress -->
                <div style="padding: 2rem;">
                    <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1.5rem;">Order Progress</h3>
                    
                    <?php
                    $statuses = ['pending', 'processing', 'shipped', 'delivered'];
                    $currentIndex = array_search($order['status'], $statuses);
                    ?>
                    
                    <div style="display: flex; justify-content: space-between; position: relative; margin-bottom: 2rem;">
                        <!-- Progress Line -->
                        <div style="position: absolute; top: 15px; left: 0; right: 0; height: 3px; background: var(--color-border);"></div>
                        <div style="position: absolute; top: 15px; left: 0; height: 3px; background: var(--color-primary); width: <?= ($currentIndex / 3) * 100 ?>%;"></div>
                        
                        <?php foreach ($statuses as $index => $status): ?>
                        <div style="position: relative; z-index: 1; text-align: center; flex: 1;">
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: <?= $index <= $currentIndex ? 'var(--color-primary)' : 'var(--color-bg-secondary)' ?>; border: 3px solid <?= $index <= $currentIndex ? 'var(--color-primary)' : 'var(--color-border)' ?>; margin: 0 auto 0.5rem; display: flex; align-items: center; justify-content: center;">
                                <?php if ($index <= $currentIndex): ?>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                <?php endif; ?>
                            </div>
                            <p style="font-size: 0.75rem; font-weight: 500; color: <?= $index <= $currentIndex ? 'var(--color-text)' : 'var(--color-text-light)' ?>;"><?= ucfirst($status) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Order Details -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--color-border);">
                        <div>
                            <p style="font-size: 0.75rem; color: var(--color-text-light); text-transform: uppercase; margin-bottom: 0.25rem;">Order Date</p>
                            <p style="font-weight: 500;"><?= date('F j, Y', strtotime($order['created_at'])) ?></p>
                        </div>
                        <div>
                            <p style="font-size: 0.75rem; color: var(--color-text-light); text-transform: uppercase; margin-bottom: 0.25rem;">Shipping To</p>
                            <p style="font-weight: 500;"><?= htmlspecialchars($order['shipping_city'] . ', ' . $order['shipping_country']) ?></p>
                        </div>
                        <div>
                            <p style="font-size: 0.75rem; color: var(--color-text-light); text-transform: uppercase; margin-bottom: 0.25rem;">Total</p>
                            <p style="font-weight: 500;"><?= formatPrice($order['total']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tracking Number -->
            <?php if ($order['status'] === 'shipped' && $order['transaction_id']): ?>
            <div style="background: rgba(40, 167, 69, 0.1); border: 1px solid var(--color-success); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 2rem;">
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <p style="font-size: 0.75rem; color: var(--color-text-light); margin-bottom: 0.25rem;">Tracking Number</p>
                        <p style="font-weight: 600; font-family: monospace;"><?= htmlspecialchars($order['transaction_id']) ?></p>
                    </div>
                    <button class="btn btn-outline btn-sm" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($order['transaction_id']) ?>'); alert('Copied!')">Copy</button>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Actions -->
            <div style="text-align: center;">
                <a href="<?= BASE_URL ?>/track-order" class="btn btn-secondary">Track Another Order</a>
            </div>
            <?php endif; ?>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



