<?php
/**
 * KARTLY - Products Page
 */
$pageTitle = 'Products';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

// Get filter parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';
$filter = $_GET['filter'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["p.status = 'active'"];
$params = [];

if ($category) {
    $where[] = "c.slug = ?";
    $params[] = $category;
}

if ($search) {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter === 'new') {
    $where[] = "p.is_new = 1";
}

if ($filter === 'bestseller') {
    $where[] = "p.is_bestseller = 1";
}

if ($filter === 'sale') {
    $where[] = "p.sale_price IS NOT NULL";
}

// Sort
$orderBy = match($sortBy) {
    'price-low' => 'p.price ASC',
    'price-high' => 'p.price DESC',
    'name' => 'p.name ASC',
    default => 'p.created_at DESC'
};

// Get products
$whereClause = implode(' AND ', $where);
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE $whereClause 
    ORDER BY $orderBy 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get total count for pagination
$stmt = $db->prepare("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE $whereClause");
$stmt->execute($params);
$totalProducts = $stmt->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

// Get categories for sidebar
$stmt = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
$categories = $stmt->fetchAll();

// Get current category name
$currentCategory = null;
if ($category) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE slug = ?");
    $stmt->execute([$category]);
    $currentCategory = $stmt->fetch();
}
?>

    <!-- Page Header -->
    <section class="section section-bg" style="padding: 1.5rem 0 2rem;">
        <div class="container">
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <nav style="font-size: 0.875rem; color: var(--color-text-light);">
                    <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light);">Home</a>
                    <span> / </span>
                    <span style="color: var(--color-text);">Products</span>
                    <?php if ($currentCategory): ?>
                        <span> / </span>
                        <span style="color: var(--color-text);"><?= htmlspecialchars($currentCategory['name']) ?></span>
                    <?php endif; ?>
                </nav>
                <h1 style="font-size: 1.875rem; font-weight: 700;">
                    <?php if ($currentCategory): ?>
                        <?= htmlspecialchars($currentCategory['name']) ?>
                    <?php elseif ($search): ?>
                        Search Results for "<?= htmlspecialchars($search) ?>"
                    <?php else: ?>
                        All Products
                    <?php endif; ?>
                </h1>
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section class="section">
        <div class="container">
            <div class="products-layout">

                <!-- Sidebar Filters -->
                <aside class="sidebar-desktop">
                    
                    <!-- Categories -->
                    <div style="margin-bottom: 2rem;">
                        <h3 style="font-weight: 600; margin-bottom: 1rem;">Categories</h3>
                        <ul style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <li>
                                <a href="<?= BASE_URL ?>/shop" style="display: flex; justify-content: space-between; padding: 0.5rem 0; color: <?= !$category ? 'var(--color-primary)' : 'var(--color-text-light)' ?>;">
                                    <span>All Products</span>
                                </a>
                            </li>
                            <?php foreach ($categories as $cat): ?>
                            <li>
                                <a href="<?= BASE_URL ?>/category/<?= urlencode($cat['slug']) ?>" style="display: flex; justify-content: space-between; padding: 0.5rem 0; color: <?= $category === $cat['slug'] ? 'var(--color-primary)' : 'var(--color-text-light)' ?>;">
                                    <span><?= htmlspecialchars($cat['name']) ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <!-- Price Filter -->
                    <div style="margin-bottom: 2rem;">
                        <h3 style="font-weight: 600; margin-bottom: 1rem;">Price Range</h3>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="number" placeholder="Min" class="form-input" style="width: 100%;">
                            <input type="number" placeholder="Max" class="form-input" style="width: 100%;">
                        </div>
                    </div>
                </aside>
                
                <!-- Products Grid -->
                <div>
                    <!-- Sort & Filter Bar -->
                    <div style="display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--color-border);">
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <?php
                            $baseUrl = $category ? BASE_URL . "/category/$category" : BASE_URL . '/shop';
                            ?>
                            <a href="<?= $baseUrl ?>" class="btn <?= !$filter ? 'btn-primary' : 'btn-secondary' ?> btn-sm">All</a>
                            <a href="<?= $baseUrl ?>?filter=new" class="btn <?= $filter === 'new' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">New</a>
                            <a href="<?= $baseUrl ?>?filter=bestseller" class="btn <?= $filter === 'bestseller' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Best Sellers</a>
                            <a href="<?= $baseUrl ?>?filter=sale" class="btn <?= $filter === 'sale' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">On Sale</a>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <label style="font-size: 0.875rem; color: var(--color-text-light);">Sort by:</label>
                            <select class="form-select" style="width: auto;" onchange="window.location.href='<?= $baseUrl ?>?<?= http_build_query(array_diff_key($_GET, ['sort' => ''])) ?>&sort=' + this.value">
                                <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest</option>
                                <option value="price-low" <?= $sortBy === 'price-low' ? 'selected' : '' ?>>Price: Low to High</option>
                                <option value="price-high" <?= $sortBy === 'price-high' ? 'selected' : '' ?>>Price: High to Low</option>
                                <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Name</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Products -->
                    <?php if (empty($products)): ?>
                        <div style="text-align: center; padding: 4rem 1rem;">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin: 0 auto 1rem; color: var(--color-text-muted);">
                                <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                            </svg>
                            <h3 style="font-weight: 600; margin-bottom: 0.5rem;">No products found</h3>
                            <p style="color: var(--color-text-light);">Try adjusting your search or filter criteria</p>
                            <a href="<?= BASE_URL ?>/shop" class="btn btn-primary" style="margin-top: 1rem;">View All Products</a>
                        </div>
                    <?php else: ?>
                        <div class="products-grid">
                            <?php foreach ($products as $product): ?>
                                <?php
                                $discount = 0;
                                if ($product['sale_price'] && $product['price'] > 0) {
                                    $discount = round((($product['price'] - $product['sale_price']) / $product['price']) * 100);
                                }
                                $image = $product['main_image'] ?: null;
                                if ($image && !str_starts_with($image, 'http')) {
                                    $image = BASE_URL . '/' . ltrim($image, '/');
                                }
                                $image = $image ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&q=80';
                                ?>
                                <div class="product-card">
                                    <div class="product-image">
                                        <a href="<?= BASE_URL ?>/product/<?= $product['slug'] ?>" class="product-image-link" aria-label="View <?= htmlspecialchars($product['name']) ?>"></a>
                                        <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                        
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
                                        
                                        <button class="product-wishlist" data-product-id="<?= $product['id'] ?>" aria-label="Add to wishlist">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                            </svg>
                                        </button>
                                        
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
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 2rem;">
                                <?php if ($page > 1): ?>
                                    <a href="<?= $baseUrl ?>?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-secondary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="15 18 9 12 15 6"/>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="<?= $baseUrl ?>?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="<?= $baseUrl ?>?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-secondary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="9 18 15 12 9 6"/>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



