<?php
/**
 * KARTLY - Privacy Policy
 */
$pageTitle = 'Privacy Policy';
require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header -->
    <section class="section section-bg">
        <div class="container">
            <nav style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem;">
                <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light);">Home</a>
                <span> / </span>
                <span style="color: var(--color-text);">Privacy Policy</span>
            </nav>
            <h1 style="font-size: 2rem; font-weight: 700;">Privacy Policy</h1>
            <p style="color: var(--color-text-light); margin-top: 0.5rem;">Last updated: December 15, 2024</p>
        </div>
    </section>

    <!-- Content -->
    <section class="section">
        <div class="container" style="max-width: 800px;">
            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">1. Introduction</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">
                    KARTLY ("we," "our," or "us") respects your privacy and is committed to protecting your personal data. 
                    This privacy policy explains how we collect, use, and safeguard your information when you visit our website.
                </p>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">2. Information We Collect</h2>
                <p style="color: var(--color-text-light); margin-bottom: 0.5rem;">We collect several types of information:</p>
                <ul style="color: var(--color-text-light); margin-bottom: 1.5rem; padding-left: 1.5rem; list-style: disc;">
                    <li style="margin-bottom: 0.5rem;"><strong>Personal Information:</strong> Name, email address, phone number, shipping address</li>
                    <li style="margin-bottom: 0.5rem;"><strong>Payment Information:</strong> Credit card details (processed securely through third-party providers)</li>
                    <li style="margin-bottom: 0.5rem;"><strong>Usage Data:</strong> Pages visited, time spent on site, browsing patterns</li>
                    <li style="margin-bottom: 0.5rem;"><strong>Device Information:</strong> Browser type, IP address, device type</li>
                </ul>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">3. How We Use Your Information</h2>
                <p style="color: var(--color-text-light); margin-bottom: 0.5rem;">We use your information to:</p>
                <ul style="color: var(--color-text-light); margin-bottom: 1.5rem; padding-left: 1.5rem; list-style: disc;">
                    <li style="margin-bottom: 0.5rem;">Process and fulfill your orders</li>
                    <li style="margin-bottom: 0.5rem;">Send order confirmations and shipping updates</li>
                    <li style="margin-bottom: 0.5rem;">Provide customer support</li>
                    <li style="margin-bottom: 0.5rem;">Send promotional emails (with your consent)</li>
                    <li style="margin-bottom: 0.5rem;">Improve our website and services</li>
                    <li style="margin-bottom: 0.5rem;">Prevent fraud and ensure security</li>
                </ul>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">4. Data Security</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">
                    We implement appropriate security measures to protect your personal information, including SSL encryption, 
                    secure servers, and regular security audits. However, no method of transmission over the Internet is 100% secure.
                </p>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">5. Your Rights</h2>
                <p style="color: var(--color-text-light); margin-bottom: 0.5rem;">You have the right to:</p>
                <ul style="color: var(--color-text-light); margin-bottom: 1.5rem; padding-left: 1.5rem; list-style: disc;">
                    <li style="margin-bottom: 0.5rem;">Access your personal data</li>
                    <li style="margin-bottom: 0.5rem;">Correct inaccurate information</li>
                    <li style="margin-bottom: 0.5rem;">Request deletion of your data</li>
                    <li style="margin-bottom: 0.5rem;">Opt out of marketing communications</li>
                    <li style="margin-bottom: 0.5rem;">Data portability</li>
                </ul>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">6. Cookies</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">
                    We use cookies to enhance your browsing experience. For more information, see our 
                    <a href="<?= BASE_URL ?>/cookies" style="color: var(--color-primary);">Cookie Policy</a>.
                </p>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">7. Third-Party Sharing</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">
                    We may share your information with trusted third parties for order fulfillment, payment processing, 
                    and analytics. These parties are contractually bound to protect your data.
                </p>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">8. Contact Us</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1rem;">
                    If you have questions about this Privacy Policy, please contact us:
                </p>
                <p style="color: var(--color-text-light);">
                    Email: <a href="mailto:privacy@kartly.com" style="color: var(--color-primary);">privacy@kartly.com</a><br>
                    Phone: +1 (555) 123-4567<br>
                    Address: 123 Commerce Street, Shopping City, SC 12345
                </p>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



