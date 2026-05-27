<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();
$q = trim($_GET['q'] ?? '');
$perPageRaw = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
$perPage = in_array($perPageRaw, [10, 50, 100], true) ? $perPageRaw : 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

if ($q !== '') {
    $searchWhere = 'WHERE d.title LIKE ? OR d.id = ? OR d.slug LIKE ?';
    $searchParams = ['%' . $q . '%', $q, '%' . $q . '%'];

    $countStmt = db()->prepare("SELECT COUNT(*) FROM documents d {$searchWhere}");
    $countStmt->execute($searchParams);
    $total = (int) $countStmt->fetchColumn();

    $stmt = db()->prepare("
        SELECT d.*, s.name AS creator_name
        FROM documents d
        JOIN staff s ON s.id = d.created_by
        {$searchWhere}
        ORDER BY d.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($searchParams, [$perPage, $offset]));
    $docs = $stmt->fetchAll();
} else {
    $total = (int) db()->query('SELECT COUNT(*) FROM documents')->fetchColumn();

    $stmt = db()->prepare('
        SELECT d.*, s.name AS creator_name
        FROM documents d
        JOIN staff s ON s.id = d.created_by
        ORDER BY d.created_at DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->execute([$perPage, $offset]);
    $docs = $stmt->fetchAll();
}

$totalPages = max(1, (int) ceil($total / $perPage));

$paginate_url = function (array $overrides): string {
    $params = array_merge($_GET, $overrides);
    if ((int) ($params['page'] ?? 1) <= 1) unset($params['page']);
    if ((int) ($params['per_page'] ?? 10) === 10) unset($params['per_page']);
    if (($params['q'] ?? '') === '') unset($params['q']);
    return '/admin.php' . ($params ? '?' . http_build_query($params) : '');
};

render_header('Admin', $staff);
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Documents</h1>
        <p class="page-subtitle">Manage documents and generate share links for recipients.</p>
    </div>
    <a href="/create.php" class="btn"><i class="fa-solid fa-plus"></i> Create document</a>
</div>

<?php if (!empty($_GET['created'])): ?>
    <div class="banner banner-success">Document <?= h($_GET['created']) ?> created.</div>
<?php endif ?>

<?php if (!empty($_GET['updated'])): ?>
    <div class="banner banner-success">Document #<?= (int) $_GET['updated'] ?> updated.</div>
<?php endif ?>

<?php if (!empty($_GET['deleted'])): ?>
    <div class="banner banner-success">Document #<?= (int) $_GET['deleted'] ?> deleted.</div>
<?php endif ?>

<section class="card">
    <form method="get" style="display:flex;gap:.5rem;margin-bottom:1rem;">
        <div class="search-wrap">
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search by title, ID, or slug...">
            <?php if ($q !== ''): ?>
                <a href="<?= h($paginate_url(['q' => ''])) ?>" class="search-clear"><i class="fa-solid fa-xmark"></i></a>
            <?php endif ?>
        </div>
        <?php if ($perPage !== 10): ?>
            <input type="hidden" name="per_page" value="<?= $perPage ?>">
        <?php endif ?>
        <button type="submit" class="btn">Search</button>
    </form>
    <?php if (empty($docs) && $q !== ''): ?>
        <p class="empty">No documents matching "<?= h($q) ?>".</p>
    <?php elseif (empty($docs)): ?>
        <p class="empty">No documents yet.</p>
    <?php else: ?>
        <table class="data">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Creator</th>
                    <th>Created</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($docs as $d): ?>
                    <tr>
                        <td class="id"><?= h($d['id']) ?></td>
                        <td><?= h($d['title']) ?></td>
                        <td><?= h($d['slug'] ?? '') ?></td>
                        <td><?= h($d['creator_name']) ?></td>
                        <td><time datetime="<?= h(iso8601($d['created_at'])) ?>"><?= h($d['created_at']) ?></time></td>
                        <td>
                            <?php if ($d['publish_at'] !== null && new DateTime($d['publish_at']) > new DateTime()): ?>
                                Scheduled: <time datetime="<?= h(iso8601($d['publish_at'])) ?>"><?= h($d['publish_at']) ?></time>
                            <?php else: ?>
                                Published
                            <?php endif ?>
                        </td>
                        <td class="actions">
                            <a href="/edit.php?doc=<?= h($d['slug'] ?? $d['id']) ?>" class="action-icon" data-tooltip="Edit document"><i class="fa-solid fa-pen-to-square"></i></a>
                            <a href="/share.php?doc=<?= h($d['slug'] ?? $d['id']) ?>" class="action-icon" data-tooltip="Create share link"><i class="fa-solid fa-share-nodes"></i></a>
                            <a href="/delete.php?doc=<?= h($d['slug'] ?? $d['id']) ?>" class="action-icon action-icon-danger" data-tooltip="Delete document"><i class="fa-solid fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>

        <div class="pagination">
            <div class="pagination-info">
                Showing <?= $offset + 1 ?>-<?= min($offset + $perPage, $total) ?> of <?= $total ?>
            </div>
            <div class="pagination-controls">
                <span class="pagination-size">
                    <?php foreach ([10, 50, 100] as $size): ?>
                        <?php if ($size === $perPage): ?>
                            <span class="pagination-size-active"><?= $size ?></span>
                        <?php else: ?>
                            <a href="<?= h($paginate_url(['per_page' => $size, 'page' => 1])) ?>"><?= $size ?></a>
                        <?php endif ?>
                    <?php endforeach ?>
                    per page
                </span>
                <?php if ($page > 1): ?>
                    <a href="<?= h($paginate_url(['page' => $page - 1])) ?>" class="btn-link"><i class="fa-solid fa-chevron-left"></i> Prev</a>
                <?php endif ?>
                <span class="pagination-page">Page <?= $page ?> of <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= h($paginate_url(['page' => $page + 1])) ?>" class="btn-link">Next <i class="fa-solid fa-chevron-right"></i></a>
                <?php endif ?>
            </div>
        </div>
    <?php endif ?>
</section>

<?php render_footer(); ?>
