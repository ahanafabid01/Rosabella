<?php
/**
 * KARTLY - 404 Page Not Found
 */
$pageTitle = 'Page Not Found';
require_once __DIR__ . '/../includes/header.php';
?>

    <section class="section" style="min-height: 60vh; display: flex; align-items: center; justify-content: center;">
        <div class="container" style="text-align: center;">
            <div style="font-size: 8rem; font-weight: 700; color: var(--color-primary); line-height: 1; margin-bottom: 1rem;">404</div>
            <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 1rem;">Page Not Found</h1>
            <p style="color: var(--color-text-light); margin-bottom: 2rem; max-width: 400px; margin-left: auto; margin-right: auto;">
                Oops! The page you're looking for doesn't exist or has been moved.
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="<?= BASE_URL ?>/" class="btn btn-primary btn-lg">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    Go Home
                </a>
                <a href="<?= BASE_URL ?>/shop" class="btn btn-outline btn-lg">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    Browse Products
                </a>
            </div>
            
            <!-- Suggestions -->
            <div style="margin-top: 4rem;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">You might be looking for:</h2>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/new-arrivals" class="btn btn-secondary">New Arrivals</a>
                    <a href="<?= BASE_URL ?>/best-sellers" class="btn btn-secondary">Best Sellers</a>
                    <a href="<?= BASE_URL ?>/sale" class="btn btn-secondary">Sale Items</a>
                    <a href="<?= BASE_URL ?>/help" class="btn btn-secondary">Help Center</a>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



