<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$docParam = $_GET['doc'] ?? '';
$authToken = $_GET['a'] ?? '';
$doc = null;
$recipientEmail = null;

if ($docParam !== '' && $authToken !== '') {
    if (ctype_digit($docParam)) {
        $stmt = db()->prepare('
            SELECT d.*, s.recipient_email
            FROM shares s
            JOIN documents d ON d.id = s.document_id
            WHERE d.id = ? AND s.token = ?
        ');
        $stmt->execute([(int) $docParam, $authToken]);
    } else {
        $stmt = db()->prepare('
            SELECT d.*, s.recipient_email
            FROM shares s
            JOIN documents d ON d.id = s.document_id
            WHERE d.slug = ? AND s.token = ?
        ');
        $stmt->execute([$docParam, $authToken]);
    }
    $doc = $stmt->fetch();
    if ($doc) {
        $recipientEmail = $doc['recipient_email'];
    }
}

if (!$doc) {
    http_response_code(404);
    render_header('Not found');
    ?>
    <div class="centered-message">
        <h1>Document not found</h1>
        <p>The link you used is invalid or has been removed.</p>
    </div>
    <?php
    render_footer();
    exit;
}

if ($doc['publish_at'] !== null && new DateTime($doc['publish_at']) > new DateTime()) {
    render_header('Not yet available');
    ?>
    <div class="centered-message">
        <h1>This document is not yet available</h1>
        <p>Please check back later.</p>
    </div>
    <?php
    render_footer();
    exit;
}

render_header($doc['title']);
?>

<h1 class="page-title"><?= h($doc['title']) ?></h1>
<?php if (!empty($doc['slug'])): ?>
    <p class="meta"><?= h($doc['slug']) ?></p>
<?php endif ?>
<?php if ($recipientEmail !== null): ?>
    <p class="meta">Shared with <?= h($recipientEmail) ?></p>
<?php endif ?>

<pre class="doc-body"><?= h($doc['body']) ?></pre>

<?php render_footer(); ?>
