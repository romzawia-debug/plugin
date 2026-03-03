<?php
/**
 * BERRADI PRINT - Ajout/Modification Produit avec Image Upload
 */
$db = getDB();
$produit_id = (int)($_GET['id'] ?? 0);
$produit = null;
$custom_fields = [];

if ($produit_id) {
    $stmt = $db->prepare("SELECT * FROM produits WHERE id = ?");
    $stmt->execute([$produit_id]);
    $produit = $stmt->fetch();
    if ($produit && !empty($produit['custom_fields'])) {
        $custom_fields = json_decode($produit['custom_fields'], true) ?: [];
    }
}

$categories = $db->query("SELECT * FROM categories WHERE actif = 1 ORDER BY ordre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sauvegarder'])) {
    verifyCsrf();

    $data = [
        'categorie_id' => (int)$_POST['categorie_id'],
        'nom' => clean($_POST['nom']),
        'slug' => preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower(clean($_POST['nom'])))),
        'description' => $_POST['description'] ?? '',
        'description_courte' => clean($_POST['description_courte']),
        'prix_base' => floatval($_POST['prix_base']),
        'prix_unitaire' => floatval($_POST['prix_unitaire']) ?: null,
        'unite' => clean($_POST['unite']),
        'quantite_min' => (int)$_POST['quantite_min'],
        'delai_production' => clean($_POST['delai_production']),
        'populaire' => isset($_POST['populaire']) ? 1 : 0,
        'actif' => isset($_POST['actif']) ? 1 : 0,
        'ordre' => (int)$_POST['ordre'],
            'stock' => (int)($_POST['stock'] ?? 0),
            'stock_min' => (int)($_POST['stock_min'] ?? 0),
    ];

    // Image upload
    $image_name = $produit['image'] ?? '';
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
            $upload_dir = __DIR__ . '/../../uploads/produits/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            // Delete old image
            if ($image_name && file_exists($upload_dir . $image_name)) {
                unlink($upload_dir . $image_name);
            }
            $image_name = uniqid('prod_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
        }
    }
    // Delete image if requested
    if (isset($_POST['supprimer_image']) && $image_name) {
        $upload_dir = __DIR__ . '/../../uploads/produits/';
        if (file_exists($upload_dir . $image_name)) {
            unlink($upload_dir . $image_name);
        }
        $image_name = '';
    }
    $data['image'] = $image_name;

    // handle custom fields
    $customs = [];
    $labels = $_POST['custom_label'] ?? [];
    $types = $_POST['custom_type'] ?? [];
    $values = $_POST['custom_value'] ?? [];
    $existing_images = $_POST['custom_image_existing'] ?? [];
    $delete_flags = $_POST['delete_custom_image'] ?? [];
    foreach ($labels as $idx => $lbl) {
        $lbl = clean($lbl);
        $type = $types[$idx] ?? 'text';
        $val = '';
        if ($type === 'image') {
            // manage upload
            if (isset($_FILES['custom_image']['name'][$idx]) && $_FILES['custom_image']['error'][$idx] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['custom_image']['name'][$idx], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) {
                    $upload_dir = __DIR__ . '/../../uploads/custom_fields/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $filename = uniqid('cf_') . '.' . $ext;
                    move_uploaded_file($_FILES['custom_image']['tmp_name'][$idx], $upload_dir . $filename);
                    $val = $filename;
                    // delete previous if requested
                    if (!empty($existing_images[$idx]) && !empty($delete_flags[$idx])) {
                        $old = $existing_images[$idx];
                        if (file_exists($upload_dir . $old)) unlink($upload_dir . $old);
                    }
                }
            } else {
                if (!empty($existing_images[$idx]) && empty($delete_flags[$idx])) {
                    $val = $existing_images[$idx];
                }
            }
        } else {
            $val = clean($values[$idx] ?? '');
        }
        if ($lbl !== '') {
            $customs[] = ['label' => $lbl, 'type' => $type, 'value' => $val];
        }
    }
    $data['custom_fields'] = json_encode($customs);

    if ($produit_id && $produit) {
        $sql = "UPDATE produits SET categorie_id=?, nom=?, slug=?, description=?, description_courte=?, prix_base=?, prix_unitaire=?, unite=?, quantite_min=?, delai_production=?, populaire=?, actif=?, ordre=?, stock=?, stock_min=?, custom_fields=?, image=? WHERE id=?";
        $db->prepare($sql)->execute([...array_values($data), $produit_id]);
        setFlash('success', 'Produit mis à jour avec succès.');
    } else {
        $sql = "INSERT INTO produits (categorie_id, nom, slug, description, description_courte, prix_base, prix_unitaire, unite, quantite_min, delai_production, populaire, actif, ordre, stock, stock_min, custom_fields, image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $db->prepare($sql)->execute(array_values($data));
        $produit_id = $db->lastInsertId();
        setFlash('success', 'Produit créé avec succès.');
    }
    redirect('index.php?page=produit_edit&id=' . $produit_id);
}

// Current image
$current_image = '';
if ($produit && !empty($produit['image'])) {
    $img_path = __DIR__ . '/../../uploads/produits/' . $produit['image'];
    if (file_exists($img_path)) {
        $current_image = '../uploads/produits/' . $produit['image'];
    }
}
?>

<div class="mb-4">
    <a href="index.php?page=produits" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Retour aux produits</a>
    <h4 class="fw-bold"><?= $produit ? 'Modifier' : 'Nouveau' ?> Produit</h4>
</div>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Informations -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-info-circle me-2"></i>Informations</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nom du produit *</label>
                            <input type="text" name="nom" class="form-control" required value="<?= htmlspecialchars($produit['nom'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Catégorie *</label>
                            <select name="categorie_id" class="form-select" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($produit && $produit['categorie_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Description courte</label>
                            <input type="text" name="description_courte" class="form-control" maxlength="500" value="<?= htmlspecialchars($produit['description_courte'] ?? '') ?>">
                            <small class="text-muted">Résumé affiché dans les listes de produits</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description complète</label>
                            <textarea name="description" id="description" class="form-control" rows="8"><?= htmlspecialchars($produit['description'] ?? '') ?></textarea>
                            <small class="text-muted">Éditeur riche : formatez votre description avec les outils ci-dessus.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Image du produit -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-image me-2"></i>Image du produit</div>
                <div class="card-body">
                    <?php if ($current_image): ?>
                    <div class="mb-3">
                        <div class="position-relative d-inline-block">
                            <img src="<?= $current_image ?>" alt="Image actuelle" class="rounded border" style="max-width:300px;max-height:200px;object-fit:cover;">
                            <div class="mt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="supprimer_image" id="supprimer_image" value="1">
                                    <label class="form-check-label text-danger" for="supprimer_image">
                                        <i class="bi bi-trash me-1"></i>Supprimer cette image
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="mb-0">
                        <label class="form-label"><?= $current_image ? 'Changer l\'image' : 'Ajouter une image' ?></label>
                        <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(this)">
                        <small class="text-muted">Formats acceptés: JPG, PNG, GIF, WebP, SVG. Max 5 MB.</small>
                    </div>
                    <div id="imagePreview" class="mt-3" style="display:none;">
                        <img id="previewImg" class="rounded border" style="max-width:300px;max-height:200px;object-fit:cover;">
                    </div>
                </div>
            </div>

            <!-- Tarification -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold"><i class="bi bi-currency-dollar me-2"></i>Tarification</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Prix de base (DH) *</label>
                            <input type="number" name="prix_base" class="form-control" step="0.01" required value="<?= $produit['prix_base'] ?? '0' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Prix unitaire (DH)</label>
                            <input type="number" name="prix_unitaire" class="form-control" step="0.01" value="<?= $produit['prix_unitaire'] ?? '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Unité</label>
                            <select name="unite" class="form-select">
                                <?php foreach (['pièce', 'lot de 100', 'lot de 500', 'lot de 1000', 'm²', 'mètre linéaire', 'page', 'carnet'] as $u): ?>
                                <option value="<?= $u ?>" <?= ($produit && $produit['unite'] == $u) ? 'selected' : '' ?>><?= $u ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Quantité minimum</label>
                            <input type="number" name="quantite_min" class="form-control" min="1" value="<?= $produit['quantite_min'] ?? 1 ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Délai de production</label>
                            <input type="text" name="delai_production" class="form-control" value="<?= htmlspecialchars($produit['delai_production'] ?? '24-48h') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ordre d'affichage</label>
                            <input type="number" name="ordre" class="form-control" value="<?= $produit['ordre'] ?? 0 ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Stock actuel</label>
                            <input type="number" name="stock" class="form-control" value="<?= isset($produit['stock']) ? (int)$produit['stock'] : 0 ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Seuil alerte</label>
                            <input type="number" name="stock_min" class="form-control" value="<?= isset($produit['stock_min']) ? (int)$produit['stock_min'] : 0 ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Champs personnalisés -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-pencil-square me-2"></i>Champs personnalisés</div>
                <div class="card-body">
                    <div id="customFieldsContainer">
                        <?php foreach ($custom_fields as $i => $field): ?>
                        <div class="custom-field-row mb-3 border rounded p-3 position-relative">
                            <button type="button" class="btn-close position-absolute top-0 end-0 remove-field" aria-label="Supprimer"></button>
                            <div class="mb-2">
                                <label class="form-label">Libellé</label>
                                <input type="text" name="custom_label[]" class="form-control" value="<?= htmlspecialchars($field['label']) ?>">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Type</label>
                                <select name="custom_type[]" class="form-select custom-type-select">
                                    <?php foreach (['text'=>'Texte','textarea'=>'Zone de texte','image'=>'Image','number'=>'Nombre'] as $k=>$v): ?>
                                    <option value="<?= $k ?>" <?= $field['type']==$k ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-2 custom-value-group">
                                <?php if ($field['type']==='image'): ?>
                                    <?php if (!empty($field['value'])): ?>
                                        <div class="mb-2">
                                            <img src="../uploads/custom_fields/<?= htmlspecialchars($field['value']) ?>" style="max-height:100px;" class="rounded border">
                                            <div class="form-check">
                                               <input class="form-check-input" type="checkbox" name="delete_custom_image[<?= $i ?>]" value="1">
                                               <label class="form-check-label">Supprimer l'image existante</label>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" name="custom_image[]" class="form-control" accept="image/*">
                                    <input type="hidden" name="custom_image_existing[]" value="<?= htmlspecialchars($field['value']) ?>">
                                <?php else: ?>
                                    <textarea name="custom_value[]" class="form-control"><?= htmlspecialchars($field['value']) ?></textarea>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addCustomField"><i class="bi bi-plus"></i> Ajouter un champ</button>
                </div>
            </div>

        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-bold">Options</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="actif" id="actif" <?= (!$produit || $produit['actif']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="actif">Actif (visible sur le site)</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="populaire" id="populaire" <?= ($produit && $produit['populaire']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="populaire">Produit populaire</label>
                    </div>
                </div>
            </div>
            <button type="submit" name="sauvegarder" class="btn btn-primary w-100 btn-lg mb-3">
                <i class="bi bi-check-circle me-2"></i>Sauvegarder
            </button>
            <?php if ($produit): ?>
            <a href="../index.php?page=produit&id=<?= $produit_id ?>" target="_blank" class="btn btn-outline-primary w-100">
                <i class="bi bi-eye me-2"></i>Voir sur le site
            </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: '#description',
    language: 'fr_FR',
    height: 400,
    menubar: false,
    plugins: 'lists link image table code wordcount',
    toolbar: 'undo redo | blocks | bold italic underline | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist | link image table | code',
    content_style: 'body { font-family: Poppins, sans-serif; font-size: 14px; }',
    branding: false,
    promotion: false
});
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('imagePreview').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// custom fields behaviour already added above in-line
</script>
