<?php

require_once __DIR__ . '/bootstrap.php';

function run_migrations(): void {
    $pdo = db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version TEXT PRIMARY KEY)');

    $applied = $pdo->query('SELECT version FROM schema_migrations')
        ->fetchAll(PDO::FETCH_COLUMN);

    $dir = __DIR__ . '/../migrations';
    if (!is_dir($dir)) {
        return;
    }

    $files = glob($dir . '/*.sql');
    sort($files);

    foreach ($files as $file) {
        $version = basename($file);
        if (in_array($version, $applied, true)) {
            continue;
        }

        $sql = file_get_contents($file);
        $pdo->exec($sql);

        $stmt = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
        $stmt->execute([$version]);

        echo "  Applied migration: {$version}\n";
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0] ?? '')) {
    run_migrations();
    echo "Migrations complete.\n";
}
