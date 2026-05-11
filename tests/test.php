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

function assert_false($cond, string $msg = ''): void {
    if ($cond) {
        throw new RuntimeException($msg !== '' ? $msg : 'expected false');
    }
}

function assert_equal($a, $b, string $msg = ''): void {
    if ($a !== $b) {
        throw new RuntimeException(
            ($msg !== '' ? $msg . ': ' : '') .
            'expected ' . var_export($b, true) . ', got ' . var_export($a, true)
        );
    }
}

echo "\nRunning tests:\n";

// --- Original test ---

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

// --- Migration system ---

test('schema_migrations table exists and has entries', function () {
    $rows = db()->query('SELECT COUNT(*) as cnt FROM schema_migrations')->fetch();
    assert_true($rows['cnt'] >= 2, 'expected at least 2 migrations, got ' . $rows['cnt']);
});

// --- Scheduled publishing ---

test('future published_at document is scheduled', function () {
    $tomorrow = date('Y-m-d H:i:s', strtotime('+1 day'));
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, published_at, slug) VALUES (?, ?, 1, ?, ?)');
    $stmt->execute(['Future Doc', 'content', $tomorrow, 'FOLIO-TEST']);
    $stmt = db()->prepare('SELECT * FROM documents WHERE slug = ?');
    $stmt->execute(['FOLIO-TEST']);
    $doc = $stmt->fetch();
    assert_true($doc['published_at'] > date('Y-m-d H:i:s'), 'future doc should be scheduled');
});

test('NULL published_at document is immediately visible', function () {
    $stmt = db()->prepare('SELECT * FROM documents WHERE published_at IS NULL AND slug = ?');
    $stmt->execute(['FOLIO-W3LC']);
    $doc = $stmt->fetch();
    assert_true($doc !== false, 'seeded doc should exist with NULL published_at');
    assert_true($doc['published_at'] === null, 'published_at should be NULL');
});

test('past published_at document is treated as published', function () {
    $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, published_at, slug) VALUES (?, ?, 1, ?, ?)');
    $stmt->execute(['Past Doc', 'content', $yesterday, 'FOLIO-PAST']);
    $stmt = db()->prepare('SELECT * FROM documents WHERE slug = ?');
    $stmt->execute(['FOLIO-PAST']);
    $doc = $stmt->fetch();
    assert_true($doc['published_at'] <= date('Y-m-d H:i:s'), 'past doc should be treated as published');
});

// --- Human-readable IDs ---

test('seeded document has expected slug', function () {
    $stmt = db()->prepare('SELECT slug FROM documents WHERE title = ?');
    $stmt->execute(['Welcome Packet']);
    $row = $stmt->fetch();
    assert_equal($row['slug'], 'FOLIO-W3LC', 'seeded document slug');
});

test('generate_slug produces valid FOLIO-XXXX format', function () {
    $slug = generate_slug();
    assert_true(preg_match('/^FOLIO-[A-Z0-9]{4,6}$/', $slug) === 1,
        'slug should match FOLIO-XXXX pattern, got: ' . $slug);
});

test('generate_slug produces unique slugs', function () {
    $slugs = [];
    for ($i = 0; $i < 10; $i++) {
        $slug = generate_slug();
        assert_false(in_array($slug, $slugs, true), "duplicate slug generated: $slug");
        $slugs[] = $slug;
    }
});

test('slug column has unique index', function () {
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, slug) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Dup Test 1', 'content', 'FOLIO-DUP1']);
    $caught = false;
    try {
        $stmt->execute(['Dup Test 2', 'content', 'FOLIO-DUP1']);
    } catch (PDOException $e) {
        $caught = true;
    }
    assert_true($caught, 'duplicate slug should throw exception');
});

test('slug URL with correct email grants access', function () {
    // The seeded doc has slug FOLIO-W3LC and a share for recipient@example.com
    $stmt = db()->prepare('
        SELECT COUNT(*) as cnt FROM shares s
        JOIN documents d ON d.id = s.document_id
        WHERE d.slug = ? AND s.recipient_email = ?
    ');
    $stmt->execute(['FOLIO-W3LC', 'recipient@example.com']);
    $row = $stmt->fetch();
    assert_true($row['cnt'] > 0, 'correct email should have access to seeded doc via slug');
});

test('slug URL with wrong email denies access', function () {
    $stmt = db()->prepare('
        SELECT COUNT(*) as cnt FROM shares s
        JOIN documents d ON d.id = s.document_id
        WHERE d.slug = ? AND s.recipient_email = ?
    ');
    $stmt->execute(['FOLIO-W3LC', 'nobody@example.com']);
    $row = $stmt->fetch();
    assert_true($row['cnt'] === 0, 'wrong email should not have access');
});

// --- Search ---

test('substring search finds document by middle word', function () {
    $stmt = db()->prepare("SELECT * FROM documents WHERE title LIKE ?");
    $stmt->execute(['%packet%']);
    $results = $stmt->fetchAll();
    assert_true(count($results) > 0, 'searching "packet" should find "Welcome Packet"');
    assert_equal($results[0]['title'], 'Welcome Packet');
});

test('search is case insensitive', function () {
    $stmt = db()->prepare("SELECT * FROM documents WHERE title LIKE ?");
    $stmt->execute(['%WELCOME%']);
    $results = $stmt->fetchAll();
    assert_true(count($results) > 0, 'searching "WELCOME" should find "Welcome Packet"');
});

test('non-matching search returns empty', function () {
    $stmt = db()->prepare("SELECT * FROM documents WHERE title LIKE ?");
    $stmt->execute(['%zzzzznotfound%']);
    $results = $stmt->fetchAll();
    assert_true(count($results) === 0, 'non-matching search should return empty');
});

// --- Audit logging ---

test('audit_log records document creation', function () {
    // Create a document through the normal flow (simulating admin.php POST)
    $slug = generate_slug();
    $stmt = db()->prepare('INSERT INTO documents (title, body, created_by, published_at, slug) VALUES (?, ?, 1, NULL, ?)');
    $stmt->execute(['Audit Test Doc', 'test body', $slug]);
    $docId = (int) db()->lastInsertId();
    audit_log('create', 'document', $docId, ['title' => 'Audit Test Doc', 'slug' => $slug]);

    $stmt = db()->prepare("SELECT * FROM audit_log WHERE action = 'create' AND entity_type = 'document' AND entity_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$docId]);
    $row = $stmt->fetch();
    assert_true($row !== false, 'expected an audit log entry for document creation');
    $details = json_decode($row['details'], true);
    assert_true(isset($details['title']), 'audit details should include title');
    assert_true(isset($details['slug']), 'audit details should include slug');
});

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
