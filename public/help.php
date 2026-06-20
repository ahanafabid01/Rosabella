<?php
/**
 * KARTLY - Help Center
 */
$pageTitle = 'Help Center';
require_once __DIR__ . '/../includes/header.php';
?>

    <section class="section section-bg" style="padding: 1.5rem 0 2rem;">
        <div class="container">
            <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">Help Center</h1>
            <nav style="font-size: 0.875rem; color: var(--color-text-light);">
                <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light); display: inline-flex; align-items: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
                <span> / </span>
                <span style="color: var(--color-text);">Help Center</span>
            </nav>
        </div>
    </section>

    <section class="section">
        <div class="container" style="max-width: 800px; margin: 0 auto;">
            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem;">
                <h2 style="font-size: 1.5rem; margin-bottom: 1rem;">How can we help you?</h2>
                <p style="color: var(--color-text-light); line-height: 1.6; margin-bottom: 1.5rem;">
                    Welcome to the KARTLY Help Center. Find answers to common questions about orders, shipping, returns, and your account.
                </p>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <a href="<?= BASE_URL ?>/track-order" style="display: block; padding: 1rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-weight: 500;">Track an Order</a>
                    <a href="<?= BASE_URL ?>/returns" style="display: block; padding: 1rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-weight: 500;">Start a Return</a>
                    <a href="<?= BASE_URL ?>/contact" style="display: block; padding: 1rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-weight: 500;">Contact Support</a>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
