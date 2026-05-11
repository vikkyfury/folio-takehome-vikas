<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();
$docId = (int) ($_GET['doc'] ?? 0);

// Search-first mode: no doc selected yet
if ($docId === 0) {
    $q = trim($_GET['q'] ?? '');
    $results = [];

    if ($q !== '') {
        $stmt = db()->prepare("SELECT * FROM documents WHERE title LIKE ? ORDER BY created_at DESC");
        $stmt->execute(['%' . $q . '%']);
        $results = $stmt->fetchAll();
    }

    render_header('Find & Share', $staff);
    ?>

    <a href="/admin.php" class="back-link">← back to admin</a>

    <h1 class="page-title">Find & Share</h1>
    <p class="page-subtitle">Search for a document by title, then create a share link.</p>

    <section class="card">
        <form method="get" action="/share.php" class="search-bar">
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search documents by title..." autofocus>
            <button type="submit" class="btn">Search</button>
        </form>

        <?php if ($q !== ''): ?>
            <?php if (empty($results)): ?>
                <p class="empty">No documents found matching "<?= h($q) ?>"</p>
            <?php else: ?>
                <?php foreach ($results as $r):
                    $now = date('Y-m-d H:i:s');
                    $isScheduled = $r['published_at'] !== null && $r['published_at'] > $now;
                ?>
                    <div class="search-result">
                        <div>
                            <div class="search-result-title"><?= h($r['title']) ?></div>
                            <div class="search-result-meta">
                                <span class="id"><?= h($r['slug']) ?: '#' . (int) $r['id'] ?></span>
                                <?php if ($isScheduled): ?>
                                    <span class="badge badge-warn">Scheduled</span>
                                <?php else: ?>
                                    <span class="badge badge-ok">Published</span>
                                <?php endif ?>
                            </div>
                        </div>
                        <a href="/share.php?doc=<?= (int) $r['id'] ?>" class="btn-link">Share →</a>
                    </div>
                <?php endforeach ?>
            <?php endif ?>
        <?php endif ?>
    </section>

    <?php
    render_footer();
    exit;
}

// Direct share mode: doc selected
$stmt = db()->prepare('SELECT * FROM documents WHERE id = ?');
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    render_header('Not found', $staff);
    ?>
    <div class="banner banner-error">Document not found.</div>
    <p><a href="/admin.php" class="back-link">← back to admin</a></p>
    <?php
    render_footer();
    exit;
}

$error = null;
$created_token = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $error = 'Recipient email is required.';
    } else {
        $token = random_token();
        $stmt = db()->prepare('
            INSERT INTO shares (document_id, token, recipient_email)
            VALUES (?, ?, ?)
        ');
        $stmt->execute([$doc['id'], $token, $email]);
        $shareId = (int) db()->lastInsertId();
        audit_log('create', 'share', $shareId, [
            'document_id' => $doc['id'],
            'recipient_email' => $email,
        ]);
        $created_token = $token;
    }
}

render_header('Share · ' . $doc['title'], $staff);
?>

<a href="/share.php" class="back-link">← find another document</a>

<h1 class="page-title">Share "<?= h($doc['title']) ?>"</h1>
<p class="page-subtitle">Generate a share link for a recipient.</p>

<?php if ($error): ?>
    <div class="banner banner-error"><?= h($error) ?></div>
<?php endif ?>

<?php if ($created_token): ?>
    <div class="banner banner-success">
        Share link ready:<br>
        <code>http://<?= h($_SERVER['HTTP_HOST']) ?>/view.php?token=<?= h($created_token) ?></code>
        <?php if ($doc['slug']): ?>
            <br><br>
            Readable link (requires email verification):<br>
            <code>http://<?= h($_SERVER['HTTP_HOST']) ?>/view.php?slug=<?= h($doc['slug']) ?></code>
        <?php endif ?>
    </div>
<?php endif ?>

<section class="card">
    <h2 class="card-title">Create share link</h2>
    <form method="post">
        <div class="form-field">
            <label for="email">Recipient email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <button type="submit" class="btn">Generate link</button>
    </form>
</section>

<?php render_footer(); ?>
