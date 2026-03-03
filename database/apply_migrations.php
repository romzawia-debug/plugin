<?php
// apply_migrations.php
// Exécute les fichiers SQL présents dans database/migrations/ qui
// ne sont pas encore répertoriés dans la table `migrations`.

require_once __DIR__ . '/../config/database.php';

$db = getDB();

// Crée la table migrations si nécessaire
$db->exec("CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;");

$migrationsDir = __DIR__ . '/migrations';
if (!is_dir($migrationsDir)) {
    echo "Dossier migrations introuvable: $migrationsDir\n";
    exit(1);
}

$files = glob($migrationsDir . '/*.sql');
sort($files);

foreach ($files as $file) {
    $filename = basename($file);

    // Vérifier si déjà appliqué
    $stmt = $db->prepare('SELECT COUNT(*) as c FROM migrations WHERE filename = ?');
    $stmt->execute([$filename]);
    $row = $stmt->fetch();
    if ($row && $row['c'] > 0) {
        echo "SKIP  $filename (déjà appliqué)\n";
        continue;
    }

    echo "APPLY $filename\n";
    $sql = file_get_contents($file);
    if ($sql === false) {
        echo "Impossible de lire $file\n";
        exit(1);
    }

    // Split statements by semicolon followed by newline to avoid simple issues
    $stmts = preg_split('/;\s*\r?\n/', $sql);

    try {
        foreach ($stmts as $s) {
            $s = trim($s);
            if ($s === '') continue;
            // Certains fichiers peuvent contenir commentaires ou DELIMITER; on tente exec
            $db->exec($s);
        }
        // Enregistrer migration appliquée
        $ins = $db->prepare('INSERT INTO migrations (filename) VALUES (?)');
        $ins->execute([$filename]);
        echo "OK    $filename\n";
    } catch (Exception $e) {
        echo "ERREUR lors de l'application de $filename: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "Toutes les migrations ont été traitées.\n";

