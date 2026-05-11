<?php

date_default_timezone_set('America/Chicago');

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

function run_migrations(PDO $pdo, bool $fresh = false): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            applied_at TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $applied = $fresh
        ? []
        : $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

    $dir = __DIR__ . '/../migrations';
    if (!is_dir($dir)) return;

    $files = glob($dir . '/*.sql');
    sort($files);

    foreach ($files as $path) {
        $name = basename($path);
        if (in_array($name, $applied, true)) continue;

        $pdo->exec(file_get_contents($path));

        $phpPath = $dir . '/' . pathinfo($name, PATHINFO_FILENAME) . '.php';
        if (file_exists($phpPath)) {
            $callback = require $phpPath;
            if (is_callable($callback)) {
                $callback($pdo);
            }
        }

        $stmt = $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)');
        $stmt->execute([$name]);
    }
}

function generate_slug(): string {
    $alphabet = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    $len = 4;
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $suffix = '';
        for ($i = 0; $i < $len; $i++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $slug = 'FOLIO-' . $suffix;
        $stmt = db()->prepare('SELECT 1 FROM documents WHERE slug = ?');
        $stmt->execute([$slug]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        if ($attempt >= 4) $len = 6;
    }
    return 'FOLIO-' . strtoupper(bin2hex(random_bytes(3)));
}
