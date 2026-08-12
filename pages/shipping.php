<?php
/**
 * Rosabella - Shipping Information
 */
$pageTitle = 'Shipping Information';
require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header -->
    <section class="section section-bg" style="padding: 1rem 0;">
        <div class="container">
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <h1 style="font-size: 1.875rem; font-weight: 700;">Shipping Information</h1>
                <nav style="font-size: 0.875rem; color: var(--color-text-light); display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light); display: flex; align-items: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
                    <span>/</span>
                    <a href="<?= BASE_URL ?>/help" style="color: var(--color-text-light);">Help</a>
                    <span>/</span>
                    <span style="color: var(--color-text);">Shipping Info</span>
                </nav>
            </div>
        </div>
    </section>

    <!-- Content -->
    <section class="section">
        <div class="container" style="max-width: 900px;">
            
            <!-- Fast Shipping Banner -->
            <div style="background: var(--color-bg-secondary); padding: 2.5rem; border-radius: var(--radius-lg); text-align: center; margin-bottom: 3rem;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="1.5" style="margin: 0 auto 1rem;"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">Fast Shipping on Orders Over Tk 5,000</h2>
                <p style="opacity: 0.9;">No code needed - automatic at checkout</p>
            </div>

            <!-- Shipping Options -->
            <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem;">Shipping Options</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <div style="width: 48px; height: 48px; background: var(--color-bg-secondary); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M13 17l5-5-5-5"/><path d="M6 17l5-5-5-5"/></svg>
                        </div>
                        <div>
                            <h3 style="font-weight: 600;">Standard Shipping</h3>
                            <p style="font-size: 0.875rem; color: var(--color-text-light);">2-4 Business Days</p>
                        </div>
                    </div>
                    <p style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">Tk 120</p>
                    <p style="font-size: 0.75rem; color: var(--color-text-light);">FREE on orders over Tk 5,000</p>
                </div>
                
                <div style="background: var(--color-bg); border: 2px solid var(--color-primary); border-radius: var(--radius-lg); padding: 1.5rem; position: relative;">
                    <span class="badge badge-primary" style="position: absolute; top: -10px; right: 1rem;">POPULAR</span>
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <div style="width: 48px; height: 48px; background: var(--color-primary-light); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        </div>
                        <div>
                            <h3 style="font-weight: 600;">Express Shipping</h3>
                            <p style="font-size: 0.875rem; color: var(--color-text-light);">1-2 Business Days</p>
                        </div>
                    </div>
                    <p style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">Tk 250</p>
                    <p style="font-size: 0.75rem; color: var(--color-text-light);">Includes tracking & insurance</p>
                </div>
                
                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <div style="width: 48px; height: 48px; background: var(--color-bg-secondary); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <div>
                            <h3 style="font-weight: 600;">Next Day Delivery</h3>
                            <p style="font-size: 0.875rem; color: var(--color-text-light);">1 Business Day</p>
                        </div>
                    </div>
                    <p style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">Tk 450</p>
                    <p style="font-size: 0.75rem; color: var(--color-text-light);">Order by 2 PM for next day</p>
                </div>
            </div>

            <!-- Nationwide Shipping -->
            <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem;">Bangladesh Shipping Coverage</h2>
            <div style="background: var(--color-bg-secondary); border-radius: var(--radius-lg); padding: 2rem; margin-bottom: 3rem;">
                <p style="margin-bottom: 1rem;">We currently ship across Bangladesh. Delivery time and charge may vary by zone.</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                    <div>
                        <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Dhaka Metro</h4>
                        <p style="font-size: 0.875rem; color: var(--color-text-light);">1-2 Business Days</p>
                        <p style="font-weight: 600;">From Tk 80</p>
                    </div>
                    <div>
                        <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Chattogram Metro</h4>
                        <p style="font-size: 0.875rem; color: var(--color-text-light);">1-3 Business Days</p>
                        <p style="font-weight: 600;">From Tk 120</p>
                    </div>
                    <div>
                        <h4 style="font-weight: 600; margin-bottom: 0.5rem;">District Towns</h4>
                        <p style="font-size: 0.875rem; color: var(--color-text-light);">2-4 Business Days</p>
                        <p style="font-weight: 600;">From Tk 150</p>
                    </div>
                    <div>
                        <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Remote Areas</h4>
                        <p style="font-size: 0.875rem; color: var(--color-text-light);">3-6 Business Days</p>
                        <p style="font-weight: 600;">From Tk 220</p>
                    </div>
                </div>
            </div>

            <!-- FAQ -->
            <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem;">Frequently Asked Questions</h2>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <details style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1rem 1.5rem; cursor: pointer;">
                    <summary style="font-weight: 600; outline: none;">How do I track my shipment?</summary>
                    <p style="margin-top: 0.75rem; color: var(--color-text-light);">Once your order ships, you'll receive an email with tracking information. You can also track your order on our <a href="<?= BASE_URL ?>/track-order" style="color: var(--color-primary);">Track Order</a> page.</p>
                </details>
                <details style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1rem 1.5rem; cursor: pointer;">
                    <summary style="font-weight: 600; outline: none;">Do you ship to PO boxes?</summary>
                    <p style="margin-top: 0.75rem; color: var(--color-text-light);">Yes, we can deliver to PO boxes through available local courier partners. Next Day and Express shipping may not be available for PO box deliveries.</p>
                </details>
                <details style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1rem 1.5rem; cursor: pointer;">
                    <summary style="font-weight: 600; outline: none;">What if my package is lost or damaged?</summary>
                    <p style="margin-top: 0.75rem; color: var(--color-text-light);">All orders include shipping insurance. If your package is lost or damaged, contact our support team within 48 hours for a replacement or refund.</p>
                </details>
                <details style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1rem 1.5rem; cursor: pointer;">
                    <summary style="font-weight: 600; outline: none;">Can I change my shipping address after ordering?</summary>
                    <p style="margin-top: 0.75rem; color: var(--color-text-light);">Address changes can only be made within 1 hour of placing your order. Contact our support team immediately for assistance.</p>
                </details>
            </div>

        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



