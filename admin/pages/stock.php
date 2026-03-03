<?php
$db = getDB();

$produits = $db->query("SELECT id, nom, slug, stock, stock_min, unite FROM produits ORDER BY nom")->fetchAll();

?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-archive me-2"></i>Stock</h4>
    <a href="index.php?page=stock_mouvement" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Ajouter mouvement</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr><th>Produit</th><th class="text-center">Stock</th><th>Seuil</th><th>Unité</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($produits as $p): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($p['nom']) ?></td>
                        <td class="text-center"><?= (int)$p['stock'] ?></td>
                        <td class="text-center"><?= (int)$p['stock_min'] ?></td>
                        <td><small><?= htmlspecialchars($p['unite']) ?></small></td>
                        <td>
                            <a href="index.php?page=stock_mouvement&produit_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">Mouvements</a>
                            <a href="index.php?page=produit_edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">Éditer</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
