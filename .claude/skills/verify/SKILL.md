---
name: verify
description: Full end-to-end verification workflow for Folio. Reseeds the database, checks schema, runs tests, and verifies audit log entries. Use after making changes to confirm everything works.
disable-model-invocation: true
allowed-tools: Bash(docker compose exec *)
---

Run the full verification workflow:

1. Reseed the database (applies base schema + all migrations):
```bash
docker compose exec app php seed.php
```

2. Verify database schema:
```bash
docker compose exec app sqlite3 db.sqlite ".schema"
```

3. Run the test suite:
```bash
docker compose exec app php tests/test.php
```

4. Verify audit log entries:
```bash
docker compose exec app sqlite3 db.sqlite "SELECT action, entity_type, entity_id, details FROM audit_log ORDER BY id;"
```

5. Check all migrations applied:
```bash
docker compose exec app sqlite3 db.sqlite "SELECT * FROM schema_migrations;"
```

Report which steps passed, which failed, and any issues found.
