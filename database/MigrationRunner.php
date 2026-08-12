<?php
/**
 * KARTLY - Database Migration Runner
 * 
 * Handles execution of database migrations stored in database/migrations/
 * Tracks executed migrations to prevent duplicate runs
 */

class MigrationRunner {
    private $db;
    private $migrationsDir;
    
    public function __construct(PDO $db, string $migrationsDir) {
        $this->db = $db;
        $this->migrationsDir = $migrationsDir;
        $this->initializeMigrationsTable();
    }
    
    /**
     * Initialize the migrations tracking table
     */
    private function initializeMigrationsTable(): void {
        try {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS migrations (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    batch INT NOT NULL,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )"
            );
        } catch (PDOException $e) {
            throw new RuntimeException("Failed to initialize migrations table: " . $e->getMessage());
        }
    }
    
    /**
     * Get the next batch number
     */
    private function getNextBatch(): int {
        try {
            $result = $this->db->query("SELECT MAX(batch) as max_batch FROM migrations")->fetch();
            return ($result['max_batch'] ?? 0) + 1;
        } catch (PDOException $e) {
            return 1;
        }
    }
    
    /**
     * Check if a migration has already been executed
     */
    private function isMigrationExecuted(string $filename): bool {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM migrations WHERE migration = ?");
            $stmt->execute([$filename]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Record a migration as executed
     */
    private function recordMigration(string $filename, int $batch): void {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO migrations (migration, batch) VALUES (?, ?)"
            );
            $stmt->execute([$filename, $batch]);
        } catch (PDOException $e) {
            throw new RuntimeException("Failed to record migration: " . $e->getMessage());
        }
    }
    
    /**
     * Run all pending migrations
     * @return array Summary of executed migrations
     */
    public function runPending(): array {
        if (!is_dir($this->migrationsDir)) {
            return ['executed' => [], 'skipped' => [], 'failed' => []];
        }
        
        $files = glob($this->migrationsDir . '/*.sql');
        if (empty($files)) {
            return ['executed' => [], 'skipped' => [], 'failed' => []];
        }
        
        sort($files); // Run migrations in order
        
        $batch = $this->getNextBatch();
        $executed = [];
        $skipped = [];
        $failed = [];
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            // Skip if already executed
            if ($this->isMigrationExecuted($filename)) {
                $skipped[] = $filename;
                continue;
            }
            
            try {
                // Read and execute SQL file
                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new RuntimeException("Could not read file");
                }
                
                // Execute each statement (split by semicolon)
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    fn($s) => !empty($s) && !preg_match('/^\-\-/', $s) && !preg_match('/^\/\*/', $s)
                );
                
                foreach ($statements as $statement) {
                    $this->db->exec($statement);
                }
                
                // Record as executed
                $this->recordMigration($filename, $batch);
                $executed[] = $filename;
                
            } catch (Throwable $e) {
                $failed[] = [
                    'file' => $filename,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'executed' => $executed,
            'skipped' => $skipped,
            'failed' => $failed,
            'batch' => $batch
        ];
    }
    
    /**
     * Get list of executed migrations
     */
    public function getExecutedMigrations(): array {
        try {
            $stmt = $this->db->query("SELECT migration, batch, executed_at FROM migrations ORDER BY batch, migration");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get list of pending migrations (not yet executed)
     */
    public function getPendingMigrations(): array {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }
        
        $files = glob($this->migrationsDir . '/*.sql');
        $pending = [];
        
        foreach ($files as $file) {
            $filename = basename($file);
            if (!$this->isMigrationExecuted($filename)) {
                $pending[] = $filename;
            }
        }
        
        return $pending;
    }
}
?>
