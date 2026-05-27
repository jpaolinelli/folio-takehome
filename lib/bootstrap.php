<?php

date_default_timezone_set('UTC');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $path = __DIR__ . '/../db.sqlite';
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function current_staff(): array {
    $stmt = db()->prepare('SELECT * FROM staff WHERE id = 1');
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('No staff row #1 found. Did you run `php seed.php`?');
    }
    return $row;
}

function audit_log(string $action, string $entity_type, int $entity_id, array $details = []): void {
    $staff = current_staff();
    $stmt = db()->prepare('
        INSERT INTO audit_log (staff_id, action, entity_type, entity_id, details)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $staff['id'],
        $action,
        $entity_type,
        $entity_id,
        json_encode($details),
    ]);
}

function random_token(int $bytes = 16): string {
    return bin2hex(random_bytes($bytes));
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function parse_publish_at(string $input): ?string {
    $input = trim($input);
    if ($input === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $input)
        ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $input);
    if (!$dt) {
        return null;
    }
    return $dt->format('Y-m-d H:i:s');
}

function find_document(string $param): array|false {
    if ($param === '') {
        return false;
    }
    if (ctype_digit($param)) {
        $stmt = db()->prepare('SELECT * FROM documents WHERE id = ?');
        $stmt->execute([(int) $param]);
    } else {
        $stmt = db()->prepare('SELECT * FROM documents WHERE slug = ?');
        $stmt->execute([$param]);
    }
    return $stmt->fetch();
}

function generate_slug(string $title): string {
    $base = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($title)));
    $base = trim($base, '-');
    $suffix = strtolower(substr(bin2hex(random_bytes(2)), 0, 4));
    return $base !== '' ? $base . '-' . $suffix : $suffix;
}
