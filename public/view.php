<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

// Path: slug-based access with email gate
$slug = trim($_GET['slug'] ?? '');
if ($slug !== '') {
    $stmt = db()->prepare('SELECT * FROM documents WHERE slug = ?');
    $stmt->execute([$slug]);
    $doc = $stmt->fetch();

    if (!$doc) {
        http_response_code(404);
        render_header('Not found');
        ?>
        <div class="centered-message">
            <h1>Document not found</h1>
            <p>No document with that ID exists.</p>
        </div>
        <?php
        render_footer();
        exit;
    }

    // Check scheduled publishing
    if ($doc['published_at'] !== null && $doc['published_at'] > date('Y-m-d H:i:s')) {
        render_header('Not yet available');
        ?>
        <div class="centered-message">
            <h1>Not yet available</h1>
            <p>This document is scheduled to be available on <?= h(date('F j, Y \a\t g:i A', strtotime($doc['published_at']))) ?>.</p>
        </div>
        <?php
        render_footer();
        exit;
    }

    // Email gate
    $accessError = null;
    $granted = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        $shareCheck = db()->prepare('SELECT * FROM shares WHERE document_id = ? AND recipient_email = ?');
        $shareCheck->execute([$doc['id'], $email]);

        if ($shareCheck->fetch()) {
            $granted = true;
        } else {
            $accessError = 'That email does not have access to this document.';
        }
    }

    if ($granted) {
        render_header($doc['title']);
        ?>
        <h1 class="page-title"><?= h($doc['title']) ?></h1>
        <p class="meta">Accessed via <?= h($slug) ?></p>
        <pre class="doc-body"><?= h($doc['body']) ?></pre>
        <?php
        render_footer();
        exit;
    }

    render_header('Access document');
    ?>
    <div class="centered-message">
        <h1>Access document</h1>
        <p>Enter your email to view this document.</p>
        <?php if ($accessError): ?>
            <div class="banner banner-error" style="margin-top:0.75rem;"><?= h($accessError) ?></div>
        <?php endif ?>
        <form method="post" class="gate-form">
            <input type="email" name="email" placeholder="your@email.com" required>
            <button type="submit" class="btn">Access</button>
        </form>
    </div>
    <?php
    render_footer();
    exit;
}

// Path: token-based access (original)
$token = $_GET['token'] ?? '';

$stmt = db()->prepare('
    SELECT d.*, s.recipient_email
    FROM shares s
    JOIN documents d ON d.id = s.document_id
    WHERE s.token = ?
');
$stmt->execute([$token]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    render_header('Not found');
    ?>
    <div class="centered-message">
        <h1>Share link not found</h1>
        <p>The link you used is invalid or has been removed.</p>
    </div>
    <?php
    render_footer();
    exit;
}

// Check scheduled publishing
if ($doc['published_at'] !== null && $doc['published_at'] > date('Y-m-d H:i:s')) {
    render_header('Not yet available');
    ?>
    <div class="centered-message">
        <h1>Not yet available</h1>
        <p>This document is scheduled to be available on <?= h(date('F j, Y \a\t g:i A', strtotime($doc['published_at']))) ?>.</p>
    </div>
    <?php
    render_footer();
    exit;
}

render_header($doc['title']);
?>

<h1 class="page-title"><?= h($doc['title']) ?></h1>
<p class="meta">Shared with <?= h($doc['recipient_email']) ?></p>

<pre class="doc-body"><?= h($doc['body']) ?></pre>

<?php render_footer(); ?>
