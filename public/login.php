<?php
/**
 * KARTLY - User Login
 */
$pageTitle = 'Login';
require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['first_name'];
            
            // Update cart items with user ID
            $stmt = $db->prepare("UPDATE cart SET user_id = ? WHERE session_id = ?");
            $stmt->execute([$user['id'], session_id()]);
            
            // Redirect to account or admin
            if ($user['role'] === 'admin') {
                redirect('admin/index.php');
            } else {
                redirect('account.php');
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Breadcrumb -->
    <div style="background: #fff; border-bottom: 1px solid #eee; padding: 1rem 0; margin-bottom: 2rem;">
        <div class="container">
            <nav style="font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; color: var(--color-text-light);">
                <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light); display: flex; align-items: center;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                </a>
                <span>/</span>
                <a href="<?= BASE_URL ?>/account" style="color: var(--color-text); text-decoration: none; font-weight: 500;">Account</a>
                <span>/</span>
                <span style="color: var(--color-text-light);">Login</span>
            </nav>
        </div>
    </div>

    <!-- Login Section -->
    <section style="padding: 1.5rem 0 5rem;">
        <div class="container" style="max-width: 460px; width: 100%;">
            <div style="padding: 0 1rem;">
                <div style="text-align: left; margin-bottom: 2rem;">
                    <h1 style="font-size: 1.5rem; font-weight: 500; color: var(--color-text); margin-bottom: 0.5rem;">Account Login</h1>
                </div>
                
                <?php if ($error): ?>
                    <div style="background: rgba(220, 53, 69, 0.1); border: 1px solid var(--color-danger); color: var(--color-danger); padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; font-size: 0.875rem;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div style="background: rgba(40, 167, 69, 0.1); border: 1px solid var(--color-success); color: var(--color-success); padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; font-size: 0.875rem;">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="email" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 0.25rem; display: block;">Phone / E-Mail</label>
                        <input type="text" id="email" name="email" class="form-input" placeholder="Phone / E-Mail" required style="border-radius: 4px;">
                    </div>
                    
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                            <label class="form-label" for="password" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 0;">Password</label>
                            <a href="#" style="font-size: 0.8rem; color: var(--color-danger); text-decoration: none;">Forgotten Password?</a>
                        </div>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Password" required style="border-radius: 4px;">
                    </div>
                    
                    <button type="submit" class="btn" style="width: 100%; background-color: var(--color-primary); color: #fff; border-radius: 4px; padding: 0.875rem; font-weight: 600; font-size: 0.95rem; border: none; margin-top: 0.5rem; cursor: pointer;">
                        Login
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <div style="position: relative; text-align: center; margin-bottom: 1.5rem;">
                        <span style="background: #fff; padding: 0 10px; color: var(--color-text-light); font-size: 0.9rem; position: relative; z-index: 1;">Don't have an account?</span>
                        <div style="position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: #eee; z-index: 0;"></div>
                    </div>
                    <a href="<?= BASE_URL ?>/register" class="btn" style="display: block; width: 100%; border: 1px solid var(--color-primary); color: var(--color-primary); border-radius: 4px; padding: 0.875rem; font-weight: 600; font-size: 0.95rem; background: #fff; text-decoration: none;">
                        Create Your Account
                    </a>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



