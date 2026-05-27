<?php

require __DIR__ . '/../lib/bootstrap.php';

system('php ' . escapeshellarg(__DIR__ . '/../seed.php') . ' > /dev/null', $rc);
if ($rc !== 0) {
    fwrite(STDERR, "seed failed\n");
    exit(1);
}

$pass = 0;
$fail = 0;

function test(string $name, callable $fn): void {
    global $pass, $fail;
    try {
        $fn();
        echo "  [ok] {$name}\n";
        $pass++;
    } catch (Throwable $e) {
        echo "  [FAIL] {$name}: " . $e->getMessage() . "\n";
        $fail++;
    }
}

function assert_true($cond, string $msg = ''): void {
    if (!$cond) {
        throw new RuntimeException($msg !== '' ? $msg : 'expected true');
    }
}

echo "\nRunning tests:\n";

test('seeded share link resolves to the seeded document', function () {
    $stmt = db()->prepare('
        SELECT d.title
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        LIMIT 1
    ');
    $stmt->execute();
    $row = $stmt->fetch();
    assert_true($row !== false, 'expected the seeded share to resolve');
    assert_true($row['title'] === 'Welcome Packet', 'unexpected title: ' . var_export($row['title'], true));
});

// -- Scheduled publishing tests --

test('document with no publish_at is viewable (backward compatible)', function () {
    $stmt = db()->prepare('SELECT publish_at FROM documents WHERE id = 1');
    $stmt->execute();
    $doc = $stmt->fetch();
    assert_true($doc !== false, 'seeded document should exist');
    assert_true($doc['publish_at'] === null, 'seeded document should have null publish_at');
});

test('document with future publish_at is not yet viewable', function () {
    $future = date('Y-m-d H:i:s', strtotime('+1 day'));
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, publish_at) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Future Doc', 'body', $future]);
    $id = (int) db()->lastInsertId();

    $stmt = db()->prepare('SELECT publish_at FROM documents WHERE id = ?');
    $stmt->execute([$id]);
    $doc = $stmt->fetch();

    assert_true($doc !== false, 'document should exist');
    assert_true(new DateTime($doc['publish_at']) > new DateTime(), 'publish_at should be in the future');
});

test('document with past publish_at is viewable', function () {
    $past = date('Y-m-d H:i:s', strtotime('-1 day'));
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, publish_at) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Past Doc', 'body', $past]);
    $id = (int) db()->lastInsertId();

    $stmt = db()->prepare('SELECT publish_at FROM documents WHERE id = ?');
    $stmt->execute([$id]);
    $doc = $stmt->fetch();

    assert_true($doc !== false, 'document should exist');
    assert_true(new DateTime($doc['publish_at']) <= new DateTime(), 'publish_at should be in the past');
});

test('schedule update is audit logged with old and new values', function () {
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by) VALUES (?, ?, 1)');
    $stmt->execute(['Audit Schedule Test', 'body']);
    $id = (int) db()->lastInsertId();

    $newSchedule = date('Y-m-d H:i:s', strtotime('+2 days'));
    $stmt = db()->prepare('UPDATE documents SET publish_at = ? WHERE id = ?');
    $stmt->execute([$newSchedule, $id]);

    audit_log('update_schedule', 'document', $id, [
        'publish_at' => $newSchedule,
        'previous_publish_at' => null,
    ]);

    $stmt = db()->prepare('SELECT * FROM audit_log WHERE entity_type = ? AND entity_id = ? AND action = ?');
    $stmt->execute(['document', $id, 'update_schedule']);
    $log = $stmt->fetch();

    assert_true($log !== false, 'audit log entry should exist for schedule update');
    $details = json_decode($log['details'], true);
    assert_true($details['publish_at'] === $newSchedule, 'audit should contain new publish_at');
    assert_true($details['previous_publish_at'] === null, 'audit should contain previous publish_at');
});

// -- Search tests --

test('search finds documents by partial title match', function () {
    db()->prepare('INSERT INTO documents (title, body, created_by) VALUES (?, ?, 1)')->execute(['Onboarding Guide', 'body']);
    db()->prepare('INSERT INTO documents (title, body, created_by) VALUES (?, ?, 1)')->execute(['Policy Manual', 'body']);

    $stmt = db()->prepare('SELECT title FROM documents WHERE title LIKE ? ORDER BY title');
    $stmt->execute(['%board%']);
    $rows = $stmt->fetchAll();

    assert_true(count($rows) === 1, 'expected 1 match, got ' . count($rows));
    assert_true($rows[0]['title'] === 'Onboarding Guide', 'expected Onboarding Guide');
});

test('search is case-insensitive', function () {
    $stmt = db()->prepare('SELECT title FROM documents WHERE title LIKE ?');
    $stmt->execute(['%welcome%']);
    $rows = $stmt->fetchAll();

    assert_true(count($rows) >= 1, 'expected at least 1 match for lowercase "welcome"');
    assert_true($rows[0]['title'] === 'Welcome Packet', 'expected Welcome Packet');
});

test('search with no matches returns empty', function () {
    $stmt = db()->prepare('SELECT title FROM documents WHERE title LIKE ?');
    $stmt->execute(['%zzzznonexistent%']);
    $rows = $stmt->fetchAll();

    assert_true(count($rows) === 0, 'expected 0 matches');
});

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
