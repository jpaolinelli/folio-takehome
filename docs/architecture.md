# Architecture

## Request flows

**Staff creates a document:**
`POST /admin.php` -> validate title + body -> `generate_slug()` -> INSERT into `documents` (with slug, optional publish_at) -> `audit_log('create', 'document', ...)` -> redirect to `/admin.php?created={id}`

**Staff searches for a document:**
`GET /admin.php?q={term}` -> `WHERE title LIKE '%term%'` (case-insensitive) -> filtered document list

**Staff generates a share link:**
`GET /share.php?doc={slug-or-id}` -> fetch document by slug or numeric ID -> show form
`POST /share.php?doc={slug-or-id}` -> validate email -> `random_token()` -> INSERT into `shares` -> `audit_log('create', 'share', ...)` -> display share URL

**Recipient views a shared document:**
`GET /doc/{slug}?a={token}` -> router.php sets `$_GET['doc']` -> view.php -> JOIN shares + documents on slug AND token -> check publish_at -> show document with recipient info, or 404 if slug/token mismatch

**Staff edits publish schedule:**
`GET /edit.php?doc={slug-or-id}` -> fetch document -> show current publish_at in form
`POST /edit.php?doc={slug-or-id}` -> validate -> UPDATE documents SET publish_at -> `audit_log('update_schedule', 'document', ...)` -> redirect to `/admin.php?updated={id}`

## How pages connect

```
index.php -> redirect -> admin.php (search via ?q=)
                           |
                           +-- "Create share" -> share.php?doc={slug}
                           +-- "Edit schedule" -> edit.php?doc={slug}
                           
share.php -> generates -> /doc/{slug}?a={token}
                            |
                            +-- router.php -> view.php
                            +-- slug identifies doc, token authorizes access
                            +-- publish_at check -> document or "not yet available"
```

## Share-token model

Tokens serve an **access control** function: knowing the token proves the recipient was explicitly given access. Tokens are 128-bit random (32 hex chars via `random_token()`), making them unguessable.

One document can have many share tokens (one per recipient). Each token is unique. No login or authentication is required to view a shared document -- the token is the credential.

This is intentionally separate from any human-readable document ID. Readable IDs serve an **identity** function (referring to a document by name). Merging the two would let anyone who guesses an ID read the document.

## Startup sequence

`docker compose up` runs: `php seed.php && php -S 0.0.0.0:8000 -t public/ public/router.php`

## URL routing

`public/router.php` is the front controller for PHP's built-in server. It intercepts `/doc/{identifier}` requests, sets `$_GET['doc']`, and requires `view.php`. All other URLs fall through to normal file serving (`return false`).

`view.php` requires both a slug (in the path) and an auth token (`?a=`). The slug identifies the document, the token proves authorization. Both must match or the request returns 404.

`seed.php` does:
1. Delete `db.sqlite` if it exists
2. Create fresh DB from `schema.sql`
3. Call `run_migrations()` to apply all `migrations/*.sql` files
4. Insert seed data (1 staff, 1 document, 1 share)
