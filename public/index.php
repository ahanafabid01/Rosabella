<?php
/**
 * KARTLY - Homepage
 */
$pageTitle = 'Home';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

// Get featured products
$stmt = $db->query("SELECT p.*, c.name as category_name 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.status = 'active' AND p.is_featured = 1 
                    ORDER BY p.created_at DESC 
                    LIMIT 8");
$featuredProducts = $stmt->fetchAll();

// Get categories
$stmt = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order LIMIT 6");
$categories = $stmt->fetchAll();

// Get Hero Slides
$allHeroSlides = [];
try {
    $stmt = $db->query("SELECT * FROM hero_slides WHERE status = 'active' ORDER BY sort_order ASC, created_at DESC");
    $allHeroSlides = $stmt->fetchAll();
} catch (Throwable $e) {}

$mainSlides = [];
$sideTopSlide = null;
$sideBottomSlide = null;

foreach ($allHeroSlides as $slide) {
    if ($slide['position'] === 'main') {
        $mainSlides[] = $slide;
    } elseif ($slide['position'] === 'side_top' && !$sideTopSlide) {
        $sideTopSlide = $slide;
    } elseif ($slide['position'] === 'side_bottom' && !$sideBottomSlide) {
        $sideBottomSlide = $slide;
    }
}

// Fallbacks if database is empty
if (empty($mainSlides)) {
    $mainSlides = [
        ['image_path' => 'https://images.unsplash.com/photo-1542204165-65bf26472b9b?w=1200&q=80', 'link_url' => 'category/accessories', 'title' => '', 'subtitle' => ''],
        ['image_path' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=1200&q=80', 'link_url' => 'category/electronics', 'title' => '', 'subtitle' => '']
    ];
}
if (!$sideTopSlide) {
    $sideTopSlide = ['image_path' => 'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=600&q=80', 'link_url' => 'shop', 'title' => '', 'subtitle' => ''];
}
if (!$sideBottomSlide) {
    $sideBottomSlide = ['image_path' => 'https://images.unsplash.com/photo-1550009158-9ebf6d1736eb?w=600&q=80', 'link_url' => 'sale', 'title' => '', 'subtitle' => ''];
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
$hotDealsCtaUrl = getSetting('home_deals_cta_url') ?: 'products.php?filter=sale';

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
            'link_url' => 'products.php?category=electronics',
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
            'link_url' => 'products.php?category=fashion',
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
            'link_url' => 'products.php?category=home-living',
            'overlay_start' => 'rgba(15, 118, 110, 0.84)',
            'overlay_end' => 'rgba(13, 89, 97, 0.62)',
            'image_position' => 'center center',
        ],
    ];
}

// Get latest approved testimonials (recent 3)
$stmt = $db->prepare("
    SELECT
        r.rating,
        r.title,
        r.review AS content,
        r.created_at,
        p.name AS product_name,
        u.first_name,
        u.last_name
    FROM reviews r
    LEFT JOIN products p ON p.id = r.product_id
    LEFT JOIN users u ON u.id = r.user_id
    WHERE r.status = 'approved'
    ORDER BY r.created_at DESC
    LIMIT 3
");
$stmt->execute();
$testimonials = [];
foreach ($stmt->fetchAll() as $reviewRow) {
    $customerName = trim((string)($reviewRow['first_name'] ?? '') . ' ' . (string)($reviewRow['last_name'] ?? ''));
    if ($customerName === '') {
        $customerName = 'Verified Customer';
    }

    $reviewContent = trim((string)($reviewRow['content'] ?? ''));
    $reviewTitle = trim((string)($reviewRow['title'] ?? ''));
    $displayContent = $reviewContent !== ''
        ? $reviewContent
        : ($reviewTitle !== '' ? $reviewTitle : 'Rated this product after purchase.');

    $testimonials[] = [
        'name' => $customerName,
        'role' => 'Verified Buyer',
        'avatar' => 'https://ui-avatars.com/api/?background=0f766e&color=fff&name=' . rawurlencode($customerName),
        'rating' => max(1, min(5, intval($reviewRow['rating'] ?? 5))),
        'title' => $reviewTitle,
        'content' => $displayContent,
        'product_name' => trim((string)($reviewRow['product_name'] ?? '')),
    ];
}
?>

    <!-- Hero Section -->
    <section class="section" style="padding-top: 1.5rem; padding-bottom: 2rem;">
        <div class="container">
            <div class="hero-bento-grid">
                
                <!-- Main Banner (Left Side Slider) -->
                <div class="hero-main-banner">
                    <div class="hero-slider">
                        <?php foreach ($mainSlides as $index => $slide): ?>
                            <?php $imgUrl = strpos($slide['image_path'], 'http') === 0 ? $slide['image_path'] : BASE_URL . '/' . htmlspecialchars($slide['image_path']); ?>
                            <div class="hero-slide <?= $index === 0 ? 'active' : '' ?>">
                                <a href="<?= BASE_URL . '/' . htmlspecialchars($slide['link_url'] ?? '') ?>" style="display: block; width: 100%; height: 100%; position: relative;">
                                    <img src="<?= $imgUrl ?>" alt="<?= htmlspecialchars($slide['title'] ?? 'Hero Slide') ?>">
                                    <?php if (!empty($slide['title']) || !empty($slide['subtitle'])): ?>
                                        <div style="position: absolute; bottom: 20%; left: 10%; color: #fff; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">
                                            <?php if (!empty($slide['title'])): ?>
                                                <h2 style="font-size: 2.5rem; margin: 0 0 0.5rem;"><?= htmlspecialchars($slide['title']) ?></h2>
                                            <?php endif; ?>
                                            <?php if (!empty($slide['subtitle'])): ?>
                                                <p style="font-size: 1.2rem; margin: 0;"><?= htmlspecialchars($slide['subtitle']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            </div>
                        <?php endforeach; ?>

                        <!-- Navigation -->
                        <?php if (count($mainSlides) > 1): ?>
                            <div class="hero-nav hero-nav-prev">
                                <button aria-label="Previous slide">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="15 18 9 12 15 6"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="hero-nav hero-nav-next">
                                <button aria-label="Next slide">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="9 18 15 12 9 6"/>
                                    </svg>
                                </button>
                            </div>
                            
                            <!-- Dots -->
                            <div class="hero-dots">
                                <?php foreach ($mainSlides as $index => $slide): ?>
                                    <button class="hero-dot <?= $index === 0 ? 'active' : '' ?>" aria-label="Go to slide <?= $index + 1 ?>"></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Side Banners (Right Side) -->
                <div class="hero-side-banners">
                    <!-- Top Side Banner -->
                    <?php 
                        $topImgUrl = strpos($sideTopSlide['image_path'], 'http') === 0 ? $sideTopSlide['image_path'] : BASE_URL . '/' . htmlspecialchars($sideTopSlide['image_path']); 
                    ?>
                    <a href="<?= BASE_URL . '/' . htmlspecialchars($sideTopSlide['link_url'] ?? '') ?>" class="hero-side-banner">
                        <img src="<?= $topImgUrl ?>" alt="<?= htmlspecialchars($sideTopSlide['title'] ?? 'Top Banner') ?>">
                        <?php if (!empty($sideTopSlide['title'])): ?>
                            <div style="position: absolute; bottom: 15%; left: 10%; color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,0.5);">
                                <h3 style="font-size: 1.5rem; margin: 0;"><?= htmlspecialchars($sideTopSlide['title']) ?></h3>
                            </div>
                        <?php endif; ?>
                    </a>
                    
                    <!-- Bottom Side Banner -->
                    <?php 
                        $bottomImgUrl = strpos($sideBottomSlide['image_path'], 'http') === 0 ? $sideBottomSlide['image_path'] : BASE_URL . '/' . htmlspecialchars($sideBottomSlide['image_path']); 
                    ?>
                    <a href="<?= BASE_URL . '/' . htmlspecialchars($sideBottomSlide['link_url'] ?? '') ?>" class="hero-side-banner flash-sale">
                        <img src="<?= $bottomImgUrl ?>" alt="<?= htmlspecialchars($sideBottomSlide['title'] ?? 'Bottom Banner') ?>">
                        <?php if (!empty($sideBottomSlide['title'])): ?>
                            <div style="position: absolute; bottom: 15%; left: 10%; color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,0.5);">
                                <h3 style="font-size: 1.5rem; margin: 0;"><?= htmlspecialchars($sideBottomSlide['title']) ?></h3>
                            </div>
                        <?php endif; ?>
                    </a>
                </div>

            </div>
        </div>
    </section>

    <!-- Categories Section -->
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
                        // Load image from database, fallback to a default placeholder if empty
                        $catImage = !empty($category['image']) ? BASE_URL . '/' . htmlspecialchars($category['image']) : 'https://raw.githubusercontent.com/mdn/learning-area/master/html/multimedia-and-embedding/images-in-html/dinosaur_small.jpg';
                        ?>
                        <a href="<?= BASE_URL ?>/category/<?= urlencode($category['slug']) ?>" class="category-card-clean">
                            <div class="category-card-img-wrap">
                                <img src="<?= $catImage ?>" alt="<?= htmlspecialchars($category['name']) ?>" loading="lazy">
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

    <!-- Hot Deals Section -->
    <?php if (!empty($hotDeals)): ?>
    <section class="section section-bg">
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
                    $dealLink = trim((string)($deal['link_url'] ?? 'products.php?filter=sale'));
                    if ($dealLink === '') {
                        $dealLink = 'products.php?filter=sale';
                    }
                    $overlayStart = trim((string)($deal['overlay_start'] ?? 'rgba(15, 118, 110, 0.84)'));
                    $overlayEnd = trim((string)($deal['overlay_end'] ?? 'rgba(11, 91, 85, 0.62)'));
                    $imagePosition = trim((string)($deal['image_position'] ?? 'center center'));
                    if ($imagePosition === '') {
                        $imagePosition = 'center center';
                    }
                    ?>
                    <a href="<?= htmlspecialchars($dealLink) ?>" class="deal-card">
                        <img src="<?= htmlspecialchars($dealImage) ?>" alt="<?= htmlspecialchars($dealTitle) ?>" style="object-position: <?= htmlspecialchars($imagePosition) ?>;">
                        <div class="deal-overlay" style="background: linear-gradient(135deg, <?= htmlspecialchars($overlayStart) ?>, <?= htmlspecialchars($overlayEnd) ?>);"></div>
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
    <section class="section">
        <div class="container">
            <div class="section-header" style="text-align: center; flex-direction: column; margin-bottom: 2.5rem;">
                <div style="display: inline-flex; align-items: center; gap: 0.5rem; background-color: var(--color-primary-light); color: var(--color-primary); padding: 0.375rem 1rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 500; margin-bottom: 1rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                    Handpicked for You
                </div>
                <h2 class="section-title">Featured Products</h2>
                <p class="section-subtitle" style="max-width: 500px; margin: 0.5rem auto 0;">
                    Discover our most popular items, carefully selected for quality and style
                </p>
            </div>
            
            <div class="products-grid">
                <?php foreach ($featuredProducts as $product): ?>
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
                    <div class="product-card">
                        <div class="product-image">
                            <a href="product.php?id=<?= $product['id'] ?>" class="product-image-link" aria-label="View <?= htmlspecialchars($product['name']) ?>"></a>
                            <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                            
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
                                <button class="btn btn-secondary btn-icon product-quick-view" data-product-id="<?= $product['id'] ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="product-content">
                            <span class="product-category"><?= htmlspecialchars($product['category_name'] ?? 'General') ?></span>
                            <h3 class="product-name">
                                <a href="product.php?id=<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></a>
                            </h3>
                            
                            <div class="product-rating">
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= floor($rating)): ?>
                                            <svg class="star" viewBox="0 0 24 24" fill="currentColor">
                                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                            </svg>
                                        <?php else: ?>
                                            <svg class="star empty" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                            </svg>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-count">(<?= $reviewCount ?>)</span>
                            </div>
                            
                            <div class="product-price">
                                <span class="price-current">
                                    <?= formatPrice($product['sale_price'] ?: $product['price']) ?>
                                </span>
                                <?php if ($product['sale_price']): ?>
                                    <span class="price-original"><?= formatPrice($product['price']) ?></span>
                                <?php endif; ?>
                            </div>
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

    <!-- Testimonials Section -->
    <section class="section section-bg">
        <div class="container">
            <div class="section-header" style="text-align: center; margin-bottom: 2.5rem;">
                <h2 class="section-title">What Our Customers Think</h2>
                <p class="section-subtitle">Join thousands of satisfied shoppers</p>
            </div>
            
            <?php if (!empty($testimonials)): ?>
            <div class="testimonials-grid">
                <?php foreach ($testimonials as $testimonial): ?>
                    <div class="testimonial-card">
                        <svg class="testimonial-quote" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V21c0 1 0 1 1 1z"/>
                            <path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/>
                        </svg>
                        
                        <div class="testimonial-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $testimonial['rating']): ?>
                                    <svg class="star" viewBox="0 0 24 24" fill="#ffc107">
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                    </svg>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        
                        <p class="testimonial-text">"<?= htmlspecialchars($testimonial['content']) ?>"</p>
                        
                        <?php if ($testimonial['product_name'] !== ''): ?>
                        <div class="testimonial-product">
                            Purchased: <span><?= htmlspecialchars($testimonial['product_name']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="testimonial-author">
                            <img src="<?= htmlspecialchars($testimonial['avatar']) ?>" alt="<?= htmlspecialchars($testimonial['name']) ?>" class="testimonial-avatar">
                            <div class="testimonial-info">
                                <h4><?= htmlspecialchars($testimonial['name']) ?></h4>
                                <p><?= htmlspecialchars($testimonial['role']) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 1rem 0 0; color: var(--color-text-light);">
                No approved customer reviews yet.
            </div>
            <?php endif; ?>
            
            <!-- Trust Stats -->
            <div class="trust-stats">
                <div class="trust-stat">
                    <div class="trust-stat-value">50K+</div>
                    <div class="trust-stat-label">Happy Customers</div>
                </div>
                <div class="trust-stat">
                    <div class="trust-stat-value">4.9</div>
                    <div class="trust-stat-label">Average Rating</div>
                </div>
                <div class="trust-stat">
                    <div class="trust-stat-value">100K+</div>
                    <div class="trust-stat-label">Products Sold</div>
                </div>
                <div class="trust-stat">
                    <div class="trust-stat-value">24/7</div>
                    <div class="trust-stat-label">Customer Support</div>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



