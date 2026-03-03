<!--
This document is used by the GitHub Copilot/AI coding agents when they are
asked to make changes in the repository.  It should contain just the facts
that help an agent be immediately productive; avoid generic advice that
applies to every PHP project.  Keep it short (20‑50 lines) and concrete.
-->

# Repository Overview

`plugin` is a **small procedural PHP application** for managing a print
service.  There are two entrypoints:

* `/index.php` – public website (catalogue, panier, commandes, pages dynamiques)
* `/admin/index.php` – back‑office with its own session-based auth

Both scripts bootstrap `config/app.php`, `config/database.php` and
`includes/functions.php` and then dispatch on `$_GET['page']`/`action`.

There is **no framework or build step**.  PHP 8+ on a LAMP/LEMP stack is
sufficient; the installer (`install.php`) bootstraps the database using
`database/schema.sql` and writes the two config files.  Delete
`config/.installed` to rerun the installer.

# Key Patterns & Conventions

* **Routing** – pages are simple files in `pages/` (public) or
  `admin/pages/` (back‑office).  `index.php` maps a slug to a filename by
  replacing `-` with `_` for public pages, or using the slug as-is for
  admin.  Dynamic pages come from the `pages` table and are rendered in a
  tiny inline template.
* **Sanitisation** – always call `clean($value)` for any user input and
  `htmlspecialchars()` when echoing.  Most admin forms `verifyCsrf()` at the
  top and output `csrfField()` in the `<form>`.
* **Session state** – `estConnecte()`/`adminConnecte()` check
  `$_SESSION['admin_id']`; the shopping cart lives in `$_SESSION['panier']`.
  Use helper functions like `ajouterAuPanier()` and `totalPanier()`.
* **Database access** – `getDB()` returns a PDO singleton configured in
  `config/database.php`.  Prepared statements are the norm; look at
  existing pages for examples (see `admin/pages/commande_detail.php` for a
  simple pattern).  `schema.sql` is the canonical source of truth for table
  structures.
* **Constants** – site-wide configuration keys (currency, upload directory,
  delivery fees, etc.) are defined in `config/app.php`.  When writing new
  constants, follow the existing naming (uppercase with underscores).
* **Uploads** – call `uploadFichier()` from `includes/functions.php` and
  observe the `ALLOWED_EXTENSIONS`/`MAX_FILE_SIZE` constants.

# Developer Workflows

* **Initial setup** – point a browser at `/install.php`, follow the steps.
  It creates the database (if necessary), imports `database/schema.sql`,
  writes `config/*.php`, and creates an admin account.  After installing
  remove the installer as instructed in the header comment.
* **Editing content** – change public pages under `pages/` or add new
  dynamic pages via the admin UI (`Pages` section).  Use slugs without
  spaces; the router converts `-` to `_` when loading a file.
* **Admin UI** – every admin page starts with
  `verifyCsrf();` and ends with rendering a Bootstrap table/card.  Look at
  `admin/pages/clients.php` for a canonical CRUD list page and
  `admin/pages/produit_edit.php` for an edit form.
* **Debugging** – enable errors by setting `display_errors=1` or modifying
  `install.php` (it turns them off after the first block).  PDO throws
  exceptions (`ERRMODE_EXCEPTION`), making SQL errors easy to see.

# Project-specific Notes

* The installer is designed to work on shared hosting (cPanel/Plesk) and
  will try to create the database when it doesn't exist.  It also writes
  both config files so developers rarely edit them by hand.
* CSS/JS assets live under `assets/`; the admin uses a separate
  `admin.css`.  Bootstrap is pulled from CDN in headers.
* Notifications (`notifications.php`) are stored in a simple table and can
  be created with `creerNotification()`; the admin header shows unread
  counts using `getNotificationsNonLues()`.
* New modules should follow the existing naming: slug = file name in
  either public or admin folder and add the slug to the `$pages_publiques`
  or `$pages_admin` array in the router.

The above is all the information an AI agent usually needs to navigate and
make changes in this repository.

> **Note to reviewer:** please point out any domain-specific behaviour or
> workflow I’ve missed so I can expand these instructions.
