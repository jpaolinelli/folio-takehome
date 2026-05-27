<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();
$doc = find_document(trim($_GET['doc'] ?? ''));

if (!$doc) {
    http_response_code(404);
    render_header('Not found', $staff);
    ?>
    <div class="banner banner-error">Document not found.</div>
    <p><a href="/admin.php" class="back-link">&#8592; back to admin</a></p>
    <?php
    render_footer();
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    if ($title === '' || $body === '') {
        $error = 'Title and body are required.';
    } else {
        $newPublishAt = parse_publish_at($_POST['publish_at'] ?? '');

        // Slug is intentionally NOT regenerated on title change.
        // Existing share links contain the slug, so changing it would break them.
        $changes = [];
        if ($title !== $doc['title']) {
            $changes['title'] = $title;
            $changes['previous_title'] = $doc['title'];
        }
        if ($body !== $doc['body']) {
            $changes['body_changed'] = true;
        }
        if ($newPublishAt !== $doc['publish_at']) {
            $changes['publish_at'] = $newPublishAt;
            $changes['previous_publish_at'] = $doc['publish_at'];
        }

        $stmt = db()->prepare('UPDATE documents SET title = ?, body = ?, publish_at = ? WHERE id = ?');
        $stmt->execute([$title, $body, $newPublishAt, $doc['id']]);

        if (!empty($changes)) {
            audit_log('update', 'document', (int) $doc['id'], $changes);
        }

        header('Location: /admin.php?updated=' . $doc['id']);
        exit;
    }
}

$publishAtValue = '';
if ($doc['publish_at'] !== null) {
    $dt = new DateTime($doc['publish_at']);
    $publishAtValue = $dt->format('Y-m-d\TH:i');
}

render_header('Edit · ' . $doc['title'], $staff);
?>

<a href="/admin.php" class="back-link">&#8592; back to admin</a>

<h1 class="page-title">Edit "<?= h($doc['title']) ?>"</h1>
<p class="page-subtitle">Update document content and publish schedule.</p>

<?php if ($error): ?>
    <div class="banner banner-error"><?= h($error) ?></div>
<?php endif ?>

<section class="card">
    <form method="post">
        <div class="form-field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= h($doc['title']) ?>" required>
        </div>
        <div class="form-field">
            <label for="body">Body</label>
            <textarea id="body" name="body" required><?= h($doc['body']) ?></textarea>
        </div>
        <div class="form-field">
            <label for="publish_at">Publish at, UTC (leave empty to publish immediately)</label>
            <input type="datetime-local" id="publish_at" name="publish_at" value="<?= h($publishAtValue) ?>">
        </div>
        <button type="submit" class="btn">Save changes</button>
    </form>
</section>

<?php render_footer(); ?>
