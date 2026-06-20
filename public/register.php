<?php
/**
 * KARTLY - User Registration
 */
$pageTitle = 'Create Account';
require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        $db = getDB();
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists';
        } else {
            // Create user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, password, country, role, status) VALUES (?, ?, ?, ?, 'Bangladesh', 'customer', 'active')");
            
            if ($stmt->execute([$firstName, $lastName, $email, $hashedPassword])) {
                $userId = $db->lastInsertId();
                
                // Auto login
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_role'] = 'customer';
                $_SESSION['user_name'] = $firstName;
                
                $success = 'Account created successfully! Redirecting...';
                
                // Redirect after 2 seconds
                header('refresh:2;url=' . cleanUrl('account'));
            } else {
                $error = 'Failed to create account. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Breadcrumb -->
    <div class="section-bg" style="border-bottom: 1px solid var(--color-border); padding: 1rem 0; margin-bottom: 2rem;">
        <div class="container">
            <nav style="font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; color: var(--color-text-light);">
                <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light); display: flex; align-items: center;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                </a>
                <span>/</span>
                <a href="<?= BASE_URL ?>/account" style="color: var(--color-text); text-decoration: none; font-weight: 500;">Account</a>
                <span>/</span>
                <span style="color: var(--color-text-light);">Register</span>
            </nav>
        </div>
    </div>

    <!-- Register Section -->
    <section style="padding: 1.5rem 0 5rem;">
        <div class="container" style="max-width: 540px; width: 100%;">
            <div style="padding: 0 1rem;">
                <div style="text-align: left; margin-bottom: 2rem;">
                    <h1 style="font-size: 1.5rem; font-weight: 500; color: var(--color-text); margin-bottom: 0.5rem;">Register Account</h1>
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
                    <div class="form-grid-2" style="gap: 1.5rem;">
                        <div class="form-group">
                            <label class="form-label" for="first_name" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 0.25rem; display: block;">First Name <span style="color: red;">*</span></label>
                            <input type="text" id="first_name" name="first_name" class="form-input" placeholder="First Name" required style="border-radius: 4px;">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="last_name" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 0.25rem; display: block;">Last Name <span style="color: red;">*</span></label>
                            <input type="text" id="last_name" name="last_name" class="form-input" placeholder="Last Name" required style="border-radius: 4px;">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="email" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 0.25rem; display: block;">Email Address<span style="color: red;">*</span></label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="Enter your Email Address" required style="border-radius: 4px;">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 0.25rem; display: block;">Password <span style="color: red;">*</span></label>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Password" required style="border-radius: 4px;">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="confirm_password" style="font-weight: 600; font-size: 0.85rem; margin-bottom: 0.25rem; display: block;">Confirm Password <span style="color: red;">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Confirm Password" required style="border-radius: 4px;">
                    </div>
                    
                    <div class="form-group" style="margin-top: 1.5rem; margin-bottom: 1rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; cursor: pointer; font-weight: 500;">
                            <input type="checkbox" name="terms" required style="width: 16px; height: 16px;">
                            <span style="color: var(--color-text);">
                                I have read and agree to the <a href="<?= BASE_URL ?>/privacy" style="color: var(--color-primary); text-decoration: none;">Privacy Policy</a>
                            </span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn" style="width: 100%; background-color: var(--color-primary); color: #fff; border-radius: 4px; padding: 0.875rem; font-weight: 600; font-size: 0.95rem; border: none; cursor: pointer;">
                        Continue
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <div style="position: relative; text-align: center; margin-bottom: 1.5rem;">
                        <span style="background: #fff; padding: 0 10px; color: var(--color-text-light); font-size: 0.9rem; position: relative; z-index: 1;">Already have an account?</span>
                        <div style="position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: #eee; z-index: 0;"></div>
                    </div>
                    <a href="<?= BASE_URL ?>/login" class="btn" style="display: block; width: 100%; border: 1px solid var(--color-primary); color: var(--color-primary); border-radius: 4px; padding: 0.875rem; font-weight: 600; font-size: 0.95rem; background: #fff; text-decoration: none;">
                        Login to Account
                    </a>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



