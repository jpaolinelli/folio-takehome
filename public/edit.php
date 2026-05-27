<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();
$docParam = trim($_GET['doc'] ?? '');

if (ctype_digit($docParam)) {
    $stmt = db()->prepare('SELECT * FROM documents WHERE id = ?');
    $stmt->execute([(int) $docParam]);
} else {
    $stmt = db()->prepare('SELECT * FROM documents WHERE slug = ?');
    $stmt->execute([$docParam]);
}
$doc = $stmt->fetch();

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
    $publishAt = trim($_POST['publish_at'] ?? '');
    $newPublishAt = $publishAt !== '' ? str_replace('T', ' ', $publishAt) . ':00' : null;
    $previousPublishAt = $doc['publish_at'];

    $stmt = db()->prepare('UPDATE documents SET publish_at = ? WHERE id = ?');
    $stmt->execute([$newPublishAt, $doc['id']]);

    audit_log('update_schedule', 'document', $doc['id'], [
        'publish_at' => $newPublishAt,
        'previous_publish_at' => $previousPublishAt,
    ]);

    header('Location: /admin.php?updated=' . $doc['id']);
    exit;
}

$formValue = '';
if ($doc['publish_at'] !== null) {
    $dt = new DateTime($doc['publish_at']);
    $formValue = $dt->format('Y-m-d\TH:i');
}

render_header('Edit schedule · ' . $doc['title'], $staff);
?>

<a href="/admin.php" class="back-link">&#8592; back to admin</a>

<h1 class="page-title">Edit schedule for "<?= h($doc['title']) ?>"</h1>
<p class="page-subtitle">Set when this document becomes visible to recipients.</p>

<?php if ($error): ?>
    <div class="banner banner-error"><?= h($error) ?></div>
<?php endif ?>

<section class="card">
    <h2 class="card-title">Publish schedule</h2>
    <form method="post">
        <div class="form-field">
            <label for="publish_at">Publish at (leave empty to publish immediately)</label>
            <input type="datetime-local" id="publish_at" name="publish_at" value="<?= h($formValue) ?>">
        </div>
        <button type="submit" class="btn">Update schedule</button>
    </form>
</section>

<?php render_footer(); ?>
