<?php
/**
 * Migration: Add Homepage Theme Setting
 * Version: 001
 * Description: Initializes the homepage_theme setting in the settings table
 * 
 * This migration adds the default 'homepage_theme' setting if it doesn't exist.
 * The theme system allows admins to select different homepage layout variants.
 */

try {
    $db = getDB();
    
    // Check if the homepage_theme setting already exists
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM settings WHERE setting_key = 'homepage_theme'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        // Insert default homepage theme setting
        $stmt = $db->prepare(
            "INSERT INTO settings (setting_key, setting_value, setting_type, created_at) 
             VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute(['homepage_theme', 'default_theme', 'text']);
        
        error_log('[Migration 001] Successfully added homepage_theme setting to database');
    } else {
        error_log('[Migration 001] homepage_theme setting already exists, skipping');
    }
    
} catch (Throwable $e) {
    error_log('[Migration 001 ERROR] ' . $e->getMessage());
    throw new Exception('Migration 001 failed: ' . $e->getMessage());
}
