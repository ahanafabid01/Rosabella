<?php
/**
 * KARTLY - Help Center
 */
$pageTitle = 'Help Center';
require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header -->
    <section class="section section-bg">
        <div class="container">
            <nav style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem;">
                <a href="/Kartly/" style="color: var(--color-text-light);">Home</a>
                <span> / </span>
                <span style="color: var(--color-text);">Help Center</span>
            </nav>
            <h1 style="font-size: 2rem; font-weight: 700; text-align: center;">How can we help you?</h1>
            
            <!-- Search -->
            <div style="max-width: 600px; margin: 1.5rem auto 0;">
                <div style="position: relative;">
                    <svg style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; color: var(--color-text-light);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                    <input type="search" placeholder="Search for help articles..." class="form-input" style="padding-left: 3rem; height: 50px; font-size: 1rem;">
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Links -->
    <section class="section">
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 3rem;">
                <a href="/Kartly/track-order" style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; text-align: center; transition: all var(--transition-base);" onmouseover="this.style.borderColor='var(--color-primary)'" onmouseout="this.style.borderColor='var(--color-border)'">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2" style="margin: 0 auto 0.75rem;"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    <h4 style="font-weight: 600; margin-bottom: 0.25rem;">Track Order</h4>
                    <p style="font-size: 0.75rem; color: var(--color-text-light);">Check your order status</p>
                </a>
                <a href="/Kartly/returns" style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; text-align: center; transition: all var(--transition-base);" onmouseover="this.style.borderColor='var(--color-primary)'" onmouseout="this.style.borderColor='var(--color-border)'">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2" style="margin: 0 auto 0.75rem;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                    <h4 style="font-weight: 600; margin-bottom: 0.25rem;">Returns</h4>
                    <p style="font-size: 0.75rem; color: var(--color-text-light);">Start a return request</p>
                </a>
                <a href="/Kartly/shipping" style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; text-align: center; transition: all var(--transition-base);" onmouseover="this.style.borderColor='var(--color-primary)'" onmouseout="this.style.borderColor='var(--color-border)'">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2" style="margin: 0 auto 0.75rem;"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    <h4 style="font-weight: 600; margin-bottom: 0.25rem;">Shipping Info</h4>
                    <p style="font-size: 0.75rem; color: var(--color-text-light);">Delivery options & times</p>
                </a>
                <a href="/Kartly/contact" style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; text-align: center; transition: all var(--transition-base);" onmouseover="this.style.borderColor='var(--color-primary)'" onmouseout="this.style.borderColor='var(--color-border)'">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2" style="margin: 0 auto 0.75rem;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <h4 style="font-weight: 600; margin-bottom: 0.25rem;">Contact Us</h4>
                    <p style="font-size: 0.75rem; color: var(--color-text-light);">Get in touch with support</p>
                </a>
            </div>

            <!-- FAQ Categories -->
            <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem;">Frequently Asked Questions</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <!-- Orders & Shipping -->
                <div>
                    <h3 style="font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        Orders & Shipping
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <details style="background: var(--color-bg-secondary); border-radius: var(--radius-md); padding: 1rem; cursor: pointer;">
                            <summary style="font-weight: 500; outline: none;">How do I track my order?</summary>
                            <p style="margin-top: 0.75rem; color: var(--color-text-light); font-size: 0.875rem;">You can track your order by visiting our <a href="/Kartly/track-order" style="color: var(--color-primary);">Track Order</a> page and entering your order number and email address. You'll receive real-time updates on your package location.</p>
                        </details>
                        <details style="background: var(--color-bg-secondary); border-radius: var(--radius-md); padding: 1rem; cursor: pointer;">
                            <summary style="font-weight: 500; outline: none;">What are the shipping options?</summary>
                            <p style="margin-top: 0.75rem; color: var(--color-text-light); font-size: 0.875rem;">We offer Standard Shipping (5-7 business days), Express Shipping (2-3 business days), and Next Day Delivery. Free shipping is available on orders over $50.</p>
                        </details>
                        <details style="background: var(--color-bg-secondary); border-radius: var(--radius-md); padding: 1rem; cursor: pointer;">
                            <summary style="font-weight: 500; outline: none;">Can I change my shipping address?</summary>
                            <p style="margin-top: 0.75rem; color: var(--color-text-light); font-size: 0.875rem;">You can change your shipping address within 1 hour of placing your order. Contact our support team immediately for assistance.</p>
                        </details>
                    </div>
                </div>

                <!-- Returns & Refunds -->
                <div>
                    <h3 style="font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                        Returns & Refunds
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <details style="background: var(--color-bg-secondary); border-radius: var(--radius-md); padding: 1rem; cursor: pointer;">
                            <summary style="font-weight: 500; outline: none;">What is your return policy?</summary>
                            <p style="margin-top: 0.75rem; color: var(--color-text-light); font-size: 0.875rem;">We accept returns within 30 days of delivery. Items must be unused and in original packaging. Some exclusions apply for hygiene products and personalized items.</p>
                        </details>
                        <details style="background: var(--color-bg-secondary); border-radius: var(--radius-md); padding: 1rem; cursor: pointer;">
                            <summary style="font-weight: 500; outline: none;">How long do refunds take?</summary>
                            <p style="margin-top: 0.75rem; color: var(--color-text-light); font-size: 0.875rem;">Refunds are processed within 3-5 business days after we receive your return. The refund will appear in your account within 5-10 business days depending on your payment method.</p>
                        </details>
                        <details style="background: var(--color-bg-secondary); border-radius: var(--radius-md); padding: 1rem; cursor: pointer;">
                            <summary style="font-weight: 500; outline: none;">Do you offer exchanges?</summary>
                            <p style="margin-top: 0.75rem; color: var(--color-text-light); font-size: 0.875rem;">Yes! We offer free exchanges for different sizes or colors. Simply start a return and select "Exchange" as your reason.</p>
                        </details>
                    </div>
                </div>

                <!-- Payment & Pricing -->
                <div>
                    <h3 style="font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        Payment & Pricing
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <details style="background: var(--color-bg-secondary); border-radius: var(--radius-md); padding: 1rem; cursor: pointer;">
                            <summary style="font-weight: 500; outline: none;">What payment methods do you accept?</summary>
                            <p style="margin-top: 0.75rem; color: var(--color-text-light); font-size: 0.875rem;">We accept all major credit cards (Visa, MasterCard, American Express), PayPal, Apple Pay, Google Pay, and KARTLY gift cards.</p>
                        </details>
                        <details style="background: var(--color-bg-secondary); border-radius: var(--radius-md); padding: 1rem; cursor: pointer;">
                            <summary style="font-weight: 500; outline: none;">Is my payment information secure?</summary>
                            <p style="margin-top: 0.75rem; color: var(--color-text-light); font-size: 0.875rem;">Yes! We use industry-standard SSL encryption and are PCI DSS compliant. Your payment information is never stored on our servers.</p>
                        </details>
                        <details style="background: var(--color-bg-secondary); border-radius: var(--radius-md); padding: 1rem; cursor: pointer;">
                            <summary style="font-weight: 500; outline: none;">Do you offer price matching?</summary>
                            <p style="margin-top: 0.75rem; color: var(--color-text-light); font-size: 0.875rem;">Yes, we offer price matching within 14 days of purchase if you find the same item at a lower price from an authorized retailer.</p>
                        </details>
                    </div>
                </div>

                <!-- Account & Orders -->
                <div>
                    <h3 style="font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Account & Orders
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <details style="background: var(--color-bg-secondary); border-radius: var(--radius-md); padding: 1rem; cursor: pointer;">
                            <summary style="font-weight: 500; outline: none;">How do I create an account?</summary>
                            <p style="margin-top: 0.75rem; color: var(--color-text-light); font-size: 0.875rem;">Click "Sign In" in the top right corner and select "Create Account". Fill in your details and you're ready to shop!</p>
                        </details>
                        <details style="background: var(--color-bg-secondary); border-radius: var(--radius-md); padding: 1rem; cursor: pointer;">
                            <summary style="font-weight: 500; outline: none;">I forgot my password. What should I do?</summary>
                            <p style="margin-top: 0.75rem; color: var(--color-text-light); font-size: 0.875rem;">Please contact support at <a href="mailto:support@kartly.com" style="color: var(--color-primary);">support@kartly.com</a> and we'll help you recover account access.</p>
                        </details>
                        <details style="background: var(--color-bg-secondary); border-radius: var(--radius-md); padding: 1rem; cursor: pointer;">
                            <summary style="font-weight: 500; outline: none;">How do I cancel my order?</summary>
                            <p style="margin-top: 0.75rem; color: var(--color-text-light); font-size: 0.875rem;">You can cancel your order within 1 hour of placing it. Go to "My Orders" in your account or contact our support team.</p>
                        </details>
                    </div>
                </div>
            </div>

            <!-- Contact Section -->
            <div style="margin-top: 3rem; padding: 2rem; background: var(--color-bg-secondary); border-radius: var(--radius-xl); text-align: center;">
                <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">Still need help?</h3>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">Our support team is available 24/7 to assist you</p>
                <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                    <a href="mailto:support@kartly.com" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Email Support
                    </a>
                    <a href="tel:+15551234567" class="btn btn-outline">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        Call Us
                    </a>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


