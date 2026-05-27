---
name: create-page
description: Guides creating new PHP pages in Folio following established patterns. Covers file location, required includes, form handling, error display, and layout usage. Use when adding a new page or endpoint to the application.
---

## File Location

All pages go in `public/`. The PHP built-in server serves from this directory.

## Required Structure

```php
<?php
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();  // Staff pages only

// POST handling (state changes)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate, execute, audit_log(), redirect
    header('Location: /page.php?success=1');
    exit;
}

// GET: fetch data for display
render_header('Title', $staff);
?>
<!-- HTML with h() escaping -->
<?php render_footer(); ?>
```

## Checklist

- `require bootstrap.php` and `layout.php`
- `current_staff()` for staff pages; omit for public pages
- `h()` for all dynamic output
- Parameterized queries only
- `audit_log()` for state changes
- POST/redirect/GET for forms
- Handle 404 cases (invalid IDs, missing records)
- Use existing CSS classes: `.card`, `.banner`, `.btn`, `.form-field`, `.data`

## Reference

See `public/admin.php` and `public/share.php` for working examples.
