<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();
$docId = (int) ($_GET['doc'] ?? 0);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $remove = isset($_POST['remove_schedule']);
    if ($remove) {
        $publishedAt = null;
    } else {
        $publishedAt = trim($_POST['published_at'] ?? '') ?: null;
    }

    $stmt = db()->prepare('UPDATE documents SET published_at = ? WHERE id = ?');
    $stmt->execute([$publishedAt, $doc['id']]);

    audit_log('schedule_update', 'document', $doc['id'], ['published_at' => $publishedAt]);

    header('Location: /admin.php?schedule_updated=' . $doc['id']);
    exit;
}

render_header('Schedule · ' . $doc['title'], $staff);
?>

<a href="/admin.php" class="back-link">← back to admin</a>

<h1 class="page-title">Schedule "<?= h($doc['title']) ?>"</h1>
<p class="page-subtitle">Set when this document becomes visible to recipients.</p>

<?php if ($error): ?>
    <div class="banner banner-error"><?= h($error) ?></div>
<?php endif ?>

<section class="card">
    <h2 class="card-title">Publish timing</h2>
    <form method="post">
        <div class="form-field">
            <label for="published_at">Publish at</label>
            <input type="datetime-local" id="published_at" name="published_at"
                   value="<?= $doc['published_at'] ? h(date('Y-m-d\TH:i', strtotime($doc['published_at']))) : '' ?>">
        </div>
        <div class="form-field" style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
            <input type="checkbox" id="remove_schedule" name="remove_schedule" style="width:auto;margin:0;">
            <label for="remove_schedule" style="margin:0;font-weight:normal;">Remove schedule (publish immediately)</label>
        </div>
        <button type="submit" class="btn">Update schedule</button>
    </form>
</section>

<?php render_footer(); ?>
