<?php
declare(strict_types=1);
/**
 * حامی اعلام می‌کند واریز کارت‌به‌کارت انجام شد (اختیاری — با کد پیگیری).
 * وضعیت را paid نمی‌کند؛ فقط رسید را ثبت و به فعال/ادمین اطلاع می‌دهد.
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/Sms.php';
require_once dirname(__DIR__) . '/lib/RateLimit.php';
require_once dirname(__DIR__) . '/lib/Notify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json(['ok' => false, 'message' => 'Method not allowed'], 405);
}

auth_start_session();
$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$csrf = $input['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (!csrf_verify(is_string($csrf) ? $csrf : null)) {
    security_log('report_csrf_fail', '');
    app_json(['ok' => false, 'message' => 'نشست نامعتبر است. صفحه را تازه کنید.'], 403);
}

$rl = RateLimit::hit('report_xfer', 10, 600);
if (!$rl['ok']) {
    security_log('report_rate_limit', '');
    app_json([
        'ok' => false,
        'message' => 'تعداد اعلام زیاد است. ' . fa_digits((string) $rl['retry_after']) . ' ثانیه بعد تلاش کنید.',
    ], 429);
}

$authority = preg_replace('/[^a-f0-9]/', '', (string) ($input['authority'] ?? '')) ?? '';
$track = trim((string) ($input['track'] ?? ''));
$track = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $track) ?? '', 0, 80);

if (strlen($authority) < 16) {
    app_json(['ok' => false, 'message' => 'شناسه نامعتبر'], 422);
}

$st = db()->prepare(
    'SELECT d.*, u.phone, u.email, u.display_name
     FROM donations d JOIN users u ON u.id=d.user_id
     WHERE d.authority=? LIMIT 1'
);
$st->execute([$authority]);
$d = $st->fetch();
if (!$d) {
    app_json(['ok' => false, 'message' => 'تراکنش یافت نشد'], 404);
}

if (($d['status'] ?? '') !== 'pending') {
    app_json(['ok' => true, 'message' => 'این تراکنش قبلاً بررسی شده است.']);
}

// جلوگیری از spam SMS: فقط یک‌بار اعلام فعال
if (str_contains((string) $d['message'], '[رسید:') || str_contains((string) $d['message'], '[اعلام واریز]')) {
    app_json(['ok' => true, 'message' => 'اعلام قبلی شما ثبت شده است. منتظر تأیید فعال بمانید.']);
}

$msg = trim((string) $d['message']);
if ($track !== '') {
    $msg = trim($msg . "\n[رسید: {$track}]");
} else {
    $msg = trim($msg . "\n[اعلام واریز]");
}
db()->prepare('UPDATE donations SET message=? WHERE id=? AND status=?')->execute([$msg, $d['id'], 'pending']);

$ref = (string) ($d['ref_id'] ?: '-');
$amt = number_format((int) $d['amount']);
if (!empty($d['phone'])) {
    $sms = "حامی اعلام کرد واریز انجام شده\nمبلغ: {$amt} تومان\nکد: {$ref}"
        . ($track !== '' ? "\nپیگیری بانک: {$track}" : '')
        . "\nدر پنل تأیید کنید.";
    try {
        Sms::send((string) $d['phone'], $sms);
    } catch (Throwable $e) {
        error_log('report-transfer sms: ' . $e->getMessage());
    }
}
try {
    Notify::admin(
        'اعلام واریز کارت‌به‌کارت توسط حامی',
        "donation_id: {$d['id']}\nفعال: {$d['display_name']}\nمبلغ: {$amt} تومان\nکد: {$ref}\n"
        . ($track !== '' ? "پیگیری: {$track}\n" : '')
        . "https://donate.sudoshz.ir/admin/settlements.php"
    );
} catch (Throwable $e) {
    // ignore
}

security_log('report_transfer', 'id=' . $d['id']);
app_json([
    'ok' => true,
    'message' => 'اعلام شما ثبت شد. پس از تأیید فعال، وضعیت به «پرداخت‌شده» تغییر می‌کند.',
]);
