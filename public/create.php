<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if ($title === '' || $body === '') {
        $error = 'Title and body are required.';
    } else {
        $rawPublishAt = trim($_POST['publish_at'] ?? '');
        if ($rawPublishAt !== '' && parse_publish_at($rawPublishAt) === null) {
            $error = 'Invalid publish date format.';
        } else {
            $publishAt = parse_publish_at($rawPublishAt);

            $maxRetries = 5;
            $slug = null;
            for ($i = 0; $i < $maxRetries; $i++) {
                $slug = generate_slug($title);
                try {
                    $stmt = db()->prepare('
                        INSERT INTO documents (title, body, created_by, publish_at, slug)
                        VALUES (?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([$title, $body, $staff['id'], $publishAt, $slug]);
                    break;
                } catch (PDOException $e) {
                    if ($i === $maxRetries - 1 || strpos($e->getMessage(), 'UNIQUE') === false) {
                        throw $e;
                    }
                }
            }
            $docId = (int) db()->lastInsertId();

            audit_log('create', 'document', $docId, [
                'title' => $title,
                'publish_at' => $publishAt,
                'slug' => $slug,
            ]);

            header('Location: /admin.php?created=' . urlencode($slug));
            exit;
        }
    }
}

render_header('New document', $staff);
?>

<a href="/admin.php" class="back-link">&#8592; back to admin</a>

<h1 class="page-title">New document</h1>
<p class="page-subtitle">Create a new document and optionally schedule its publish date.</p>

<?php if ($error): ?>
    <div class="banner banner-error"><?= h($error) ?></div>
<?php endif ?>

<section class="card">
    <form method="post">
        <div class="form-field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" required>
        </div>
        <div class="form-field">
            <label for="body">Body</label>
            <textarea id="body" name="body" required></textarea>
        </div>
        <div class="form-field">
            <label for="publish_at">Publish at, UTC (optional)</label>
            <input type="datetime-local" id="publish_at" name="publish_at">
        </div>
        <button type="submit" class="btn">Create document</button>
    </form>
</section>

<?php render_footer(); ?>
