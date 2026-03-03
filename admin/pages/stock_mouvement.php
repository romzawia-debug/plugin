<?php
$db = getDB();
$produit_id = (int)($_GET['produit_id'] ?? 0);
$produit = null;
if ($produit_id) {
    $stmt = $db->prepare("SELECT id, nom, stock FROM produits WHERE id = ?");
    $stmt->execute([$produit_id]);
    $produit = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_mouvement'])) {
    verifyCsrf();
    $p_id = (int)($_POST['produit_id'] ?? 0);
    $type = $_POST['type'] ?? 'entree';
    $quantite = (int)($_POST['quantite'] ?? 0);
    $commentaire = $_POST['commentaire'] ?? '';
    $admin = adminConnecte();

    if ($p_id && $quantite !== 0) {
        $db->prepare("INSERT INTO stock_mouvements (produit_id, type, quantite, commentaire, admin_id) VALUES (?,?,?,?,?)")->execute([$p_id, $type, $quantite, $commentaire, $admin['id'] ?? null]);

        // Mettre à jour le stock produit
        $cur = $db->prepare("SELECT stock FROM produits WHERE id = ?");
        $cur->execute([$p_id]);
        $curp = $cur->fetch();
        $current = $curp ? (int)$curp['stock'] : 0;
        if ($type === 'entree') {
            $new = $current + $quantite;
        } elseif ($type === 'sortie') {
            $new = max(0, $current - $quantite);
        } else {
            // ajustement => valeur absolue
            $new = $quantite;
        }
        $db->prepare("UPDATE produits SET stock = ? WHERE id = ?")->execute([$new, $p_id]);
        setFlash('success', 'Mouvement enregistré.');
    }
    redirect('index.php?page=stock_mouvement&produit_id=' . $p_id);
}

$movements_sql = "SELECT sm.*, p.nom as produit_nom, a.nom as admin_nom, a.prenom as admin_prenom FROM stock_mouvements sm LEFT JOIN produits p ON sm.produit_id = p.id LEFT JOIN admins a ON sm.admin_id = a.id";
$params = [];
if ($produit_id) {
    $movements_sql .= " WHERE sm.produit_id = ?";
    $params[] = $produit_id;
}
$movements_sql .= " ORDER BY sm.created_at DESC LIMIT 200";
$movements = $db->prepare($movements_sql);
$movements->execute($params);
$movements = $movements->fetchAll();

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-list-ul me-2"></i>Mouvements de stock</h4>
    <a href="index.php?page=stock" class="btn btn-outline-secondary">Retour au stock</a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold">Ajouter mouvement</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="produit_id" value="<?= $produit_id ?>">
                    <div class="mb-3">
                        <label class="form-label">Produit</label>
                        <select name="produit_id" class="form-select">
                            <option value="">-- Sélectionner --</option>
                            <?php
                            $prods = $db->query("SELECT id, nom FROM produits ORDER BY nom")->fetchAll();
                            foreach ($prods as $pp): ?>
                            <option value="<?= $pp['id'] ?>" <?= ($produit_id && $produit_id == $pp['id']) ? 'selected' : '' ?>><?= htmlspecialchars($pp['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="entree">Entrée</option>
                            <option value="sortie">Sortie</option>
                            <option value="ajustement">Ajustement (valeur absolue)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantité</label>
                        <input type="number" name="quantite" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Commentaire</label>
                        <textarea name="commentaire" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" name="ajouter_mouvement" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr><th>Produit</th><th>Type</th><th class="text-end">Quantité</th><th>Commentaire</th><th>Admin</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movements as $m): ?>
                            <tr>
                                <td><?= htmlspecialchars($m['produit_nom'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($m['type']) ?></td>
                                <td class="text-end fw-bold"><?= (int)$m['quantite'] ?></td>
                                <td><?= htmlspecialchars($m['commentaire']) ?></td>
                                <td><?= htmlspecialchars(($m['admin_prenom'] ?? '') . ' ' . ($m['admin_nom'] ?? '')) ?></td>
                                <td><small class="text-muted"><?= $m['created_at'] ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
