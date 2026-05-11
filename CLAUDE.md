# Folio — Project Context

## Overview
A small document-sharing app built with plain PHP 8.3, SQLite, and Docker. No framework, no Composer, no external dependencies. Staff create documents and share them with recipients via one-time secret links.

## How to Run
- Start: `docker compose up` (first run builds the image, ~30s)
- App: http://localhost:8000
- Stop: `Ctrl+C`
- Database is re-seeded from scratch on every `docker compose up`

## How to Test
```bash
docker compose exec app php tests/test.php
```

## File Map
```
CLAUDE.md              → This file — project context and conventions
schema.sql             → Base database schema (4 tables). DO NOT EDIT — use migrations instead.
seed.php               → Wipes and re-creates db.sqlite with test data. Runs on every docker compose up.
migrate.php            → Applies pending migrations to an existing database without wiping.
lib/bootstrap.php      → Core helpers: db(), current_staff(), audit_log(), random_token(), h(), generate_slug(), run_migrations()
lib/layout.php         → HTML header/footer templates (render_header, render_footer)
public/admin.php       → Staff admin page: create documents, view list with status badges
public/schedule.php    → Staff page: edit publish schedule for a document
public/share.php       → Staff page: create share link (with search-first mode)
public/view.php        → Recipient page: view document via token or slug (with email gate)
public/index.php       → Redirects to admin.php
public/assets/style.css → All CSS styles
migrations/            → Numbered SQL migration files (with optional PHP companions)
tests/test.php         → Test runner — seeds DB then runs test functions
docker-compose.yml     → Docker Compose config
Dockerfile             → PHP 8.3-cli with pdo_sqlite
```

## Conventions
- **HTML escaping**: Always use `h($var)` for any user-supplied data in HTML output
- **SQL**: Always use prepared statements (`$stmt = db()->prepare(...); $stmt->execute([...])`)
- **Audit logging**: Call `audit_log($action, $entity_type, $entity_id, $details)` for all create/schedule/share actions
- **Timezone**: `America/Chicago` (set in bootstrap.php)
- **Tokens**: Use `random_token()` for generating secret share tokens
- **Slugs**: Use `generate_slug()` for generating readable document IDs (FOLIO-XXXX format)

## Database
- SQLite via PDO, path: `db.sqlite` (gitignored)
- Access: `db()` returns a PDO singleton with ERRMODE_EXCEPTION and FETCH_ASSOC
- Foreign keys are enabled via PRAGMA
- Tables: `staff`, `documents`, `shares`, `audit_log`, `schema_migrations`

## Migration System
- Schema changes go in `migrations/NNN_description.sql` — NEVER edit `schema.sql`
- Optional PHP companion: `migrations/NNN_description.php` returns `callable(PDO): void`
- `seed.php` runs all migrations after creating the base schema (fresh builds)
- `migrate.php` runs only pending migrations (existing database updates)
- Migrations are tracked in `schema_migrations` table (name + applied_at)

## Architecture Notes
- No routing framework — PHP built-in server serves files from `public/`
- No authentication — `current_staff()` is hardcoded to staff id #1
- No CSRF protection — acceptable for this scope but worth flagging
- CSS uses custom properties for theming, already supports `input[type=datetime-local]`
