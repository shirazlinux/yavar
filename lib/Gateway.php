<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/campaigns.php';
require_once __DIR__ . '/Notify.php';

final class Gateway
{
    public static function start(int $amount, string $name, string $message, string $cause, ?int $activistId = null, ?int $campaignId = null, bool $publicPost = false, bool $anonymous = false): array
    {
        $cfg = app_config();
        $gateway = $cfg['gateway'] ?? 'bank';

        $campaign = null;
        if ($campaignId) {
            campaign_refresh_status($campaignId);
            $st = db()->prepare('SELECT * FROM campaigns WHERE id=?');
            $st->execute([$campaignId]);
            $campaign = $st->fetch();
            if (!$campaign || $campaign['status'] !== 'active') {
                return ['ok' => false, 'message' => 'این کمپین فعال نیست یا مهلتش تمام شده است.'];
            }
            $activistId = (int) $campaign['user_id'];
            // fixed tiers: if campaign has tiers, amount must match one of them OR allow any if tiers empty
            $tiers = campaign_parse_tiers((string) $campaign['tiers_json']);
            if ($tiers && !in_array($amount, $tiers, true)) {
                return ['ok' => false, 'message' => 'برای این کمپین فقط مبالغ ثابت تعریف‌شده قابل پرداخت است.'];
            }
        }

        $act = null;
        if ($activistId) {
            $st = db()->prepare('SELECT id, display_name, status, card_number, sheba, phone, email FROM users WHERE id = ? AND is_admin = 0');
            $st->execute([$activistId]);
            $act = $st->fetch();
            if (!$act || $act['status'] !== 'approved') {
                return ['ok' => false, 'message' => 'این فعال تأیید نشده یا وجود ندارد.'];
            }
        }

        // اگر توکن پی‌پینگ واقعاً کار کند، آنلاین؛ وگرنه واریز کارت‌به‌کارت به خود فعال
        require_once __DIR__ . '/PayPing.php';
        $paypingReady = PayPing::accessToken() !== null;
        $hasBank = $act && user_has_settlement($act);
        if (in_array($gateway, ['payping', 'auto'], true) && $paypingReady) {
            $useGateway = 'payping';
        } elseif (in_array($gateway, ['zarinpal', 'idpay', 'payir'], true)) {
            $useGateway = $gateway;
        } else {
            $useGateway = 'bank';
        }
        // مسیر کارت‌به‌کارت بدون شبا/کارت ممکن نیست؛ درگاه آنلاین بدون آن OK (تسویه بعداً)
        if ($useGateway === 'bank' && $activistId && !$hasBank) {
            return ['ok' => false, 'message' => 'این فعال هنوز اطلاعات تسویه (کارت/شبا) ثبت نکرده است.'];
        }

        $authority = bin2hex(random_bytes(16));
        $refCode = 'DON-' . strtoupper(bin2hex(random_bytes(3)));
        $meta = [
            'amount'       => $amount,
            'name'         => $name,
            'message'      => $message,
            'cause'        => $cause,
            'gateway'      => $useGateway,
            'authority'    => $authority,
            'ref_code'     => $refCode,
            'activist_id'  => $activistId,
            'campaign_id'  => $campaignId,
            'public_post'  => $publicPost,
            'anonymous'    => $anonymous,
            'created'      => time(),
        ];
        self::storePending($authority, $meta);

        if ($activistId) {
            // donor_name برای پنل فعال نگه داشته می‌شود؛ is_anonymous فقط نمایش عمومی را عوض می‌کند
            $ins = db()->prepare('INSERT INTO donations (user_id, amount, donor_name, message, authority, ref_id, status, gateway, created_at, campaign_id, settlement_status, is_public_post, is_anonymous) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $ins->execute([
                $activistId, $amount, $name, $message, $authority, $refCode, 'pending', $useGateway, gmdate('c'), $campaignId, 'none', $publicPost ? 1 : 0, $anonymous ? 1 : 0,
            ]);
            $meta['donation_id'] = (int) db()->lastInsertId();
            self::storePending($authority, $meta);

            // SMS + ایمیل: حمایت جدید در صف
            Notify::donationPending(
                (string) ($act['phone'] ?? ''),
                (string) ($act['email'] ?? ''),
                (string) ($act['display_name'] ?? 'فعال'),
                $amount,
                $refCode,
                $name
            );
        }

        $descSuffix = '';
        if ($activistId && !empty($act['display_name'])) {
            $descSuffix = ' — ' . $act['display_name'];
        }

        if ($useGateway === 'payping') {
            $res = self::paypingRequest($amount, $name, $message, $authority, $descSuffix);
            if (!empty($res['ok'])) {
                return $res;
            }
            // اگر پی‌پینگ شکست خورد → کارت‌به‌کارت (فقط اگر شبا/کارت دارد)
            if (!$hasBank) {
                return ['ok' => false, 'message' => 'درگاه آنلاین موقتاً در دسترس نیست و این فعال هنوز کارت/شبا ثبت نکرده است.'];
            }
            $useGateway = 'bank';
            if (!empty($meta['donation_id'])) {
                db()->prepare('UPDATE donations SET gateway=? WHERE id=?')->execute(['bank', $meta['donation_id']]);
            }
        }
        if ($useGateway === 'zarinpal') {
            return self::zarinpalRequest($amount, $name, $message, $authority, $descSuffix);
        }
        if ($useGateway === 'idpay') {
            return self::idpayRequest($amount, $name, $message, $authority);
        }
        if ($useGateway === 'payir') {
            return self::payirRequest($amount, $name, $message, $authority);
        }

        // واریز مستقیم به کارت/شبا فعال — بدون کارمزد، بدون وابستگی به OAuth
        return [
            'ok'        => true,
            'mode'      => 'bank',
            'message'   => 'مبلغ را مستقیم به حساب خودِ فعال واریز کنید. پلتفرم کارمزدی نمی‌گیرد.',
            'authority' => $authority,
            'ref_code'  => $refCode,
            'amount'    => $amount,
            'bank'      => [
                'owner' => $act['display_name'] ?? '',
                'card'  => $act['card_number'] ?? '',
                'sheba' => $act['sheba'] ?? '',
                'note'  => 'در توضیح واریز حتماً کد پیگیری را بنویسید: ' . $refCode,
            ],
        ];
    }

    /**
     * علامت‌گذاری پرداخت — idempotent: فقط از pending به paid.
     * @return bool true اگر تازه paid شد
     */
    private static function markPaid(array $pending, string $ref): bool
    {
        $ref = mb_substr(trim($ref), 0, 120);
        $now = gmdate('c');
        $changed = false;

        // جلوگیری از replay با payment_ref تکراری
        if ($ref !== '') {
            $dup = db()->prepare("SELECT id FROM donations WHERE payment_ref = ? AND payment_ref <> '' AND status='paid' LIMIT 1");
            $dup->execute([$ref]);
            if ($dup->fetch()) {
                return false;
            }
        }

        if (!empty($pending['donation_id'])) {
            $st = db()->prepare(
                "UPDATE donations SET status='paid', ref_id=?, payment_ref=?, paid_at=?, settlement_status='ready'
                 WHERE id=? AND status='pending'"
            );
            $st->execute([$ref, $ref, $now, (int) $pending['donation_id']]);
            $changed = $st->rowCount() > 0;
        } elseif (!empty($pending['authority'])) {
            $st = db()->prepare(
                "UPDATE donations SET status='paid', ref_id=?, payment_ref=?, paid_at=?, settlement_status='ready'
                 WHERE authority=? AND status='pending'"
            );
            $st->execute([$ref, $ref, $now, $pending['authority']]);
            $changed = $st->rowCount() > 0;
        }

        if (!$changed) {
            return false;
        }

        app_log_donation(array_merge($pending, ['status' => 'paid', 'ref_id' => $ref]));
        if (!empty($pending['campaign_id'])) {
            campaign_refresh_status((int) $pending['campaign_id']);
        }

        // SMS + ایمیل به فعال — فقط بار اول
        if (!empty($pending['activist_id'])) {
            $st = db()->prepare('SELECT display_name, phone, email FROM users WHERE id = ?');
            $st->execute([(int) $pending['activist_id']]);
            $u = $st->fetch();
            if ($u) {
                Notify::donationPaid(
                    (string) ($u['phone'] ?? ''),
                    (string) ($u['email'] ?? ''),
                    (string) $u['display_name'],
                    (int) $pending['amount'],
                    $ref
                );
            }
            // اعلان تلگرام کانال/گروه (صف → worker خارج)
            try {
                require_once __DIR__ . '/Telegram.php';
                $anon = !empty($pending['anonymous']);
                $donor = (string) ($pending['name'] ?? '');
                $donId = (int) ($pending['donation_id'] ?? 0);
                Telegram::enqueueDonationPaid(
                    (int) $pending['activist_id'],
                    (int) $pending['amount'],
                    $ref,
                    $donId,
                    $anon,
                    $donor,
                    (string) ($pending['message'] ?? '')
                );
            } catch (Throwable $e) {
                error_log('Telegram enqueue: ' . $e->getMessage());
            }
        }
        return true;
    }

    public static function verify(string $authority, ?string $status = null, ?string $ref = null): array
    {
        $pending = self::loadPending($authority);
        if (!$pending) {
            // try load by authority without prefix stripping issues
            $pending = self::loadPending(preg_replace('/[^a-f0-9]/', '', $authority) ?? $authority);
        }
        if (!$pending) {
            return ['ok' => false, 'message' => 'تراکنش یافت نشد یا منقضی شده است.'];
        }
        $cfg = app_config();
        $gateway = $pending['gateway'] ?? $cfg['gateway'];

        if ($gateway === 'payping') {
            return self::paypingVerify($pending, $authority, $ref);
        }
        if ($gateway === 'zarinpal') {
            return self::zarinpalVerify($pending, $authority, $status);
        }
        if ($gateway === 'idpay') {
            return self::idpayVerify($pending, $authority, $status, $ref);
        }
        if ($gateway === 'payir') {
            return self::payirVerify($pending, $authority, $status);
        }

        return ['ok' => false, 'message' => 'تأیید برای حالت دستی از این مسیر ممکن نیست.'];
    }

    // ---------- PayPing ----------
    /**
     * تأیید بازگشت پی‌پینگ v3 (data JSON + paymentRefId/clientRefId).
     */
    public static function verifyPayPingReturn(
        string $clientRefOrAuth,
        ?string $paymentRefId,
        ?string $paymentCode = null,
        array $responseData = []
    ): array {
        $auth = preg_replace('/[^a-f0-9]/', '', $clientRefOrAuth) ?? '';
        $pending = $auth !== '' ? self::loadPending($auth) : null;

        // Fallback: find pending by stored paymentCode
        if (!$pending && $paymentCode) {
            $dir = dirname(__DIR__) . '/data/pending';
            if (is_dir($dir)) {
                foreach (glob($dir . '/*.json') ?: [] as $file) {
                    $meta = json_decode((string) file_get_contents($file), true);
                    if (is_array($meta) && ($meta['payping_code'] ?? '') === $paymentCode) {
                        $pending = $meta;
                        $auth = (string) ($meta['authority'] ?? basename($file, '.json'));
                        break;
                    }
                }
            }
        }

        if (!$pending) {
            return [
                'ok' => false,
                'message' => 'تراکنش یافت نشد یا منقضی شده است. اگر مبلغ از حساب کم شده، با پشتیبانی تماس بگیرید و کد پیگیری درگاه را بفرستید.',
            ];
        }

        // Prefer paymentCode from return payload when present
        if ($paymentCode) {
            $pending['payping_code'] = $paymentCode;
        }
        // If return payload has amount, soft-check
        if (isset($responseData['amount']) && (int) $responseData['amount'] > 0
            && (int) $responseData['amount'] !== (int) $pending['amount']) {
            return [
                'ok' => false,
                'message' => sprintf(
                    'مبلغ برگشتی (%s) با مبلغ سفارش (%s) یکسان نیست.',
                    number_format((int) $responseData['amount']),
                    number_format((int) $pending['amount'])
                ),
            ];
        }

        return self::paypingVerify($pending, $auth !== '' ? $auth : (string) $pending['authority'], $paymentRefId);
    }

    private static function paypingRequest(int $amount, string $name, string $message, string $localAuth, string $descSuffix = ''): array
    {
        require_once __DIR__ . '/PayPing.php';
        $cfg = app_config();
        $callback = rtrim($cfg['site_url'], '/') . '/api/callback.php?g=payping&la=' . urlencode($localAuth);
        $desc = ($cfg['payment_description'] ?? 'حمایت') . $descSuffix;
        $res = PayPing::createPayment($amount, $callback, $desc, $localAuth, $name !== '' ? $name : 'حامی');
        if (empty($res['ok'])) {
            return ['ok' => false, 'message' => $res['message'] ?? 'خطای پی‌پینگ', 'need_token' => !empty($res['need_token'])];
        }
        $pending = self::loadPending($localAuth);
        if ($pending) {
            $pending['payping_code'] = $res['code'];
            self::storePending($localAuth, $pending);
            if (!empty($meta['donation_id'] ?? $pending['donation_id'])) {
                // keep payment code for verify
            }
        }
        return [
            'ok' => true,
            'mode' => 'redirect',
            'redirect' => $res['redirect'],
            'authority' => $localAuth,
        ];
    }

    private static function paypingVerify(array $pending, string $localAuth, ?string $refId): array
    {
        require_once __DIR__ . '/PayPing.php';
        if ($refId === null || $refId === '') {
            return [
                'ok' => false,
                'message' => 'شناسه پرداخت از درگاه برنگشت (paymentRefId). اگر پول کم شده، کد پیگیری بانکی را به پشتیبانی بدهید.',
            ];
        }
        $payCode = (string) ($pending['payping_code'] ?? '');
        $res = PayPing::verify((int) $pending['amount'], $refId, $payCode);
        if (!empty($res['ok'])) {
            $ref = (string) ($res['ref_id'] ?? $refId);
            self::markPaid($pending, $ref);
            self::clearPending($localAuth);
            return [
                'ok' => true,
                'ref_id' => $ref,
                'amount' => $pending['amount'],
                'message' => 'پرداخت با موفقیت تأیید شد.',
            ];
        }
        return ['ok' => false, 'message' => $res['message'] ?? 'تأیید ناموفق'];
    }

    private static function pendingPath(string $authority): string
    {
        $dir = dirname(__DIR__) . '/data/pending';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return $dir . '/' . preg_replace('/[^a-f0-9]/', '', $authority) . '.json';
    }

    private static function storePending(string $authority, array $meta): void
    {
        file_put_contents(self::pendingPath($authority), json_encode($meta, JSON_UNESCAPED_UNICODE));
    }

    private static function loadPending(string $authority): ?array
    {
        $path = self::pendingPath($authority);
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            return null;
        }
        // انقضای pending (۴۸ ساعت) — جلوگیری از تأیید قدیمی/replay
        $created = (int) ($data['created'] ?? 0);
        if ($created > 0 && (time() - $created) > 172800) {
            @unlink($path);
            return null;
        }
        return $data;
    }

    private static function clearPending(string $authority): void
    {
        $path = self::pendingPath($authority);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    // ---------- Zarinpal ----------
    private static function zarinpalRequest(int $amount, string $name, string $message, string $localAuth, string $descSuffix = ''): array
    {
        $cfg = app_config();
        $merchant = trim((string) ($cfg['zarinpal_merchant_id'] ?? ''));
        if ($merchant === '' || str_contains($merchant, 'XXXX')) {
            return ['ok' => false, 'message' => 'مرچنت‌کد زرین‌پال تنظیم نشده است.'];
        }
        $sandbox = !empty($cfg['zarinpal_sandbox']);
        $base = $sandbox
            ? 'https://sandbox.zarinpal.com/pg/v4/payment'
            : 'https://api.zarinpal.com/pg/v4/payment';
        $callback = rtrim($cfg['site_url'], '/') . '/api/callback.php?g=zarinpal&la=' . urlencode($localAuth);
        $desc = $cfg['payment_description'] . $descSuffix;
        if ($name !== '') {
            $desc .= ' — ' . $name;
        }

        // amount for Zarinpal v4 is Rial
        $payload = [
            'merchant_id'  => $merchant,
            'amount'       => $amount * 10,
            'callback_url' => $callback,
            'description'  => mb_substr($desc, 0, 250),
            'metadata'     => array_filter([
                'email' => null,
                'mobile'=> null,
                'order_id' => $localAuth,
            ]),
        ];
        $res = app_http_json($base . '/request.json', $payload);
        $data = $res['data']['data'] ?? null;
        $code = $data['code'] ?? ($res['data']['errors']['code'] ?? null);
        if (!$res['ok'] || !is_array($data) || ($data['code'] ?? 0) !== 100) {
            $msg = $res['data']['errors']['message'] ?? 'خطا در اتصال به زرین‌پال';
            return ['ok' => false, 'message' => $msg, 'debug' => $sandbox ? $res : null];
        }
        $authority = $data['authority'];
        // map gateway authority to our pending
        $pending = self::loadPending($localAuth);
        if ($pending) {
            $pending['zp_authority'] = $authority;
            self::storePending($localAuth, $pending);
            // also store under zp authority for callback convenience
            self::storePending('zp_' . $authority, $pending);
        }
        $start = $sandbox
            ? 'https://sandbox.zarinpal.com/pg/StartPay/' . $authority
            : 'https://www.zarinpal.com/pg/StartPay/' . $authority;
        return ['ok' => true, 'mode' => 'redirect', 'redirect' => $start, 'authority' => $localAuth];
    }

    private static function zarinpalVerify(array $pending, string $localAuth, ?string $status): array
    {
        $cfg = app_config();
        if (strtoupper((string) $status) !== 'OK') {
            self::clearPending($localAuth);
            return ['ok' => false, 'message' => 'پرداخت توسط کاربر لغو شد یا ناموفق بود.'];
        }
        $authority = $pending['zp_authority'] ?? '';
        if ($authority === '') {
            return ['ok' => false, 'message' => 'شناسه درگاه نامعتبر است.'];
        }
        $sandbox = !empty($cfg['zarinpal_sandbox']);
        $base = $sandbox
            ? 'https://sandbox.zarinpal.com/pg/v4/payment'
            : 'https://api.zarinpal.com/pg/v4/payment';
        $payload = [
            'merchant_id' => $cfg['zarinpal_merchant_id'],
            'amount'      => ((int) $pending['amount']) * 10,
            'authority'   => $authority,
        ];
        $res = app_http_json($base . '/verify.json', $payload);
        $data = $res['data']['data'] ?? null;
        $code = is_array($data) ? ($data['code'] ?? 0) : 0;
        if ($code === 100 || $code === 101) {
            $ref = (string) ($data['ref_id'] ?? '');
            self::markPaid($pending, $ref);
            self::clearPending($localAuth);
            if (!empty($pending['zp_authority'])) {
                self::clearPending('zp_' . $pending['zp_authority']);
            }
            return ['ok' => true, 'ref_id' => $ref, 'amount' => $pending['amount'], 'message' => 'پرداخت با موفقیت تأیید شد.'];
        }
        return ['ok' => false, 'message' => 'تأیید پرداخت ناموفق بود.', 'debug' => $sandbox ? $res : null];
    }

    // ---------- IDPay ----------
    private static function idpayRequest(int $amount, string $name, string $message, string $localAuth): array
    {
        $cfg = app_config();
        $key = trim((string) ($cfg['idpay_api_key'] ?? ''));
        if ($key === '') {
            return ['ok' => false, 'message' => 'API Key آیدی‌پی تنظیم نشده است.'];
        }
        $callback = rtrim($cfg['site_url'], '/') . '/api/callback.php?g=idpay&la=' . urlencode($localAuth);
        $headers = ['X-API-KEY: ' . $key];
        if (!empty($cfg['idpay_sandbox'])) {
            $headers[] = 'X-SANDBOX: 1';
        }
        $payload = [
            'order_id' => $localAuth,
            'amount'   => $amount * 10, // Rial
            'callback' => $callback,
            'name'     => $name ?: 'حامی ناشناس',
            'desc'     => mb_substr($cfg['payment_description'] . ($message ? ' — ' . $message : ''), 0, 250),
        ];
        $res = app_http_json('https://api.idpay.ir/v1.1/payment', $payload, $headers);
        $data = $res['data'] ?? null;
        if (!$res['ok'] || !is_array($data) || empty($data['link'])) {
            $msg = is_array($data) ? ($data['error_message'] ?? json_encode($data, JSON_UNESCAPED_UNICODE)) : 'خطا در آیدی‌پی';
            return ['ok' => false, 'message' => $msg];
        }
        $pending = self::loadPending($localAuth);
        if ($pending) {
            $pending['idpay_id'] = $data['id'] ?? '';
            self::storePending($localAuth, $pending);
        }
        return ['ok' => true, 'mode' => 'redirect', 'redirect' => $data['link'], 'authority' => $localAuth];
    }

    private static function idpayVerify(array $pending, string $localAuth, ?string $status, ?string $trackId): array
    {
        $cfg = app_config();
        $key = $cfg['idpay_api_key'];
        $headers = ['X-API-KEY: ' . $key];
        if (!empty($cfg['idpay_sandbox'])) {
            $headers[] = 'X-SANDBOX: 1';
        }
        $payload = [
            'id'       => $pending['idpay_id'] ?? $trackId,
            'order_id' => $localAuth,
        ];
        $res = app_http_json('https://api.idpay.ir/v1.1/payment/verify', $payload, $headers);
        $data = $res['data'] ?? null;
        $st = is_array($data) ? (int) ($data['status'] ?? 0) : 0;
        if ($st === 100 || $st === 101) {
            $ref = (string) ($data['track_id'] ?? $data['payment']['track_id'] ?? '');
            self::markPaid($pending, $ref);
            self::clearPending($localAuth);
            return ['ok' => true, 'ref_id' => $ref, 'amount' => $pending['amount'], 'message' => 'پرداخت با موفقیت تأیید شد.'];
        }
        return ['ok' => false, 'message' => 'تأیید پرداخت آیدی‌پی ناموفق بود.'];
    }

    // ---------- Pay.ir ----------
    private static function payirRequest(int $amount, string $name, string $message, string $localAuth): array
    {
        $cfg = app_config();
        $key = trim((string) ($cfg['payir_api_key'] ?? ''));
        if ($key === '') {
            return ['ok' => false, 'message' => 'API Key پی‌آیر تنظیم نشده است.'];
        }
        $callback = rtrim($cfg['site_url'], '/') . '/api/callback.php?g=payir&la=' . urlencode($localAuth);
        $payload = [
            'api'          => $key,
            'amount'       => $amount * 10,
            'redirect'     => $callback,
            'factorNumber' => $localAuth,
            'description'  => mb_substr($cfg['payment_description'], 0, 250),
        ];
        $res = app_http_json('https://pay.ir/pg/send', $payload);
        $data = $res['data'] ?? null;
        if (!is_array($data) || (int) ($data['status'] ?? 0) !== 1) {
            return ['ok' => false, 'message' => $data['errorMessage'] ?? 'خطا در پی‌آیر'];
        }
        $token = $data['token'];
        $pending = self::loadPending($localAuth);
        if ($pending) {
            $pending['payir_token'] = $token;
            self::storePending($localAuth, $pending);
        }
        return ['ok' => true, 'mode' => 'redirect', 'redirect' => 'https://pay.ir/pg/' . $token, 'authority' => $localAuth];
    }

    private static function payirVerify(array $pending, string $localAuth, ?string $status): array
    {
        $cfg = app_config();
        $token = $pending['payir_token'] ?? '';
        $payload = [
            'api'   => $cfg['payir_api_key'],
            'token' => $token,
        ];
        $res = app_http_json('https://pay.ir/pg/verify', $payload);
        $data = $res['data'] ?? null;
        if (is_array($data) && (int) ($data['status'] ?? 0) === 1) {
            $ref = (string) ($data['transId'] ?? '');
            self::markPaid($pending, $ref);
            self::clearPending($localAuth);
            return ['ok' => true, 'ref_id' => $ref, 'amount' => $pending['amount'], 'message' => 'پرداخت با موفقیت تأیید شد.'];
        }
        return ['ok' => false, 'message' => 'تأیید پرداخت پی‌آیر ناموفق بود.'];
    }
}
