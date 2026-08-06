<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Gateway.php';
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/auth.php';
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

// CSRF — body یا هدر
$csrf = $input['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (!csrf_verify(is_string($csrf) ? $csrf : null)) {
    security_log('start_csrf_fail', '');
    app_json(['ok' => false, 'message' => 'نشست نامعتبر است. صفحه را تازه کنید.'], 403);
}

// Rate limit: ۲۰ درخواست در ۱۰ دقیقه per IP
$rl = RateLimit::hit('pay_start', 20, 600);
if (!$rl['ok']) {
    security_log('start_rate_limit', 'retry=' . $rl['retry_after']);
    app_json([
        'ok' => false,
        'message' => 'تعداد درخواست‌ها زیاد است. ' . fa_digits((string) $rl['retry_after']) . ' ثانیه بعد دوباره تلاش کنید.',
    ], 429);
}

$amount = (int) ($input['amount'] ?? 0);
$name = app_sanitize_name($input['name'] ?? '');
$message = app_sanitize_message($input['message'] ?? '');
$cause = app_sanitize_name($input['cause'] ?? 'activists');
$activistId = isset($input['activist_id']) ? (int) $input['activist_id'] : null;
$campaignId = isset($input['campaign_id']) ? (int) $input['campaign_id'] : null;
if ($activistId === 0) {
    $activistId = null;
}
if ($campaignId === 0) {
    $campaignId = null;
}

$allowedCauses = ['activists', 'events', 'infrastructure', 'general', 'campaign'];
if (!in_array($cause, $allowedCauses, true)) {
    $cause = 'general';
}

if (!app_amount_valid($amount)) {
    $cfg = app_config();
    app_json([
        'ok' => false,
        'message' => sprintf(
            'مبلغ باید بین %s و %s %s باشد.',
            number_format($cfg['min_amount']),
            number_format($cfg['max_amount']),
            $cfg['currency_label']
        ),
    ], 422);
}

if (!$activistId && !$campaignId) {
    app_json([
        'ok' => false,
        'message' => 'لطفاً از صفحهٔ یک فعال یا یک کمپین حمایت کنید.',
    ], 422);
}

// honeypot
if (!empty($input['website'])) {
    app_json(['ok' => true, 'mode' => 'manual', 'message' => 'OK']);
}

$publicPost = !empty($input['public_post']);
$anonymous = !empty($input['anonymous']);

$result = Gateway::start($amount, $name, $message, $cause, $activistId, $campaignId, $publicPost, $anonymous);
app_json($result, !empty($result['ok']) ? 200 : 400);
