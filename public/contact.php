<?php
/**
 * KARTLY - Contact Us
 */
$pageTitle = 'Contact Us';
require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if (!$name || !$email || !$message) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $message]);
            $success = 'Thanks for contacting us. We will get back to you shortly.';
        } catch (Throwable $e) {
            $error = 'Unable to submit your message right now. Please try again later.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

    <section class="section section-bg">
        <div class="container">
            <nav style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem;">
                <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light);">Home</a>
                <span> / </span>
                <span style="color: var(--color-text);">Contact Us</span>
            </nav>
            <h1 style="font-size: 2rem; font-weight: 700;">Contact Us</h1>
            <p style="color: var(--color-text-light);">Tell us how we can help and our support team will respond.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div style="display: grid; gap: 1.5rem; grid-template-columns: 1fr; max-width: 980px; margin: 0 auto;">
                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                    <?php if ($error): ?>
                        <div style="background: rgba(220,53,69,0.1); border: 1px solid var(--color-danger); color: var(--color-danger); padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--color-success); color: var(--color-success); padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label" for="name">Name *</label>
                                <input id="name" type="text" name="name" class="form-input" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="email">Email *</label>
                                <input id="email" type="email" name="email" class="form-input" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="subject">Subject</label>
                                <input id="subject" type="text" name="subject" class="form-input" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="message">Message *</label>
                                <textarea id="message" name="message" class="form-textarea" rows="6" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



