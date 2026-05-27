---
name: add-test
description: Guides writing tests for Folio using the existing test framework. Covers the test() and assert_true() pattern, test naming, and database setup. Use when adding tests for new features.
---

## Framework

```php
test('description of behavior', function () {
    // Arrange, Act, Assert
    assert_true($condition, 'message if false');
});
```

Add tests to `tests/test.php` after the existing tests. The seeded database (seed.php) runs before all tests.

## Example

```php
test('document with future publish_at is not viewable', function () {
    $future = date('Y-m-d H:i:s', strtotime('+1 day'));
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, publish_at) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Future Doc', 'body', $future]);
    $id = (int) db()->lastInsertId();

    $stmt = db()->prepare('SELECT * FROM documents WHERE id = ?');
    $stmt->execute([$id]);
    $doc = $stmt->fetch();

    $publishAt = new DateTime($doc['publish_at']);
    $now = new DateTime();
    assert_true($publishAt > $now, 'publish_at should be in the future');
});
```

## Guidelines

- Test names describe behavior, not implementation
- One `assert_true` per concern (multiple per test is fine)
- No cleanup needed; seed.php resets DB before each run
- Test edge cases: NULL values, empty strings, boundary times
- Run: `docker compose exec app php tests/test.php`
