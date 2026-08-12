<?php
/**
 * Rosabella - Homepage
 */
require_once __DIR__ . '/includes/router.php';
require_once __DIR__ . '/includes/themes.php';  // Load theme system
dispatchCleanRoute();

$pageTitle = 'Home';

// ── LCP PRELOAD: Get first hero image URL before <head> is written ──
// This lets header.php emit <link rel="preload" as="image"> for the
// Largest Contentful Paint element so the browser fetches it immediately.
(function() use (&$lcpImagePreload) {
    try {
        $db = getDB();
        $row = $db->query("SELECT image_path FROM hero_slides WHERE status='active' AND position='main' ORDER BY sort_order ASC, created_at DESC LIMIT 1")->fetch();
        if ($row && !empty($row['image_path'])) {
            $lcpImagePreload = BASE_URL . '/' . ltrim($row['image_path'], '/');
        }
    } catch (Throwable $e) { /* silently ignore */ }
})();

require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Get current theme
$currentTheme = getHomepageTheme();
$themeConfig = getThemeConfig($currentTheme);
$isClothingBrandTheme = $currentTheme === 'clothing_brand';

// Theme-specific data loading
$categoryCount = ($currentTheme === 'clothing_brand') ? 4 : 12;

// Get featured products
$stmt = $db->query("SELECT p.*, c.name as category_name 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.status = 'active' AND p.is_featured = 1 
                    ORDER BY p.created_at DESC 
                    LIMIT 15");
$featuredProducts = $stmt->fetchAll();

// Get categories
$stmt = $db->query("SELECT * FROM categories WHERE status = 'active' AND show_on_home = 1 ORDER BY sort_order LIMIT " . $categoryCount);
$categories = $stmt->fetchAll();

// Get new arrivals
$stmt = $db->query("SELECT p.*, c.name as category_name 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.status = 'active' AND p.is_new = 1 
                    ORDER BY p.created_at DESC 
                    LIMIT 15");
$newArrivalProducts = $stmt->fetchAll();

// Helper function to get product grid columns CSS
function getProductGridCols($theme) {
    $config = getThemeConfig($theme);
    $cols = $config['products_columns_desktop'] ?? 4;
    return 'grid-template-columns: repeat(' . $cols . ', 1fr);';
}

function formatCountdownDisplay(int $remainingSeconds): string
{
    $remainingSeconds = max(0, $remainingSeconds);
    $days = intdiv($remainingSeconds, 86400);
    $hours = intdiv($remainingSeconds % 86400, 3600);
    $minutes = intdiv($remainingSeconds % 3600, 60);
    $seconds = $remainingSeconds % 60;

    if ($days > 0) {
        return $days . 'd ' . sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

// Homepage deals settings
$hotDealsSectionTitle = getSetting('home_deals_title') ?: 'Hot Deals';
$hotDealsSectionSubtitle = getSetting('home_deals_subtitle') ?: "Don't miss out on these amazing offers";
$hotDealsCtaLabel = getSetting('home_deals_cta_label') ?: 'View All Deals';
$hotDealsCtaUrl = cleanUrl(getSetting('home_deals_cta_url') ?: 'sale');

$hotDeals = [];
$hotDealsTableReady = true;
try {
    $stmt = $db->query("SELECT * FROM deals WHERE status = 'active' ORDER BY sort_order ASC, created_at DESC LIMIT 3");
    $hotDeals = $stmt->fetchAll();
} catch (Throwable $e) {
    $hotDealsTableReady = false;
    $hotDeals = [];
}

if (!$hotDealsTableReady) {
    $hotDeals = [
        [
            'title' => 'Up to 70% Off',
            'subtitle' => 'Electronics & Gadgets',
            'badge_text' => 'Limited Time',
            'badge_style' => 'danger',
            'timer_text' => '12:45:30',
            'image_path' => 'https://images.unsplash.com/photo-1593642632559-0c6d3fc62b89?w=800&q=80',
            'link_url' => 'category/electronics',
            'overlay_start' => 'rgba(15, 118, 110, 0.84)',
            'overlay_end' => 'rgba(11, 91, 85, 0.62)',
            'image_position' => 'center center',
        ],
        [
            'title' => 'Fashion Forward',
            'subtitle' => 'Summer Collection 2024',
            'badge_text' => 'New Arrivals',
            'badge_style' => 'primary',
            'timer_text' => '23:59:59',
            'image_path' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=800&q=80',
            'link_url' => 'category/fashion',
            'overlay_start' => 'rgba(30, 64, 175, 0.82)',
            'overlay_end' => 'rgba(30, 58, 138, 0.62)',
            'image_position' => 'center top',
        ],
        [
            'title' => 'Buy 2 Get 1 Free',
            'subtitle' => 'Home & Living Essentials',
            'badge_text' => 'This Weekend',
            'badge_style' => 'success',
            'timer_text' => '48:00:00',
            'image_path' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=800&q=80',
            'link_url' => 'category/home-living',
            'overlay_start' => 'rgba(15, 118, 110, 0.84)',
            'overlay_end' => 'rgba(13, 89, 97, 0.62)',
            'image_position' => 'center center',
        ],
    ];
}

// Fetch hero slides from DB
$heroMain = [];
$heroSideTop = null;
$heroSideBottom = null;
try {
    $stmt = $db->query("SELECT * FROM hero_slides WHERE status = 'active' AND position = 'main' ORDER BY sort_order ASC, created_at DESC");
    $heroMain = $stmt->fetchAll();

    $stmt = $db->query("SELECT * FROM hero_slides WHERE status = 'active' AND position = 'side_top' ORDER BY sort_order ASC, created_at DESC");
    $heroSideTop = $stmt->fetchAll();

    $stmt = $db->query("SELECT * FROM hero_slides WHERE status = 'active' AND position = 'side_bottom' ORDER BY sort_order ASC, created_at DESC");
    $heroSideBottom = $stmt->fetchAll();
} catch (Throwable $e) {
    $heroMain = [];
    $heroSideTop = null;
    $heroSideBottom = null;
}
?>

    <!-- Hero Section -->
    <?php if (!empty($heroMain) || $heroSideTop || $heroSideBottom): ?>
    <section class="section <?= $isClothingBrandTheme ? 'clothing-brand-hero' : '' ?>" style="padding-top: 1.5rem; padding-bottom: 2rem;">
        <div class="container">
            <div class="hero-bento-grid <?= ($isClothingBrandTheme || (!$heroSideTop && !$heroSideBottom)) ? 'hero-bento-grid--full' : '' ?>">

                <!-- Main Banner (Left Side Slider) -->
                <?php if (!empty($heroMain)): ?>
                <div class="hero-main-banner">
                    <div class="hero-slider">
                        <?php foreach ($heroMain as $slideIdx => $slide): ?>
                        <div class="hero-slide <?= $slideIdx === 0 ? 'active' : '' ?>">
                            <?php $slideLink = !empty($slide['link_url']) ? cleanUrl($slide['link_url']) : null; ?>
                            <?php if ($slideLink): ?>
                                <a href="<?= htmlspecialchars($slideLink) ?>" aria-label="Hero Slide <?= $slideIdx + 1 ?>" style="display:block;width:100%;height:100%;">
                                    <img src="<?= BASE_URL . '/' . htmlspecialchars($slide['image_path']) ?>" alt="<?= htmlspecialchars($slide['title'] ?? 'Promotion Banner') ?>" <?= $slideIdx === 0 ? 'fetchpriority="high" loading="eager"' : 'loading="lazy"' ?> width="800" height="400">
                                </a>
                            <?php else: ?>
                                <img src="<?= BASE_URL . '/' . htmlspecialchars($slide['image_path']) ?>" alt="<?= htmlspecialchars($slide['title'] ?? 'Promotion Banner') ?>" <?= $slideIdx === 0 ? 'fetchpriority="high" loading="eager"' : 'loading="lazy"' ?> width="800" height="400">
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>

                        <?php if (count($heroMain) > 1): ?>
                        <!-- Navigation -->
                        <div class="hero-nav hero-nav-prev">
                            <button aria-label="Previous slide">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                            </button>
                        </div>
                        <div class="hero-nav hero-nav-next">
                            <button aria-label="Next slide">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                        </div>
                        <!-- Dots -->
                        <div class="hero-dots">
                            <?php foreach ($heroMain as $dotIdx => $dot): ?>
                                <button class="hero-dot <?= $dotIdx === 0 ? 'active' : '' ?>" aria-label="Go to slide <?= $dotIdx + 1 ?>"></button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($isClothingBrandTheme && !empty($heroMain)): ?>
                    <?php $brandHeroLink = !empty($heroMain[0]['link_url']) ? cleanUrl($heroMain[0]['link_url']) : BASE_URL . '/shop'; ?>
                    <div class="clothing-brand-hero-copy">
                        <p>New season / 2026</p>
                        <h1><?= htmlspecialchars($heroMain[0]['title'] ?? 'Modern essentials, made to last.') ?></h1>
                        <a href="<?= htmlspecialchars($brandHeroLink) ?>">Shop the collection <span aria-hidden="true">→</span></a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Side Banners (Right Side) -->
                <?php if (!$isClothingBrandTheme && (!empty($heroSideTop) || !empty($heroSideBottom))): ?>
                <div class="hero-side-banners">
                    <?php if (!empty($heroSideTop)): ?>
                    <div class="hero-side-slider" data-slide-interval="5000">
                        <div class="side-slides-wrapper" style="display:flex; width:100%; height:100%;">
                            <?php foreach($heroSideTop as $idx => $sideTop): ?>
                            <?php $hasLink = !empty($sideTop['link_url']); ?>
                            <<?= $hasLink ? 'a href="'.htmlspecialchars(cleanUrl($sideTop['link_url'])).'"' : 'div' ?> aria-label="Side Promotion" class="hero-side-banner side-slide <?= $idx === 0 ? 'active' : '' ?>">
                                <img src="<?= BASE_URL . '/' . htmlspecialchars($sideTop['image_path']) ?>" alt="<?= htmlspecialchars($sideTop['title'] ?? 'Promotion Banner') ?>" loading="lazy" width="400" height="200">

                                <div class="hero-side-content">
                                    <?php if (!empty($sideTop['subtitle'])): ?>
                                        <div class="hero-side-subtitle"><?= htmlspecialchars($sideTop['subtitle']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($sideTop['title'])): ?>
                                        <div class="hero-side-title"><?= htmlspecialchars($sideTop['title']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </<?= $hasLink ? 'a' : 'div' ?>>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($heroSideTop) > 1): ?>
                        <div class="hero-dots" style="bottom: 15px; z-index: 50; pointer-events: auto;">
                            <?php foreach ($heroSideTop as $dotIdx => $dot): ?>
                                <button class="hero-dot <?= $dotIdx === 0 ? 'active' : '' ?>" aria-label="Go to slide <?= $dotIdx + 1 ?>"></button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($heroSideBottom)): ?>
                    <div class="hero-side-slider" data-slide-interval="5000">
                        <div class="side-slides-wrapper" style="display:flex; width:100%; height:100%;">
                            <?php foreach($heroSideBottom as $idx => $sideBottom): ?>
                            <?php $hasLink = !empty($sideBottom['link_url']); ?>
                            <<?= $hasLink ? 'a href="'.htmlspecialchars(cleanUrl($sideBottom['link_url'])).'"' : 'div' ?> aria-label="Side Promotion" class="hero-side-banner side-slide <?= $idx === 0 ? 'active' : '' ?>">
                                <img src="<?= BASE_URL . '/' . htmlspecialchars($sideBottom['image_path']) ?>" alt="<?= htmlspecialchars($sideBottom['title'] ?? 'Promotion Banner') ?>" loading="lazy" width="400" height="200">

                                <div class="hero-side-content">
                                    <?php if (!empty($sideBottom['subtitle'])): ?>
                                        <div class="hero-side-subtitle"><?= htmlspecialchars($sideBottom['subtitle']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($sideBottom['title'])): ?>
                                        <div class="hero-side-title"><?= htmlspecialchars($sideBottom['title']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </<?= $hasLink ? 'a' : 'div' ?>>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($heroSideBottom) > 1): ?>
                        <div class="hero-dots" style="bottom: 15px; z-index: 50; pointer-events: auto;">
                            <?php foreach ($heroSideBottom as $dotIdx => $dot): ?>
                                <button class="hero-dot <?= $dotIdx === 0 ? 'active' : '' ?>" aria-label="Go to slide <?= $dotIdx + 1 ?>"></button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </section>
        <script>
    (function() {
        if (window.innerWidth >= 768) return;
        var grid = document.querySelector('.hero-bento-grid');
        if (!grid || grid.children.length < 2) return;
        var slides = Array.from(grid.children);
        Object.assign(grid.style, {display:'flex',flexDirection:'row',overflowX:'scroll',scrollSnapType:'x mandatory',scrollBehavior:'smooth',gap:'0',msOverflowStyle:'none',scrollbarWidth:'none'});
        var s = document.createElement('style');
        s.textContent = '.hero-bento-grid::-webkit-scrollbar{display:none}';
        document.head.appendChild(s);
        slides.forEach(function(sl){Object.assign(sl.style,{minWidth:'100%',width:'100%',flexShrink:'0',scrollSnapAlign:'start'});});
        var wrap = document.createElement('div');
        wrap.style.cssText = 'display:flex;justify-content:center;gap:8px;margin-top:10px;';
        var dots = slides.map(function(_,i){
            var d=document.createElement('button');
            d.setAttribute('aria-label', 'Go to slide ' + (i+1));
            d.style.cssText='width:8px;height:8px;border-radius:50%;border:none;padding:0;cursor:pointer;transition:background .2s,transform .2s;background:'+(i===0?'var(--color-primary,#0f766e)':'#d1d5db');
            d.onclick=function(){grid.scrollTo({left:grid.offsetWidth*i,behavior:'smooth'});};
            wrap.appendChild(d); return d;
        });
        grid.parentNode.insertBefore(wrap,grid.nextSibling);
        grid.addEventListener('scroll',function(){var idx=Math.round(grid.scrollLeft/Math.max(grid.offsetWidth,1));dots.forEach(function(d,i){d.style.background=i===idx?'var(--color-primary,#0f766e)':'#d1d5db';d.style.transform=i===idx?'scale(1.4)':'scale(1)';});},{passive:true});
        var sx=0;
        grid.addEventListener('touchstart',function(e){sx=e.touches[0].clientX;},{passive:true});
        grid.addEventListener('touchend',function(e){var diff=sx-e.changedTouches[0].clientX;if(Math.abs(diff)>40){var idx=Math.round(grid.scrollLeft/grid.offsetWidth);idx+=diff>0?1:-1;idx=Math.max(0,Math.min(idx,slides.length-1));grid.scrollTo({left:grid.offsetWidth*idx,behavior:'smooth'});}},{passive:true});
    })();
    </script>
<?php endif; ?>

    <!-- Categories Section -->
    <?php if ($isClothingBrandTheme): ?>
    <section class="section clothing-brand-categories" style="padding-top: 1rem; padding-bottom: 2rem;">
        <div class="container">
            <div class="section-header clothing-brand-section-header">
                <p class="clothing-brand-eyebrow">Shop by style</p>
                <h2 class="section-title">Explore Our Collections</h2>
                <p class="section-subtitle">Designed for every part of your wardrobe.</p>
            </div>
            <div class="category-showcase-grid">
                <?php foreach ($categories as $category): ?>
                    <?php
                    if (!empty($category['image'])) {
                        $catImage = str_starts_with($category['image'], 'http')
                            ? $category['image']
                            : BASE_URL . '/' . ltrim($category['image'], '/');
                    } else {
                        $catImage = 'https://placehold.co/800x1000/1f2937/ffffff?text=' . urlencode($category['name']);
                    }
                    ?>
                    <a href="<?= BASE_URL ?>/category/<?= urlencode($category['slug']) ?>" class="category-showcase-card">
                        <img src="<?= htmlspecialchars($catImage) ?>" alt="<?= htmlspecialchars($category['name']) ?>" loading="lazy" width="600" height="750">
                        <span class="category-showcase-label"><?= htmlspecialchars($category['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if (!empty($hotDeals)): ?>
        <?php
        $brandPromo = $hotDeals[0];
        $brandPromoTitle = trim((string)($brandPromo['title'] ?? 'The New Season Is Here'));
        $brandPromoSubtitle = trim((string)($brandPromo['subtitle'] ?? 'Discover considered pieces made for everyday wear.'));
        $brandPromoImage = trim((string)($brandPromo['image_path'] ?? ''));
        $brandPromoImage = $brandPromoImage !== '' ? $brandPromoImage : 'https://placehold.co/1200x800/1f2937/ffffff?text=New+Collection';
        $brandPromoLink = cleanUrl(trim((string)($brandPromo['link_url'] ?? 'shop')) ?: 'shop');
        ?>
        <section class="section clothing-brand-promo">
            <div class="container">
                <div class="promo-banner-section">
                    <div class="promo-banner-content">
                        <img class="promo-banner-image" src="<?= htmlspecialchars($brandPromoImage) ?>" alt="<?= htmlspecialchars($brandPromoTitle) ?>" loading="lazy" width="1200" height="800">
                        <div class="promo-banner-text">
                            <p class="clothing-brand-eyebrow">Limited edit</p>
                            <h2><?= htmlspecialchars($brandPromoTitle) ?></h2>
                            <p><?= htmlspecialchars($brandPromoSubtitle) ?></p>
                            <a href="<?= htmlspecialchars($brandPromoLink) ?>" class="promo-cta-btn">Shop the collection</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>
    <?php else: ?>
    <section class="section" style="padding-top: 1rem; padding-bottom: 2rem;">
        <div class="container">
            <div class="section-header" style="margin-bottom: 1.5rem;">
                <h2 class="section-title" style="font-size: 1.5rem; color: #1f2937;">Categories</h2>
            </div>
            
            <div class="categories-carousel-container" style="position: relative;">
                <button class="carousel-nav prev" aria-label="Previous categories">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                </button>
                
                <div class="categories-grid" id="categoriesGrid">
                    <?php foreach ($categories as $index => $category): ?>
                        <?php 
                        // Use category image from DB if available, else a generated placeholder
                        if (!empty($category['image'])) {
                            // If the image is a full URL, use it directly, else prepend BASE_URL
                            $catImage = str_starts_with($category['image'], 'http') 
                                ? $category['image'] 
                                : BASE_URL . '/' . ltrim($category['image'], '/');
                        } else {
                            // Fallback generated placeholder
                            $catImage = 'https://placehold.co/200x200/0f766e/ffffff?text=' . urlencode(substr($category['name'], 0, 1));
                        }
                        ?>
                        <a href="<?= BASE_URL ?>/category/<?= urlencode($category['slug']) ?>" class="category-card-clean">
                            <div class="category-card-img-wrap">
                                <img src="<?= htmlspecialchars($catImage) ?>" alt="<?= htmlspecialchars($category['name']) ?>" loading="lazy" width="150" height="150">
                            </div>
                            <div class="category-card-title"><?= htmlspecialchars($category['name']) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <button class="carousel-nav next" aria-label="Next categories">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </button>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const grid = document.getElementById('categoriesGrid');
                    const container = document.querySelector('.categories-carousel-container');
                    const prevBtn = document.querySelector('.categories-carousel-container .prev');
                    const nextBtn = document.querySelector('.categories-carousel-container .next');
                    
                    if(grid && prevBtn && nextBtn) {
                        prevBtn.addEventListener('click', () => {
                            grid.scrollBy({ left: -200, behavior: 'smooth' });
                        });
                        nextBtn.addEventListener('click', () => {
                            grid.scrollBy({ left: 200, behavior: 'smooth' });
                        });
                        
                        // Show/hide buttons based on scroll position
                        const checkScroll = () => {
                            if (grid.scrollWidth <= grid.clientWidth) {
                                prevBtn.style.display = 'none';
                                nextBtn.style.display = 'none';
                                return;
                            }
                            
                            // On desktop where it's a grid, don't show arrows
                            if (window.innerWidth >= 1024) {
                                prevBtn.style.display = 'none';
                                nextBtn.style.display = 'none';
                                return;
                            }

                            prevBtn.style.display = grid.scrollLeft > 0 ? 'flex' : 'none';
                            nextBtn.style.display = grid.scrollLeft < (grid.scrollWidth - grid.clientWidth - 5) ? 'flex' : 'none';
                        };
                        
                        grid.addEventListener('scroll', checkScroll);
                        window.addEventListener('resize', checkScroll);
                        // Initial check after a short delay to allow layout
                        setTimeout(checkScroll, 100);

                        // Auto-slide functionality
                        let autoSlideInterval;
                        const startAutoSlide = () => {
                            if (window.innerWidth >= 1024) return; // Don't auto-slide on desktop grid
                            autoSlideInterval = setInterval(() => {
                                if (grid.scrollLeft < (grid.scrollWidth - grid.clientWidth - 5)) {
                                    grid.scrollBy({ left: 200, behavior: 'smooth' });
                                } else {
                                    // Reset to start
                                    grid.scrollTo({ left: 0, behavior: 'smooth' });
                                }
                            }, 1000); // 1 seconds
                        };

                        const stopAutoSlide = () => {
                            clearInterval(autoSlideInterval);
                        };

                        // Start initially
                        startAutoSlide();

                        // Pause on hover or touch
                        if (container) {
                            container.addEventListener('mouseenter', stopAutoSlide);
                            container.addEventListener('mouseleave', startAutoSlide);
                            container.addEventListener('touchstart', stopAutoSlide, {passive: true});
                            container.addEventListener('touchend', startAutoSlide);
                        }
                    }
                });
            </script>
        </div>
    </section>
    <?php endif; ?>

    <!-- New Arrivals Section -->
    <?php if (!empty($newArrivalProducts)): ?>
    <section class="section <?= $isClothingBrandTheme ? 'clothing-brand-new-arrivals' : '' ?>">
        <div class="container">
            <div class="section-header" style="text-align: center; flex-direction: column; align-items: center; justify-content: center; margin-bottom: 2.5rem;">

                <h2 class="section-title"><?= htmlspecialchars($themeConfig['new_arrivals_title']) ?></h2>
                <p class="section-subtitle" style="max-width: 500px; margin: 0.5rem auto 0;">
                    Be the first to check out our newest additions
                </p>
            </div>
            
            <div class="products-grid">
                <?php foreach ($newArrivalProducts as $idx => $product): ?>
                    <?php
                    // Calculate discount
                    $discount = 0;
                    if ($product['sale_price'] && $product['price'] > 0) {
                        $discount = round((($product['price'] - $product['sale_price']) / $product['price']) * 100);
                    }
                    
                    // Default image
                    $image = $product['main_image'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&q=80';
                    ?>
                    <div class="product-card <?= $idx === 14 ? 'hide-on-mobile-grid' : '' ?>">
                        <div class="product-image">
                            <a href="<?= BASE_URL ?>/product/<?= $product['slug'] ?>" class="product-image-link" aria-label="View <?= htmlspecialchars($product['name']) ?>"></a>
                            <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($product['name']) ?>" width="400" height="400" loading="lazy">
                            
                            <!-- Badges -->
                            <div class="product-badges">
                                <span class="badge badge-new">New</span>
                                <?php if ($discount > 0): ?>
                                    <span class="badge badge-sale">-<?= $discount ?>%</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Wishlist Button -->
                            <button class="product-wishlist" data-product-id="<?= $product['id'] ?>" aria-label="Add to wishlist">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                </svg>
                            </button>
                            
                            <!-- Quick Actions -->
                            <div class="product-actions">
                                <button class="btn btn-primary product-add-cart" data-product-id="<?= $product['id'] ?>" style="flex: 1;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                                    </svg>
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                        
                        <div class="product-content">
                            <h3 class="product-name">
                                <a href="<?= BASE_URL ?>/product/<?= $product['slug'] ?>"><?= htmlspecialchars($product['name']) ?></a>
                            </h3>
                            <div class="product-price">
                                <span class="price-current">
                                    <?= formatPrice($product['sale_price'] ?: $product['price']) ?>
                                </span>
                                <?php if ($product['sale_price']): ?>
                                    <span class="price-original"><?= formatPrice($product['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php
                            $colorsArr = [];
                            if (!empty($product['colors'])) {
                                $decoded = json_decode($product['colors'], true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !isset($decoded[0]) && !isset($decoded['color'])) {
                                    $colorsArr = $decoded;
                                }
                            }
                            ?>
                            <?php if (!empty($colorsArr)): ?>
                            <div style="display: flex; gap: 0.35rem; margin-top: 0.75rem; flex-wrap: wrap;">
                                <?php foreach ($colorsArr as $cName => $cData): ?>
                                    <?php
                                    $cImg = '';
                                    if (!empty($cData['main_image'])) {
                                        $cImg = strpos($cData['main_image'], 'http') === 0 ? $cData['main_image'] : BASE_URL . '/' . ltrim($cData['main_image'], '/');
                                    }
                                    ?>
                                    <div style="width: 14px; height: 14px; border-radius: 50%; background-color: <?= htmlspecialchars($cData['hex'] ?? '#000') ?>; cursor: pointer; border: 1px solid rgba(0,0,0,0.15); box-shadow: inset 0 1px 2px rgba(0,0,0,0.1); transition: transform 0.2s;" title="<?= htmlspecialchars($cName) ?>" <?= $cImg ? 'onmouseover="this.closest(\'.product-card\').querySelector(\'.product-image img\').src=\''.htmlspecialchars($cImg).'\';"' : '' ?> onmouseenter="this.style.transform='scale(1.2)'" onmouseleave="this.style.transform='scale(1)'"></div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 2.5rem;">
                <a href="<?= BASE_URL ?>/shop" class="btn btn-outline btn-lg">
                    View All Products
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                    </svg>
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Hot Deals Section -->
    <?php if (!empty($hotDeals)): ?>
    <section class="section section-bg <?= $isClothingBrandTheme ? 'clothing-brand-deals' : '' ?>">
        <div class="container">
            <div class="section-header">
                <div>
                    <h2 class="section-title"><?= htmlspecialchars($hotDealsSectionTitle) ?></h2>
                    <p class="section-subtitle"><?= htmlspecialchars($hotDealsSectionSubtitle) ?></p>
                </div>
                <a href="<?= htmlspecialchars($hotDealsCtaUrl) ?>" class="btn btn-outline">
                    <?= htmlspecialchars($hotDealsCtaLabel) ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                    </svg>
                </a>
            </div>
            
            <div class="deals-grid">
                <?php foreach ($hotDeals as $deal): ?>
                    <?php
                    $dealTitle = trim((string)($deal['title'] ?? 'Special Deal'));
                    $dealSubtitle = trim((string)($deal['subtitle'] ?? 'Limited Offer'));
                    $dealBadgeText = trim((string)($deal['badge_text'] ?? ''));
                    $dealBadgeStyle = trim((string)($deal['badge_style'] ?? 'primary'));
                    if (!in_array($dealBadgeStyle, ['primary', 'success', 'danger', 'warning'], true)) {
                        $dealBadgeStyle = 'primary';
                    }
                    $dealTimerText = trim((string)($deal['timer_text'] ?? ''));
                    $dealCountdownEndAt = trim((string)($deal['countdown_end_at'] ?? ''));
                    $dealCountdownTs = null;
                    if ($dealCountdownEndAt !== '') {
                        $parsedCountdownTs = strtotime($dealCountdownEndAt);
                        if ($parsedCountdownTs !== false) {
                            $dealCountdownTs = $parsedCountdownTs;
                        }
                    }
                    if ($dealCountdownTs === null && preg_match('/^(\d{1,2}):([0-5]\d):([0-5]\d)$/', $dealTimerText, $durationMatch)) {
                        $dealCountdownTs = time() + (intval($durationMatch[1]) * 3600) + (intval($durationMatch[2]) * 60) + intval($durationMatch[3]);
                    }
                    if ($dealTimerText === '' && $dealCountdownTs !== null) {
                        $dealTimerText = formatCountdownDisplay($dealCountdownTs - time());
                    }
                    $dealImage = trim((string)($deal['image_path'] ?? ''));
                    if ($dealImage === '') {
                        $dealImage = 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&q=80';
                    }
                    $dealLink = trim((string)($deal['link_url'] ?? 'sale'));
                    if ($dealLink === '') {
                        $dealLink = 'sale';
                    }
                    $dealLink = cleanUrl($dealLink);
                    $overlayStart = trim((string)($deal['overlay_start'] ?? 'rgba(15, 118, 110, 0.84)'));
                    $overlayEnd = trim((string)($deal['overlay_end'] ?? 'rgba(11, 91, 85, 0.62)'));
                    $imagePosition = trim((string)($deal['image_position'] ?? 'center center'));
                    if ($imagePosition === '') {
                        $imagePosition = 'center center';
                    }
                    ?>
                    <a href="<?= htmlspecialchars($dealLink) ?>" class="deal-card">
                        <img src="<?= htmlspecialchars($dealImage) ?>" alt="<?= htmlspecialchars($dealTitle) ?>" style="object-position: <?= htmlspecialchars($imagePosition) ?>;">

                        <?php if ($dealBadgeText !== ''): ?>
                        <div class="deal-badge">
                            <span class="badge badge-<?= htmlspecialchars($dealBadgeStyle) ?>"><?= htmlspecialchars($dealBadgeText) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($dealTimerText !== '' || $dealCountdownTs !== null): ?>
                        <div class="deal-timer"<?= $dealCountdownTs !== null ? ' data-deal-end-ts="' . intval($dealCountdownTs) . '"' : '' ?>>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                            </svg>
                            <span class="deal-timer-value"><?= htmlspecialchars($dealTimerText !== '' ? $dealTimerText : '00:00:00') ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="deal-content">
                            <p class="deal-subtitle"><?= htmlspecialchars($dealSubtitle) ?></p>
                            <h3 class="deal-title"><?= htmlspecialchars($dealTitle) ?></h3>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Featured Products Section -->
    <section class="section <?= $isClothingBrandTheme ? 'clothing-brand-featured' : '' ?>">
        <div class="container">
            <div class="section-header" style="margin-bottom: 2.5rem; text-align: center; display: flex; flex-direction: column; align-items: center;">
                <div style="width: 100%;">
                    <?php if ($isClothingBrandTheme): ?><p class="clothing-brand-eyebrow">Curated for you</p><?php endif; ?>
                    <h2 class="section-title"><?= htmlspecialchars($themeConfig['featured_section_title']) ?></h2>
                    <p class="section-subtitle" style="max-width: 500px; margin: 0.5rem auto 0;">
                        Discover our most popular items, carefully selected for quality and style
                    </p>
                </div>
            </div>
            
            <div class="products-grid">
                <?php foreach ($featuredProducts as $idx => $product): ?>
                    <?php
                    // Calculate discount
                    $discount = 0;
                    if ($product['sale_price'] && $product['price'] > 0) {
                        $discount = round((($product['price'] - $product['sale_price']) / $product['price']) * 100);
                    }
                    
                    // Get average rating (mock for demo)
                    $rating = 4.5 + (rand(0, 5) / 10);
                    $reviewCount = rand(50, 300);
                    
                    // Default image
                    $image = $product['main_image'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&q=80';
                    ?>
                    <div class="product-card <?= $idx === 14 ? 'hide-on-mobile-grid' : '' ?>">
                        <div class="product-image">
                            <a href="<?= BASE_URL ?>/product/<?= $product['slug'] ?>" class="product-image-link" aria-label="View <?= htmlspecialchars($product['name']) ?>"></a>
                            <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($product['name']) ?>" width="400" height="400" loading="lazy">
                            
                            <!-- Badges -->
                            <div class="product-badges">
                                <?php if ($product['is_new']): ?>
                                    <span class="badge badge-new">New</span>
                                <?php endif; ?>
                                <?php if ($product['is_bestseller']): ?>
                                    <span class="badge badge-bestseller">Best Seller</span>
                                <?php endif; ?>
                                <?php if ($discount > 0): ?>
                                    <span class="badge badge-sale">-<?= $discount ?>%</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Wishlist Button -->
                            <button class="product-wishlist" data-product-id="<?= $product['id'] ?>" aria-label="Add to wishlist">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                </svg>
                            </button>
                            
                            <!-- Quick Actions -->
                            <div class="product-actions">
                                <button class="btn btn-primary product-add-cart" data-product-id="<?= $product['id'] ?>" style="flex: 1;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                                    </svg>
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                        
                        <div class="product-content">
                            <h3 class="product-name">
                                <a href="<?= BASE_URL ?>/product/<?= $product['slug'] ?>"><?= htmlspecialchars($product['name']) ?></a>
                            </h3>
                            <div class="product-price">
                                <span class="price-current">
                                    <?= formatPrice($product['sale_price'] ?: $product['price']) ?>
                                </span>
                                <?php if ($product['sale_price']): ?>
                                    <span class="price-original"><?= formatPrice($product['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php
                            $colorsArr = [];
                            if (!empty($product['colors'])) {
                                $decoded = json_decode($product['colors'], true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !isset($decoded[0]) && !isset($decoded['color'])) {
                                    $colorsArr = $decoded;
                                }
                            }
                            ?>
                            <?php if (!empty($colorsArr)): ?>
                            <div style="display: flex; gap: 0.35rem; margin-top: 0.75rem; flex-wrap: wrap;">
                                <?php foreach ($colorsArr as $cName => $cData): ?>
                                    <?php
                                    $cImg = '';
                                    if (!empty($cData['main_image'])) {
                                        $cImg = strpos($cData['main_image'], 'http') === 0 ? $cData['main_image'] : BASE_URL . '/' . ltrim($cData['main_image'], '/');
                                    }
                                    ?>
                                    <div style="width: 14px; height: 14px; border-radius: 50%; background-color: <?= htmlspecialchars($cData['hex'] ?? '#000') ?>; cursor: pointer; border: 1px solid rgba(0,0,0,0.15); box-shadow: inset 0 1px 2px rgba(0,0,0,0.1); transition: transform 0.2s;" title="<?= htmlspecialchars($cName) ?>" <?= $cImg ? 'onmouseover="this.closest(\'.product-card\').querySelector(\'.product-image img\').src=\''.htmlspecialchars($cImg).'\';"' : '' ?> onmouseenter="this.style.transform='scale(1.2)'" onmouseleave="this.style.transform='scale(1)'"></div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 2.5rem;">
                <a href="<?= BASE_URL ?>/shop" class="btn btn-outline btn-lg">
                    View All Products
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                    </svg>
                </a>
            </div>
        </div>
    </section>


<?php require_once __DIR__ . '/includes/footer.php'; ?>



