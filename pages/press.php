<?php
/**
 * KARTLY - Press
 */
$pageTitle = 'Press';
require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Hero -->
    <section style="background: var(--color-primary); padding: 3rem 0; color: white; text-align: center;">
        <div class="container">
            <h1 style="font-size: 2.5rem; font-weight: 700;">Press & Media</h1>
        </div>
    </section>

    <!-- Content -->
    <section class="section">
        <div class="container" style="max-width: 1000px;">
            
            <!-- Media Contact -->
            <div style="background: var(--color-bg-secondary); border-radius: var(--radius-lg); padding: 2rem; text-align: center; margin-bottom: 3rem;">
                <h3 style="font-weight: 600; margin-bottom: 0.5rem;">Media Inquiries</h3>
                <p style="color: var(--color-text-light); margin-bottom: 1rem;">For press inquiries, please contact our communications team</p>
                <a href="mailto:press@kartly.com" class="btn btn-primary">press@kartly.com</a>
            </div>

            <!-- Press Kit -->
            <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem;">Press Kit</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2" style="margin-bottom: 1rem;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Company Overview</h4>
                    <p style="font-size: 0.75rem; color: var(--color-text-light); margin-bottom: 1rem;">Company history, mission, and key facts</p>
                    <a href="#" class="btn btn-outline btn-sm">Download PDF</a>
                </div>
                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2" style="margin-bottom: 1rem;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Logo & Brand Assets</h4>
                    <p style="font-size: 0.75rem; color: var(--color-text-light); margin-bottom: 1rem;">Official logos, icons, and brand guidelines</p>
                    <a href="#" class="btn btn-outline btn-sm">Download ZIP</a>
                </div>
                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2" style="margin-bottom: 1rem;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Executive Bios</h4>
                    <p style="font-size: 0.75rem; color: var(--color-text-light); margin-bottom: 1rem;">Leadership team biographies and photos</p>
                    <a href="#" class="btn btn-outline btn-sm">Download PDF</a>
                </div>
            </div>

            <!-- News -->
            <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem;">Latest News</h2>
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <article style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
                        <span class="badge badge-primary">Press Release</span>
                        <time style="font-size: 0.875rem; color: var(--color-text-light);">December 15, 2024</time>
                    </div>
                    <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.75rem;">KARTLY Announces Expansion to 50 New Countries</h3>
                    <p style="color: var(--color-text-light); margin-bottom: 1rem;">KARTLY today announced its expansion to 50 new countries across Europe, Asia, and South America, bringing quality products to millions more customers worldwide.</p>
                    <a href="#" style="color: var(--color-primary); font-weight: 500; font-size: 0.875rem;">Read More →</a>
                </article>
                <article style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
                        <span class="badge badge-success">Award</span>
                        <time style="font-size: 0.875rem; color: var(--color-text-light);">November 28, 2024</time>
                    </div>
                    <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.75rem;">KARTLY Wins "Best E-Commerce Platform 2024"</h3>
                    <p style="color: var(--color-text-light); margin-bottom: 1rem;">KARTLY has been recognized as the Best E-Commerce Platform at the 2024 Tech Excellence Awards for outstanding customer experience and innovation.</p>
                    <a href="#" style="color: var(--color-primary); font-weight: 500; font-size: 0.875rem;">Read More →</a>
                </article>
                <article style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
                        <span class="badge badge-warning">Partnership</span>
                        <time style="font-size: 0.875rem; color: var(--color-text-light);">October 10, 2024</time>
                    </div>
                    <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.75rem;">KARTLY Partners with Leading Sustainable Brands</h3>
                    <p style="color: var(--color-text-light); margin-bottom: 1rem;">In a move to promote sustainable shopping, KARTLY has partnered with over 100 eco-friendly brands to offer customers more environmentally conscious choices.</p>
                    <a href="#" style="color: var(--color-primary); font-weight: 500; font-size: 0.875rem;">Read More →</a>
                </article>
            </div>

        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

