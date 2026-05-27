# Database Schema

SQLite database at `db.sqlite`. Foreign keys enforced via `PRAGMA foreign_keys = ON` in `bootstrap.php`.

All timestamps stored as TEXT in ISO 8601 format. Default value: `datetime('now')`.

## staff

| Column | Type | Constraints |
|--------|------|-------------|
| id | INTEGER | PRIMARY KEY AUTOINCREMENT |
| email | TEXT | NOT NULL UNIQUE |
| name | TEXT | NOT NULL |

Only one staff member exists (id=1). `current_staff()` hardcodes this.

## documents

| Column | Type | Constraints |
|--------|------|-------------|
| id | INTEGER | PRIMARY KEY AUTOINCREMENT |
| title | TEXT | NOT NULL |
| body | TEXT | NOT NULL |
| created_by | INTEGER | NOT NULL, FK -> staff(id) |
| publish_at | TEXT | DEFAULT NULL (added by migration 001) |
| created_at | TEXT | NOT NULL DEFAULT datetime('now') |

`publish_at` = NULL means "published immediately." A future datetime means the document is not yet visible to recipients.

## shares

| Column | Type | Constraints |
|--------|------|-------------|
| id | INTEGER | PRIMARY KEY AUTOINCREMENT |
| document_id | INTEGER | NOT NULL, FK -> documents(id) |
| token | TEXT | NOT NULL UNIQUE |
| recipient_email | TEXT | NOT NULL |
| created_at | TEXT | NOT NULL DEFAULT datetime('now') |

Token is 32-char hex (128-bit random). One document can have many shares. Recipient email is informational, not enforced for access.

## audit_log

| Column | Type | Constraints |
|--------|------|-------------|
| id | INTEGER | PRIMARY KEY AUTOINCREMENT |
| staff_id | INTEGER | nullable, FK -> staff(id) |
| action | TEXT | NOT NULL |
| entity_type | TEXT | nullable |
| entity_id | INTEGER | nullable |
| details | TEXT | nullable (JSON-encoded) |
| created_at | TEXT | NOT NULL DEFAULT datetime('now') |

Append-only log. `staff_id` is nullable to allow system-level entries. `details` is a JSON string with context (titles, old/new values, emails).

## schema_migrations

| Column | Type | Constraints |
|--------|------|-------------|
| version | TEXT | PRIMARY KEY |

Tracks which migration files have been applied. Created by `lib/migrate.php`.

## Relationships

```
staff(id) <--1:N-- documents(created_by)
documents(id) <--1:N-- shares(document_id)
staff(id) <--1:N-- audit_log(staff_id)
```
