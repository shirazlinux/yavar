<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Gateway.php';

/**
 * بازگشت از پی‌پینگ v3 — مطابق افزونه رسمی ووکامرس:
 * پارامترها داخل ?data={json} و status جدا می‌آیند.
 * (نه paymentRefId مستقیم در query string)
 */
$cfg = app_config();

// لاگ تشخیصی (بدون توکن)
$logDir = dirname(__DIR__) . '/data';
$logLine = date('c') . ' ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? '') . "\n"
    . 'GET=' . json_encode($_GET, JSON_UNESCAPED_UNICODE) . "\n"
    . 'POST_keys=' . json_encode(array_keys($_POST), JSON_UNESCAPED_UNICODE) . "\n";
@file_put_contents($logDir . '/callback.log', $logLine . "\n", FILE_APPEND | LOCK_EX);

$g = $_GET['g'] ?? $_POST['g'] ?? $_REQUEST['g'] ?? 'payping';

// PayPing v3: data is URL-encoded JSON
$rawData = $_REQUEST['data'] ?? $_GET['data'] ?? $_POST['data'] ?? '';
if (is_array($rawData)) {
    $responseData = $rawData;
} else {
    $rawData = is_string($rawData) ? rawurldecode($rawData) : '';
    // sometimes double-encoded
    $responseData = json_decode($rawData, true);
    if (!is_array($responseData) && $rawData !== '') {
        $responseData = json_decode(urldecode($rawData), true);
    }
    if (!is_array($responseData)) {
        $responseData = [];
    }
}

// Also accept flat params (fallback / older gateways)
$pick = static function (array $sources, array $keys) {
    foreach ($sources as $src) {
        if (!is_array($src)) {
            continue;
        }
        foreach ($keys as $k) {
            if (isset($src[$k]) && $src[$k] !== '' && $src[$k] !== null) {
                return $src[$k];
            }
        }
    }
    return null;
};

$sources = [$responseData, $_GET, $_POST, $_REQUEST];

if ($g === 'payping' || $g === '' || $g === null) {
    $status = $pick($sources, ['status', 'Status']);
    $status = $status !== null ? (int) $status : null;

    // status=0 → کاربر انصراف داده
    if ($status === 0) {
        $qs = http_build_query([
            'ok'  => '0',
            'ref' => '',
            'amount' => '',
            'msg' => 'پرداخت توسط شما لغو شد.',
        ]);
        header('Location: ' . rtrim($cfg['site_url'], '/') . '/result.php?' . $qs);
        exit;
    }

    $ref = $pick($sources, [
        'paymentRefId', 'PaymentRefId', 'refid', 'refId', 'RefId', 'ref_id',
    ]);
    $client = $pick($sources, [
        'la', 'clientRefId', 'ClientRefId', 'clientrefid', 'client_ref_id',
    ]);
    $paymentCode = $pick($sources, [
        'paymentCode', 'PaymentCode', 'code',
    ]);

    // authority/local id
    $la = (string) ($client ?? '');

    $result = Gateway::verifyPayPingReturn(
        $la,
        $ref !== null ? (string) $ref : null,
        $paymentCode !== null ? (string) $paymentCode : null,
        $responseData
    );
} elseif ($g === 'zarinpal') {
    $status = $_GET['Status'] ?? $_GET['status'] ?? null;
    $authority = $_GET['Authority'] ?? '';
    $la = $_GET['la'] ?? '';
    $local = $la !== '' ? $la : ($authority !== '' ? 'zp_' . $authority : '');
    $result = Gateway::verify((string) $local, $status, $authority);
} else {
    $result = ['ok' => false, 'message' => 'درگاه نامعتبر'];
}

$qs = http_build_query([
    'ok'     => !empty($result['ok']) ? '1' : '0',
    'ref'    => $result['ref_id'] ?? '',
    'amount' => $result['amount'] ?? '',
    'msg'    => $result['message'] ?? '',
]);
header('Location: ' . rtrim($cfg['site_url'], '/') . '/result.php?' . $qs);
exit;
