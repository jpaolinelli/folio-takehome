---
name: audit-check
description: Queries the audit_log table to verify that state-changing actions are being logged correctly. Use when verifying audit trail completeness.
disable-model-invocation: true
allowed-tools: Bash(docker compose exec *)
---

Query recent audit log entries:

```bash
docker compose exec app sqlite3 db.sqlite "SELECT id, action, entity_type, entity_id, details, created_at FROM audit_log ORDER BY id DESC LIMIT 20;"
```

Verify:
- Every document creation has action='create', entity_type='document'
- Every share creation has action='create', entity_type='share'
- Every schedule update has action='update_schedule', entity_type='document'
- The `details` JSON contains relevant context (title, publish_at, recipient_email)
- `staff_id` is populated (should be 1 for the hardcoded staff user)

Report any missing or malformed audit entries.
