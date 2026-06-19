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
                header("refresh:2;url=account.php");
            } else {
                $error = 'Failed to create account. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Register Section -->
    <section class="section" style="min-height: 60vh; display: flex; align-items: center;">
        <div class="container" style="max-width: 450px;">
            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem;">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h1 style="font-size: 1.5rem; font-weight: 700;">Create Account</h1>
                    <p style="color: var(--color-text-light); margin-top: 0.5rem;">Join KARTLY for the best shopping experience</p>
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
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-input" placeholder="John" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-input" placeholder="Doe" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="john@example.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Minimum 6 characters" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Confirm your password" required>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.875rem; cursor: pointer;">
                            <input type="checkbox" name="terms" required style="width: 16px; height: 16px; margin-top: 2px;">
                            <span style="color: var(--color-text-light);">
                                I agree to the <a href="<?= BASE_URL ?>/terms" style="color: var(--color-primary);">Terms of Service</a> and <a href="<?= BASE_URL ?>/privacy" style="color: var(--color-primary);">Privacy Policy</a>
                            </span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        Create Account
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--color-border);">
                    <p style="color: var(--color-text-light); font-size: 0.875rem;">
                        Already have an account? 
                        <a href="<?= BASE_URL ?>/login" style="color: var(--color-primary); font-weight: 500;">Sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



