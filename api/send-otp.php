<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/Sms.php';
require_once dirname(__DIR__) . '/lib/Captcha.php';
require_once dirname(__DIR__) . '/lib/RateLimit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json(['ok' => false, 'message' => 'Method not allowed'], 405);
}
auth_start_session();
$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}
if (!csrf_verify($input['csrf'] ?? null)) {
    security_log('otp_csrf_fail', '');
    app_json(['ok' => false, 'message' => 'نشست منقضی شد'], 403);
}

// کپچا: اگر قبلاً در نشست پاس شده، دوباره نمی‌خواهیم (رفتار استاندارد)
if (!Captcha::check($input['captcha'] ?? null)) {
    $capNew = Captcha::generate();
    app_json([
        'ok' => false,
        'message' => 'پاسخ کپچا نادرست است. عدد را انگلیسی وارد کنید (مثلاً 12).',
        'captcha_required' => true,
        'captcha_question' => $capNew['question'],
        'captcha_hint' => $capNew['hint'] ?? '',
    ], 422);
}

$phone = Sms::normalizeMobile((string) ($input['phone'] ?? ''));
if (!Sms::validMobile($phone)) {
    app_json(['ok' => false, 'message' => 'شماره موبایل معتبر نیست', 'captcha_passed' => true], 422);
}

// Rate limit: IP (۸ در ساعت) + شماره (۵ در ساعت) + فاصلهٔ نشست (۶۰ث)
$rlIp = RateLimit::hit('otp_ip', 8, 3600);
if (!$rlIp['ok']) {
    security_log('otp_rate_ip', $phone);
    app_json([
        'ok' => false,
        'message' => 'تعداد درخواست پیامک زیاد است. ' . fa_digits((string) $rlIp['retry_after']) . ' ثانیه بعد تلاش کنید.',
        'captcha_passed' => true,
    ], 429);
}
$rlPhone = RateLimit::hitKey('otp_ph:' . $phone, 5, 3600);
if (!$rlPhone['ok']) {
    security_log('otp_rate_phone', $phone);
    app_json([
        'ok' => false,
        'message' => 'برای این شماره پیامک زیاد ارسال شده. یک ساعت بعد دوباره تلاش کنید.',
        'captcha_passed' => true,
    ], 429);
}
$last = (int) ($_SESSION['otp_last'] ?? 0);
if ($last && time() - $last < 60) {
    app_json(['ok' => false, 'message' => 'لطفاً یک دقیقه صبر کنید و دوباره درخواست دهید.', 'captcha_passed' => true], 429);
}

$code = (string) random_int(100000, 999999);
// فقط hash در نشست — کد plaintext نگهداری نمی‌شود
$_SESSION['otp_phone'] = $phone;
$_SESSION['otp_hash'] = hash_hmac('sha256', $code, (string) session_id());
$_SESSION['otp_exp'] = time() + 300;
$_SESSION['otp_last'] = time();
$_SESSION['otp_attempts'] = 0;
unset($_SESSION['otp_code'], $_SESSION['otp_ok']);

$res = Sms::sendOtp($phone, $code);
// پاک‌سازی فوری از حافظهٔ محلی
$code = '';
if (empty($res['ok'])) {
    app_json(['ok' => false, 'message' => 'ارسال پیامک ناموفق: ' . ($res['message'] ?? ''), 'captcha_passed' => true], 502);
}
app_json([
    'ok' => true,
    'message' => 'کد ۶ رقمی ارسال شد.',
    'captcha_passed' => true,
]);
