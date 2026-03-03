# plugin

## Migrations

Les migrations SQL se trouvent dans `database/migrations/`.

Pour appliquer les migrations, vous pouvez exécuter :

```bash
# si vous n'avez pas composer installé :
php database/apply_migrations.php

# ou via composer (si installé) :
composer migrate
```

Le script `database/apply_migrations.php` enregistre les migrations appliquées dans la table `migrations` pour éviter les doublons.
