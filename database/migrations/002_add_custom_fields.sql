-- Migration: 002_add_custom_fields.sql
-- Ajoute la colonne `custom_fields` JSON à la table `produits`.

SET @sql := (
  SELECT IF(COUNT(*)=0,
    'ALTER TABLE produits ADD COLUMN custom_fields JSON DEFAULT NULL;',
    'SELECT "column custom_fields exists";')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produits' AND COLUMN_NAME = 'custom_fields'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
