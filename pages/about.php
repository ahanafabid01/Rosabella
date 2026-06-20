<?php
/**
 * KARTLY - About Us
 */
$pageTitle = 'About Us';
require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Hero -->
    <section style="background: var(--color-primary); padding: 3rem 0; color: white; text-align: center;">
        <div class="container">
            <h1 style="font-size: 2.5rem; font-weight: 700;">Our Story</h1>
        </div>
    </section>

    <!-- Content -->
    <section class="section">
        <div class="container" style="max-width: 900px;">
            
            <!-- Mission -->
            <div style="text-align: center; margin-bottom: 4rem;">
                <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 1rem;">Our Mission</h2>
                <p style="font-size: 1.125rem; color: var(--color-text-light); max-width: 700px; margin: 0 auto;">
                    At KARTLY, we believe everyone deserves access to quality products at fair prices. 
                    Our mission is to make online shopping simple, enjoyable, and accessible for everyone.
                </p>
            </div>

            <!-- Story -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem; margin-bottom: 4rem;">
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">How It Started</h3>
                    <p style="color: var(--color-text-light); margin-bottom: 1rem;">
                        KARTLY was founded in 2020 with a simple idea: create an online shopping experience 
                        that puts customers first. We noticed that many e-commerce platforms were complicated, 
                        impersonal, and often frustrating to use.
                    </p>
                    <p style="color: var(--color-text-light);">
                        We set out to change that by building a platform that's easy to use, offers great 
                        value, and provides exceptional customer service every step of the way.
                    </p>
                </div>
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Where We Are Today</h3>
                    <p style="color: var(--color-text-light); margin-bottom: 1rem;">
                        Today, KARTLY serves millions of customers worldwide with a curated selection of 
                        products across electronics, fashion, home goods, and more.
                    </p>
                    <p style="color: var(--color-text-light);">
                        We've grown from a small team to over 500 employees, but our commitment to 
                        customer satisfaction remains the same.
                    </p>
                </div>
            </div>

            <!-- Stats -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 2rem; text-align: center; margin-bottom: 4rem; padding: 2rem 0; border-top: 1px solid var(--color-border); border-bottom: 1px solid var(--color-border);">
                <div>
                    <div style="font-size: 2.5rem; font-weight: 700; color: var(--color-primary);">10M+</div>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Happy Customers</p>
                </div>
                <div>
                    <div style="font-size: 2.5rem; font-weight: 700; color: var(--color-primary);">500K+</div>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Products</p>
                </div>
                <div>
                    <div style="font-size: 2.5rem; font-weight: 700; color: var(--color-primary);">100+</div>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Countries</p>
                </div>
                <div>
                    <div style="font-size: 2.5rem; font-weight: 700; color: var(--color-primary);">500+</div>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Team Members</p>
                </div>
            </div>

            <!-- Values -->
            <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 2rem; text-align: center;">Our Values</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 4rem;">
                <div style="background: var(--color-bg-secondary); border-radius: var(--radius-lg); padding: 1.5rem;">
                    <div style="width: 48px; height: 48px; background: var(--color-primary-light); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    </div>
                    <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Customer First</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Every decision we make starts with our customers in mind.</p>
                </div>
                <div style="background: var(--color-bg-secondary); border-radius: var(--radius-lg); padding: 1.5rem;">
                    <div style="width: 48px; height: 48px; background: var(--color-primary-light); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Trust & Transparency</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">We believe in honest pricing and clear communication.</p>
                </div>
                <div style="background: var(--color-bg-secondary); border-radius: var(--radius-lg); padding: 1.5rem;">
                    <div style="width: 48px; height: 48px; background: var(--color-primary-light); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                    </div>
                    <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Quality Products</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">We carefully curate products that meet our high standards.</p>
                </div>
                <div style="background: var(--color-bg-secondary); border-radius: var(--radius-lg); padding: 1.5rem;">
                    <div style="width: 48px; height: 48px; background: var(--color-primary-light); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Community Impact</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">We give back to communities through various initiatives.</p>
                </div>
            </div>

            <!-- Team -->
            <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 2rem; text-align: center;">Leadership Team</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem;">
                <div style="text-align: center;">
                    <div style="width: 120px; height: 120px; background: var(--color-bg-secondary); border-radius: var(--radius-full); margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-size: 3rem;">👨‍💼</div>
                    <h4 style="font-weight: 600;">John Smith</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">CEO & Founder</p>
                </div>
                <div style="text-align: center;">
                    <div style="width: 120px; height: 120px; background: var(--color-bg-secondary); border-radius: var(--radius-full); margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-size: 3rem;">👩‍💼</div>
                    <h4 style="font-weight: 600;">Sarah Johnson</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Chief Operating Officer</p>
                </div>
                <div style="text-align: center;">
                    <div style="width: 120px; height: 120px; background: var(--color-bg-secondary); border-radius: var(--radius-full); margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-size: 3rem;">👨‍💻</div>
                    <h4 style="font-weight: 600;">Michael Chen</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Chief Technology Officer</p>
                </div>
                <div style="text-align: center;">
                    <div style="width: 120px; height: 120px; background: var(--color-bg-secondary); border-radius: var(--radius-full); margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-size: 3rem;">👩‍🎨</div>
                    <h4 style="font-weight: 600;">Emily Davis</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Chief Marketing Officer</p>
                </div>
            </div>

        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

