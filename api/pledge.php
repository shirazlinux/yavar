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
$amount = (int) ($in['amount'] ?? 0);
$active = !empty($in['active']) ? 1 : 0;
if (!in_array($type, ['hamyar', 'campaign'], true) || $id < 1) {
    app_json(['ok' => false, 'message' => 'هدف نامعتبر'], 422);
}
if ($type === 'hamyar') {
    $chk = db()->prepare("SELECT id FROM users WHERE id=? AND status='approved' AND is_admin=0");
    $chk->execute([$id]);
    if (!$chk->fetch()) {
        app_json(['ok' => false, 'message' => 'هدف نامعتبر'], 422);
    }
} else {
    $chk = db()->prepare("SELECT id FROM campaigns WHERE id=?");
    $chk->execute([$id]);
    if (!$chk->fetch()) {
        app_json(['ok' => false, 'message' => 'هدف نامعتبر'], 422);
    }
}
if ($amount < 0 || $amount > 500000000) {
    app_json(['ok' => false, 'message' => 'مبلغ نامعتبر'], 422);
}
$uid = (int) $user['id'];
$now = gmdate('c');
$st = db()->prepare('SELECT id FROM monthly_pledges WHERE user_id=? AND target_type=? AND target_id=?');
$st->execute([$uid, $type, $id]);
$row = $st->fetch();
if ($row) {
    db()->prepare('UPDATE monthly_pledges SET amount=?, active=?, updated_at=? WHERE id=?')
        ->execute([$amount, $active, $now, (int)$row['id']]);
} else {
    db()->prepare('INSERT INTO monthly_pledges (user_id, target_type, target_id, amount, active, updated_at) VALUES (?,?,?,?,?,?)')
        ->execute([$uid, $type, $id, $amount, $active, $now]);
}
app_json(['ok' => true, 'amount' => $amount, 'active' => (bool)$active, 'amount_fa' => money_fa($amount)]);
