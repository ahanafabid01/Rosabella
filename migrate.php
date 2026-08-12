#!/usr/bin/env php
<?php
/**
 * Rosabella - Database Migration CLI
 * 
 * Usage:
 *   php migrate.php status       - Show migration status
 *   php migrate.php run          - Run pending migrations
 *   php migrate.php executed     - Show executed migrations
 *   php migrate.php pending      - Show pending migrations
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/database/MigrationRunner.php';

$command = $argv[1] ?? 'status';
$migrationsDir = __DIR__ . '/database/migrations';

try {
    $db = getDB();
    $runner = new MigrationRunner($db, $migrationsDir);
    
    switch ($command) {
        case 'run':
            echo "🔄 Running pending migrations...\n\n";
            $result = $runner->runPending();
            
            if (!empty($result['executed'])) {
                echo "✅ Executed migrations:\n";
                foreach ($result['executed'] as $migration) {
                    echo "   • " . $migration . "\n";
                }
                echo "\n";
            }
            
            if (!empty($result['skipped'])) {
                echo "⏭️  Already executed (skipped):\n";
                foreach ($result['skipped'] as $migration) {
                    echo "   • " . $migration . "\n";
                }
                echo "\n";
            }
            
            if (!empty($result['failed'])) {
                echo "❌ Failed migrations:\n";
                foreach ($result['failed'] as $failure) {
                    echo "   • " . $failure['file'] . "\n";
                    echo "      Error: " . $failure['error'] . "\n";
                }
                echo "\n";
            }
            
            if (empty($result['executed']) && empty($result['failed'])) {
                echo "ℹ️  No new migrations to run.\n";
            } else {
                echo "Batch: " . $result['batch'] . "\n";
            }
            break;
            
        case 'executed':
            echo "📋 Executed Migrations:\n";
            echo str_repeat("─", 70) . "\n";
            $executed = $runner->getExecutedMigrations();
            if (empty($executed)) {
                echo "No migrations have been executed yet.\n";
            } else {
                printf("%-35s | %-5s | %s\n", 'Migration', 'Batch', 'Executed At');
                echo str_repeat("─", 70) . "\n";
                foreach ($executed as $migration) {
                    printf("%-35s | %-5d | %s\n", 
                        $migration['migration'], 
                        $migration['batch'], 
                        $migration['executed_at']
                    );
                }
            }
            break;
            
        case 'pending':
            echo "⏳ Pending Migrations:\n";
            echo str_repeat("─", 50) . "\n";
            $pending = $runner->getPendingMigrations();
            if (empty($pending)) {
                echo "✅ All migrations have been executed.\n";
            } else {
                foreach ($pending as $migration) {
                    echo "   • " . $migration . "\n";
                }
            }
            break;
            
        case 'status':
        default:
            $executed = $runner->getExecutedMigrations();
            $pending = $runner->getPendingMigrations();
            
            echo "📊 Migration Status\n";
            echo str_repeat("═", 50) . "\n";
            echo "Executed: " . count($executed) . "\n";
            echo "Pending:  " . count($pending) . "\n";
            echo str_repeat("═", 50) . "\n";
            
            if (!empty($pending)) {
                echo "\n⏳ Pending migrations:\n";
                foreach ($pending as $migration) {
                    echo "   • " . $migration . "\n";
                }
                echo "\nRun 'php migrate.php run' to execute pending migrations.\n";
            } else {
                echo "\n✅ All migrations are up to date!\n";
            }
            break;
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
