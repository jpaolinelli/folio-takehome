---
name: seed
description: Reseeds the Folio database to a fresh state by destroying and recreating db.sqlite from schema.sql, then applying all migrations. Use when the database needs resetting.
disable-model-invocation: true
allowed-tools: Bash(docker compose exec *)
---

Reseed the database:

```bash
docker compose exec app php seed.php
```

Confirm the database is healthy:

```bash
docker compose exec app sqlite3 db.sqlite ".tables"
docker compose exec app sqlite3 db.sqlite "SELECT count(*) FROM documents;"
docker compose exec app sqlite3 db.sqlite "SELECT count(*) FROM staff;"
```
