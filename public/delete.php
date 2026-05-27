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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $docId = (int) $doc['id'];

    $stmt = db()->prepare('DELETE FROM shares WHERE document_id = ?');
    $stmt->execute([$docId]);
    $sharesDeleted = $stmt->rowCount();

    $stmt = db()->prepare('DELETE FROM documents WHERE id = ?');
    $stmt->execute([$docId]);

    audit_log('delete', 'document', $docId, [
        'title' => $doc['title'],
        'slug' => $doc['slug'],
        'shares_deleted' => $sharesDeleted,
    ]);

    header('Location: /admin.php?deleted=' . $docId);
    exit;
}

render_header('Delete · ' . $doc['title'], $staff);
?>

<a href="/admin.php" class="back-link">&#8592; back to admin</a>

<h1 class="page-title">Delete "<?= h($doc['title']) ?>"</h1>
<p class="page-subtitle">This action cannot be undone. All share links for this document will also be removed.</p>

<section class="card">
    <table class="data">
        <tr><td><strong>ID</strong></td><td><?= h($doc['id']) ?></td></tr>
        <tr><td><strong>Title</strong></td><td><?= h($doc['title']) ?></td></tr>
        <tr><td><strong>Slug</strong></td><td><?= h($doc['slug'] ?? '') ?></td></tr>
        <tr><td><strong>Created</strong></td><td><time datetime="<?= h($doc['created_at']) ?>Z"><?= h($doc['created_at']) ?></time></td></tr>
    </table>
</section>

<form method="post" style="display:flex;gap:0.75rem;align-items:center;">
    <button type="submit" class="btn btn-danger">Delete document</button>
    <a href="/admin.php" class="btn-link">Cancel</a>
</form>

<?php render_footer(); ?>
