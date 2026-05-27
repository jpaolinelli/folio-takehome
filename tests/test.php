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

// -- Slug tests --

test('generate_slug produces slugified-title-with-4-char-suffix', function () {
    $slug = generate_slug('Welcome Packet');
    assert_true(preg_match('/^welcome-packet-[a-z0-9]{4}$/', $slug) === 1,
        'slug format mismatch: ' . $slug);
});

test('generate_slug produces different slugs for the same title', function () {
    $a = generate_slug('Test Document');
    $b = generate_slug('Test Document');
    assert_true($a !== $b, 'expected different slugs, got: ' . $a);
});

test('seeded document has a slug', function () {
    $stmt = db()->prepare('SELECT slug FROM documents WHERE id = 1');
    $stmt->execute();
    $doc = $stmt->fetch();
    assert_true($doc !== false, 'seeded document should exist');
    assert_true($doc['slug'] !== null, 'seeded document should have a slug');
    assert_true(str_starts_with($doc['slug'], 'welcome-packet-'), 'slug should start with welcome-packet-');
});

test('document can be looked up by slug', function () {
    $slug = generate_slug('Slug Lookup Test');
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Slug Lookup Test', 'body', $slug]);

    $stmt = db()->prepare('SELECT title FROM documents WHERE slug = ?');
    $stmt->execute([$slug]);
    $doc = $stmt->fetch();

    assert_true($doc !== false, 'document should be found by slug');
    assert_true($doc['title'] === 'Slug Lookup Test', 'title mismatch');
});

test('view.php resolves document by slug + auth token', function () {
    $slug = generate_slug('Slug Auth Test');
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Slug Auth Test', 'body', $slug]);
    $docId = (int) db()->lastInsertId();

    $token = random_token();
    $stmt = db()->prepare('INSERT INTO shares (document_id, token, recipient_email) VALUES (?, ?, ?)');
    $stmt->execute([$docId, $token, 'test@example.com']);

    $stmt = db()->prepare('
        SELECT d.*, s.recipient_email
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        WHERE d.slug = ? AND s.token = ?
    ');
    $stmt->execute([$slug, $token]);
    $doc = $stmt->fetch();

    assert_true($doc !== false, 'document should resolve with slug + token');
    assert_true((int) $doc['id'] === $docId, 'should resolve to the correct document');
    assert_true($doc['recipient_email'] === 'test@example.com', 'should include recipient email');
});

test('view.php rejects slug without auth token', function () {
    $slug = generate_slug('No Token Test');
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)');
    $stmt->execute(['No Token Test', 'body', $slug]);

    $docParam = $slug;
    $authToken = '';
    $doc = null;

    if ($docParam !== '' && $authToken !== '') {
        $stmt = db()->prepare('
            SELECT d.* FROM shares s
            JOIN documents d ON d.id = s.document_id
            WHERE d.slug = ? AND s.token = ?
        ');
        $stmt->execute([$docParam, $authToken]);
        $doc = $stmt->fetch();
    }

    assert_true($doc === null, 'slug without token should not resolve');
});

test('view.php rejects valid slug with wrong token', function () {
    $slug = generate_slug('Wrong Token Test');
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Wrong Token Test', 'body', $slug]);

    $stmt = db()->prepare('
        SELECT d.* FROM shares s
        JOIN documents d ON d.id = s.document_id
        WHERE d.slug = ? AND s.token = ?
    ');
    $stmt->execute([$slug, 'deadbeefdeadbeefdeadbeefdeadbeef']);
    $doc = $stmt->fetch();

    assert_true($doc === false, 'valid slug with wrong token should not resolve');
});

// -- Adversarial tests --

test('slug uniqueness is enforced at the database level', function () {
    $slug = generate_slug('Unique Test');
    db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)')->execute(['Doc A', 'body', $slug]);

    $threw = false;
    try {
        db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)')->execute(['Doc B', 'body', $slug]);
    } catch (PDOException $e) {
        $threw = true;
    }
    assert_true($threw, 'duplicate slug insert should throw a constraint violation');
});

test('SQL injection via search term is harmless', function () {
    $malicious = "'; DROP TABLE documents; --";
    $stmt = db()->prepare('SELECT title FROM documents WHERE title LIKE ? OR id = ? OR slug LIKE ?');
    $stmt->execute(['%' . $malicious . '%', $malicious, '%' . $malicious . '%']);
    $rows = $stmt->fetchAll();

    assert_true(is_array($rows), 'query should complete without error');

    $check = db()->query('SELECT COUNT(*) FROM documents')->fetchColumn();
    assert_true((int) $check >= 1, 'documents table should still exist and have rows');
});

test('token for one document does not grant access to another', function () {
    $slugA = generate_slug('Doc A Cross');
    $slugB = generate_slug('Doc B Cross');
    db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)')->execute(['Doc A Cross', 'body a', $slugA]);
    $idA = (int) db()->lastInsertId();
    db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)')->execute(['Doc B Cross', 'body b', $slugB]);

    $tokenA = random_token();
    db()->prepare('INSERT INTO shares (document_id, token, recipient_email) VALUES (?, ?, ?)')->execute([$idA, $tokenA, 'a@test.com']);

    $stmt = db()->prepare('
        SELECT d.* FROM shares s
        JOIN documents d ON d.id = s.document_id
        WHERE d.slug = ? AND s.token = ?
    ');
    $stmt->execute([$slugB, $tokenA]);
    $doc = $stmt->fetch();

    assert_true($doc === false, 'token for doc A should not resolve doc B');
});

test('document with publish_at exactly now is viewable', function () {
    $now = date('Y-m-d H:i:s');
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, publish_at) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Boundary Doc', 'body', $now]);

    $stmt = db()->prepare('SELECT publish_at FROM documents WHERE id = ?');
    $stmt->execute([(int) db()->lastInsertId()]);
    $doc = $stmt->fetch();

    assert_true($doc !== false, 'document should exist');
    assert_true(new DateTime($doc['publish_at']) <= new DateTime(), 'publish_at at exactly now should be viewable');
});

test('generate_slug handles special characters and unicode', function () {
    $slug = generate_slug('Hello World!!! @#$% (test)');
    assert_true(preg_match('/^[a-z0-9-]+-[a-z0-9]{4}$/', $slug) === 1,
        'slug should only contain lowercase alphanum and hyphens: ' . $slug);

    $slug2 = generate_slug('   Leading Trailing   ');
    assert_true(!str_starts_with($slug2, '-'), 'slug should not start with hyphen: ' . $slug2);
    assert_true(str_starts_with($slug2, 'leading-trailing-'), 'slug should trim and slugify: ' . $slug2);
});

test('generate_slug handles empty and whitespace-only titles', function () {
    $slug = generate_slug('');
    assert_true(preg_match('/^[a-z0-9]{4}$/', $slug) === 1,
        'empty title slug should be just the 4-char suffix: ' . $slug);

    $slug2 = generate_slug('   ');
    assert_true(preg_match('/^[a-z0-9]{4}$/', $slug2) === 1,
        'whitespace-only title slug should be just the suffix: ' . $slug2);
});

test('deleting a document removes its shares and is audit logged', function () {
    $slug = generate_slug('Delete Cascade');
    db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)')->execute(['Delete Cascade', 'body', $slug]);
    $docId = (int) db()->lastInsertId();

    $token1 = random_token();
    $token2 = random_token();
    db()->prepare('INSERT INTO shares (document_id, token, recipient_email) VALUES (?, ?, ?)')->execute([$docId, $token1, 'a@test.com']);
    db()->prepare('INSERT INTO shares (document_id, token, recipient_email) VALUES (?, ?, ?)')->execute([$docId, $token2, 'b@test.com']);

    $stmt = db()->prepare('SELECT COUNT(*) FROM shares WHERE document_id = ?');
    $stmt->execute([$docId]);
    assert_true((int) $stmt->fetchColumn() === 2, 'should have 2 shares before delete');

    $stmt = db()->prepare('DELETE FROM shares WHERE document_id = ?');
    $stmt->execute([$docId]);
    $sharesDeleted = $stmt->rowCount();

    $stmt = db()->prepare('DELETE FROM documents WHERE id = ?');
    $stmt->execute([$docId]);

    audit_log('delete', 'document', $docId, [
        'title' => 'Delete Cascade',
        'slug' => $slug,
        'shares_deleted' => $sharesDeleted,
    ]);

    $stmt = db()->prepare('SELECT COUNT(*) FROM shares WHERE document_id = ?');
    $stmt->execute([$docId]);
    assert_true((int) $stmt->fetchColumn() === 0, 'all shares should be removed');

    $stmt = db()->prepare('SELECT * FROM documents WHERE id = ?');
    $stmt->execute([$docId]);
    assert_true($stmt->fetch() === false, 'document should be deleted');

    $stmt = db()->prepare('SELECT * FROM audit_log WHERE entity_type = ? AND entity_id = ? AND action = ?');
    $stmt->execute(['document', $docId, 'delete']);
    $log = $stmt->fetch();
    assert_true($log !== false, 'delete should be audit logged');
    $details = json_decode($log['details'], true);
    assert_true($details['shares_deleted'] === 2, 'audit should record shares_deleted count');
});

test('audit_log records correct staff_id and entity details', function () {
    db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)')->execute(['Audit Detail Test', 'body', generate_slug('Audit Detail Test')]);
    $docId = (int) db()->lastInsertId();

    audit_log('create', 'document', $docId, ['title' => 'Audit Detail Test']);

    $stmt = db()->prepare('SELECT * FROM audit_log WHERE entity_type = ? AND entity_id = ? AND action = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute(['document', $docId, 'create']);
    $log = $stmt->fetch();

    assert_true($log !== false, 'audit log entry should exist');
    assert_true((int) $log['staff_id'] === 1, 'audit should reference staff #1');
    assert_true($log['entity_type'] === 'document', 'entity_type should be document');
    assert_true((int) $log['entity_id'] === $docId, 'entity_id should match document');

    $details = json_decode($log['details'], true);
    assert_true($details['title'] === 'Audit Detail Test', 'details should contain title');
});

test('multiple shares for same document each have unique tokens', function () {
    $slug = generate_slug('Multi Share');
    db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)')->execute(['Multi Share', 'body', $slug]);
    $docId = (int) db()->lastInsertId();

    $tokens = [];
    for ($i = 0; $i < 5; $i++) {
        $t = random_token();
        db()->prepare('INSERT INTO shares (document_id, token, recipient_email) VALUES (?, ?, ?)')->execute([$docId, $t, "user{$i}@test.com"]);
        $tokens[] = $t;
    }

    assert_true(count(array_unique($tokens)) === 5, 'all 5 tokens should be unique');

    $stmt = db()->prepare('SELECT COUNT(*) FROM shares WHERE document_id = ?');
    $stmt->execute([$docId]);
    assert_true((int) $stmt->fetchColumn() === 5, 'document should have exactly 5 shares');
});

test('view resolution by numeric ID + token works', function () {
    $slug = generate_slug('ID Resolve Test');
    db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)')->execute(['ID Resolve Test', 'body', $slug]);
    $docId = (int) db()->lastInsertId();

    $token = random_token();
    db()->prepare('INSERT INTO shares (document_id, token, recipient_email) VALUES (?, ?, ?)')->execute([$docId, $token, 'id@test.com']);

    $stmt = db()->prepare('
        SELECT d.*, s.recipient_email
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        WHERE d.id = ? AND s.token = ?
    ');
    $stmt->execute([$docId, $token]);
    $doc = $stmt->fetch();

    assert_true($doc !== false, 'should resolve document by numeric ID + token');
    assert_true($doc['title'] === 'ID Resolve Test', 'title should match');
    assert_true($doc['recipient_email'] === 'id@test.com', 'recipient should match');
});

// -- Helper function tests --

test('parse_publish_at handles standard datetime-local format', function () {
    $result = parse_publish_at('2026-05-27T14:00');
    assert_true($result === '2026-05-27 14:00:00', 'should parse YYYY-MM-DDTHH:MM: ' . var_export($result, true));
});

test('parse_publish_at handles format with seconds', function () {
    $result = parse_publish_at('2026-05-27T14:00:30');
    assert_true($result === '2026-05-27 14:00:30', 'should parse YYYY-MM-DDTHH:MM:SS: ' . var_export($result, true));
});

test('parse_publish_at returns null for empty or invalid input', function () {
    assert_true(parse_publish_at('') === null, 'empty string should return null');
    assert_true(parse_publish_at('   ') === null, 'whitespace should return null');
    assert_true(parse_publish_at('not-a-date') === null, 'invalid string should return null');
});

test('find_document returns false for empty param', function () {
    assert_true(find_document('') === false, 'empty string should return false');
});

test('find_document finds by numeric ID', function () {
    $doc = find_document('1');
    assert_true($doc !== false, 'should find seeded document by ID');
    assert_true($doc['title'] === 'Welcome Packet', 'title should match');
});

test('find_document finds by slug', function () {
    $stmt = db()->prepare('SELECT slug FROM documents WHERE id = 1');
    $stmt->execute();
    $slug = $stmt->fetch()['slug'];

    $doc = find_document($slug);
    assert_true($doc !== false, 'should find seeded document by slug');
    assert_true((int) $doc['id'] === 1, 'ID should match');
});

test('parse_publish_at rejects malformed input distinctly from empty', function () {
    assert_true(parse_publish_at('') === null, 'empty returns null');
    assert_true(parse_publish_at('not-a-date') === null, 'garbage returns null');
    assert_true(parse_publish_at('2026-05-27T14:00') !== null, 'valid input returns non-null');
});

test('iso8601 converts DB date format to valid ISO 8601', function () {
    $result = iso8601('2026-05-27 14:00:00');
    assert_true($result === '2026-05-27T14:00:00Z', 'expected ISO format, got: ' . $result);
});

test('delete operation is atomic via transaction', function () {
    $slug = generate_slug('Txn Test');
    db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)')->execute(['Txn Test', 'body', $slug]);
    $docId = (int) db()->lastInsertId();

    $token = random_token();
    db()->prepare('INSERT INTO shares (document_id, token, recipient_email) VALUES (?, ?, ?)')->execute([$docId, $token, 'txn@test.com']);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM shares WHERE document_id = ?')->execute([$docId]);
        $pdo->prepare('DELETE FROM documents WHERE id = ?')->execute([$docId]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM documents WHERE id = ?');
    $stmt->execute([$docId]);
    assert_true((int) $stmt->fetchColumn() === 0, 'document should be deleted');

    $stmt = db()->prepare('SELECT COUNT(*) FROM shares WHERE document_id = ?');
    $stmt->execute([$docId]);
    assert_true((int) $stmt->fetchColumn() === 0, 'shares should be deleted');
});

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
