-- Migration: 001_add_stock.sql
-- Ajoute les colonnes `stock`, `stock_min` à la table `produits`
-- et crée la table `stock_mouvements` si nécessaire.
-- Exécuter dans la base MySQL utilisée par l'application.

-- Ajoute la colonne `stock` si elle n'existe pas
SET @sql := (
  SELECT IF(COUNT(*)=0,
    'ALTER TABLE produits ADD COLUMN stock INT DEFAULT 0;',
    'SELECT "column stock exists";')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produits' AND COLUMN_NAME = 'stock'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ajoute la colonne `stock_min` si elle n'existe pas
SET @sql := (
  SELECT IF(COUNT(*)=0,
    'ALTER TABLE produits ADD COLUMN stock_min INT DEFAULT 0;',
    'SELECT "column stock_min exists";')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produits' AND COLUMN_NAME = 'stock_min'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Crée la table `stock_mouvements` si elle n'existe pas
CREATE TABLE IF NOT EXISTS stock_mouvements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produit_id INT NOT NULL,
  type ENUM('entree','sortie','ajustement') NOT NULL,
  quantite INT NOT NULL,
  commentaire TEXT DEFAULT NULL,
  admin_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Fin de migration
