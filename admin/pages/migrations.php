<?php
/**
 * Page admin: exécuter les migrations SQL depuis l'interface
 */

verifyCsrf();
$admin = adminConnecte();
$db = getDB();

// Assure la table migrations
$db->exec("CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;");

$migrationsDir = __DIR__ . '/../../database/migrations';
if (!is_dir($migrationsDir)) {
    echo '<div class="alert alert-warning">Dossier migrations introuvable.</div>';
    return;
}

$files = glob($migrationsDir . '/*.sql');
sort($files);

$applied = [];
$stmt = $db->query('SELECT filename FROM migrations');
foreach ($stmt->fetchAll() as $r) $applied[$r['filename']] = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migrations'])) {
    verifyCsrf();
    $selected = $_POST['files'] ?? [];
    if (empty($selected)) {
        setFlash('warning', 'Aucune migration sélectionnée.');
        redirect('index.php?page=migrations');
    }

    foreach ($selected as $filename) {
        $path = $migrationsDir . '/' . basename($filename);
        if (!file_exists($path)) continue;
        // Skip if already applied
        $s = $db->prepare('SELECT COUNT(*) as c FROM migrations WHERE filename = ?');
        $s->execute([$filename]);
        $r = $s->fetch();
        if ($r && $r['c'] > 0) continue;

        $sql = file_get_contents($path);
        if ($sql === false) {
            setFlash('danger', "Impossible de lire $filename");
            redirect('index.php?page=migrations');
        }

        try {
            $stmts = preg_split('/;\s*\r?\n/', $sql);
            $db->beginTransaction();
            foreach ($stmts as $smt) {
                $smt = trim($smt);
                if ($smt === '') continue;
                $db->exec($smt);
            }
            $ins = $db->prepare('INSERT INTO migrations (filename) VALUES (?)');
            $ins->execute([$filename]);
            $db->commit();
            setFlash('success', "Migration $filename appliquée.");
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('danger', "Erreur $filename: " . $e->getMessage());
            redirect('index.php?page=migrations');
        }
    }
    redirect('index.php?page=migrations');
}

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-database-fill me-2"></i>Migrations</h4>
</div>

<form method="POST">
    <?= csrfField() ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr><th></th><th>Fichier</th><th>Statut</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files as $f): $fn = basename($f); ?>
                        <tr>
                            <td><input type="checkbox" name="files[]" value="<?= htmlspecialchars($fn) ?>" <?= isset($applied[$fn]) ? 'disabled' : '' ?>></td>
                            <td><?= htmlspecialchars($fn) ?></td>
                            <td>
                                <?php if (isset($applied[$fn])): ?>
                                    <span class="badge bg-success">Appliquée</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">En attente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button type="submit" name="run_migrations" class="btn btn-primary" onclick="return confirm('Êtes-vous sûr(e) de vouloir lancer les migrations sélectionnées ?');">Exécuter les migrations sélectionnées</button>
        <a href="index.php?page=stock" class="btn btn-outline-secondary">Retour</a>
    </div>
</form>
