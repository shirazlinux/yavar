<?php
declare(strict_types=1);

function feed_push(string $kind, string $title, string $body = '', string $refType = '', int $refId = 0): void
{
    $st = db()->prepare('INSERT INTO feed_events (kind, ref_type, ref_id, title, body, created_at) VALUES (?,?,?,?,?,?)');
    $st->execute([$kind, $refType, $refId, $title, $body, gmdate('c')]);
}

function feed_list(int $limit = 40): array
{
    $st = db()->prepare('SELECT * FROM feed_events ORDER BY id DESC LIMIT ?');
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}
