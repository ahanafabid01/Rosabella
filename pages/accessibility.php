<?php
/**
 * Rosabella - Accessibility
 */
$pageTitle = 'Accessibility';
require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header -->
    <section class="section section-bg" style="padding: 1rem 0;">
        <div class="container">
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <h1 style="font-size: 1.875rem; font-weight: 700;">Accessibility Statement</h1>
                <nav style="font-size: 0.875rem; color: var(--color-text-light); display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light); display: flex; align-items: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
                    <span>/</span>
                    <span style="color: var(--color-text);">Accessibility</span>
                </nav>
            </div>
        </div>
    </section>

    <!-- Content -->
    <section class="section">
        <div class="container" style="max-width: 800px;">
            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Our Commitment</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">
                    At Rosabella, we are committed to ensuring digital accessibility for people with disabilities. 
                    We are continually improving the user experience for everyone and applying the relevant accessibility standards.
                </p>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Conformance Status</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">
                    We aim to conform to the Web Content Accessibility Guidelines (WCAG) 2.1, Level AA. 
                    These guidelines explain how to make web content more accessible for people with disabilities.
                </p>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Accessibility Features</h2>
                <p style="color: var(--color-text-light); margin-bottom: 0.5rem;">Our website includes the following accessibility features:</p>
                <ul style="color: var(--color-text-light); margin-bottom: 1.5rem; padding-left: 1.5rem; list-style: disc;">
                    <li style="margin-bottom: 0.5rem;"><strong>Keyboard Navigation:</strong> All functionality is accessible via keyboard</li>
                    <li style="margin-bottom: 0.5rem;"><strong>Screen Reader Support:</strong> Compatible with popular screen readers</li>
                    <li style="margin-bottom: 0.5rem;"><strong>Alt Text:</strong> Images include descriptive alternative text</li>
                    <li style="margin-bottom: 0.5rem;"><strong>Color Contrast:</strong> Sufficient contrast ratios for readability</li>
                    <li style="margin-bottom: 0.5rem;"><strong>Resizable Text:</strong> Text can be resized without loss of functionality</li>
                    <li style="margin-bottom: 0.5rem;"><strong>Clear Navigation:</strong> Consistent and predictable navigation structure</li>
                    <li style="margin-bottom: 0.5rem;"><strong>Focus Indicators:</strong> Visible focus indicators for interactive elements</li>
                </ul>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Known Issues</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">
                    We are actively working to identify and address any accessibility barriers. If you encounter any issues, 
                    please let us know so we can address them promptly.
                </p>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Assistive Technology Compatibility</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">
                    Our website is designed to be compatible with the following assistive technologies:
                </p>
                <ul style="color: var(--color-text-light); margin-bottom: 1.5rem; padding-left: 1.5rem; list-style: disc;">
                    <li style="margin-bottom: 0.5rem;">JAWS screen reader</li>
                    <li style="margin-bottom: 0.5rem;">NVDA screen reader</li>
                    <li style="margin-bottom: 0.5rem;">VoiceOver (Mac and iOS)</li>
                    <li style="margin-bottom: 0.5rem;">Dragon NaturallySpeaking</li>
                </ul>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Feedback</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">
                    We welcome your feedback on the accessibility of our website. Please let us know if you encounter 
                    any barriers or have suggestions for improvement:
                </p>
                
                <div style="background: var(--color-bg-secondary); border-radius: var(--radius-md); padding: 1.5rem; margin-bottom: 1.5rem;">
                    <p style="margin-bottom: 0.5rem;"><strong>Email:</strong> <a href="mailto:accessibility@Rosabella.com" style="color: var(--color-primary);">accessibility@Rosabella.com</a></p>
                    <p style="margin-bottom: 0.5rem;"><strong>Phone:</strong> +880 1700-000000</p>
                    <p><strong>Address:</strong> Dhaka, Bangladesh</p>
                </div>

                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Enforcement Procedure</h2>
                <p style="color: var(--color-text-light);">
                    In case of an unsatisfactory response to your complaint, you can contact the relevant authorities 
                    in your jurisdiction.
                </p>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



