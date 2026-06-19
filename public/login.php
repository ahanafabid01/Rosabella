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

    <!-- Login Section -->
    <section class="section" style="min-height: 60vh; display: flex; align-items: center;">
        <div class="container" style="max-width: 400px;">
            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem;">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h1 style="font-size: 1.5rem; font-weight: 700;">Welcome Back</h1>
                    <p style="color: var(--color-text-light); margin-top: 0.5rem;">Sign in to your KARTLY account</p>
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
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email" required>
                    </div>
                    
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                            <label class="form-label" for="password" style="margin-bottom: 0;">Password</label>
                            <span style="font-size: 0.75rem; color: var(--color-text-light);">Password reset unavailable</span>
                        </div>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; cursor: pointer;">
                            <input type="checkbox" name="remember" style="width: 16px; height: 16px;">
                            Remember me
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        Sign In
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--color-border);">
                    <p style="color: var(--color-text-light); font-size: 0.875rem;">
                        Don't have an account? 
                        <a href="/Kartly/register" style="color: var(--color-primary); font-weight: 500;">Create one</a>
                    </p>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


