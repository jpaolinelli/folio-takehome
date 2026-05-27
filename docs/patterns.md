# Code Patterns

Patterns extracted from existing files. Follow these when adding new code.

## Form handling (POST/redirect/GET)

From `admin.php`:

```php
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if ($title === '' || $body === '') {
        $error = 'Title and body are required.';
    } else {
        // INSERT, get lastInsertId, audit_log, redirect
        header('Location: /admin.php?created=' . $docId);
        exit;
    }
}
```

Success: redirect with query param, display `.banner-success` on GET.
Failure: set `$error`, re-render form with `.banner-error`.

## Success and error banners

```php
<?php if (!empty($_GET['created'])): ?>
    <div class="banner banner-success">Document #<?= (int) $_GET['created'] ?> created.</div>
<?php endif ?>

<?php if ($error): ?>
    <div class="banner banner-error"><?= h($error) ?></div>
<?php endif ?>
```

## 404 handling

From `view.php`:

```php
if (!$doc) {
    http_response_code(404);
    render_header('Not found');
    ?>
    <div class="centered-message">
        <h1>Share link not found</h1>
        <p>The link you used is invalid or has been removed.</p>
    </div>
    <?php
    render_footer();
    exit;
}
```

## Audit logging placement

Always after the DB write succeeds, before the redirect:

```php
$stmt->execute([...]);
$docId = (int) db()->lastInsertId();
audit_log('create', 'document', $docId, ['title' => $title]);
header('Location: /admin.php?created=' . $docId);
exit;
```

## Page layout

Staff page:
```php
$staff = current_staff();
render_header('Page Title', $staff);
// ... HTML ...
render_footer();
```

Public page (no staff):
```php
render_header('Page Title');
// ... HTML ...
render_footer();
```

## Card sections

```html
<section class="card">
    <h2 class="card-title">Section Title</h2>
    <!-- content -->
</section>
```

## Data tables

```html
<table class="data">
    <thead><tr><th>Column</th></tr></thead>
    <tbody>
        <?php foreach ($items as $item): ?>
            <tr><td><?= h($item['value']) ?></td></tr>
        <?php endforeach ?>
    </tbody>
</table>
```

## Back links

```html
<a href="/admin.php" class="back-link">&#8592; back to admin</a>
```

## Test pattern

From `tests/test.php`:

```php
test('description of expected behavior', function () {
    // arrange
    $stmt = db()->prepare('INSERT INTO ...');
    $stmt->execute([...]);

    // act
    $stmt = db()->prepare('SELECT ...');
    $stmt->execute([...]);
    $row = $stmt->fetch();

    // assert
    assert_true($row !== false, 'expected row to exist');
    assert_true($row['title'] === 'Expected', 'title mismatch');
});
```
