---
name: audit-logging
description: Guides adding audit trail entries for state-changing operations. Covers the audit_log function signature, action naming, and placement patterns. Use when implementing a feature that creates, updates, or deletes records.
---

## The Function

```php
audit_log(string $action, string $entity_type, int $entity_id, array $details = []): void
```

Defined in `lib/bootstrap.php`. Automatically captures current staff and timestamp.

## Parameters

| Parameter | Examples |
|-----------|----------|
| `$action` | `'create'`, `'update_schedule'`, `'delete'` |
| `$entity_type` | `'document'`, `'share'` |
| `$entity_id` | The ID of the affected row |
| `$details` | `['title' => 'Welcome']` (JSON-encoded) |

## Placement

- **Creates:** After INSERT succeeds, before redirect
- **Updates:** Include both old and new values in details
- **Deletes:** Log before the DELETE executes

## Example

```php
// After creating a document
audit_log('create', 'document', $docId, ['title' => $title]);

// After updating a schedule
audit_log('update_schedule', 'document', $docId, [
    'publish_at' => $newValue,
    'previous_publish_at' => $oldValue,
]);
```
