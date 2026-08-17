<?php
/**
 * Rosabella - Admin Products Management
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/layout.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$db = getDB();

// \u2500\u2500 Security: Verify CSRF on all admin POST requests \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
}

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

    require_once __DIR__ . '/../includes/image_helper.php';
    $newPath = optimizeAndSaveImage($file, $uploadDir, 1200);
    if (!$newPath) {
        $uploadError = 'Unable to process and save image as WebP.';
        return null;
    }

    return $newPath;
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

function getStandardColorDatabase(): array
{
    static $colorMap = null;
    if ($colorMap !== null) {
        return $colorMap;
    }

    $colorMap = [
        // Basics & Monochrome
        'black' => '#000000',
        'jet black' => '#0A0A0A',
        'midnight black' => '#121212',
        'matte black' => '#222222',
        'white' => '#FFFFFF',
        'off white' => '#FAF9F6',
        'pure white' => '#FFFFFF',
        'ivory' => '#FFFFF0',
        'cream' => '#FFFDD0',
        'charcoal' => '#36454F',
        'gray' => '#808080',
        'grey' => '#808080',
        'dark gray' => '#555555',
        'dark grey' => '#555555',
        'light gray' => '#D3D3D3',
        'light grey' => '#D3D3D3',
        'slate gray' => '#708090',
        'slate grey' => '#708090',
        'silver' => '#C0C0C0',
        'metallic silver' => '#AAA9AD',
        'space gray' => '#4B4B4D',
        'space grey' => '#4B4B4D',

        // Blues & Teals
        'navy blue' => '#000080',
        'navy' => '#000080',
        'royal blue' => '#4169E1',
        'sky blue' => '#87CEEB',
        'baby blue' => '#89CFF0',
        'midnight blue' => '#191970',
        'denim blue' => '#1560BD',
        'denim' => '#1560BD',
        'blue' => '#0000FF',
        'cyan' => '#00FFFF',
        'teal' => '#008080',
        'turquoise' => '#40E0D0',
        'electric blue' => '#7DF9FF',
        'powder blue' => '#B0E0E6',
        'ocean blue' => '#006994',
        'steel blue' => '#4682B4',
        'sapphire blue' => '#0F52BA',
        'sapphire' => '#0F52BA',
        'cobalt blue' => '#0047AB',
        'cobalt' => '#0047AB',
        'aquamarine' => '#7FFFD4',
        'aqua' => '#00FFFF',
        'sierra blue' => '#9BB5CE',
        'pacific blue' => '#284A5C',

        // Reds & Pinks
        'red' => '#FF0000',
        'crimson red' => '#DC143C',
        'crimson' => '#DC143C',
        'scarlet' => '#FF2400',
        'burgundy' => '#800020',
        'wine red' => '#722F37',
        'wine' => '#722F37',
        'maroon' => '#800000',
        'cherry red' => '#D2042D',
        'cherry' => '#D2042D',
        'ruby red' => '#9B111E',
        'ruby' => '#9B111E',
        'pink' => '#FFC0CB',
        'rose pink' => '#FF66CC',
        'baby pink' => '#F4C2C2',
        'hot pink' => '#FF69B4',
        'blush pink' => '#DE5D83',
        'blush' => '#DE5D83',
        'magenta' => '#FF00FF',
        'coral pink' => '#F88379',
        'dusty rose' => '#DCAE96',
        'rose gold' => '#B76E79',
        'fuchsia' => '#FF00FF',
        'salmon' => '#FA8072',

        // Greens
        'green' => '#008000',
        'olive green' => '#808000',
        'olive' => '#808000',
        'emerald green' => '#50C878',
        'emerald' => '#50C878',
        'sage green' => '#9DC183',
        'sage' => '#9DC183',
        'mint green' => '#98FF98',
        'mint' => '#98FF98',
        'forest green' => '#228B22',
        'dark green' => '#006400',
        'lime green' => '#32CD32',
        'lime' => '#00FF00',
        'army green' => '#4B5320',
        'pistachio green' => '#93C572',
        'pistachio' => '#93C572',
        'jade green' => '#00A86B',
        'jade' => '#00A86B',
        'seafoam green' => '#9FE2BF',
        'seafoam' => '#9FE2BF',
        'khaki green' => '#8A9A5B',
        'alpine green' => '#505E4D',

        // Yellows, Oranges & Earth Tones
        'yellow' => '#FFFF00',
        'mustard yellow' => '#FFDB58',
        'mustard' => '#FFDB58',
        'lemon yellow' => '#FFF700',
        'lemon' => '#FFF700',
        'gold' => '#FFD700',
        'golden' => '#FFD700',
        'metallic gold' => '#D4AF37',
        'amber' => '#FFBF00',
        'orange' => '#FFA500',
        'burnt orange' => '#CC5500',
        'coral' => '#FF7F50',
        'peach' => '#FFDAB9',
        'tangerine' => '#F28500',
        'rust' => '#B7410E',
        'terracotta' => '#E2725B',
        'apricot' => '#FBCEB1',
        'champagne' => '#F7E7CE',

        // Purples
        'purple' => '#800080',
        'violet' => '#EE82EE',
        'lavender' => '#E6E6FA',
        'plum' => '#8E4585',
        'lilac' => '#C8A2C8',
        'mauve' => '#E0B0FF',
        'eggplant' => '#614051',
        'grape' => '#6F2DA8',
        'orchid' => '#DA70D6',
        'indigo' => '#4B0082',

        // Neutrals & Browns
        'beige' => '#F5F5DC',
        'khaki' => '#C3B091',
        'brown' => '#964B00',
        'chocolate brown' => '#7B3F00',
        'chocolate' => '#7B3F00',
        'dark brown' => '#654321',
        'camel' => '#C19A6B',
        'tan' => '#D2B48C',
        'taupe' => '#483C32',
        'nude' => '#E3BC9A',
        'sand' => '#C2B280',
        'espresso' => '#4E3629',
        'copper' => '#B87333',
        'bronze' => '#CD7F32',
        'mocha' => '#967969',
        'coffee' => '#6F4E37',
    ];

    return $colorMap;
}

function resolveColorHex(string $name, ?string $hex = null): string
{
    $hex = trim((string)$hex);
    if ($hex !== '') {
        if ($hex[0] !== '#') {
            $hex = '#' . $hex;
        }
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $hex)) {
            return strtoupper($hex);
        }
    }

    $trimmedName = trim($name);
    if (preg_match('/^#([0-9a-fA-F]{3,8})$/', $trimmedName)) {
        return strtoupper($trimmedName);
    }

    $nameClean = strtolower($trimmedName);
    $db = getStandardColorDatabase();
    if (isset($db[$nameClean])) {
        return $db[$nameClean];
    }

    return '#000000';
}

function generateUniqueProductSlug(PDO $db, string $name, int $currentProductId = 0): string
{
    $baseSlug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
    if ($baseSlug === '') {
        $baseSlug = 'product';
    }

    $slug = $baseSlug;
    $counter = 1;

    while (true) {
        if ($currentProductId > 0) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $currentProductId]);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE slug = ?");
            $stmt->execute([$slug]);
        }

        $exists = (int)$stmt->fetchColumn();
        if ($exists === 0) {
            return $slug;
        }

        $counter++;
        $slug = $baseSlug . '-' . $counter;
    }
}

// Handle actions
$action = $_GET['action'] ?? 'list';
$productId = intval($_GET['id'] ?? 0);  // Always cast to int — prevents type-juggling attacks

// Delete product — requires a POST form with CSRF (GET-based delete is CSRF-vulnerable)
if ($action === 'delete' && $productId > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF already verified by the blanket check above (lines 18-21)
    // Cascade: delete associated images from disk first
    $imgStmt = $db->prepare("SELECT main_image, gallery_images FROM products WHERE id = ?");
    $imgStmt->execute([$productId]);
    $imgRow = $imgStmt->fetch();
    if ($imgRow) {
        if ($imgRow['main_image']) deleteManagedProductUpload($imgRow['main_image']);
        foreach (parseGalleryImages($imgRow['gallery_images'] ?? null) as $gImg) {
            deleteManagedProductUpload($gImg);
        }
    }
    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    if ($stmt->execute([$productId])) {
        $message = 'Product deleted successfully';
        $action = 'list';
    }
}


// Save product (create/update) - only run on add/edit actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'], true) && isset($_POST['name'])) {
    $name = sanitize($_POST['name'] ?? '');
    $slug = generateUniqueProductSlug($db, $name, ($action === 'edit' ? $productId : 0));
    $category_id = intval($_POST['category_id'] ?? 0);
    $priceRaw = trim($_POST['price'] ?? '');
    $price = $priceRaw !== '' ? floatval($priceRaw) : null;
    $salePriceRaw = trim($_POST['sale_price'] ?? '');
    $sale_price = $salePriceRaw !== '' ? floatval($salePriceRaw) : null;
    $stockRaw = trim($_POST['stock_quantity'] ?? '');
    $stock_quantity = $stockRaw !== '' ? intval($stockRaw) : null;
    // Allow basic HTML for the rich text editor
    $description = strip_tags($_POST['description'] ?? '', '<b><i><u><strong><em><p><br><ul><li><ol><h1><h2><h3><h4><h5><h6><a><span><div>');
    
    // Explicit Validation with Specific Field Identification
    if ($name === '') {
        $error = 'Product Name is required. Please fill in the product name.';
    } elseif ($category_id <= 0) {
        $error = 'Category is required. Please select a category for this product.';
    } elseif ($price === null || $price < 0) {
        $error = 'Price is required. Please enter a valid product price.';
    } elseif ($stock_quantity === null || $stock_quantity < 0) {
        $error = 'Stock Quantity is required. Please enter a valid stock quantity.';
    } elseif ($sale_price !== null && $price !== null && $sale_price >= $price) {
        $error = 'Sale Price must be lower than the regular Price.';
    }
    
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
            $pair = trim($pair);
            if ($pair === '') continue;

            $cName = $pair;
            $cHex = null;

            if (strpos($pair, ':') !== false) {
                [$cName, $cHex] = explode(':', $pair, 2);
                $cName = trim($cName);
                $cHex = trim($cHex);
            }

            if ($cName !== '') {
                $finalHex = resolveColorHex($cName, $cHex);
                $colorsData[$cName] = [
                    'hex' => $finalHex,
                    'main_image' => '',
                    'gallery_images' => []
                ];
            }
        }
    }
    
    // Existing colors from DB to retain existing hex and images
    $existingColors = [];
    if ($action === 'edit' && $productId > 0) {
        $existingStmt2 = $db->prepare("SELECT colors FROM products WHERE id = ?");
        $existingStmt2->execute([$productId]);
        $existingProdColors = $existingStmt2->fetchColumn();
        if ($existingProdColors) {
            $existingColors = json_decode($existingProdColors, true) ?: [];
            if (!isset($existingColors[0]) && !isset($existingColors['color'])) {
                // it's an associative array
                $removeGalleryImagesArr = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['remove_gallery_images'] ?? [])))));
                foreach ($colorsData as $cName => &$data) {
                    // Retain existing custom hex if user didn't specify a different hex
                    if (isset($existingColors[$cName]['hex']) && $existingColors[$cName]['hex'] !== '') {
                        // If current hex is default #000000 but existing had a valid hex, keep existing
                        if ($data['hex'] === '#000000' && $existingColors[$cName]['hex'] !== '#000000') {
                            $data['hex'] = $existingColors[$cName]['hex'];
                        }
                    }
                    if (isset($existingColors[$cName]['main_image'])) {
                        if (!in_array($existingColors[$cName]['main_image'], $removeGalleryImagesArr, true)) {
                            $data['main_image'] = $existingColors[$cName]['main_image'];
                        }
                    }
                    if (isset($existingColors[$cName]['images']) && is_array($existingColors[$cName]['images'])) {
                        // migrate old structure
                        $data['gallery_images'] = array_values(array_filter($existingColors[$cName]['images'], static function ($path) use ($removeGalleryImagesArr) {
                            return !in_array($path, $removeGalleryImagesArr, true);
                        }));
                    }
                    if (isset($existingColors[$cName]['gallery_images']) && is_array($existingColors[$cName]['gallery_images'])) {
                        $data['gallery_images'] = array_values(array_filter($existingColors[$cName]['gallery_images'], static function ($path) use ($removeGalleryImagesArr) {
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
    // Allowlist-validate status — never trust raw POST input for enum columns
    $statusRaw = sanitize($_POST['status'] ?? '');
    $status = in_array($statusRaw, ['active', 'inactive', 'out_of_stock'], true) ? $statusRaw : 'active';

    $existingProduct = null;
    if ($action === 'edit' && $productId > 0) {
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
        foreach ($_FILES['color_main_image']['name'] as $colorName => $cImgName) {
            if (!isset($colorsData[$colorName])) continue;
            if (empty($cImgName)) continue;
            
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

// Get product for editing or restore POSTed values on validation error
$product = null;
if ($action === 'edit' && $productId) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
}

if ($error && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $product = [
        'id' => $productId,
        'name' => $_POST['name'] ?? '',
        'brand' => $_POST['brand'] ?? '',
        'sku' => $_POST['sku'] ?? '',
        'style' => $_POST['style'] ?? '',
        'description' => $_POST['description'] ?? '',
        'key_features' => $_POST['key_features'] ?? '',
        'sizes' => $_POST['sizes'] ?? '',
        'colors' => $colors ?? ($_POST['colors_input'] ?? ''),
        'variants' => $_POST['variants'] ?? '',
        'price' => $_POST['price'] ?? '',
        'sale_price' => $_POST['sale_price'] ?? '',
        'stock_quantity' => $_POST['stock_quantity'] ?? '',
        'category_id' => $_POST['category_id'] ?? '',
        'status' => $_POST['status'] ?? 'active',
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'is_new' => isset($_POST['is_new']) ? 1 : 0,
        'is_bestseller' => isset($_POST['is_bestseller']) ? 1 : 0,
        'main_image' => $mainImage ?? ($product['main_image'] ?? ''),
        'gallery_images' => $galleryImagesJson ?? ($product['gallery_images'] ?? ''),
    ];
}
$productGalleryImages = parseGalleryImages($product['gallery_images'] ?? null);

// Get all products for listing
$search         = trim($_GET['search'] ?? '');
$categoryFilter = intval($_GET['category'] ?? 0);
$statusFilter   = trim($_GET['status'] ?? '');
$stockFilter    = trim($_GET['stock'] ?? '');
$badgeFilter    = trim($_GET['badge'] ?? '');
$sort           = trim($_GET['sort'] ?? 'newest');

$whereConditions = [];
$params = [];

if ($search !== '') {
    $whereConditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($categoryFilter > 0) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $categoryFilter;
}

if ($statusFilter !== '' && in_array($statusFilter, ['active', 'inactive', 'out_of_stock'], true)) {
    $whereConditions[] = "p.status = ?";
    $params[] = $statusFilter;
}

if ($stockFilter === 'in_stock') {
    $whereConditions[] = "p.stock_quantity > 5";
} elseif ($stockFilter === 'low_stock') {
    $whereConditions[] = "(p.stock_quantity > 0 AND p.stock_quantity <= 5)";
} elseif ($stockFilter === 'out_of_stock') {
    $whereConditions[] = "p.stock_quantity = 0";
}

if ($badgeFilter === 'featured') {
    $whereConditions[] = "p.is_featured = 1";
} elseif ($badgeFilter === 'on_sale') {
    $whereConditions[] = "(p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price)";
} elseif ($badgeFilter === 'new') {
    $whereConditions[] = "p.is_new = 1";
} elseif ($badgeFilter === 'bestseller') {
    $whereConditions[] = "p.is_bestseller = 1";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

$orderBy = "p.created_at DESC";
if ($sort === 'oldest') {
    $orderBy = "p.created_at ASC";
} elseif ($sort === 'price_asc') {
    $orderBy = "COALESCE(NULLIF(p.sale_price, 0), p.price) ASC";
} elseif ($sort === 'price_desc') {
    $orderBy = "COALESCE(NULLIF(p.sale_price, 0), p.price) DESC";
} elseif ($sort === 'stock_asc') {
    $orderBy = "p.stock_quantity ASC";
} elseif ($sort === 'stock_desc') {
    $orderBy = "p.stock_quantity DESC";
}

// KPI Counters
$statTotal     = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$statActive    = (int)$db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
$statLowStock  = (int)$db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 5")->fetchColumn();
$statDiscount  = (int)$db->query("SELECT COUNT(*) FROM products WHERE (sale_price IS NOT NULL AND sale_price > 0 AND sale_price < price) OR is_featured = 1")->fetchColumn();

// Pagination Setup
$perPage = max(1, min(100, intval($_GET['per_page'] ?? 15)));
$page = max(1, intval($_GET['page'] ?? 1));

$countStmt = $db->prepare("SELECT COUNT(*) FROM products p $whereClause");
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalProducts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id $whereClause ORDER BY $orderBy LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter dropdown
$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$pageTitle = 'Products Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $siteFavicon = getSetting('site_favicon'); if ($siteFavicon): ?>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . '/' . htmlspecialchars($siteFavicon) ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Rosabella Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link href="css/quill.snow.css" rel="stylesheet">
    <script src="../assets/js/color-picker-autocomplete.js"></script>
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
                <?php
                $activeFilterCount = 0;
                if ($categoryFilter) $activeFilterCount++;
                if ($statusFilter) $activeFilterCount++;
                if ($stockFilter) $activeFilterCount++;
                if ($badgeFilter) $activeFilterCount++;
                if ($sort !== 'newest') $activeFilterCount++;
                ?>
                <style>
                    .prod-kpi-grid {
                        display: grid;
                        grid-template-columns: repeat(4, 1fr);
                        gap: 1rem;
                        margin-bottom: 1.25rem;
                    }
                    .prod-filter-card {
                        margin-bottom: 1.25rem;
                        padding: 0.85rem 1rem;
                        background: #ffffff;
                        border: 1.5px solid #e2e8f0;
                        border-radius: 12px;
                    }
                    .prod-filter-form {
                        display: flex;
                        flex-direction: column;
                        width: 100%;
                    }
                    .prod-filter-top-bar {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        width: 100%;
                    }
                    .prod-filter-search {
                        position: relative;
                        flex: 1 1 auto;
                        min-width: 200px;
                    }
                    .filter-toggle-btn {
                        display: inline-flex !important;
                        align-items: center;
                        justify-content: center;
                        gap: 6px;
                        height: 38px;
                        padding: 0 14px;
                        font-size: 0.85rem;
                        font-weight: 600;
                        border-radius: 8px;
                        white-space: nowrap;
                        flex-shrink: 0;
                        cursor: pointer;
                    }
                    .prod-filter-drawer {
                        display: none;
                        width: 100%;
                        padding-top: 12px;
                        border-top: 1px dashed #e2e8f0;
                        margin-top: 10px;
                        flex-wrap: wrap;
                        gap: 10px;
                        align-items: center;
                    }
                    .prod-filter-drawer.active {
                        display: flex !important;
                    }
                    .prod-filter-select {
                        height: 38px;
                        font-size: 0.85rem;
                        padding: 0 0.75rem;
                        border-radius: 8px;
                        border: 1px solid #cbd5e1;
                        background-color: #ffffff;
                        color: #1e293b;
                        flex: 1 1 130px;
                        min-width: 125px;
                    }
                    .prod-filter-actions {
                        display: flex;
                        gap: 6px;
                        margin-left: auto;
                        flex-shrink: 0;
                    }
                    @media (max-width: 900px) {
                        .prod-kpi-grid {
                            grid-template-columns: repeat(2, 1fr) !important;
                            gap: 0.75rem !important;
                        }
                    }
                    @media (max-width: 768px) {
                        .prod-filter-drawer.active {
                            display: grid !important;
                            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                            gap: 8px !important;
                        }
                        .prod-filter-select {
                            width: 100% !important;
                            max-width: 100% !important;
                            min-width: 0 !important;
                            flex: none !important;
                            box-sizing: border-box !important;
                            height: 38px !important;
                            font-size: 0.82rem !important;
                            padding: 0 8px !important;
                        }
                        .prod-filter-actions {
                            grid-column: span 2 !important;
                            margin-left: 0 !important;
                            width: 100% !important;
                            display: flex !important;
                            gap: 8px !important;
                        }
                        .prod-filter-actions button, .prod-filter-actions a {
                            flex: 1 !important;
                            height: 38px !important;
                            display: inline-flex !important;
                            align-items: center !important;
                            justify-content: center !important;
                            text-align: center !important;
                        }
                    }
                </style>

                <!-- Top Metric Cards (2 Columns per row on Mobile) -->
                <div class="prod-kpi-grid">
                    <a href="<?= BASE_URL ?>/admin/products" style="text-decoration: none; background: #ffffff; border: 1px solid <?= (!$statusFilter && !$stockFilter && !$badgeFilter) ? 'var(--color-primary)' : '#e2e8f0' ?>; border-radius: 10px; padding: 0.75rem 0.9rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="font-size: 1.18rem; font-weight: 600; color: #0f172a; line-height: 1.2;"><?= number_format($statTotal) ?></div>
                            <div style="font-size: 0.74rem; font-weight: 450; color: #64748b; margin-top: 3px;">Total Products</div>
                        </div>
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: #f8fafc; color: #475569; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                        </div>
                    </a>
                    <a href="<?= BASE_URL ?>/admin/products?status=active" style="text-decoration: none; background: #ffffff; border: 1px solid <?= $statusFilter === 'active' ? '#10b981' : '#e2e8f0' ?>; border-radius: 10px; padding: 0.75rem 0.9rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="font-size: 1.18rem; font-weight: 600; color: #0f172a; line-height: 1.2;"><?= number_format($statActive) ?></div>
                            <div style="font-size: 0.74rem; font-weight: 450; color: #64748b; margin-top: 3px;">Active Products</div>
                        </div>
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: #ecfdf5; color: #10b981; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </div>
                    </a>
                    <a href="<?= BASE_URL ?>/admin/products?stock=low_stock" style="text-decoration: none; background: #ffffff; border: 1px solid <?= ($stockFilter === 'low_stock' || $stockFilter === 'out_of_stock') ? '#ef4444' : '#e2e8f0' ?>; border-radius: 10px; padding: 0.75rem 0.9rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="font-size: 1.18rem; font-weight: 600; color: #0f172a; line-height: 1.2;"><?= number_format($statLowStock) ?></div>
                            <div style="font-size: 0.74rem; font-weight: 450; color: #64748b; margin-top: 3px;">Low & Out of Stock</div>
                        </div>
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: #fef2f2; color: #ef4444; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        </div>
                    </a>
                    <a href="<?= BASE_URL ?>/admin/products?badge=featured" style="text-decoration: none; background: #ffffff; border: 1px solid <?= ($badgeFilter === 'featured' || $badgeFilter === 'on_sale') ? '#0f766e' : '#e2e8f0' ?>; border-radius: 10px; padding: 0.75rem 0.9rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="font-size: 1.18rem; font-weight: 600; color: #0f172a; line-height: 1.2;"><?= number_format($statDiscount) ?></div>
                            <div style="font-size: 0.74rem; font-weight: 450; color: #64748b; margin-top: 3px;">Featured & Sale</div>
                        </div>
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: #f0fdfa; color: #0f766e; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0f766e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        </div>
                    </a>
                </div>

                <!-- Professional Multi-Filter Control Toolbar -->
                <div class="prod-filter-card">
                    <form method="GET" action="<?= BASE_URL ?>/admin/products" class="prod-filter-form">
                        <!-- Search Bar & Filter Toggle Button -->
                        <div class="prod-filter-top-bar">
                            <div class="prod-filter-search">
                                <input type="text" name="search" class="form-input" placeholder="Search product, SKU, brand..." value="<?= htmlspecialchars($search) ?>" style="padding-left: 2.2rem; height: 38px; font-size: 0.85rem; width: 100%; border-radius: 8px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            </div>

                            <button type="button" class="btn btn-outline filter-toggle-btn" onclick="document.getElementById('prod-filter-drawer').classList.toggle('active')" title="Toggle Filter Options">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                <span>Filter<?= $activeFilterCount > 0 ? " ($activeFilterCount)" : "" ?></span>
                            </button>
                        </div>

                        <!-- Filter Options Drawer -->
                        <div id="prod-filter-drawer" class="prod-filter-drawer <?= ($activeFilterCount > 0) ? 'active' : '' ?>">
                            <!-- Category Filter -->
                            <select name="category" class="form-select prod-filter-select" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $categoryFilter === intval($cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Status Filter -->
                            <select name="status" class="form-select prod-filter-select" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="out_of_stock" <?= $statusFilter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                            </select>

                            <!-- Stock Filter -->
                            <select name="stock" class="form-select prod-filter-select" onchange="this.form.submit()">
                                <option value="">All Stock</option>
                                <option value="in_stock" <?= $stockFilter === 'in_stock' ? 'selected' : '' ?>>In Stock (>5)</option>
                                <option value="low_stock" <?= $stockFilter === 'low_stock' ? 'selected' : '' ?>>Low Stock (1-5)</option>
                                <option value="out_of_stock" <?= $stockFilter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock (0)</option>
                            </select>

                            <!-- Badge Filter -->
                            <select name="badge" class="form-select prod-filter-select" onchange="this.form.submit()">
                                <option value="">All Types</option>
                                <option value="featured" <?= $badgeFilter === 'featured' ? 'selected' : '' ?>>Featured</option>
                                <option value="on_sale" <?= $badgeFilter === 'on_sale' ? 'selected' : '' ?>>On Sale</option>
                                <option value="new" <?= $badgeFilter === 'new' ? 'selected' : '' ?>>New Arrival</option>
                                <option value="bestseller" <?= $badgeFilter === 'bestseller' ? 'selected' : '' ?>>Best Seller</option>
                            </select>

                            <!-- Sort By -->
                            <select name="sort" class="form-select prod-filter-select" onchange="this.form.submit()">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Sort: Newest</option>
                                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Sort: Oldest</option>
                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low-High</option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High-Low</option>
                                <option value="stock_asc" <?= $sort === 'stock_asc' ? 'selected' : '' ?>>Stock: Low-High</option>
                                <option value="stock_desc" <?= $sort === 'stock_desc' ? 'selected' : '' ?>>Stock: High-Low</option>
                            </select>

                            <div class="prod-filter-actions">
                                <button type="submit" class="btn btn-primary" style="height: 38px; font-size: 0.85rem; padding: 0 1rem; border-radius: 8px;">Filter</button>
                                <?php if ($search || $categoryFilter || $statusFilter || $stockFilter || $badgeFilter || $sort !== 'newest'): ?>
                                    <a href="<?= BASE_URL ?>/admin/products" class="btn btn-secondary" style="height: 38px; font-size: 0.85rem; padding: 0 0.75rem; border-radius: 8px; display: inline-flex; align-items: center;">Clear</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Products Table -->
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th style="min-width: 250px;">Product</th>
                                <th>Category</th>
                                <th style="text-align: right;">Price</th>
                                <th style="text-align: center;">Stock</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 3rem; color: #94a3b8;">
                                        No products found matching the selected filters.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $p): ?>
                                <?php
                                    $imgSrc = !empty($p['main_image']) ? resolveAdminImageSrc($p['main_image']) : '';
                                    $hasSale = (!empty($p['sale_price']) && $p['sale_price'] > 0 && $p['sale_price'] < $p['price']);
                                    $stockQty = intval($p['stock_quantity']);
                                ?>
                                <tr>
                                    <td style="font-weight: 600; color: #64748b;">#<?= $p['id'] ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <?php if ($imgSrc): ?>
                                                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="" style="width: 44px; height: 44px; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; background: #f8fafc; flex-shrink: 0;">
                                            <?php else: ?>
                                                <div style="width: 44px; height: 44px; border-radius: 8px; background: #f1f5f9; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 0.7rem; font-weight: 700; flex-shrink: 0;">IMG</div>
                                            <?php endif; ?>
                                            <div style="display: flex; flex-direction: column; gap: 2px;">
                                                <div style="font-weight: 700; color: #0f172a; font-size: 0.93rem; line-height: 1.25;">
                                                    <?= htmlspecialchars($p['name']) ?>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.78rem; color: #64748b;">
                                                    <?php if (!empty($p['sku'])): ?><span>SKU: <strong style="color: #334155; font-family: monospace;"><?= htmlspecialchars($p['sku']) ?></strong></span><?php endif; ?>
                                                    <?php if (!empty($p['brand'])): ?><span>• <?= htmlspecialchars($p['brand']) ?></span><?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.82rem; background: #f1f5f9; color: #334155; padding: 3px 8px; border-radius: 6px; font-weight: 600;">
                                            <?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if ($hasSale): ?>
                                            <div style="font-weight: 700; color: #0f766e; font-size: 0.93rem;"><?= formatPrice($p['sale_price']) ?></div>
                                            <div style="font-size: 0.76rem; color: #94a3b8; text-decoration: line-through;"><?= formatPrice($p['price']) ?></div>
                                        <?php else: ?>
                                            <div style="font-weight: 700; color: #1e293b; font-size: 0.93rem;"><?= formatPrice($p['price']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($stockQty > 5): ?>
                                            <span class="badge badge-success" style="font-size: 0.78rem; padding: 3px 8px; font-weight: 700;"><?= $stockQty ?> in stock</span>
                                        <?php elseif ($stockQty > 0): ?>
                                            <span class="badge badge-warning" style="font-size: 0.78rem; padding: 3px 8px; font-weight: 700;">Low Stock (<?= $stockQty ?>)</span>
                                        <?php else: ?>
                                            <span class="badge badge-error" style="font-size: 0.78rem; padding: 3px 8px; font-weight: 700;">Out of Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge badge-<?= $p['status'] === 'active' ? 'success' : ($p['status'] === 'inactive' ? 'secondary' : 'error') ?>" style="font-size: 0.78rem; padding: 3px 8px; font-weight: 700;">
                                            <?= ucfirst($p['status']) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="admin-actions-row">
                                            <a href="<?= BASE_URL ?>/product/<?= htmlspecialchars($p['slug']) ?>" target="_blank" class="btn-action-icon view" title="View Product on Storefront">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                            </a>
                                            <a href="?action=edit&id=<?= $p['id'] ?>" class="btn-action-icon edit" title="Edit Product">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            </a>
                                            <form method="POST" action="?action=delete&id=<?= $p['id'] ?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product?')">
                                                <?= csrfField() ?>
                                                <button type="submit" class="btn-action-icon delete" title="Delete Product">
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php renderAdminPagination($page, $totalProducts, $perPage, BASE_URL . '/admin/products', array_filter(['search' => $search, 'category' => $categoryFilter, 'status' => $statusFilter, 'stock' => $stockFilter, 'badge' => $badgeFilter, 'sort' => $sort])); ?>
            <?php else: ?>
                <!-- Professional Two-Column Product Form -->
                <form method="POST" enctype="multipart/form-data" class="admin-product-form-layout">
                        <!-- Security: CSRF token -->
                        <?= csrfField() ?>
                    
                    <!-- Left Column: Main Details -->
                    <div class="admin-form-main">
                        <div class="admin-card">
                            <h3 class="admin-section-heading">Basic Information</h3>
                            <div class="form-group">
                                <label class="form-label">Product Name *</label>
                                <input type="text" name="name" class="form-input" required value="<?= htmlspecialchars($product['name'] ?? '') ?>" placeholder="e.g., Premium Floral Silk Evening Dress">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Brand</label>
                                <input type="text" name="brand" class="form-input" value="<?= htmlspecialchars($product['brand'] ?? '') ?>" placeholder="e.g., Rosabella Paris / Zara / Gucci">
                            </div>
                            <div class="admin-two-col-grid">
                                <div class="form-group">
                                    <label class="form-label">SKU</label>
                                    <input type="text" name="sku" class="form-input" value="<?= htmlspecialchars($product['sku'] ?? '') ?>" placeholder="e.g., RSB-2026-DRS">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Style</label>
                                    <input type="text" name="style" class="form-input" value="<?= htmlspecialchars($product['style'] ?? '') ?>" placeholder="e.g., Evening Wear / Casual / Slim Fit">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <div id="quill-editor" style="height: 200px; background: #fff;"><?= $product['description'] ?? '' ?></div>
                                <input type="hidden" name="description" id="quill-description">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Key Features (One per line)</label>
                                <textarea name="key_features" class="form-textarea" rows="4" placeholder="• 100% Premium handcrafted quality&#10;• Ultra-soft breathable luxury fabric&#10;• Elegant gift packaging included&#10;• Easy care & durable finish"><?= htmlspecialchars($product['key_features'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Sizes (Comma separated)</label>
                                <input type="text" name="sizes" class="form-input" value="<?= htmlspecialchars($product['sizes'] ?? '') ?>" placeholder="e.g., XS, S, M, L, XL, XXL (or 38, 39, 40, 41, 42)">
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
                                <input type="text" name="colors_input" id="color-variants-input" class="form-input" value="<?= htmlspecialchars($colorsString) ?>" placeholder="e.g., Red, Crimson Red, Navy Blue, Rose Gold, Black, White">
                                <p class="admin-upload-help" style="margin-top: 0.25rem;">Enter comma-separated color names. Use the search picker below to select standard colors with live swatches!</p>
                                
                                <!-- Color Swatch Search Autocomplete Container -->
                                <div id="prod-color-swatch-picker-widget" style="margin-top: 10px; background: #f0fdf4; border: 1.5px solid #a7f3d0; border-radius: 10px; padding: 12px;">
                                    <label style="font-size: 0.82rem; font-weight: 700; color: #047857; display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#047857" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.92 0 1.7-.72 1.7-1.65 0-.43-.17-.83-.44-1.14-.29-.33-.46-.77-.46-1.21 0-.93.75-1.7 1.68-1.7H16c3.31 0 6-2.69 6-6 0-4.97-4.48-9-10-9z"/></svg>
                                        Search & Pick E-Commerce Colors (~150 Swatches)
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Other Variants (Comma separated)</label>
                                <input type="text" name="variants" class="form-input" value="<?= htmlspecialchars($product['variants'] ?? '') ?>" placeholder="e.g., Standard Edition, Deluxe Gift Box, Velvet Bundle">
                            </div>
                        </div>

                        <div class="admin-card">
                            <h3 class="admin-section-heading">Pricing & Inventory</h3>
                            <div class="admin-two-col-grid">
                                <div class="form-group">
                                    <label class="form-label">Price *</label>
                                    <input type="number" step="0.01" min="0" name="price" class="form-input" required value="<?= $product['price'] ?? '' ?>" placeholder="0.00">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Sale Price</label>
                                    <input type="number" step="0.01" min="0" name="sale_price" class="form-input" value="<?= $product['sale_price'] ?? '' ?>" placeholder="0.00 (optional discount)">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Stock Quantity *</label>
                                <input type="number" min="0" name="stock_quantity" class="form-input" required value="<?= isset($product['stock_quantity']) ? htmlspecialchars((string)$product['stock_quantity']) : '' ?>" placeholder="e.g., 50">
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
                                <label class="form-label">Category *</label>
                                <select name="category_id" id="product_category_id" class="form-select" required onchange="fetchCategoryAttributes(this.value)">
                                    <option value="">Select category</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($product['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <!-- Dynamic Category Attributes Section -->
                                <div id="category-attributes-box" style="display: none; background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 10px; padding: 12px; margin-top: 12px;">
                                    <h4 style="margin: 0 0 6px; font-size: 0.82rem; font-weight: 700; color: #166534; display: flex; align-items: center; gap: 4px;">
                                        🏷️ Category Quick Options
                                    </h4>
                                    <div id="category-attributes-pills" style="display: flex; flex-direction: column; gap: 8px;"></div>
                                </div>
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
                            <div class="form-group" id="color-media-container" style="<?= empty($parsedColors) ? 'display:none;' : '' ?>">
                                <label class="form-label" style="border-bottom: 1px solid #ddd; padding-bottom: 8px; margin-bottom: 12px; font-weight: 600;">Color-Specific Media (Main & Gallery Images)</label>
                                <p class="admin-upload-help" style="margin-bottom: 15px;">Select a color from the dropdown below to upload its specific <strong>Main Product Image</strong> and <strong>Gallery Images</strong>.</p>
                                
                                <select id="color-upload-selector" class="form-select" style="margin-bottom: 1.5rem;">
                                    <option value="">-- Select a color --</option>
                                    <?php if (!empty($parsedColors)): ?>
                                        <?php foreach ($parsedColors as $colorName => $colorData): ?>
                                            <option value="<?= htmlspecialchars($colorName) ?>"><?= htmlspecialchars($colorName) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                
                                <div id="dynamic-color-uploads">
                                <?php if (!empty($parsedColors)): ?>
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
    <script src="js/quill.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var editorEl = document.getElementById('quill-editor');
            if (editorEl) {
                var quill = new Quill('#quill-editor', {
                    theme: 'snow',
                    placeholder: 'Write a comprehensive, engaging product overview, material specs, care instructions, and styling details...',
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
                    function clearValidationStyles(el) {
                        if (!el) return;
                        el.style.borderColor = '';
                        el.style.boxShadow = '';
                        var parent = el.closest('.form-group') || el.parentNode;
                        if (parent) {
                            var feedback = parent.querySelector('.form-inline-validation-error');
                            if (feedback) feedback.remove();
                        }
                    }

                    form.querySelectorAll('input, select, textarea').forEach(function(el) {
                        el.addEventListener('input', function() { clearValidationStyles(this); });
                        el.addEventListener('change', function() { clearValidationStyles(this); });
                    });

                    function highlightFieldWithError(el, fieldLabel, message) {
                        if (!el) return;
                        el.style.transition = 'border-color 0.25s ease, box-shadow 0.25s ease';
                        el.style.borderColor = '#ef4444';
                        el.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.25)';
                        
                        var parent = el.closest('.form-group') || el.parentNode;
                        if (parent && !parent.querySelector('.form-inline-validation-error')) {
                            var errDiv = document.createElement('div');
                            errDiv.className = 'form-inline-validation-error';
                            errDiv.style.cssText = 'color: #dc2626; font-size: 0.8rem; font-weight: 600; margin-top: 6px; display: flex; align-items: center; gap: 4px;';
                            errDiv.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg> ' + message;
                            parent.appendChild(errDiv);
                        }

                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        setTimeout(function() { el.focus(); }, 250);

                        showValidationToast(fieldLabel, message);
                    }

                    function showValidationToast(fieldLabel, message) {
                        var oldToast = document.getElementById('admin-form-val-toast');
                        if (oldToast) oldToast.remove();

                        var toast = document.createElement('div');
                        toast.id = 'admin-form-val-toast';
                        toast.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 99999; background: #991b1b; color: #ffffff; padding: 14px 18px; border-radius: 10px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); font-size: 0.88rem; font-weight: 600; display: flex; align-items: center; gap: 12px; max-width: 440px; border-left: 5px solid #f87171;';
                        toast.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fecaca" stroke-width="2.5" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>' +
                            '<div><strong style="display:block; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.06em; color:#fca5a5;">Required Field Missing: ' + fieldLabel + '</strong>' + message + '</div>';

                        document.body.appendChild(toast);
                        setTimeout(function() {
                            if (toast.parentNode) {
                                toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                                toast.style.opacity = '0';
                                toast.style.transform = 'translateY(-10px)';
                                setTimeout(function() { if (toast.parentNode) toast.remove(); }, 300);
                            }
                        }, 4000);
                    }

                    form.addEventListener('submit', function(e) {
                        var descriptionInput = document.getElementById('quill-description');
                        if (quill && descriptionInput) {
                            var html = quill.root.innerHTML;
                            descriptionInput.value = (html === '<p><br></p>') ? '' : html;
                        }

                        var nameField = form.querySelector('[name="name"]');
                        var catField = form.querySelector('[name="category_id"]');
                        var priceField = form.querySelector('[name="price"]');
                        var stockField = form.querySelector('[name="stock_quantity"]');
                        var salePriceField = form.querySelector('[name="sale_price"]');

                        if (nameField && !nameField.value.trim()) {
                            e.preventDefault();
                            e.stopPropagation();
                            highlightFieldWithError(nameField, 'Product Name', 'Please fill in the Product Name before saving.');
                            return false;
                        }

                        if (catField && (!catField.value || catField.value === '' || catField.value === '0')) {
                            e.preventDefault();
                            e.stopPropagation();
                            highlightFieldWithError(catField, 'Category', 'Please choose a Category from the dropdown.');
                            return false;
                        }

                        if (priceField && (priceField.value.trim() === '' || isNaN(parseFloat(priceField.value)) || parseFloat(priceField.value) < 0)) {
                            e.preventDefault();
                            e.stopPropagation();
                            highlightFieldWithError(priceField, 'Price', 'Please enter a valid Price (e.g. 29.99).');
                            return false;
                        }

                        if (stockField && (stockField.value.trim() === '' || isNaN(parseInt(stockField.value, 10)) || parseInt(stockField.value, 10) < 0)) {
                            e.preventDefault();
                            e.stopPropagation();
                            highlightFieldWithError(stockField, 'Stock Quantity', 'Please enter the available Stock Quantity.');
                            return false;
                        }

                        if (salePriceField && salePriceField.value.trim() !== '' && priceField && priceField.value.trim() !== '') {
                            var regP = parseFloat(priceField.value);
                            var saleP = parseFloat(salePriceField.value);
                            if (saleP >= regP) {
                                e.preventDefault();
                                e.stopPropagation();
                                highlightFieldWithError(salePriceField, 'Sale Price', 'Discounted Sale Price must be lower than the regular Price.');
                                return false;
                            }
                        }
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
                    
                    // Parse clean color names array
                    var activeColorNames = val.split(',')
                        .map(function(s) { return s.trim(); })
                        .filter(Boolean)
                        .map(function(s) { return s.includes(':') ? s.split(':')[0].trim() : s; });

                    // Deduplicate active color names preserving order
                    var uniqueActiveColors = [];
                    activeColorNames.forEach(function(c) {
                        if (c && !uniqueActiveColors.includes(c)) {
                            uniqueActiveColors.push(c);
                        }
                    });

                    if (uniqueActiveColors.length === 0) {
                        if (container) container.style.display = 'none';
                        if (globalMainImg) globalMainImg.style.display = 'block';
                        if (globalGalleryImg) globalGalleryImg.style.display = 'block';
                        if (colorSelector) colorSelector.innerHTML = '<option value="">-- Select a color --</option>';
                        return;
                    }
                    
                    if (container) container.style.display = 'block';
                    if (globalMainImg) globalMainImg.style.display = 'none';
                    if (globalGalleryImg) globalGalleryImg.style.display = 'none';
                    
                    // 1. Rebuild Dropdown Options cleanly
                    if (colorSelector) {
                        var currentSelected = colorSelector.value;
                        colorSelector.innerHTML = '<option value="">-- Select a color --</option>';
                        uniqueActiveColors.forEach(function(name) {
                            var option = document.createElement('option');
                            option.value = name;
                            option.textContent = name;
                            colorSelector.appendChild(option);
                        });
                        
                        if (uniqueActiveColors.includes(currentSelected)) {
                            colorSelector.value = currentSelected;
                        } else if (uniqueActiveColors.length === 1) {
                            // Single color product: auto-select the color automatically!
                            colorSelector.value = uniqueActiveColors[0];
                        } else {
                            colorSelector.value = '';
                        }
                    }
                    
                    // 2. Sync Color Upload Blocks
                    if (dynamicUploads) {
                        // Remove groups that are no longer in active colors
                        Array.from(dynamicUploads.querySelectorAll('.color-upload-group')).forEach(function(group) {
                            var colorName = group.dataset.color;
                            if (!uniqueActiveColors.includes(colorName)) {
                                group.remove();
                            }
                        });

                        // Create groups for new active colors
                        uniqueActiveColors.forEach(function(name) {
                            var existing = dynamicUploads.querySelector('.color-upload-group[data-color="' + name.replace(/"/g, '\\"') + '"]');
                            if (!existing) {
                                var hex = '#000000';
                                if (window.RosabellaColorDb && Array.isArray(window.RosabellaColorDb)) {
                                    var matched = window.RosabellaColorDb.find(function(c) {
                                        return c.name.toLowerCase() === name.toLowerCase();
                                    });
                                    if (matched) hex = matched.hex;
                                }

                                var div = document.createElement('div');
                                div.className = 'color-upload-group';
                                div.dataset.color = name;
                                div.style.display = (colorSelector && colorSelector.value === name) ? 'block' : 'none';
                                div.style.marginBottom = '20px';
                                div.style.padding = '15px';
                                div.style.background = '#f8f9fa';
                                div.style.borderRadius = '6px';
                                div.style.border = '1px solid #e9ecef';
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
                            }
                        });
                    }

                    // Trigger change to update active visible block
                    if (colorSelector) {
                        colorSelector.dispatchEvent(new Event('change'));
                    }
                });
                
                // Trigger initial check if colors already entered
                if (colorInput.value.trim()) {
                    colorInput.dispatchEvent(new Event('input'));
                }
            }
        });

        // Category Attributes Fetcher & Dynamic Option Applicator
        function fetchCategoryAttributes(catId) {
            const box = document.getElementById('category-attributes-box');
            const pillsDiv = document.getElementById('category-attributes-pills');
            if (!catId || !box || !pillsDiv) {
                if (box) box.style.display = 'none';
                return;
            }

            fetch('<?= BASE_URL ?>/api/category_attributes.php?category_id=' + catId)
                .then(res => res.json())
                .then(data => {
                    if (!data.success || !data.attributes || data.attributes.length === 0) {
                        box.style.display = 'none';
                        return;
                    }

                    box.style.display = 'block';
                    pillsDiv.innerHTML = '';

                    data.attributes.forEach(attr => {
                        const groupDiv = document.createElement('div');
                        groupDiv.style = "margin-bottom: 6px;";
                        let pillsHtml = `<div style="font-size: 0.76rem; font-weight: 700; color: #15803d; margin-bottom: 4px;">${attr.name} (${attr.type}):</div><div style="display: flex; flex-wrap: wrap; gap: 4px;">`;
                        
                        attr.values.forEach(val => {
                            const escVal = val.replace(/'/g, "\\'");
                            pillsHtml += `<button type="button" class="btn btn-sm" onclick="applyAttrToProduct('${attr.type}', '${escVal}')" style="font-size: 0.72rem; padding: 2px 7px; background: #ffffff; border: 1px solid #86efac; color: #166534; border-radius: 4px; cursor: pointer; transition: all 0.15s ease;">+ ${val}</button>`;
                        });
                        
                        pillsHtml += `</div>`;
                        groupDiv.innerHTML = pillsHtml;
                        pillsDiv.appendChild(groupDiv);
                    });
                })
                .catch(err => {
                    if (box) box.style.display = 'none';
                });
        }

        function applyAttrToProduct(type, value) {
            let targetSelector = 'input[name="variants"]';
            if (type === 'size') {
                targetSelector = 'input[name="sizes"]';
            } else if (type === 'color') {
                targetSelector = '#color-variants-input, input[name="colors_input"]';
            }

            const input = document.querySelector(targetSelector);
            if (input) {
                let current = input.value.split(',').map(s => s.trim()).filter(Boolean);
                if (!current.includes(value)) {
                    current.push(value);
                    input.value = current.join(', ');
                    input.dispatchEvent(new Event('input'));
                    // Flash feedback outline on the target input box
                    input.style.transition = 'border-color 0.2s ease, box-shadow 0.2s ease';
                    input.style.borderColor = '#10b981';
                    input.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.2)';
                    setTimeout(() => {
                        input.style.borderColor = '';
                        input.style.boxShadow = '';
                    }, 800);
                }
            }
        }

        // On initial page load, trigger category attributes & init color search picker
        document.addEventListener('DOMContentLoaded', () => {
            const catSelect = document.getElementById('product_category_id');
            if (catSelect && catSelect.value) {
                fetchCategoryAttributes(catSelect.value);
            }
            if (window.initColorSearchPicker) {
                window.initColorSearchPicker('prod-color-swatch-picker-widget', 'color-variants-input');
            }
        });
    </script>
</body>
</html>
