<?php
/**
 * Rosabella - Single Product Page
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

// Support both slug-based URLs (pretty) and id-based URLs (legacy)
$slug = trim($_GET['slug'] ?? '');
$productId = intval($_GET['id'] ?? 0);

if ($slug !== '') {
    $stmt = $db->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.slug = ? LIMIT 1");
    $stmt->execute([$slug]);
} elseif ($productId > 0) {
    $stmt = $db->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? LIMIT 1");
    $stmt->execute([$productId]);
} else {
    header('Location: ' . BASE_URL . '/shop');
    exit;
}

$product = $stmt->fetch();

if (!$product) {
    header('Location: ' . BASE_URL . '/shop');
    exit;
}

// Redirect legacy ?id= URLs to the clean slug URL
if ($productId > 0 && !empty($product['slug'])) {
    header('Location: ' . BASE_URL . '/product/' . $product['slug'], true, 301);
    exit;
}

function parseProductGalleryImages(?string $raw): array
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return array_values(array_filter(array_map('trim', $decoded)));
    }

    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}

$fallbackImage = 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&q=80';
$productImages = [];
if (!empty($product['main_image'])) {
    $productImages[] = $product['main_image'];
}
$productImages = array_merge($productImages, parseProductGalleryImages($product['gallery_images'] ?? null));
$productImages = array_values(array_unique(array_filter($productImages)));
if (empty($productImages)) {
    $productImages[] = $fallbackImage;
}

// Ensure all local paths are absolute URLs (fixes broken images on slug-based URLs)
$productImages = array_map(function($path) {
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    return BASE_URL . '/' . ltrim($path, '/');
}, $productImages);

$primaryProductImage = $productImages[0];

$pageTitle = $product['name'];
$footerProductDescription = trim((string)($product['description'] ?? $product['short_description'] ?? ''));

// Get related products
$stmt = $db->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? AND status = 'active' LIMIT 4");
$stmt->execute([$product['category_id'], $productId]);
$relatedProducts = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Breadcrumb -->
    <section class="section section-bg" style="padding: 1rem 0 1.1rem;">
        <div class="container">
            <nav style="font-size: 0.875rem; color: var(--color-text-light);">
                <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light); display: inline-flex; align-items: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
                <span> / </span>
                <a href="<?= BASE_URL ?>/shop" style="color: var(--color-text-light);">Products</a>
                <span> / </span>
                <a href="<?= BASE_URL ?>/products?category=<?= urlencode($product['category_slug']) ?>" style="color: var(--color-text-light);"><?= htmlspecialchars($product['category_name']) ?></a>
                <span> / </span>
                <span style="color: var(--color-text);"><?= htmlspecialchars($product['name']) ?></span>
            </nav>
        </div>
    </section>

    <!-- Product Detail -->
    <section class="section product-detail-page" style="padding-top: 1.2rem;">
        <div class="container">
            <div class="product-detail-grid">
                
                <!-- Product Images -->
                <div>
                    <div class="product-gallery-main">
                        <img id="product-main-image" src="<?= htmlspecialchars($primaryProductImage) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    </div>
                    <div class="product-gallery-thumbs" style="<?= count($productImages) <= 1 ? 'display: none;' : '' ?>">
                        <?php foreach ($productImages as $index => $imagePath): ?>
                            <button type="button" class="product-thumb-btn <?= $index === 0 ? 'active' : '' ?>" data-image-src="<?= htmlspecialchars($imagePath) ?>" data-image-alt="<?= htmlspecialchars($product['name']) ?>">
                                <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Product Info -->
                <div>
                    <h1 style="font-size: 1.75rem; font-weight: 500; margin: 0 0 1.1rem; color: #1e293b; letter-spacing: -0.01em; line-height: 1.3;"><?= htmlspecialchars($product['name']) ?></h1>

                    <!-- Meta Data: separator-line style -->
                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0; margin-bottom: 1.5rem; font-size: 0.875rem; color: var(--color-text-light); border-top: 1px solid var(--color-border); border-bottom: 1px solid var(--color-border); padding: 0.65rem 0;">
                        <?php if (!empty($product['brand'])): ?>
                        <div style="padding: 0 1rem 0 0; display: flex; align-items: center; gap: 0.3rem;">
                            <span>Brand:</span>
                            <strong style="color: var(--color-text); font-weight: 600;"><?= htmlspecialchars($product['brand']) ?></strong>
                        </div>
                        <span style="width: 1px; height: 1rem; background: var(--color-border); display: inline-block; margin-right: 1rem;"></span>
                        <?php endif; ?>

                        <?php if (!empty($product['sku'])): ?>
                        <div style="padding: 0 1rem 0 0; display: flex; align-items: center; gap: 0.3rem;">
                            <span>SKU:</span>
                            <strong style="color: var(--color-text); font-weight: 600;"><?= htmlspecialchars($product['sku']) ?></strong>
                        </div>
                        <span style="width: 1px; height: 1rem; background: var(--color-border); display: inline-block; margin-right: 1rem;"></span>
                        <?php endif; ?>

                        <?php if (!empty($product['style'])): ?>
                        <div style="padding: 0 1rem 0 0; display: flex; align-items: center; gap: 0.3rem;">
                            <span>Style:</span>
                            <strong style="color: var(--color-text); font-weight: 600;"><?= htmlspecialchars($product['style']) ?></strong>
                        </div>
                        <span style="width: 1px; height: 1rem; background: var(--color-border); display: inline-block; margin-right: 1rem;"></span>
                        <?php endif; ?>

                        <div style="display: flex; align-items: center; gap: 0.4rem;">
                            <span>Availability:</span>
                            <?php if ($product['stock_quantity'] > 0): ?>
                            <strong style="color: #16a34a; font-weight: 600;">&#10003; In Stock</strong>
                            <?php else: ?>
                            <strong style="color: var(--color-danger); font-weight: 600;">&#10007; Out of Stock</strong>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Price -->
                    <div style="display: flex; align-items: baseline; gap: 0.75rem; margin-bottom: 1.75rem;">
                        <span style="font-size: 1.85rem; font-weight: 400; color: var(--color-primary);"><?= formatPrice($product['sale_price'] ?: $product['price']) ?></span>
                        <?php if ($product['sale_price']): ?>
                        <span style="font-size: 1.1rem; color: var(--color-text-light); text-decoration: line-through; font-weight: 400;"><?= formatPrice($product['price']) ?></span>
                        <?php $discount = round((($product['price'] - $product['sale_price']) / $product['price']) * 100); ?>
                        <span style="background: #fef2f2; color: #dc2626; font-size: 0.8rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 999px; letter-spacing: 0.02em;"><?= $discount ?>% OFF</span>
                        <?php endif; ?>
                    </div>

                    <!-- Key Features -->
                    <?php if (!empty($product['key_features'])): ?>
                    <div style="margin-bottom: 1.75rem;">
                        <h3 style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-text-light); margin-bottom: 0.6rem;">Key Features</h3>
                        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.4rem;">
                            <?php foreach (explode("\n", $product['key_features']) as $feature): ?>
                                <?php if (trim($feature) !== ''): ?>
                                <li style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.9rem; color: var(--color-text);">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" style="flex-shrink:0; margin-top: 3px;"><polyline points="20 6 9 17 4 12"/></svg>
                                    <?= htmlspecialchars(trim($feature)) ?>
                                </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Colors -->
                    <!-- Colors -->
                    <?php 
                    $colorsJson = $product['colors'] ?? '';
                    $colorsArr = [];
                    $isNewColorFormat = false;
                    if (!empty($colorsJson)) {
                        $decoded = json_decode($colorsJson, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            if (!isset($decoded[0]) && !isset($decoded['color'])) {
                                $isNewColorFormat = true;
                                $colorsArr = $decoded;
                            } else {
                                $colorsArr = $decoded;
                            }
                        }
                    }
                    ?>
                    <?php if (!empty($colorsArr) && $isNewColorFormat): ?>
                    <div style="margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 0.75rem; color: #1e293b;">Color: <span id="selected-color-name" style="font-weight: 400; color: var(--color-text-light);">Select color</span></h3>
                        <div class="color-swatches" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                            <?php foreach ($colorsArr as $cName => $cData): ?>
                                <?php 
                                    // Prepare valid URLs for JS
                                    $readyImages = [];
                                    
                                    // Main Image
                                    if (!empty($cData['main_image'])) {
                                        $readyImages[] = strpos($cData['main_image'], 'http') === 0 ? $cData['main_image'] : BASE_URL . '/' . ltrim($cData['main_image'], '/');
                                    }
                                    
                                    // Gallery Images
                                    if (!empty($cData['gallery_images'])) {
                                        foreach ($cData['gallery_images'] as $img) {
                                            $readyImages[] = strpos($img, 'http') === 0 ? $img : BASE_URL . '/' . ltrim($img, '/');
                                        }
                                    }
                                    // Fallback to legacy format
                                    if (!empty($cData['images'])) {
                                        foreach ($cData['images'] as $img) {
                                            $readyImages[] = strpos($img, 'http') === 0 ? $img : BASE_URL . '/' . ltrim($img, '/');
                                        }
                                    }
                                ?>
                                <button type="button" class="color-swatch-btn" data-color-name="<?= htmlspecialchars($cName) ?>" data-color-images="<?= htmlspecialchars(json_encode($readyImages)) ?>" style="width: 36px; height: 36px; border-radius: 50%; border: 2px solid transparent; background-color: <?= htmlspecialchars($cData['hex'] ?? '#000') ?>; cursor: pointer; transition: all 0.2s; outline-offset: 2px; box-shadow: 0 0 0 1px rgba(0,0,0,0.1);" title="<?= htmlspecialchars($cName) ?>" aria-label="<?= htmlspecialchars($cName) ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php elseif (!empty($colorsArr)): ?>
                    <div style="margin-bottom: 1.5rem;">
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <?php foreach ($colorsArr as $c): ?>
                                <?php if (!empty($c['image'])): ?>
                                <a href="<?= BASE_URL ?>/product/<?= htmlspecialchars($c['url'] ?? '#') ?>" style="display: block; border: 2px solid <?= ($c['url'] ?? '') === $product['slug'] ? 'var(--color-primary)' : 'var(--color-border)' ?>; padding: 2px; border-radius: 4px;" title="<?= htmlspecialchars($c['color'] ?? '') ?>">
                                    <img src="<?= htmlspecialchars(strpos($c['image'], 'http') === 0 ? $c['image'] : BASE_URL . '/' . ltrim($c['image'], '/')) ?>" alt="<?= htmlspecialchars($c['color'] ?? '') ?>" style="width: 50px; height: 50px; object-fit: contain;">
                                </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Sizes -->
                    <?php if (!empty($product['sizes'])): ?>
                    <div style="margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 0.75rem; color: #1e293b;">Sizes</h3>
                        <div class="size-selector" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <?php foreach (explode(",", $product['sizes']) as $size): ?>
                                <?php $size = trim($size); if ($size !== ''): ?>
                                <button type="button" class="size-btn" data-size="<?= htmlspecialchars($size) ?>" style="min-width: 3rem; padding: 0.5rem; text-align: center; border: 1px solid var(--color-border); background: #fff; color: var(--color-text); border-radius: 4px; font-size: 0.9rem; cursor: pointer; transition: all 0.2s;"><?= htmlspecialchars($size) ?></button>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Variants -->
                    <?php if (!empty($product['variants'])): ?>
                    <div style="margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">Variant:</h3>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <?php foreach (explode(",", $product['variants']) as $variant): ?>
                                <?php if (trim($variant) !== ''): ?>
                                <button type="button" class="btn btn-outline variant-btn" data-variant="<?= htmlspecialchars(trim($variant)) ?>" style="padding: 0.5rem 1rem; font-size: 0.875rem; border-color: var(--color-border); color: var(--color-text); transition: all 0.2s;"><?= htmlspecialchars(trim($variant)) ?></button>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Add to Cart Form -->
                    <form action="<?= BASE_URL ?>/api/cart.php" method="POST" id="add-to-cart-form" class="add-to-cart-form" data-product-id="<?= $product['id'] ?>" style="margin-bottom: 1.5rem;">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <?php if (!empty($product['sizes'])): ?>
                        <input type="hidden" name="selected_size" id="selected-size-input" required>
                        <?php endif; ?>
                        <?php if (!empty($product['variants'])): ?>
                        <input type="hidden" name="selected_variant" id="selected-variant-input" required>
                        <?php endif; ?>
                        
                        <?php if (!empty($colorsArr) && $isNewColorFormat): ?>
                        <input type="hidden" name="selected_color" id="selected-color-input" required>
                        <?php endif; ?>
                        
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                            <label style="font-weight: 500;">Quantity:</label>
                            <div class="quantity-input" style="display: flex; align-items: center; border: 1px solid var(--color-border); border-radius: var(--radius-md);">
                                <button type="button" class="quantity-minus" style="width: 40px; height: 40px; border: none; background: none; cursor: pointer; font-size: 1.25rem;">-</button>
                                <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock_quantity'] ?>" style="width: 60px; text-align: center; border: none; font-size: 1rem;">
                                <button type="button" class="quantity-plus" style="width: 40px; height: 40px; border: none; background: none; cursor: pointer; font-size: 1.25rem;">+</button>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <button type="submit" class="btn btn-primary btn-lg" style="flex: 1;" <?= $product['stock_quantity'] <= 0 ? 'disabled' : '' ?>>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                Add to Cart
                            </button>
                            <button type="button" class="btn btn-outline btn-lg buy-now-btn" <?= $product['stock_quantity'] <= 0 ? 'disabled' : '' ?>>Buy Now</button>
                        </div>
                    </form>
                    
                    <!-- Features -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            <span style="font-size: 0.875rem;">Fast Shipping</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                            <span style="font-size: 0.875rem;">30-Day Returns</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <span style="font-size: 0.875rem;">Secure Payment</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span style="font-size: 0.875rem;">Fast Delivery</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Product Bottom Layout (Description & Related) -->
            <div class="product-bottom-layout" style="margin-top: 4rem; padding-top: 2rem; border-top: 1px solid var(--color-border);">
                <!-- Product Description Section -->
                <div>
                    <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem;">Product Description</h2>
                    <div class="product-description-content" style="color: var(--color-text); line-height: 1.8;">
                        <?= !empty($product['description']) ? $product['description'] : htmlspecialchars($product['short_description'] ?? '') ?>
                    </div>
                </div>
                
                <!-- Related Products -->
                <?php if (count($relatedProducts) > 0): ?>
                <div>
                    <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem;">Related Products</h2>
                    <div class="related-products-sidebar" style="display: grid; gap: 1.5rem;">
                        <?php foreach ($relatedProducts as $rp): ?>
                        <div class="product-card" style="display: flex; gap: 1rem; padding: 1rem; align-items: center;">
                            <div class="product-image" style="width: 100px; height: 100px; flex-shrink: 0; padding: 0;">
                                <?php
                                $rpImg = $rp['main_image'] ?: null;
                                if ($rpImg && !str_starts_with($rpImg, 'http')) {
                                    $rpImg = BASE_URL . '/' . ltrim($rpImg, '/');
                                }
                                $rpImg = $rpImg ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&q=80';
                                ?>
                                <img src="<?= htmlspecialchars($rpImg) ?>" alt="<?= htmlspecialchars($rp['name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <div class="product-content" style="padding: 0;">
                                <h3 class="product-name" style="font-size: 0.95rem; margin-bottom: 0.4rem; font-weight: 500;"><a href="<?= BASE_URL ?>/product/<?= $rp['slug'] ?>"><?= htmlspecialchars($rp['name']) ?></a></h3>
                                <div class="product-price">
                                    <span class="price-current" style="font-size: 1rem;"><?= formatPrice($rp['sale_price'] ?: $rp['price']) ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Thumbnail click binding logic (needs to be callable)
        function bindThumbClickEvents() {
            const thumbs = document.querySelectorAll('.product-thumb-btn');
            const mainImg = document.getElementById('product-main-image');
            thumbs.forEach(btn => {
                btn.addEventListener('click', function() {
                    thumbs.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    mainImg.src = this.dataset.imageSrc;
                });
            });
        }
        // Bind initially
        bindThumbClickEvents();

        // Handle size selection
        const sizeBtns = document.querySelectorAll('.size-btn');
        const sizeInput = document.getElementById('selected-size-input');
        
        sizeBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                sizeBtns.forEach(b => {
                    b.style.borderColor = 'var(--color-border)';
                    b.style.backgroundColor = '#fff';
                    b.style.color = 'var(--color-text)';
                    b.style.fontWeight = 'normal';
                });
                
                this.style.borderColor = 'var(--color-primary)';
                this.style.backgroundColor = 'var(--color-primary-light)';
                this.style.color = 'var(--color-primary)';
                this.style.fontWeight = '600';
                
                if (sizeInput) {
                    sizeInput.value = this.dataset.size;
                }
            });
        });

        // Handle variant selection
        const variantBtns = document.querySelectorAll('.variant-btn');
        const variantInput = document.getElementById('selected-variant-input');
        
        variantBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                variantBtns.forEach(b => {
                    b.style.borderColor = 'var(--color-border)';
                    b.style.backgroundColor = 'transparent';
                    b.style.color = 'var(--color-text)';
                    b.style.fontWeight = 'normal';
                });
                
                this.style.borderColor = 'var(--color-primary)';
                this.style.backgroundColor = 'var(--color-primary-light)';
                this.style.color = 'var(--color-primary)';
                this.style.fontWeight = '600';
                
                if (variantInput) {
                    variantInput.value = this.dataset.variant;
                }
            });
        });

        // Handle color swatch selection
        const colorBtns = document.querySelectorAll('.color-swatch-btn');
        const colorInput = document.getElementById('selected-color-input');
        const colorNameLabel = document.getElementById('selected-color-name');
        
        colorBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active styling
                colorBtns.forEach(b => {
                    b.style.borderColor = 'transparent';
                    b.style.transform = 'scale(1)';
                });
                
                // Add active styling
                this.style.borderColor = 'var(--color-primary)';
                this.style.transform = 'scale(1.1)';
                
                const cName = this.dataset.colorName;
                if (colorNameLabel) colorNameLabel.textContent = cName;
                if (colorInput) colorInput.value = cName;
                
                // Update images dynamically
                const imagesStr = this.dataset.colorImages;
                if (imagesStr) {
                    try {
                        const images = JSON.parse(imagesStr);
                        if (images.length > 0) {
                            // Update main image
                            const mainImg = document.getElementById('product-main-image');
                            if (mainImg) mainImg.src = images[0];
                            
                            // Update thumbs
                            const thumbsContainer = document.querySelector('.product-gallery-thumbs');
                            if (thumbsContainer) {
                                if (images.length > 1) {
                                    thumbsContainer.style.display = ''; // Re-show container
                                    let html = '';
                                    images.forEach((img, idx) => {
                                        const activeCls = idx === 0 ? 'active' : '';
                                        html += `<button type="button" class="product-thumb-btn ${activeCls}" data-image-src="${img}"><img src="${img}"></button>`;
                                    });
                                    thumbsContainer.innerHTML = html;
                                    bindThumbClickEvents(); // Rebind!
                                } else {
                                    thumbsContainer.style.display = 'none'; // Hide if no thumbs
                                }
                            }
                        }
                    } catch(e) {
                        console.error("Error parsing color images", e);
                    }
                }
            });
        });

        // Auto-select first color
        if (colorBtns.length > 0) {
            colorBtns[0].click();
        }
    });
    </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



