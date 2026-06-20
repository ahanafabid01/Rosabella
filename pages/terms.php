<?php
/**
 * KARTLY - Terms of Service
 */
$pageTitle = 'Terms of Service';
require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header -->
    <section class="section section-bg" style="padding: 1rem 0;">
        <div class="container">
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <h1 style="font-size: 1.875rem; font-weight: 700;">Terms of Service</h1>
                <nav style="font-size: 0.875rem; color: var(--color-text-light); display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light); display: flex; align-items: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
                    <span>/</span>
                    <span style="color: var(--color-text);">Terms of Service</span>
                </nav>
            </div>
            <p style="color: var(--color-text-light); margin-top: 0.5rem; font-size: 0.875rem;">Last updated: December 15, 2024</p>
        </div>
    </section>

    <!-- Content -->
    <section class="section">
        <div class="container" style="max-width: 800px;">
            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">1. Acceptance of Terms</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">
                    By accessing and using KARTLY's website, you agree to be bound by these Terms of Service and all applicable laws and regulations. 
                    If you do not agree with any of these terms, you are prohibited from using this site.
                </p>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">2. Use License</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">
                    Permission is granted to temporarily access the materials on KARTLY's website for personal, non-commercial use only. 
                    This is the grant of a license, not a transfer of title. Under this license you may not:
                </p>
                <ul style="color: var(--color-text-light); margin-bottom: 1.5rem; padding-left: 1.5rem; list-style: disc;">
                    <li style="margin-bottom: 0.5rem;">Modify or copy the materials</li>
                    <li style="margin-bottom: 0.5rem;">Use the materials for any commercial purpose</li>
                    <li style="margin-bottom: 0.5rem;">Attempt to decompile or reverse engineer any software</li>
                    <li style="margin-bottom: 0.5rem;">Remove any copyright or proprietary notations</li>
                </ul>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">3. Account Registration</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">
                    To access certain features, you must create an account. You agree to provide accurate and complete information 
                    during registration and to update such information as needed. You are responsible for maintaining the confidentiality 
                    of your account credentials.
                </p>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">4. Orders and Payments</h2>
                <p style="color: var(--color-text-light); margin-bottom: 0.5rem;">When placing an order:</p>
                <ul style="color: var(--color-text-light); margin-bottom: 1.5rem; padding-left: 1.5rem; list-style: disc;">
                    <li style="margin-bottom: 0.5rem;">You warrant that you are legally capable of entering into contracts</li>
                    <li style="margin-bottom: 0.5rem;">You agree to pay all charges incurred, including applicable taxes</li>
                    <li style="margin-bottom: 0.5rem;">We reserve the right to refuse or cancel any order</li>
                    <li style="margin-bottom: 0.5rem;">Prices are subject to change without notice</li>
                </ul>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">5. Shipping and Delivery</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">
                    We ship to the address provided during checkout. Delivery times are estimates and not guaranteed. 
                    Risk of loss passes to you upon delivery to the carrier. For more information, see our 
                    <a href="<?= BASE_URL ?>/shipping" style="color: var(--color-primary);">Shipping Policy</a>.
                </p>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">6. Returns and Refunds</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">
                    We accept returns within 30 days of delivery. Items must be unused and in original packaging. 
                    For complete details, see our <a href="<?= BASE_URL ?>/returns" style="color: var(--color-primary);">Returns Policy</a>.
                </p>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">7. Prohibited Activities</h2>
                <p style="color: var(--color-text-light); margin-bottom: 0.5rem;">You may not:</p>
                <ul style="color: var(--color-text-light); margin-bottom: 1.5rem; padding-left: 1.5rem; list-style: disc;">
                    <li style="margin-bottom: 0.5rem;">Use the site for any unlawful purpose</li>
                    <li style="margin-bottom: 0.5rem;">Attempt to gain unauthorized access to our systems</li>
                    <li style="margin-bottom: 0.5rem;">Interfere with the proper working of the site</li>
                    <li style="margin-bottom: 0.5rem;">Collect user information without consent</li>
                </ul>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">8. Limitation of Liability</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">
                    KARTLY shall not be liable for any indirect, incidental, special, consequential, or punitive damages 
                    resulting from your use of or inability to use the service.
                </p>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">9. Governing Law</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">
                    These terms shall be governed by the laws of Bangladesh,
                    without regard to its conflict of law provisions.
                </p>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">10. Contact</h2>
                <p style="color: var(--color-text-light);">
                    For questions about these Terms, contact us at:<br>
                    Email: <a href="mailto:legal@kartly.com" style="color: var(--color-primary);">legal@kartly.com</a>
                </p>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



