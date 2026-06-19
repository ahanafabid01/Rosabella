<?php
/**
 * KARTLY - Footer Include
 */
$db = getDB();

// Get footer categories
$stmt = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name LIMIT 6");
$footerCategories = $stmt->fetchAll();

$siteAddress = getSetting('site_address') ?: 'Dhaka, Bangladesh';
$sitePhone = getSetting('site_phone') ?: '+880 1700-000000';
$siteEmail = getSetting('site_email') ?: SITE_EMAIL;
?>

    <!-- Footer -->
    <footer class="footer">
        <!-- Trust Badges -->
        <div class="footer-trust">
            <div class="footer-trust-grid container">
                <div class="footer-trust-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                    </svg>
                    <span>Free Shipping</span>
                </div>
                <div class="footer-trust-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>
                    </svg>
                    <span>30-Day Returns</span>
                </div>
                <div class="footer-trust-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    <span>Secure Checkout</span>
                </div>
                <div class="footer-trust-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                    <span>Multiple Payments</span>
                </div>
            </div>
        </div>


        
        <!-- Newsletter -->
        <div class="newsletter">
            <div class="newsletter-content container">
                <div class="newsletter-text">
                    <h3>Subscribe to our newsletter</h3>
                    <p>Get the latest updates, deals, and exclusive offers directly in your inbox.</p>
                </div>
                <form class="newsletter-form">
                    <input type="email" placeholder="Enter your email" required>
                    <button type="submit" class="btn btn-primary">Subscribe</button>
                </form>
            </div>
        </div>
        
        <!-- Main Footer -->
        <div class="footer-main">
            <div class="footer-grid container">
                <!-- Brand -->
                <div class="footer-brand">
                    <div class="footer-logo">
                        <div class="logo-icon">K</div>
                        <span style="font-size: 1.5rem; font-weight: 700;">KARTLY</span>
                    </div>
                    <p class="footer-description">
                        Your one-stop destination for quality products at unbeatable prices. Shop smart, shop KARTLY.
                    </p>
                    <div class="footer-contact">
                        <div class="footer-contact-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                            </svg>
                            <span><?= htmlspecialchars($siteAddress) ?></span>
                        </div>
                        <div class="footer-contact-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                            </svg>
                            <span><?= htmlspecialchars($sitePhone) ?></span>
                        </div>
                        <div class="footer-contact-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                            </svg>
                            <span><?= htmlspecialchars($siteEmail) ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Shop Links -->
                <div>
                    <h4 class="footer-heading">Shop</h4>
                    <ul class="footer-links">
                        <li><a href="<?= BASE_URL ?>/shop" class="footer-link">All Products</a></li>
                        <li><a href="<?= BASE_URL ?>/new-arrivals" class="footer-link">New Arrivals</a></li>
                        <li><a href="<?= BASE_URL ?>/best-sellers" class="footer-link">Best Sellers</a></li>
                        <li><a href="<?= BASE_URL ?>/sale" class="footer-link">Sale</a></li>
                        <li><a href="<?= BASE_URL ?>/gift-cards" class="footer-link">Gift Cards</a></li>
                    </ul>
                </div>
                
                <!-- Support Links -->
                <div>
                    <h4 class="footer-heading">Support</h4>
                    <ul class="footer-links">
                        <li><a href="<?= BASE_URL ?>/help" class="footer-link">Help Center</a></li>
                        <li><a href="<?= BASE_URL ?>/track-order" class="footer-link">Track Order</a></li>
                        <li><a href="<?= BASE_URL ?>/shipping" class="footer-link">Shipping Info</a></li>
                        <li><a href="<?= BASE_URL ?>/returns" class="footer-link">Returns & Exchanges</a></li>
                        <li><a href="<?= BASE_URL ?>/size-guide" class="footer-link">Size Guide</a></li>
                    </ul>
                </div>
                
                <!-- Company Links -->
                <div>
                    <h4 class="footer-heading">Company</h4>
                    <ul class="footer-links">
                        <li><a href="<?= BASE_URL ?>/about" class="footer-link">About Us</a></li>
                        <li><a href="<?= BASE_URL ?>/careers" class="footer-link">Careers</a></li>
                        <li><a href="<?= BASE_URL ?>/press" class="footer-link">Press</a></li>
                        <li><a href="<?= BASE_URL ?>/sustainability" class="footer-link">Sustainability</a></li>
                        <li><a href="<?= BASE_URL ?>/affiliate" class="footer-link">Affiliate Program</a></li>
                    </ul>
                </div>
                
                <!-- Legal Links -->
                <div>
                    <h4 class="footer-heading">Legal</h4>
                    <ul class="footer-links">
                        <li><a href="<?= BASE_URL ?>/privacy" class="footer-link">Privacy Policy</a></li>
                        <li><a href="<?= BASE_URL ?>/terms" class="footer-link">Terms of Service</a></li>
                        <li><a href="<?= BASE_URL ?>/cookies" class="footer-link">Cookie Policy</a></li>
                        <li><a href="<?= BASE_URL ?>/accessibility" class="footer-link">Accessibility</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Bottom Bar -->
        <div class="footer-bottom">
            <div class="footer-bottom-content container">
                <p class="footer-copyright">
                    &copy; <?= date('Y') ?> KARTLY. All rights reserved.
                </p>
                <div class="footer-social">
                    <a href="#" aria-label="Facebook">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                        </svg>
                    </a>
                    <a href="#" aria-label="Twitter">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/>
                        </svg>
                    </a>
                    <a href="#" aria-label="Instagram">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                        </svg>
                    </a>
                    <a href="#" aria-label="YouTube">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02" fill="white"/>
                        </svg>
                    </a>
                </div>
                <div class="footer-payments">
                    <span style="font-size: 0.75rem; color: var(--color-text-light); margin-right: 0.5rem;">We accept:</span>
                    <span class="payment-method">Visa</span>
                    <span class="payment-method">MC</span>
                    <span class="payment-method">Amex</span>
                    <span class="payment-method">PayPal</span>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Scripts -->
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
    
    <!-- Additional page-specific scripts -->
    <?php if (isset($additionalScripts)): ?>
        <script><?= $additionalScripts ?></script>
    <?php endif; ?>
</body>
</html>


