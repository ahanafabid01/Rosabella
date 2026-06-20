<?php
/**
 * Image helper functions for automated WebP conversion and resizing.
 */

/**
 * Handles image upload, resizes it if needed, and converts it to WebP.
 * 
 * @param array $file The $_FILES array element (e.g., $_FILES['image'])
 * @param string $uploadDir The destination directory path (e.g., '../assets/images/products/')
 * @param int $maxWidth Maximum width for the image. Defaults to 1200.
 * @return string|false The relative path to the saved WebP image, or false on failure.
 */
function optimizeAndSaveImage($file, $uploadDir, $maxWidth = 1200) {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $tmpName = $file['tmp_name'];
    $info = getimagesize($tmpName);
    
    if (!$info) {
        return false; // Not a valid image
    }

    list($width, $height, $type) = $info;

    // Allowed types: JPEG, PNG, WEBP, GIF
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF])) {
        return false;
    }

    $mime = $info['mime'];
    $image = null;

    // Create GD image resource based on mime type
    switch ($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($tmpName);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($tmpName);
            break;
        case 'image/webp':
            $image = @imagecreatefromwebp($tmpName);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($tmpName);
            break;
    }

    if (!$image) {
        return false;
    }

    // Calculate new dimensions
    $newWidth = $width;
    $newHeight = $height;

    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = intval(($height / $width) * $newWidth);
    }

    // Create a new true color image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // Handle transparency for PNG/WEBP/GIF
    if (in_array($mime, ['image/png', 'image/webp', 'image/gif'])) {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }

    // Resample the image to the new dimensions
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Generate destination path
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
    $safeName = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $originalName);
    $fileName = time() . '_' . $safeName . '.webp';
    $targetPath = rtrim($uploadDir, '/') . '/' . $fileName;

    // Save as WebP with 80% quality
    $success = imagewebp($newImage, $targetPath, 80);

    // Clean up memory
    imagedestroy($image);
    imagedestroy($newImage);

    if ($success) {
        // Return the relative path from the app root
        $baseRoot = realpath(__DIR__ . '/../');
        $realTargetPath = realpath($targetPath);
        
        if ($realTargetPath && $baseRoot && strpos($realTargetPath, $baseRoot) === 0) {
            $relPath = substr($realTargetPath, strlen($baseRoot));
            // Ensure forward slashes for URLs and remove leading slash
            $relPath = ltrim(str_replace('\\', '/', $relPath), '/');
            return $relPath;
        }

        // Fallback if realpath fails (shouldn't happen)
        $fallback = preg_replace('/^\.\.\//', '', rtrim($uploadDir, '/')) . '/' . $fileName;
        return ltrim(str_replace('\\', '/', $fallback), '/');
    }

    return false;
}
