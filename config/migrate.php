<?php
/**
 * KARTLY - Database Migration Runner
 * 
 * This script manages database schema changes through versioned migrations.
 * Run this script from the command line or access via web to execute pending migrations.
 * 
 * Usage:
 *   php config/migrate.php              (runs all pending migrations)
 *   php config/migrate.php --rollback   (shows executed migrations)
 */

require_once __DIR__ . '/database.php';

$db = getDB();

// Create migrations tracking table if it doesn't exist
$db->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Get list of migration files
$migrationDir = __DIR__ . '/migrations';
if (!is_dir($migrationDir)) {
    mkdir($migrationDir, 0755, true);
}

$migrationFiles = array_filter(
    scandir($migrationDir),
    fn($f) => preg_match('/^\d+_.*\.php$/', $f)
);

if (empty($migrationFiles)) {
    echo "✓ No migrations found.\n";
    exit(0);
}

sort($migrationFiles);

// Check for --rollback flag to show history
if (in_array('--rollback', $argv) || in_array('--status', $argv)) {
    echo "\n📊 Migration Status:\n";
    echo str_repeat("─", 60) . "\n";
    
    $executed = $db->query("SELECT migration, executed_at FROM migrations ORDER BY executed_at DESC")->fetchAll();
    
    if (empty($executed)) {
        echo "No migrations executed yet.\n";
    } else {
        foreach ($executed as $migration) {
            echo "✓ {$migration['migration']} (at {$migration['executed_at']})\n";
        }
    }
    
    echo str_repeat("─", 60) . "\n";
    exit(0);
}

// Get executed migrations
$executedMigrations = $db->query("SELECT migration FROM migrations")->fetchAll();
$executedNames = array_map(fn($m) => $m['migration'], $executedMigrations);

// Run pending migrations
$pendingCount = 0;
foreach ($migrationFiles as $file) {
    $migrationName = pathinfo($file, PATHINFO_FILENAME);
    
    if (in_array($migrationName, $executedNames)) {
        continue;
    }
    
    try {
        echo "⏳ Running migration: $migrationName...\n";
        
        // Include and execute the migration file
        include $migrationDir . '/' . $file;
        
        // Record migration as executed
        $stmt = $db->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $stmt->execute([$migrationName]);
        
        echo "✓ Migration '$migrationName' completed successfully.\n";
        $pendingCount++;
        
    } catch (Throwable $e) {
        echo "✗ Migration '$migrationName' failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if ($pendingCount === 0) {
    echo "✓ All migrations are up to date.\n";
} else {
    echo "\n✓ $pendingCount migration(s) executed successfully.\n";
}
