---
name: migrate
description: Runs pending database migrations against the current db.sqlite. Use when migrations need to be applied after adding a new migration file.
disable-model-invocation: true
allowed-tools: Bash(docker compose exec *)
---

Run pending migrations:

```bash
docker compose exec app php lib/migrate.php
```

Verify the migration was applied:

```bash
docker compose exec app sqlite3 db.sqlite "SELECT * FROM schema_migrations;"
docker compose exec app sqlite3 db.sqlite ".schema documents"
```

If a migration fails, do NOT retry without investigating. Read the failing migration file in `migrations/` and the current schema to diagnose the issue.
