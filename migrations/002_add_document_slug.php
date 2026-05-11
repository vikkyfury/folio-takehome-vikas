<?php

return function (PDO $pdo): void {
    $alphabet = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    $stmt = $pdo->query("SELECT id FROM documents WHERE slug = ''");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $id) {
        $slug = 'FOLIO-' . generate_random_suffix($alphabet, 4);
        $check = $pdo->prepare('SELECT 1 FROM documents WHERE slug = ?');
        $check->execute([$slug]);
        if ($check->fetch()) {
            $slug = 'FOLIO-' . generate_random_suffix($alphabet, 6);
        }
        $update = $pdo->prepare('UPDATE documents SET slug = ? WHERE id = ?');
        $update->execute([$slug, $id]);
    }
};

function generate_random_suffix(string $alphabet, int $len): string {
    $suffix = '';
    for ($i = 0; $i < $len; $i++) {
        $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $suffix;
}
