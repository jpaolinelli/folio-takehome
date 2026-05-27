---
name: database-migration
description: Guides schema changes through the migration system. Covers creating numbered SQL files, SQLite ALTER TABLE constraints, and verification steps. Use when adding columns, creating indexes, or modifying the database schema.
---

## Creating a Migration

1. Create a numbered SQL file in `migrations/`:
   ```
   migrations/001_add_publish_at.sql
   migrations/002_add_slug.sql
   ```

2. Write the SQL using SQLite-compatible DDL:
   ```sql
   ALTER TABLE documents ADD COLUMN publish_at TEXT DEFAULT NULL;
   ```

3. Run: `docker compose exec app php lib/migrate.php`
4. Verify: `docker compose exec app sqlite3 db.sqlite ".schema documents"`

## SQLite Constraints

- Only `ADD COLUMN` is supported (no DROP, MODIFY, or RENAME)
- New columns must have a DEFAULT or allow NULL
- For UNIQUE constraints, use a separate `CREATE UNIQUE INDEX` statement
- For NOT NULL columns: add as NULL first, backfill, then add a CHECK constraint

## Rules

- Migrations are append-only. Never edit a committed migration file.
- Never edit `schema.sql` directly.
- Test that `docker compose up` works from scratch after adding a migration.
