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

// Get current category info first so we can include child categories
$currentCategory = null;
if ($category) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE slug = ?");
    $stmt->execute([$category]);
    $currentCategory = $stmt->fetch();
}

// Build query
$where = ["p.status = 'active'"];
$params = [];

if ($currentCategory) {
    $where[] = "(p.category_id = ? OR c.parent_id = ?)";
    $params[] = $currentCategory['id'];
    $params[] = $currentCategory['id'];
} elseif ($category) {
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
?>

    <!-- Page Header -->
    <section class="section section-bg" style="padding: 1rem 0;">
        <div class="container">
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <h1 style="font-size: 1.875rem; font-weight: 700;">
                    <?php if ($currentCategory): ?>
                        <?= htmlspecialchars($currentCategory['name']) ?>
                    <?php elseif ($search): ?>
                        Search Results for "<?= htmlspecialchars($search) ?>"
                    <?php else: ?>
                        All Products
                    <?php endif; ?>
                </h1>
                <nav style="font-size: 0.875rem; color: var(--color-text-light); display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light); display: flex; align-items: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
                    <span>/</span>
                    <span style="color: var(--color-text);">Products</span>
                    <?php if ($currentCategory): ?>
                        <span>/</span>
                        <span style="color: var(--color-text);"><?= htmlspecialchars($currentCategory['name']) ?></span>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section class="section">
        <div class="container">
            <div class="products-layout">

                <!-- Sidebar Filters -->
                <aside class="sidebar-desktop">
                    <div style="background: var(--color-bg); border-radius: var(--radius-lg); padding: 1.5rem; border: 1px solid var(--color-border); box-shadow: 0 4px 12px -2px rgba(0,0,0,0.03); position: sticky; top: 2rem; height: 100%;">
                        <!-- Mobile Close Button -->
                        <div class="mobile-close-btn" style="display: none; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--color-border-light);">
                            <span style="font-weight: 700; font-size: 1.1rem; color: var(--color-text);">Filters</span>
                            <button onclick="toggleSidebarFilters()" style="background: var(--color-bg-secondary); border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">&times;</button>
                        </div>
                        
                        <!-- Categories -->
                        <details open style="margin-bottom: 2.5rem;">
                            <summary style="list-style: none; display: flex; justify-content: space-between; align-items: center; cursor: pointer; margin-bottom: 1.2rem; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; color: var(--color-text);">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
                                    Categories
                                </div>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="cat-chevron" style="transition: transform 0.2s; opacity: 0.7;"><polyline points="6 9 12 15 18 9"/></svg>
                            </summary>
                            <ul style="display: flex; flex-direction: column; gap: 0.35rem; list-style: none; padding: 0; margin: 0;">
                                <li>
                                    <a href="<?= BASE_URL ?>/shop" style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0.85rem; border-radius: var(--radius-md); font-size: 0.95rem; font-weight: 500; transition: all 0.2s ease; <?= !$category ? 'background: var(--color-primary); color: white;' : 'color: var(--color-text-light); background: transparent;' ?>" onmouseover="this.style.background='<?= !$category ? 'var(--color-primary)' : 'var(--color-bg-secondary)' ?>'; this.style.color='<?= !$category ? 'white' : 'var(--color-text)' ?>';" onmouseout="this.style.background='<?= !$category ? 'var(--color-primary)' : 'transparent' ?>'; this.style.color='<?= !$category ? 'white' : 'var(--color-text-light)' ?>';">
                                        <span>All Products</span>
                                        <?php if (!$category): ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg><?php endif; ?>
                                    </a>
                                </li>
                                
                                <?php 
                                $parentCategories = [];
                                $childCategories = [];
                                foreach ($categories as $cat) {
                                    if (empty($cat['parent_id'])) {
                                        $parentCategories[] = $cat;
                                    } else {
                                        $childCategories[$cat['parent_id']][] = $cat;
                                    }
                                }
                                ?>
                                
                                <?php foreach ($parentCategories as $pCat): ?>
                                    <?php 
                                    $hasChildren = isset($childCategories[$pCat['id']]); 
                                    $isActiveParent = $category === $pCat['slug'];
                                    if ($hasChildren && !$isActiveParent) {
                                        foreach ($childCategories[$pCat['id']] as $cCat) {
                                            if ($category === $cCat['slug']) {
                                                $isActiveParent = true;
                                                break;
                                            }
                                        }
                                    }
                                    $isExactlyParent = $category === $pCat['slug'];
                                    ?>
                                    <li>
                                        <?php if ($hasChildren): ?>
                                            <details <?= $isActiveParent ? 'open' : '' ?> style="margin-bottom: 0;">
                                                <summary style="list-style: none; display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0.85rem; border-radius: var(--radius-md); font-size: 0.95rem; font-weight: 500; transition: all 0.2s ease; cursor: pointer; <?= $isExactlyParent ? 'background: var(--color-primary); color: white;' : 'color: var(--color-text-light); background: transparent;' ?>" onmouseover="if(!this.hasAttribute('data-active')){this.style.background='var(--color-bg-secondary)'; this.style.color='var(--color-text)';}" onmouseout="if(!this.hasAttribute('data-active')){this.style.background='transparent'; this.style.color='var(--color-text-light)';}" <?= $isExactlyParent ? 'data-active="true"' : '' ?>>
                                                    <a href="<?= BASE_URL ?>/category/<?= urlencode($pCat['slug']) ?>" style="color: inherit; text-decoration: none; flex: 1;" onclick="event.stopPropagation();">
                                                        <?= htmlspecialchars($pCat['name']) ?>
                                                    </a>
                                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                        <?php if ($isExactlyParent): ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg><?php endif; ?>
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="cat-chevron" style="transition: transform 0.2s; opacity: 0.7;"><polyline points="6 9 12 15 18 9"/></svg>
                                                    </div>
                                                </summary>
                                                <ul style="padding-left: 1.5rem; margin-top: 0.35rem; display: flex; flex-direction: column; gap: 0.2rem; list-style: none; margin-bottom: 0.5rem;">
                                                    <?php foreach ($childCategories[$pCat['id']] as $cCat): ?>
                                                    <?php $isChildActive = $category === $cCat['slug']; ?>
                                                    <li>
                                                        <a href="<?= BASE_URL ?>/category/<?= urlencode($cCat['slug']) ?>" style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 500; transition: all 0.2s ease; <?= $isChildActive ? 'color: var(--color-primary); background: var(--color-primary-light, rgba(0,0,0,0.03));' : 'color: var(--color-text-muted); background: transparent;' ?>" onmouseover="this.style.background='<?= $isChildActive ? 'var(--color-primary-light, rgba(0,0,0,0.03))' : 'var(--color-bg-secondary)' ?>'; this.style.color='<?= $isChildActive ? 'var(--color-primary)' : 'var(--color-text)' ?>';" onmouseout="this.style.background='<?= $isChildActive ? 'var(--color-primary-light, rgba(0,0,0,0.03))' : 'transparent' ?>'; this.style.color='<?= $isChildActive ? 'var(--color-primary)' : 'var(--color-text-muted)' ?>';">
                                                            <span><?= htmlspecialchars($cCat['name']) ?></span>
                                                            <?php if ($isChildActive): ?><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg><?php endif; ?>
                                                        </a>
                                                    </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </details>
                                        <?php else: ?>
                                            <a href="<?= BASE_URL ?>/category/<?= urlencode($pCat['slug']) ?>" style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0.85rem; border-radius: var(--radius-md); font-size: 0.95rem; font-weight: 500; transition: all 0.2s ease; <?= $isExactlyParent ? 'background: var(--color-primary); color: white;' : 'color: var(--color-text-light); background: transparent;' ?>" onmouseover="this.style.background='<?= $isExactlyParent ? 'var(--color-primary)' : 'var(--color-bg-secondary)' ?>'; this.style.color='<?= $isExactlyParent ? 'white' : 'var(--color-text)' ?>';" onmouseout="this.style.background='<?= $isExactlyParent ? 'var(--color-primary)' : 'transparent' ?>'; this.style.color='<?= $isExactlyParent ? 'white' : 'var(--color-text-light)' ?>';">
                                                <span><?= htmlspecialchars($pCat['name']) ?></span>
                                                <?php if ($isExactlyParent): ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg><?php endif; ?>
                                            </a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            
                            <style>
                                details[open] > summary .cat-chevron {
                                    transform: rotate(180deg);
                                }
                                details > summary::-webkit-details-marker {
                                    display: none;
                                }
                            </style>
                        </details>
                        
                        <!-- Price Filter -->
                        <details open>
                            <summary style="list-style: none; display: flex; justify-content: space-between; align-items: center; cursor: pointer; margin-bottom: 1.2rem; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; color: var(--color-text);">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                    Price Range
                                </div>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="cat-chevron" style="transition: transform 0.2s; opacity: 0.7;"><polyline points="6 9 12 15 18 9"/></svg>
                            </summary>
                            <div style="background: var(--color-bg-secondary); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border-light);">
                                <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem;">
                                    <div style="position: relative;">
                                        <span style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); font-size: 0.85rem; font-weight: 600;">Tk</span>
                                        <input type="number" placeholder="From" class="form-input" style="width: 100%; padding-left: 2.5rem; height: 42px; font-size: 0.95rem; background: var(--color-bg); border-color: var(--color-border-light);">
                                    </div>
                                    <div style="position: relative;">
                                        <span style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); font-size: 0.85rem; font-weight: 600;">Tk</span>
                                        <input type="number" placeholder="To" class="form-input" style="width: 100%; padding-left: 2.5rem; height: 42px; font-size: 0.95rem; background: var(--color-bg); border-color: var(--color-border-light);">
                                    </div>
                                </div>
                                <button type="button" class="btn btn-primary" style="width: 100%; padding: 0.6rem; font-size: 0.85rem; font-weight: 600;">Apply Filter</button>
                            </div>
                        </details>

                    </div>
                </aside>
                
                <!-- Products Grid -->
                <div class="products-grid-container" style="transition: width 0.3s ease;">
                    <!-- Sort & Filter Bar -->
                    <div style="display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--color-border);">
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; flex: 1;">
                            <?php
                            $baseUrl = $category ? BASE_URL . "/category/$category" : BASE_URL . '/shop';
                            ?>
                            <a href="<?= $baseUrl ?>" class="btn <?= !$filter ? 'btn-primary' : 'btn-secondary' ?> btn-sm">All</a>
                            <a href="<?= $baseUrl ?>?filter=new" class="btn <?= $filter === 'new' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">New</a>
                            <a href="<?= $baseUrl ?>?filter=bestseller" class="btn <?= $filter === 'bestseller' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Best Sellers</a>
                            <a href="<?= $baseUrl ?>?filter=sale" class="btn <?= $filter === 'sale' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">On Sale</a>
                        </div>
                        
                        <div class="sort-filter-wrapper" style="display: flex; align-items: center; justify-content: flex-end; gap: 1rem; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <label style="font-size: 0.875rem; color: var(--color-text-light); white-space: nowrap;">Sort by:</label>
                                <select class="form-select" style="width: auto;" onchange="window.location.href='<?= $baseUrl ?>?<?= http_build_query(array_diff_key($_GET, ['sort' => ''])) ?>&sort=' + this.value">
                                    <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest</option>
                                    <option value="price-low" <?= $sortBy === 'price-low' ? 'selected' : '' ?>>Price: Low to High</option>
                                    <option value="price-high" <?= $sortBy === 'price-high' ? 'selected' : '' ?>>Price: High to Low</option>
                                    <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Name</option>
                                </select>
                            </div>
                            <button onclick="toggleSidebarFilters()" class="btn btn-outline btn-sm" id="toggle-filters-btn" style="display: flex; align-items: center; gap: 0.4rem; border-color: var(--color-border-dark); padding: 0.45rem 0.65rem;" title="Toggle Filters">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                <span>Hide Filters</span>
                            </button>
                        </div>
                        
                        <script>
                            function toggleSidebarFilters() {
                                const sidebar = document.querySelector('.sidebar-desktop');
                                const layout = document.querySelector('.products-layout');
                                const btnSpan = document.querySelector('#toggle-filters-btn span');
                                
                                if (window.innerWidth <= 768) {
                                    // Mobile behavior: toggle off-canvas
                                    sidebar.classList.toggle('mobile-open');
                                    
                                    let overlay = document.querySelector('.sidebar-overlay');
                                    if (!overlay) {
                                        overlay = document.createElement('div');
                                        overlay.className = 'sidebar-overlay';
                                        document.body.appendChild(overlay);
                                        overlay.onclick = toggleSidebarFilters;
                                    }
                                    
                                    if (sidebar.classList.contains('mobile-open')) {
                                        overlay.style.display = 'block';
                                        setTimeout(() => overlay.style.opacity = '1', 10);
                                        document.body.style.overflow = 'hidden';
                                    } else {
                                        overlay.style.opacity = '0';
                                        setTimeout(() => overlay.style.display = 'none', 300);
                                        document.body.style.overflow = '';
                                    }
                                } else {
                                    // Desktop behavior: toggle grid columns
                                    if (sidebar.style.display === 'none' || sidebar.style.display === '') {
                                        sidebar.style.display = 'block';
                                        layout.style.gridTemplateColumns = '';
                                        btnSpan.textContent = 'Hide Filters';
                                    } else {
                                        sidebar.style.display = 'none';
                                        layout.style.gridTemplateColumns = '1fr';
                                        btnSpan.textContent = 'Show Filters';
                                    }
                                }
                            }
                        </script>
                        <style>
                            /* Mobile Off-Canvas Drawer Styles */
                            .sidebar-overlay {
                                position: fixed;
                                top: 0;
                                left: 0;
                                width: 100vw;
                                height: 100vh;
                                background: rgba(0,0,0,0.5);
                                z-index: 998;
                                display: none;
                                opacity: 0;
                                transition: opacity 0.3s ease;
                                backdrop-filter: blur(2px);
                            }
                            
                            @media (max-width: 768px) {
                                .sort-filter-wrapper {
                                    width: 100%;
                                    justify-content: space-between !important;
                                }
                                #toggle-filters-btn span {
                                    display: none;
                                }
                                .sidebar-desktop {
                                    position: fixed !important;
                                    top: 0 !important;
                                    left: -100% !important;
                                    width: 85vw !important;
                                    max-width: 320px !important;
                                    height: 100vh !important;
                                    z-index: 999 !important;
                                    transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                                    display: block !important;
                                }
                                .sidebar-desktop > div {
                                    height: 100vh !important;
                                    border-radius: 0 !important;
                                    overflow-y: auto !important;
                                    border-left: none !important;
                                    border-top: none !important;
                                    border-bottom: none !important;
                                    padding-bottom: 5rem !important;
                                }
                                .sidebar-desktop.mobile-open {
                                    left: 0 !important;
                                }
                                .mobile-close-btn {
                                    display: flex !important;
                                }
                            }
                        </style>
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



