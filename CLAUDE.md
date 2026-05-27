# Rules

- Escape all dynamic output with `h()`. No exceptions.
- Use parameterized queries (`?` placeholders) for every SQL statement. Never interpolate variables into SQL.
- Log every state-changing operation with `audit_log($action, $entity_type, $entity_id, $details)`. Include old and new values for updates.
- Follow POST/redirect/GET for all forms. Validate on POST, redirect on success, re-render with `.banner-error` on failure.
- Never edit `schema.sql`. Schema changes go in numbered files under `migrations/` (e.g., `001_add_publish_at.sql`).
- Run tests with `docker compose exec app php tests/test.php`.
- All dates are stored and compared in UTC. PHP timezone is set to UTC in `bootstrap.php`. SQLite `datetime('now')` defaults are also UTC.
- Display dates to users via `<time datetime="...">` elements. Use `iso8601()` to convert DB dates to valid ISO 8601 for the `datetime` attribute. The JS in `render_footer()` converts these to the client's local timezone.
- Use PHP `DateTime` objects for all date comparisons, never raw string comparison.

# Do not

- Add comments unless the "why" is non-obvious.
- Create new CSS classes when existing ones work: `.card`, `.banner`, `.btn`, `.btn-link`, `.data`, `.form-field`, `.centered-message`, `.back-link`.
- Skip audit logging for any create, update, or delete operation.
- Use `echo` or `print` for HTML output in pages. Use inline PHP templates with `<?= h(...) ?>`.

# Key facts

- PHP 8.3, SQLite, no framework, no ORM. Plain PHP with inline templates.
- Single staff user. `current_staff()` returns staff #1. No auth system.
- Documents are shared via 128-bit random tokens (32-char hex). Token = access control. Recipients need no login.
- `db()` returns a singleton PDO. Foreign keys are enabled via PRAGMA.
- `seed.php` destroys and recreates `db.sqlite` on every `docker compose up`, then runs migrations via `run_migrations()`.
- Staff pages require `bootstrap.php`, `layout.php`, call `current_staff()`, and pass `$staff` to `render_header()`. Public pages omit `$staff`.

# Project structure

```
public/            Web root (PHP built-in server document root)
  router.php       Front controller: routes /doc/{slug-or-token} to view.php
  admin.php        Staff dashboard: create docs, list docs, search
  share.php        Generate share link for a document
  view.php         Recipient-facing document view (slug or token)
  edit.php         Edit document publish schedule
  index.php        Redirects to admin.php
  assets/style.css Full CSS design system
lib/
  bootstrap.php    db(), current_staff(), audit_log(), random_token(), generate_slug(), parse_publish_at(), find_document(), iso8601(), h()
  layout.php       render_header(), render_footer()
  migrate.php      Migration runner (run_migrations())
migrations/        Numbered SQL migration files
schema.sql         Base schema (read-only, use migrations for changes)
seed.php           DB reset + seed data + migration runner
tests/test.php     Test suite: test() and assert_true() helpers
```

# Finding answers

When you need context beyond this file, start at [docs/index.md](docs/index.md) and follow the relevant links. Do not load every doc upfront.
