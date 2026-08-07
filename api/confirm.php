<?php
declare(strict_types=1);
/**
 * فعال یا ادمین: تأیید دریافت واریز کارت‌به‌کارت
 */
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/Gateway.php';
require_once dirname(__DIR__) . '/lib/Notify.php';

$user = auth_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf'] ?? null)) {
    header('Location: /dashboard/');
    exit;
}

$id = (int) ($_POST['donation_id'] ?? 0);
$action = $_POST['action'] ?? 'confirm';

$st = db()->prepare('SELECT * FROM donations WHERE id = ?');
$st->execute([$id]);
$d = $st->fetch();
if (!$d) {
    header('Location: /dashboard/?err=notfound');
    exit;
}

$isOwner = (int) $d['user_id'] === (int) $user['id'];
$isAdmin = !empty($user['is_admin']);
if (!$isOwner && !$isAdmin) {
    http_response_code(403);
    exit('forbidden');
}

if ($action === 'confirm' && $d['status'] === 'pending') {
    $ref = $d['ref_id'] !== '' ? $d['ref_id'] : ('OK-' . $id);
    // ادعای دریافت توسط فعال → paid ولی settlement فقط برای ادمین «ready» می‌شود
    // فعال: claim_received (paid + settlement none) تا ادمین بررسی کند
    // ادمین: مستقیم ready
    if ($isAdmin) {
        $st = db()->prepare(
            "UPDATE donations SET status='paid', paid_at=?, ref_id=?, settlement_status='ready'
             WHERE id=? AND status='pending'"
        );
        $st->execute([gmdate('c'), $ref, $id]);
        $ready = true;
    } else {
        // مالک: paid ولی در صف بررسی ادمین (نه ready خودکار)
        $st = db()->prepare(
            "UPDATE donations SET status='paid', paid_at=?, ref_id=?, settlement_status='none'
             WHERE id=? AND status='pending'"
        );
        $st->execute([gmdate('c'), $ref, $id]);
        $ready = false;
        Notify::admin(
            'ادعای دریافت واریز (نیاز بررسی)',
            "فعال ادعای دریافت واریز کارت‌به‌کارت کرده است.\n"
            . "donation_id: {$id}\nمبلغ: " . number_format((int) $d['amount']) . " تومان\nکد: {$ref}\n"
            . "user_id: {$d['user_id']}\n\nhttps://donate.sudoshz.ir/admin/settlements.php"
        );
    }

    if ($st->rowCount() > 0) {
        $st = db()->prepare('SELECT display_name, phone, email FROM users WHERE id = ?');
        $st->execute([(int) $d['user_id']]);
        $u = $st->fetch();
        if ($u && $ready) {
            Notify::donationPaid(
                (string) ($u['phone'] ?? ''),
                (string) ($u['email'] ?? ''),
                (string) $u['display_name'],
                (int) $d['amount'],
                $ref
            );
        }
        // تلگرام: بعد از paid (ادعای فعال یا تأیید ادمین) — تشویق مخاطب
        try {
            require_once dirname(__DIR__) . '/lib/Telegram.php';
            Telegram::enqueueDonationPaid(
                (int) $d['user_id'],
                (int) $d['amount'],
                $ref,
                (int) $id,
                !empty($d['is_anonymous']),
                (string) ($d['donor_name'] ?? ''),
                (string) ($d['message'] ?? '')
            );
        } catch (Throwable $e) {
            error_log('Telegram enqueue confirm: ' . $e->getMessage());
        }
    }
}

if ($action === 'reject' && $d['status'] === 'pending') {
    $st = db()->prepare('UPDATE donations SET status=? WHERE id=?');
    $st->execute(['failed', $id]);
}

if (!empty($user['is_admin']) && !$isOwner) {
    $redir = '/admin/settlements.php?s=ready';
} else {
    $redir = '/dashboard/?ok=1';
}
header('Location: ' . $redir);
exit;
