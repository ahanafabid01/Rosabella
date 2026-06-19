<?php
/**
 * KARTLY - Admin Products Management
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once __DIR__ . '/includes/layout.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$db = getDB();
$message = '';
$error = '';

function resolveAdminImageSrc(?string $imagePath): string
{
    $imagePath = trim((string)$imagePath);
    if ($imagePath === '') {
        return '';
    }

    if (preg_match('/^(https?:)?\/\//i', $imagePath) || strpos($imagePath, 'data:') === 0 || strpos($imagePath, '../') === 0 || strpos($imagePath, '/') === 0) {
        return $imagePath;
    }

    return '../' . $imagePath;
}

function uploadProductImage(array $file, ?string &$uploadError): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $uploadError = 'Image upload failed. Please try again.';
        return null;
    }

    $maxSize = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        $uploadError = 'Image is too large. Maximum allowed size is 5 MB.';
        return null;
    }

    $tmpName = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmpName)) {
        $uploadError = 'Invalid upload source detected.';
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $tmpName) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMimeToExtension = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowedMimeToExtension[$mimeType])) {
        $uploadError = 'Only JPG, PNG, WEBP, and GIF images are allowed.';
        return null;
    }

    $uploadDir = __DIR__ . '/../assets/uploads/products';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        $uploadError = 'Unable to create image upload directory.';
        return null;
    }

    if (!is_writable($uploadDir)) {
        $uploadError = 'Image upload directory is not writable.';
        return null;
    }

    try {
        $fileName = 'product_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowedMimeToExtension[$mimeType];
    } catch (Throwable $e) {
        $fileName = 'product_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.' . $allowedMimeToExtension[$mimeType];
    }

    $targetPath = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($tmpName, $targetPath)) {
        $uploadError = 'Unable to move uploaded image.';
        return null;
    }

    return 'assets/uploads/products/' . $fileName;
}

function parseGalleryImages(?string $raw): array
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

function normalizeUploadFileArray(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $normalized = [];
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $normalized[] = [
            'name' => $files['name'][$i] ?? '',
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];
    }

    return $normalized;
}

function isManagedProductUploadPath(string $path): bool
{
    return strpos($path, 'assets/uploads/products/') === 0;
}

function deleteManagedProductUpload(string $path): void
{
    if (!isManagedProductUploadPath($path)) {
        return;
    }

    $absolute = __DIR__ . '/../' . $path;
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

// Handle actions
$action = $_GET['action'] ?? 'list';
$productId = $_GET['id'] ?? null;

// Delete product
if ($action === 'delete' && $productId) {
    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    if ($stmt->execute([$productId])) {
        $message = 'Product deleted successfully';
        $action = 'list';
    }
}

// Save product (create/update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
    $category_id = intval($_POST['category_id']);
    $price = floatval($_POST['price']);
    $sale_price = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
    $stock_quantity = intval($_POST['stock_quantity']);
    // Allow basic HTML for the rich text editor
    $description = strip_tags($_POST['description'] ?? '', '<b><i><u><strong><em><p><br><ul><li><ol><h1><h2><h3><h4><h5><h6><a><span><div>');
    
    // New fields
    $sku = sanitize($_POST['sku'] ?? '');
    if ($sku === '') $sku = null;
    
    $style = sanitize($_POST['style'] ?? '');
    $sizes = sanitize($_POST['sizes'] ?? '');
    
    // Parse color variants and images
    $colorsInput = sanitize($_POST['colors_input'] ?? '');
    $colorsData = [];
    if ($colorsInput !== '') {
        $colorPairs = explode(',', $colorsInput);
        foreach ($colorPairs as $pair) {
            if (strpos($pair, ':') !== false) {
                [$name, $hex] = explode(':', $pair);
                $name = trim($name);
                $hex = trim($hex);
                if ($name !== '' && $hex !== '') {
                    $colorsData[$name] = ['hex' => $hex, 'main_image' => '', 'gallery_images' => []];
                }
            }
        }
    }
    
    // Existing colors from DB to retain existing images
    $existingColors = [];
    if ($action === 'edit' && isset($_GET['id'])) {
        $existingStmt2 = $db->prepare("SELECT colors FROM products WHERE id = ?");
        $existingStmt2->execute([intval($_GET['id'])]);
        $existingProdColors = $existingStmt2->fetchColumn();
        if ($existingProdColors) {
            $existingColors = json_decode($existingProdColors, true) ?: [];
            if (!isset($existingColors[0]) && !isset($existingColors['color'])) {
                // it's an associative array
                $removeGalleryImagesArr = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['remove_gallery_images'] ?? [])))));
                foreach ($colorsData as $name => &$data) {
                    if (isset($existingColors[$name]['main_image'])) {
                        if (!in_array($existingColors[$name]['main_image'], $removeGalleryImagesArr, true)) {
                            $data['main_image'] = $existingColors[$name]['main_image'];
                        }
                    }
                    if (isset($existingColors[$name]['images']) && is_array($existingColors[$name]['images'])) {
                        // migrate old structure
                        $data['gallery_images'] = array_values(array_filter($existingColors[$name]['images'], static function ($path) use ($removeGalleryImagesArr) {
                            return !in_array($path, $removeGalleryImagesArr, true);
                        }));
                    }
                    if (isset($existingColors[$name]['gallery_images']) && is_array($existingColors[$name]['gallery_images'])) {
                        $data['gallery_images'] = array_values(array_filter($existingColors[$name]['gallery_images'], static function ($path) use ($removeGalleryImagesArr) {
                            return !in_array($path, $removeGalleryImagesArr, true);
                        }));
                    }
                }
                unset($data);
            }
        }
    }
    
    $brand = sanitize($_POST['brand'] ?? '');
    $key_features = sanitize($_POST['key_features'] ?? '');
    $variants = sanitize($_POST['variants'] ?? '');

    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_new = isset($_POST['is_new']) ? 1 : 0;
    $is_bestseller = isset($_POST['is_bestseller']) ? 1 : 0;
    $status = sanitize($_POST['status']);

    $existingProduct = null;
    if ($action === 'edit' && $productId) {
        $existingStmt = $db->prepare("SELECT main_image, gallery_images FROM products WHERE id = ?");
        $existingStmt->execute([$productId]);
        $existingProduct = $existingStmt->fetch();
        if (!$existingProduct) {
            $error = 'Product not found.';
        }
    }

    $currentImage = trim((string)($existingProduct['main_image'] ?? ''));
    $mainImage = $currentImage !== '' ? $currentImage : null;
    $galleryImages = parseGalleryImages($existingProduct['gallery_images'] ?? null);
    $newUploadedImages = [];
    $queuedDeleteImages = [];

    $removeGalleryImages = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['remove_gallery_images'] ?? [])))));
    if ($removeGalleryImages) {
        $galleryImages = array_values(array_filter($galleryImages, static function ($path) use ($removeGalleryImages) {
            return !in_array($path, $removeGalleryImages, true);
        }));
    }

    $uploadError = null;
    if (!$error && isset($_FILES['main_image_file'])) {
        $uploadedMainImage = uploadProductImage($_FILES['main_image_file'], $uploadError);
        if ($uploadError !== null) {
            $error = $uploadError;
        } elseif ($uploadedMainImage !== null) {
            $mainImage = $uploadedMainImage;
            $newUploadedImages[] = $uploadedMainImage;
        }
    }

    if (!$error && isset($_FILES['gallery_image_files'])) {
        $galleryUploads = normalizeUploadFileArray($_FILES['gallery_image_files']);
        foreach ($galleryUploads as $galleryUpload) {
            $uploadedGalleryImage = uploadProductImage($galleryUpload, $uploadError);
            if ($uploadError !== null) {
                $error = $uploadError;
                break;
            }
            if ($uploadedGalleryImage !== null) {
                $galleryImages[] = $uploadedGalleryImage;
                $newUploadedImages[] = $uploadedGalleryImage;
            }
        }
    }
    
    // Process color-specific main image
    if (!$error && isset($_FILES['color_main_image'])) {
        foreach ($_FILES['color_main_image']['name'] as $colorName => $name) {
            if (!isset($colorsData[$colorName])) continue;
            if (empty($name)) continue;
            
            $file = [
                'name' => $_FILES['color_main_image']['name'][$colorName],
                'type' => $_FILES['color_main_image']['type'][$colorName],
                'tmp_name' => $_FILES['color_main_image']['tmp_name'][$colorName],
                'error' => $_FILES['color_main_image']['error'][$colorName],
                'size' => $_FILES['color_main_image']['size'][$colorName]
            ];
            
            $uploadedImg = uploadProductImage($file, $uploadError);
            if ($uploadError !== null) {
                $error = $uploadError;
                break;
            }
            if ($uploadedImg !== null) {
                $colorsData[$colorName]['main_image'] = $uploadedImg;
                $newUploadedImages[] = $uploadedImg;
            }
        }
    }

    // Process color-specific gallery images
    if (!$error && isset($_FILES['color_gallery'])) {
        foreach ($_FILES['color_gallery']['name'] as $colorName => $names) {
            if (!isset($colorsData[$colorName])) continue;
            
            $files = [
                'name' => $_FILES['color_gallery']['name'][$colorName],
                'type' => $_FILES['color_gallery']['type'][$colorName],
                'tmp_name' => $_FILES['color_gallery']['tmp_name'][$colorName],
                'error' => $_FILES['color_gallery']['error'][$colorName],
                'size' => $_FILES['color_gallery']['size'][$colorName]
            ];
            
            $colorGalleryUploads = normalizeUploadFileArray($files);
            foreach ($colorGalleryUploads as $upload) {
                $uploadedImg = uploadProductImage($upload, $uploadError);
                if ($uploadError !== null) {
                    $error = $uploadError;
                    break 2;
                }
                if ($uploadedImg !== null) {
                    $colorsData[$colorName]['gallery_images'][] = $uploadedImg;
                    $newUploadedImages[] = $uploadedImg;
                }
            }
        }
    }
    
    $colors = !empty($colorsData) ? json_encode($colorsData, JSON_UNESCAPED_SLASHES) : null;

    $galleryImages = array_values(array_unique(array_filter($galleryImages)));

    if (in_array($mainImage, $removeGalleryImages, true)) {
        $mainImage = $galleryImages[0] ?? null;
    }

    if ($mainImage === null && !empty($galleryImages)) {
        $mainImage = $galleryImages[0];
    }
    
    // Fallback: If still no global main image, try to use the first color's main image or first gallery image
    if ($mainImage === null && !empty($colorsData)) {
        foreach ($colorsData as $cData) {
            if (!empty($cData['main_image'])) {
                $mainImage = $cData['main_image'];
                break;
            } elseif (!empty($cData['gallery_images'][0])) {
                $mainImage = $cData['gallery_images'][0];
                break;
            } elseif (!empty($cData['images'][0])) {
                $mainImage = $cData['images'][0];
                break;
            }
        }
    }

    $galleryImagesJson = !empty($galleryImages) ? json_encode($galleryImages, JSON_UNESCAPED_SLASHES) : null;

    if ($existingProduct) {
        foreach ($removeGalleryImages as $path) {
            if ($path !== $mainImage && !in_array($path, $galleryImages, true)) {
                $queuedDeleteImages[] = $path;
            }
        }

        if ($currentImage !== '' && $currentImage !== $mainImage && !in_array($currentImage, $galleryImages, true)) {
            $queuedDeleteImages[] = $currentImage;
        }
    }

    if (!$error) {
        try {
            if ($action === 'edit' && $productId) {
                $stmt = $db->prepare("UPDATE products SET name=?, slug=?, sku=?, style=?, sizes=?, colors=?, category_id=?, price=?, sale_price=?, stock_quantity=?, description=?, brand=?, key_features=?, variants=?, main_image=?, gallery_images=?, is_featured=?, is_new=?, is_bestseller=?, status=? WHERE id=?");
                $saved = $stmt->execute([$name, $slug, $sku, $style, $sizes, $colors, $category_id, $price, $sale_price, $stock_quantity, $description, $brand, $key_features, $variants, $mainImage, $galleryImagesJson, $is_featured, $is_new, $is_bestseller, $status, $productId]);
                if ($saved) {
                    $message = 'Product updated successfully';
                    $action = 'list';
                } else {
                    $error = 'Unable to update product.';
                }
            } else {
                $stmt = $db->prepare("INSERT INTO products (name, slug, sku, style, sizes, colors, category_id, price, sale_price, stock_quantity, description, brand, key_features, variants, main_image, gallery_images, is_featured, is_new, is_bestseller, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $saved = $stmt->execute([$name, $slug, $sku, $style, $sizes, $colors, $category_id, $price, $sale_price, $stock_quantity, $description, $brand, $key_features, $variants, $mainImage, $galleryImagesJson, $is_featured, $is_new, $is_bestseller, $status]);
                if ($saved) {
                    $message = 'Product created successfully';
                    $action = 'list';
                } else {
                    $error = 'Unable to create product.';
                }
            }
        } catch (Throwable $e) {
            $error = 'Unable to save product: ' . $e->getMessage();
        }

        if (!$error) {
            foreach (array_unique($queuedDeleteImages) as $oldImagePath) {
                deleteManagedProductUpload($oldImagePath);
            }
        }
    }

    if ($error && !empty($newUploadedImages)) {
        foreach (array_unique($newUploadedImages) as $newImagePath) {
            deleteManagedProductUpload($newImagePath);
        }
    }
}

// Get product for editing
$product = null;
if ($action === 'edit' && $productId) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
}
$productGalleryImages = parseGalleryImages($product['gallery_images'] ?? null);

// Get all products for listing
$search = $_GET['search'] ?? '';
$where = '';
$params = [];
if ($search) {
    $where = "WHERE name LIKE ?";
    $params[] = "%$search%";
}
$stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id $where ORDER BY p.created_at DESC");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories
$categories = $db->query("SELECT * FROM categories WHERE status = 'active'")->fetchAll();

$pageTitle = 'Products Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - KARTLY Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="css/admin.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <?php renderAdminSidebar('products'); ?>

        <!-- Main Content -->
        <main class="admin-content">
        <?php renderAdminTopbar($pageTitle ?? 'Admin Panel'); ?>
<div class="admin-header">
                <h1 class="admin-title"><?= $action === 'add' ? 'Add New Product' : ($action === 'edit' ? 'Edit Product' : 'Products') ?></h1>
                <?php if ($action === 'list'): ?>
                <a href="?action=add" class="btn btn-primary">+ Add Product</a>
                <?php endif; ?>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <?php if ($action === 'list'): ?>
                <!-- Search -->
                <div class="admin-card admin-card-gap-lg">
                    <form method="GET" class="admin-form-row-center">
                        <input type="text" name="search" class="form-input admin-input-max-300" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-secondary">Search</button>
                    </form>
                </div>

                <!-- Products Table -->
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                            <tr>
                                <td><?= $p['id'] ?></td>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><?= htmlspecialchars($p['category_name'] ?? '-') ?></td>
                                <td><?= formatPrice($p['sale_price'] ?: $p['price']) ?></td>
                                <td><?= $p['stock_quantity'] ?></td>
                                <td><span class="badge badge-<?= $p['status'] === 'active' ? 'success' : 'warning' ?>"><?= ucfirst($p['status']) ?></span></td>
                                <td>
                                    <div class="admin-actions-row">
                                        <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                                        <a href="?action=delete&id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary" onclick="return confirm('Are you sure?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <!-- Professional Two-Column Product Form -->
                <form method="POST" enctype="multipart/form-data" class="admin-product-form-layout">
                    
                    <!-- Left Column: Main Details -->
                    <div class="admin-form-main">
                        <div class="admin-card">
                            <h3 class="admin-section-heading">Basic Information</h3>
                            <div class="form-group">
                                <label class="form-label">Product Name *</label>
                                <input type="text" name="name" class="form-input" required value="<?= htmlspecialchars($product['name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Brand</label>
                                <input type="text" name="brand" class="form-input" value="<?= htmlspecialchars($product['brand'] ?? '') ?>" placeholder="e.g., Sony">
                            </div>
                            <div class="admin-two-col-grid">
                                <div class="form-group">
                                    <label class="form-label">SKU</label>
                                    <input type="text" name="sku" class="form-input" value="<?= htmlspecialchars($product['sku'] ?? '') ?>" placeholder="e.g., 9455BA03">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Style</label>
                                    <input type="text" name="style" class="form-input" value="<?= htmlspecialchars($product['style'] ?? '') ?>" placeholder="e.g., Sports Shoes">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <div id="quill-editor" style="height: 200px; background: #fff;"><?= $product['description'] ?? '' ?></div>
                                <input type="hidden" name="description" id="quill-description">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Key Features (One per line)</label>
                                <textarea name="key_features" class="form-textarea" rows="4" placeholder="Model: PlayStation 5 Slim..."><?= htmlspecialchars($product['key_features'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Sizes (Comma separated)</label>
                                <input type="text" name="sizes" class="form-input" value="<?= htmlspecialchars($product['sizes'] ?? '') ?>" placeholder="e.g., 39, 40, 41, 42, 43, 44">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Color Variants</label>
                                <?php
                                $colorsString = '';
                                $parsedColors = [];
                                if (!empty($product['colors'])) {
                                    $parsedColors = json_decode($product['colors'], true) ?: [];
                                    if (is_array($parsedColors) && !isset($parsedColors[0]) && !isset($parsedColors['color'])) {
                                        $parts = [];
                                        foreach ($parsedColors as $name => $data) {
                                            $parts[] = $name . ':' . ($data['hex'] ?? '');
                                        }
                                        $colorsString = implode(', ', $parts);
                                    } else {
                                        $colorsString = $product['colors'];
                                    }
                                }
                                ?>
                                <input type="text" name="colors_input" id="color-variants-input" class="form-input" value="<?= htmlspecialchars($colorsString) ?>" placeholder="e.g., White:#ffffff, Crimson:#DC143C">
                                <p class="admin-upload-help" style="margin-top: 0.25rem;">Format: Name:HexCode, separated by commas.</p>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Other Variants (Comma separated)</label>
                                <input type="text" name="variants" class="form-input" value="<?= htmlspecialchars($product['variants'] ?? '') ?>" placeholder="e.g., UK Edition, US Edition">
                            </div>
                        </div>

                        <div class="admin-card">
                            <h3 class="admin-section-heading">Pricing & Inventory</h3>
                            <div class="admin-two-col-grid">
                                <div class="form-group">
                                    <label class="form-label">Price *</label>
                                    <input type="number" step="0.01" name="price" class="form-input" required value="<?= $product['price'] ?? '' ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Sale Price</label>
                                    <input type="number" step="0.01" name="sale_price" class="form-input" value="<?= $product['sale_price'] ?? '' ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Stock Quantity *</label>
                                <input type="number" name="stock_quantity" class="form-input" required value="<?= $product['stock_quantity'] ?? '0' ?>">
                            </div>
                        </div>

                        <div class="admin-actions-row">
                            <button type="submit" class="btn btn-primary">Save Product</button>
                            <a href="<?= BASE_URL ?>/admin/products" class="btn btn-secondary">Cancel</a>
                        </div>
                    </div>

                    <!-- Right Column: Sidebar Details -->
                    <div class="admin-form-sidebar">
                        
                        <div class="admin-card">
                            <h3 class="admin-section-heading">Status & Organization</h3>
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select name="category_id" class="form-select">
                                    <option value="">Select category</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($product['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" <?= ($product['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= ($product['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="out_of_stock" <?= ($product['status'] ?? '') === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="admin-checkbox-row">
                                    <input type="checkbox" name="is_featured" <?= ($product['is_featured'] ?? 0) ? 'checked' : '' ?>>
                                    Featured Product
                                </label>
                                <label class="admin-checkbox-row">
                                    <input type="checkbox" name="is_new" <?= ($product['is_new'] ?? 0) ? 'checked' : '' ?>>
                                    New Arrival
                                </label>
                                <label class="admin-checkbox-row">
                                    <input type="checkbox" name="is_bestseller" <?= ($product['is_bestseller'] ?? 0) ? 'checked' : '' ?>>
                                    Best Seller
                                </label>
                            </div>
                        </div>

                        <div class="admin-card">
                            <h3 class="admin-section-heading">Media</h3>
                            <div class="form-group" id="global-main-image-container" <?= (!empty($parsedColors)) ? 'style="display:none;"' : '' ?>>
                                <label class="form-label">Main Product Image</label>
                                <?php if (!empty($product['main_image'])): ?>
                                    <div class="admin-image-preview-wrap">
                                        <img src="<?= htmlspecialchars(resolveAdminImageSrc($product['main_image'])) ?>" alt="Current product image" class="admin-image-preview">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="main_image_file" class="form-input" accept="image/jpeg,image/png,image/webp,image/gif">
                                <p class="admin-upload-help">Upload JPG, PNG, WEBP, or GIF (max 5 MB).</p>
                                <input type="hidden" name="current_image" value="<?= htmlspecialchars($product['main_image'] ?? '') ?>">
                            </div>
                            <?php if (!empty($parsedColors) && is_array($parsedColors)): ?>
                                <div class="form-group" id="color-media-container">
                                    <label class="form-label" style="border-bottom: 1px solid #ddd; padding-bottom: 8px; margin-bottom: 12px; font-weight: 600;">Color-Specific Media (Main & Gallery Images)</label>
                                    <p class="admin-upload-help" style="margin-bottom: 15px;">Select a color from the dropdown below to upload its specific <strong>Main Product Image</strong> and <strong>Gallery Images</strong>.</p>
                                    
                                    <select id="color-upload-selector" class="form-select" style="margin-bottom: 1.5rem;">
                                        <option value="">-- Select a color --</option>
                                        <?php foreach ($parsedColors as $colorName => $colorData): ?>
                                            <option value="<?= htmlspecialchars($colorName) ?>"><?= htmlspecialchars($colorName) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <div id="dynamic-color-uploads">
                                    <?php foreach ($parsedColors as $colorName => $colorData): ?>
                                        <div class="color-upload-group" data-color="<?= htmlspecialchars($colorName) ?>" style="display: none; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
                                            <label class="form-label" style="display:flex; align-items:center; gap:8px; margin-bottom: 1.2rem; font-size: 1.1rem;">Manage Images for 
                                                <span style="display:inline-block; width:16px; height:16px; border-radius:50%; background-color:<?= htmlspecialchars($colorData['hex'] ?? '#000') ?>; border:1px solid rgba(0,0,0,0.1);"></span>
                                                <strong><?= htmlspecialchars($colorName) ?></strong>
                                            </label>
                                            
                                            <!-- Main Color Image -->
                                            <div style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0;">
                                                <label class="form-label" style="font-weight: 600; color: #334155;">Main Product Image</label>
                                                <?php if (!empty($colorData['main_image'])): ?>
                                                    <div class="admin-image-preview-wrap" style="margin-bottom: 10px;">
                                                        <img src="<?= htmlspecialchars(resolveAdminImageSrc($colorData['main_image'])) ?>" alt="Main color image" class="admin-image-preview" style="max-width: 120px; border-radius: 4px;">
                                                        <div style="margin-top: 5px;">
                                                            <label style="font-size: 0.85rem; color: var(--color-danger); cursor: pointer;">
                                                                <input type="checkbox" name="remove_gallery_images[]" value="<?= htmlspecialchars($colorData['main_image']) ?>"> Remove Main Image
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <input type="file" name="color_main_image[<?= htmlspecialchars($colorName) ?>]" class="form-input" accept="image/jpeg,image/png,image/webp,image/gif">
                                                <p class="admin-upload-help" style="margin-top: 4px;">Upload the primary image shown for this color.</p>
                                            </div>

                                            <!-- Gallery Color Images -->
                                            <div>
                                                <label class="form-label" style="font-weight: 600; color: #334155;">Gallery Images</label>
                                                <?php if (!empty($colorData['gallery_images'])): ?>
                                                    <div class="admin-gallery-grid" style="margin-bottom: 15px;">
                                                        <?php foreach ($colorData['gallery_images'] as $galleryImage): ?>
                                                            <label class="admin-gallery-item">
                                                                <img src="<?= htmlspecialchars(resolveAdminImageSrc($galleryImage)) ?>" alt="Gallery image" class="admin-gallery-thumb">
                                                                <span class="admin-gallery-remove">
                                                                    <input type="checkbox" name="remove_gallery_images[]" value="<?= htmlspecialchars($galleryImage) ?>">
                                                                    Remove
                                                                </span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <input type="file" name="color_gallery[<?= htmlspecialchars($colorName) ?>][]" class="form-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                                                <p class="admin-upload-help" style="margin-top: 4px;">Select multiple secondary thumbnail images for this color.</p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="form-group" id="color-media-container" style="display:none;">
                                    <label class="form-label" style="border-bottom: 1px solid #ddd; padding-bottom: 8px; margin-bottom: 12px; font-weight: 600;">Color-Specific Media (Main & Gallery Images)</label>
                                    <p class="admin-upload-help" style="margin-bottom: 15px;">Select a color from the dropdown below to upload its specific <strong>Main Product Image</strong> and <strong>Gallery Images</strong>.</p>
                                    <select id="color-upload-selector" class="form-select" style="margin-bottom: 1.5rem;">
                                        <option value="">-- Select a color --</option>
                                    </select>
                                    <div id="dynamic-color-uploads"></div>
                                </div>
                            <?php endif; ?>

                            <div class="form-group" id="global-gallery-image-container" <?= (!empty($parsedColors)) ? 'style="display:none;"' : '' ?>>
                                <label class="form-label">General Gallery Images</label>
                                <?php if (!empty($productGalleryImages)): ?>
                                    <div class="admin-gallery-grid">
                                        <?php foreach ($productGalleryImages as $galleryImage): ?>
                                            <label class="admin-gallery-item">
                                                <img src="<?= htmlspecialchars(resolveAdminImageSrc($galleryImage)) ?>" alt="Gallery image" class="admin-gallery-thumb">
                                                <span class="admin-gallery-remove">
                                                    <input type="checkbox" name="remove_gallery_images[]" value="<?= htmlspecialchars($galleryImage) ?>">
                                                    Remove
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="gallery_image_files[]" class="form-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                                <p class="admin-upload-help">Select multiple images at once.</p>
                            </div>
                        </div>

                    </div>
                </form>
            <?php endif; ?>
        </main>
    </div>
    <script src="js/admin.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var editorEl = document.getElementById('quill-editor');
            if (editorEl) {
                var quill = new Quill('#quill-editor', {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'header': [1, 2, 3, false] }],
                            ['clean']
                        ]
                    }
                });

                var form = document.querySelector('.admin-product-form-layout');
                if (form) {
                    form.addEventListener('submit', function() {
                        var descriptionInput = document.getElementById('quill-description');
                        var html = quill.root.innerHTML;
                        // Avoid saving empty tags if editor is blank
                        descriptionInput.value = (html === '<p><br></p>') ? '' : html;
                    });
                }
            }

            var colorInput = document.getElementById('color-variants-input');
            var colorSelector = document.getElementById('color-upload-selector');
            if (colorInput) {
                // Initialize existing blocks
                Array.from(document.querySelectorAll('.color-upload-group')).forEach(function(el) {
                    var strong = el.querySelector('strong');
                    if (strong) {
                        el.dataset.color = strong.textContent.trim();
                    }
                });

                if (colorSelector) {
                    colorSelector.addEventListener('change', function() {
                        var selectedColor = this.value;
                        document.querySelectorAll('.color-upload-group').forEach(function(el) {
                            el.style.display = (el.dataset.color === selectedColor) ? 'block' : 'none';
                        });
                    });
                }

                colorInput.addEventListener('input', function() {
                    var val = this.value.trim();
                    var container = document.getElementById('color-media-container');
                    var dynamicUploads = document.getElementById('dynamic-color-uploads');
                    var globalMainImg = document.getElementById('global-main-image-container');
                    var globalGalleryImg = document.getElementById('global-gallery-image-container');
                    
                    if (!val) {
                        container.style.display = 'none';
                        if (globalMainImg) globalMainImg.style.display = 'block';
                        if (globalGalleryImg) globalGalleryImg.style.display = 'block';
                        return;
                    }
                    
                    container.style.display = 'block';
                    if (globalMainImg) globalMainImg.style.display = 'none';
                    if (globalGalleryImg) globalGalleryImg.style.display = 'none';
                    
                    var pairs = val.split(',');
                    
                    var existingBlocks = Array.from(dynamicUploads.querySelectorAll('.color-upload-group')).map(function(el) {
                        return el.dataset.color;
                    });
                    
                    pairs.forEach(function(p) {
                        var parts = p.split(':');
                        if (parts.length >= 2) {
                            var name = parts[0].trim();
                            var hex = parts[1].trim();
                            if (name && hex) {
                                if (!existingBlocks.includes(name)) {
                                    existingBlocks.push(name);
                                    
                                    // Add to dropdown
                                    if (colorSelector) {
                                        var option = document.createElement('option');
                                        option.value = name;
                                        option.textContent = name;
                                        colorSelector.appendChild(option);
                                    }

                                    // Create hidden block
                                    var div = document.createElement('div');
                                    div.className = 'color-upload-group';
                                    div.dataset.color = name;
                                    div.style = 'display: none; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;';
                                    div.innerHTML = '<label class="form-label" style="display:flex; align-items:center; gap:8px; margin-bottom: 1.2rem; font-size: 1.1rem;">Manage Images for ' + 
                                        '<span style="display:inline-block; width:16px; height:16px; border-radius:50%; background-color:' + hex + '; border:1px solid rgba(0,0,0,0.1);"></span>' + 
                                        '<strong>' + name + '</strong></label>' +
                                        '<div style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0;">' +
                                            '<label class="form-label" style="font-weight: 600; color: #334155;">Main Product Image</label>' +
                                            '<input type="file" name="color_main_image[' + name + ']" class="form-input" accept="image/jpeg,image/png,image/webp,image/gif">' +
                                            '<p class="admin-upload-help" style="margin-top: 4px;">Upload the primary image shown for this color.</p>' +
                                        '</div>' +
                                        '<div>' +
                                            '<label class="form-label" style="font-weight: 600; color: #334155;">Gallery Images</label>' +
                                            '<input type="file" name="color_gallery[' + name + '][]" class="form-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple>' +
                                            '<p class="admin-upload-help" style="margin-top: 4px;">Select multiple secondary thumbnail images for this color.</p>' +
                                        '</div>';
                                    dynamicUploads.appendChild(div);
                                } else {
                                    // Live update the background color as they continue typing valid hex codes
                                    var existingBlock = dynamicUploads.querySelector('.color-upload-group[data-color="' + name.replace(/"/g, '\\"') + '"]');
                                    if (existingBlock) {
                                        var colorSpan = existingBlock.querySelector('span');
                                        if (colorSpan) {
                                            colorSpan.style.backgroundColor = hex;
                                        }
                                    }
                                }
                            }
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>

