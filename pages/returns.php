<?php
/**
 * KARTLY - Returns & Exchanges
 */
$pageTitle = 'Returns & Exchanges';
require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header -->
    <section class="section section-bg" style="padding: 1rem 0;">
        <div class="container">
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <h1 style="font-size: 1.875rem; font-weight: 700;">Returns & Exchanges</h1>
                <nav style="font-size: 0.875rem; color: var(--color-text-light); display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light); display: flex; align-items: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
                    <span>/</span>
                    <a href="<?= BASE_URL ?>/help" style="color: var(--color-text-light);">Help</a>
                    <span>/</span>
                    <span style="color: var(--color-text);">Returns & Exchanges</span>
                </nav>
            </div>
        </div>
    </section>

    <!-- Content -->
    <section class="section">
        <div class="container" style="max-width: 900px;">
            
            <!-- Policy Summary -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
                <div style="text-align: center; padding: 1.5rem; background: var(--color-bg-secondary); border-radius: var(--radius-lg);">
                    <div style="width: 50px; height: 50px; background: var(--color-primary-light); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <h3 style="font-size: 2rem; font-weight: 700; color: var(--color-primary);">30</h3>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Days to Return</p>
                </div>
                <div style="text-align: center; padding: 1.5rem; background: var(--color-bg-secondary); border-radius: var(--radius-lg);">
                    <div style="width: 50px; height: 50px; background: var(--color-primary-light); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <h3 style="font-size: 2rem; font-weight: 700; color: var(--color-primary);">Free</h3>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Return Shipping</p>
                </div>
                <div style="text-align: center; padding: 1.5rem; background: var(--color-bg-secondary); border-radius: var(--radius-lg);">
                    <div style="width: 50px; height: 50px; background: var(--color-primary-light); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                    </div>
                    <h3 style="font-size: 2rem; font-weight: 700; color: var(--color-primary);">Easy</h3>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Exchange Process</p>
                </div>
            </div>

            <!-- Return Process -->
            <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem;">How to Return or Exchange</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 3rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <div style="width: 40px; height: 40px; background: var(--color-primary); color: white; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-weight: 700;">1</div>
                        <h3 style="font-weight: 600;">Start Your Return</h3>
                    </div>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Log into your account, find your order, and click "Start Return". Select the items you want to return or exchange.</p>
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <div style="width: 40px; height: 40px; background: var(--color-primary); color: white; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-weight: 700;">2</div>
                        <h3 style="font-weight: 600;">Pack Your Items</h3>
                    </div>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Pack items in original packaging with all tags attached. Print your prepaid return label and attach it to the box.</p>
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <div style="width: 40px; height: 40px; background: var(--color-primary); color: white; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-weight: 700;">3</div>
                        <h3 style="font-weight: 600;">Ship & Receive</h3>
                    </div>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Drop off your package at any authorized location. Refunds process within 3-5 business days after we receive your return.</p>
                </div>
            </div>

            <!-- Return Conditions -->
            <div style="background: var(--color-bg-secondary); border-radius: var(--radius-lg); padding: 2rem; margin-bottom: 3rem;">
                <h3 style="font-weight: 600; margin-bottom: 1rem;">Return Conditions</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem;">
                    <div>
                        <h4 style="font-weight: 600; color: var(--color-success); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                            Accepted
                        </h4>
                        <ul style="font-size: 0.875rem; color: var(--color-text-light); display: flex; flex-direction: column; gap: 0.5rem;">
                            <li>• Unused items in original packaging</li>
                            <li>• Items with all tags attached</li>
                            <li>• Returned within 30 days</li>
                            <li>• With proof of purchase</li>
                        </ul>
                    </div>
                    <div>
                        <h4 style="font-weight: 600; color: var(--color-danger); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            Not Accepted
                        </h4>
                        <ul style="font-size: 0.875rem; color: var(--color-text-light); display: flex; flex-direction: column; gap: 0.5rem;">
                            <li>• Worn or washed items</li>
                            <li>• Items without original tags</li>
                            <li>• Final sale items</li>
                            <li>• Personalized/custom items</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Start Return Button -->
            <div style="text-align: center;">
                <a href="<?= BASE_URL ?>/account" class="btn btn-primary btn-lg">Start a Return</a>
                <p style="font-size: 0.875rem; color: var(--color-text-light); margin-top: 1rem;">Or contact our support team for assistance</p>
            </div>

        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



