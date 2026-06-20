<?php
/**
 * KARTLY - Size Guide
 */
$pageTitle = 'Size Guide';
require_once __DIR__ . '/../includes/header.php';
?>

    <section class="section section-bg" style="padding: 1.5rem 0 2rem;">
        <div class="container">
            <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">Size Guide</h1>
            <nav style="font-size: 0.875rem; color: var(--color-text-light);">
                <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light); display: inline-flex; align-items: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
                <span> / </span>
                <span style="color: var(--color-text);">Size Guide</span>
            </nav>
        </div>
    </section>

    <section class="section">
        <div class="container" style="max-width: 800px; margin: 0 auto;">
            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem;">
                <h2 style="font-size: 1.5rem; margin-bottom: 1rem;">Find Your Perfect Fit</h2>
                <p style="color: var(--color-text-light); line-height: 1.6; margin-bottom: 1.5rem;">
                    Use the charts below to help determine the best size for you. If you are between sizes, we recommend ordering the larger size for a more comfortable fit.
                </p>
                
                <h3 style="font-size: 1.25rem; margin-bottom: 0.75rem;">Clothing Sizes</h3>
                <div style="overflow-x: auto; margin-bottom: 2rem;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--color-border);">
                                <th style="padding: 0.75rem;">Size</th>
                                <th style="padding: 0.75rem;">Chest (in)</th>
                                <th style="padding: 0.75rem;">Waist (in)</th>
                                <th style="padding: 0.75rem;">Hips (in)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-bottom: 1px solid var(--color-border-light);">
                                <td style="padding: 0.75rem;">Small (S)</td>
                                <td style="padding: 0.75rem;">34 - 36</td>
                                <td style="padding: 0.75rem;">28 - 30</td>
                                <td style="padding: 0.75rem;">34 - 36</td>
                            </tr>
                            <tr style="border-bottom: 1px solid var(--color-border-light);">
                                <td style="padding: 0.75rem;">Medium (M)</td>
                                <td style="padding: 0.75rem;">38 - 40</td>
                                <td style="padding: 0.75rem;">32 - 34</td>
                                <td style="padding: 0.75rem;">38 - 40</td>
                            </tr>
                            <tr style="border-bottom: 1px solid var(--color-border-light);">
                                <td style="padding: 0.75rem;">Large (L)</td>
                                <td style="padding: 0.75rem;">42 - 44</td>
                                <td style="padding: 0.75rem;">36 - 38</td>
                                <td style="padding: 0.75rem;">42 - 44</td>
                            </tr>
                            <tr style="border-bottom: 1px solid var(--color-border-light);">
                                <td style="padding: 0.75rem;">X-Large (XL)</td>
                                <td style="padding: 0.75rem;">46 - 48</td>
                                <td style="padding: 0.75rem;">40 - 42</td>
                                <td style="padding: 0.75rem;">46 - 48</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
