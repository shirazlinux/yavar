<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
$user = auth_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json(['ok' => false, 'message' => 'POST only'], 405);
}
$raw = file_get_contents('php://input') ?: '';
$in = json_decode($raw, true) ?: $_POST;
if (!csrf_verify($in['csrf'] ?? null)) {
    app_json(['ok' => false, 'message' => 'csrf'], 403);
}
$type = (string) ($in['target_type'] ?? '');
$id = (int) ($in['target_id'] ?? 0);
if (!in_array($type, ['hamyar', 'campaign'], true) || $id < 1) {
    app_json(['ok' => false, 'message' => 'هدف نامعتبر'], 422);
}
// وجود هدف
if ($type === 'hamyar') {
    $chk = db()->prepare("SELECT id FROM users WHERE id=? AND status='approved' AND is_admin=0");
    $chk->execute([$id]);
    if (!$chk->fetch()) {
        app_json(['ok' => false, 'message' => 'هدف نامعتبر'], 422);
    }
} else {
    $chk = db()->prepare("SELECT id FROM campaigns WHERE id=? AND status IN ('active','successful')");
    $chk->execute([$id]);
    if (!$chk->fetch()) {
        app_json(['ok' => false, 'message' => 'هدف نامعتبر'], 422);
    }
}
$uid = (int) $user['id'];
$st = db()->prepare('SELECT id FROM supporter_likes WHERE user_id=? AND target_type=? AND target_id=?');
$st->execute([$uid, $type, $id]);
$ex = $st->fetch();
if ($ex) {
    db()->prepare('DELETE FROM supporter_likes WHERE id=?')->execute([(int)$ex['id']]);
    app_json(['ok' => true, 'liked' => false]);
}
db()->prepare('INSERT INTO supporter_likes (user_id, target_type, target_id, created_at) VALUES (?,?,?,?)')
    ->execute([$uid, $type, $id, gmdate('c')]);
app_json(['ok' => true, 'liked' => true]);
