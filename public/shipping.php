<?php
/**
 * KARTLY - Shipping Info
 */
$pageTitle = 'Shipping Information';
require_once __DIR__ . '/../includes/header.php';
?>

    <section class="section section-bg" style="padding: 1.5rem 0 2rem;">
        <div class="container">
            <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">Shipping Information</h1>
            <nav style="font-size: 0.875rem; color: var(--color-text-light);">
                <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light); display: inline-flex; align-items: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
                <span> / </span>
                <span style="color: var(--color-text);">Shipping Info</span>
            </nav>
        </div>
    </section>

    <section class="section">
        <div class="container" style="max-width: 800px; margin: 0 auto;">
            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem;">
                <h2 style="font-size: 1.5rem; margin-bottom: 1rem;">Delivery Options</h2>
                <p style="color: var(--color-text-light); line-height: 1.6; margin-bottom: 1.5rem;">
                    We offer multiple shipping options to get your items to you as quickly as possible.
                </p>
                <ul style="color: var(--color-text-light); line-height: 1.6; margin-bottom: 1.5rem; padding-left: 1.5rem;">
                    <li><strong>Standard Shipping:</strong> 3-5 business days</li>
                    <li><strong>Express Shipping:</strong> 1-2 business days</li>
                    <li><strong>Same Day Delivery:</strong> Available in select areas</li>
                </ul>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
