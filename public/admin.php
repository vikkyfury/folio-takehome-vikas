<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $publishedAt = trim($_POST['published_at'] ?? '') ?: null;

    if ($title === '' || $body === '') {
        $error = 'Title and body are required.';
    } else {
        $slug = generate_slug();
        $stmt = db()->prepare('
            INSERT INTO documents (title, body, created_by, published_at, slug)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$title, $body, $staff['id'], $publishedAt, $slug]);
        $docId = (int) db()->lastInsertId();

        audit_log('create', 'document', $docId, ['title' => $title, 'published_at' => $publishedAt, 'slug' => $slug]);

        header('Location: /admin.php?created=' . $docId . '&slug=' . urlencode($slug));
        exit;
    }
}

// Filter by search query
$searchQ = trim($_GET['q'] ?? '');
if ($searchQ !== '') {
    $stmt = db()->prepare("SELECT d.*, s.name AS creator_name
        FROM documents d
        JOIN staff s ON s.id = d.created_by
        WHERE d.title LIKE ?
        ORDER BY d.created_at DESC");
    $stmt->execute(['%' . $searchQ . '%']);
    $docs = $stmt->fetchAll();
} else {
    $docs = db()->query('
        SELECT d.*, s.name AS creator_name
        FROM documents d
        JOIN staff s ON s.id = d.created_by
        ORDER BY d.created_at DESC
    ')->fetchAll();
}

render_header('Admin', $staff);
?>

<h1 class="page-title">Admin</h1>
<p class="page-subtitle">Create documents and generate share links for recipients.</p>

<?php if (!empty($_GET['created'])): ?>
    <div class="banner banner-success">Document <?= h($_GET['slug'] ?? '#' . (int) $_GET['created']) ?> created.</div>
<?php endif ?>

<?php if (!empty($_GET['schedule_updated'])): ?>
    <div class="banner banner-success">Schedule updated for document #<?= (int) $_GET['schedule_updated'] ?>.</div>
<?php endif ?>

<?php if ($error): ?>
    <div class="banner banner-error"><?= h($error) ?></div>
<?php endif ?>

<section class="card">
    <h2 class="card-title">New document</h2>
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
            <label for="published_at">Publish at <span style="font-weight:normal;color:var(--text-muted)">(leave empty to publish immediately; times in Central)</span></label>
            <input type="datetime-local" id="published_at" name="published_at">
        </div>
        <button type="submit" class="btn">Create document</button>
    </form>
</section>

<section class="card">
    <h2 class="card-title">Documents</h2>
    <form method="get" action="/admin.php" class="search-bar">
        <input type="text" name="q" value="<?= h($searchQ) ?>" placeholder="Filter by title...">
        <?php if ($searchQ !== ''): ?>
            <a href="/admin.php" class="btn-link" style="white-space:nowrap;align-self:center;">Clear</a>
        <?php endif ?>
    </form>
    <?php if (empty($docs)): ?>
        <p class="empty">No documents yet.</p>
    <?php else: ?>
        <table class="data">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Creator</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($docs as $d):
                    $now = date('Y-m-d H:i:s');
                    $isScheduled = $d['published_at'] !== null && $d['published_at'] > $now;
                ?>
                    <tr>
                        <td class="id"><?= h($d['slug']) ?: '#' . (int) $d['id'] ?></td>
                        <td><?= h($d['title']) ?></td>
                        <td><?= h($d['creator_name']) ?></td>
                        <td>
                            <?php if ($isScheduled): ?>
                                <span class="badge badge-warn">Scheduled <?= h(date('M j, Y g:i A', strtotime($d['published_at']))) ?></span>
                            <?php else: ?>
                                <span class="badge badge-ok">Published</span>
                            <?php endif ?>
                        </td>
                        <td><?= h($d['created_at']) ?></td>
                        <td>
                            <a href="/share.php?doc=<?= (int) $d['id'] ?>" class="btn-link">Share →</a>
                            &nbsp;
                            <a href="/schedule.php?doc=<?= (int) $d['id'] ?>" class="btn-link">Schedule</a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</section>

<?php render_footer(); ?>
