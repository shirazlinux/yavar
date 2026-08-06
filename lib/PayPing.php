<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * پی‌پینگ — همان روش افزونه رسمی ووکامرس:
 * API v3 + Authorization: Bearer {TOKEN}
 *
 * Client ID / Secret برای افزونه‌ها استفاده نمی‌شود.
 * فقط «توکن» از پنل پی‌پینگ (مثل فیلد paypingToken در ووکامرس).
 *
 * @see woo-payping-gateway class-wc-gateway-payping.php
 */
final class PayPing
{
    private const BASE = 'https://api.payping.ir/v3';

    public static function tokenFile(): string
    {
        return dirname(__DIR__) . '/data/payping_oauth.json';
    }

    public static function directToken(): string
    {
        $cfg = require dirname(__DIR__) . '/config.php';
        $sp = dirname(__DIR__) . '/data/settings.json';
        if (is_file($sp)) {
            $over = json_decode((string) file_get_contents($sp), true);
            if (is_array($over) && !empty($over['payping_token'])) {
                return trim((string) $over['payping_token']);
            }
        }
        return trim((string) ($cfg['payping_token'] ?? ''));
    }

    public static function accessToken(): ?string
    {
        $t = self::directToken();
        return $t !== '' ? $t : null;
    }

    public static function isConnected(): bool
    {
        return self::accessToken() !== null;
    }

    /** سازگاری با UI قدیمی OAuth — دیگر لازم نیست */
    public static function redirectUri(): string
    {
        $cfg = require dirname(__DIR__) . '/config.php';
        return rtrim((string) $cfg['site_url'], '/') . '/payping-callback.php';
    }

    public static function loadTokens(): ?array
    {
        return null;
    }

    /**
     * ساخت پرداخت — مطابق افزونه ووکامرس v3
     */
    public static function createPayment(
        int $amountToman,
        string $returnUrl,
        string $description,
        string $clientRefId,
        string $payerName = '',
        string $payerIdentity = ''
    ): array {
        $token = self::accessToken();
        if (!$token) {
            return [
                'ok' => false,
                'message' => 'توکن پی‌پینگ تنظیم نشده. مثل افزونه ووکامرس فقط «توکن» لازم است (نه Client ID).',
                'need_token' => true,
            ];
        }

        $data = [
            'PayerName'     => $payerName !== '' ? mb_substr($payerName, 0, 50) : 'حامی',
            'Amount'        => $amountToman,
            'PayerIdentity' => $payerIdentity,
            'ReturnUrl'     => $returnUrl,
            'Description'   => mb_substr($description, 0, 250),
            'ClientRefId'   => $clientRefId,
            'NationalCode'  => '',
        ];

        $res = self::httpPost(self::BASE . '/pay', $data, $token);
        if (!$res['ok']) {
            return $res;
        }
        $body = $res['data'];
        $paymentCode = null;
        if (is_array($body)) {
            $paymentCode = $body['paymentCode'] ?? $body['code'] ?? null;
        }
        if (!$paymentCode) {
            return [
                'ok' => false,
                'message' => 'پاسخ پی‌پینگ بدون paymentCode: ' . mb_substr((string) $res['raw'], 0, 200),
                'http' => $res['http'],
            ];
        }
        return [
            'ok' => true,
            'code' => (string) $paymentCode,
            'redirect' => self::BASE . '/pay/start/' . rawurlencode((string) $paymentCode),
        ];
    }

    /**
     * تأیید پرداخت v3
     */
    public static function verify(int $amountToman, $paymentRefId, string $paymentCode = ''): array
    {
        $token = self::accessToken();
        if (!$token) {
            return ['ok' => false, 'message' => 'توکن پی‌پینگ موجود نیست'];
        }
        if ($paymentRefId === null || $paymentRefId === '') {
            return ['ok' => false, 'message' => 'شناسه پرداخت برگشتی خالی است'];
        }

        // PaymentRefId must stay string — casting large IDs to int breaks on 32-bit PHP
        $data = [
            'PaymentRefId' => is_string($paymentRefId) ? $paymentRefId : (string) $paymentRefId,
            'PaymentCode'  => $paymentCode,
            'Amount'       => $amountToman,
        ];

        $res = self::httpPost(self::BASE . '/pay/verify', $data, $token);
        $http = $res['http'] ?? 0;
        $body = $res['data'];

        // duplicate already verified
        if (is_array($body) && isset($body['status']) && (int) $body['status'] === 409) {
            $code = (int) ($body['metaData']['code'] ?? 0);
            if ($code === 110) {
                return [
                    'ok' => true,
                    'ref_id' => (string) $paymentRefId,
                    'duplicate' => true,
                ];
            }
        }

        if ($http >= 200 && $http < 300) {
            return [
                'ok' => true,
                'ref_id' => (string) ($body['paymentRefId'] ?? $body['PaymentRefId'] ?? $paymentRefId),
                'data' => $body,
            ];
        }

        $msg = is_array($body)
            ? ($body['metaData']['message'] ?? $body['title'] ?? $body['message'] ?? json_encode($body, JSON_UNESCAPED_UNICODE))
            : (string) ($res['raw'] ?? 'verify failed');
        return ['ok' => false, 'message' => (string) $msg, 'http' => $http];
    }

    /** تست سریع توکن با یک درخواست ساخت پرداخت آزمایشی */
    public static function testToken(?string $token = null): array
    {
        $token = $token !== null && $token !== '' ? $token : self::accessToken();
        if (!$token) {
            return ['ok' => false, 'message' => 'توکن خالی است'];
        }
        $data = [
            'PayerName'     => 'test',
            'Amount'        => 1000,
            'PayerIdentity' => '',
            'ReturnUrl'     => 'https://donate.sudoshz.ir/api/callback.php?g=payping&la=test',
            'Description'   => 'token-test',
            'ClientRefId'   => 'test' . time(),
            'NationalCode'  => '',
        ];
        $res = self::httpPost(self::BASE . '/pay', $data, $token);
        if (($res['http'] ?? 0) >= 200 && ($res['http'] ?? 0) < 300 && is_array($res['data']) && !empty($res['data']['paymentCode'])) {
            return ['ok' => true, 'message' => 'توکن معتبر است (API v3 مثل افزونه ووکامرس).', 'paymentCode' => $res['data']['paymentCode']];
        }
        $friendly = self::extractError($res['data'] ?? null, (string) ($res['raw'] ?? ''));
        return [
            'ok' => false,
            'message' => $friendly,
            'http' => $res['http'] ?? 0,
            'raw' => $res['raw'] ?? '',
        ];
    }

    public static function extractError($data, string $raw = ''): string
    {
        $msg = '';
        if (is_array($data)) {
            if (!empty($data['metaData']['errors'][0]['message'])) {
                $msg = (string) $data['metaData']['errors'][0]['message'];
            } elseif (!empty($data['Error'])) {
                $msg = (string) $data['Error'];
            } elseif (!empty($data['title'])) {
                $msg = (string) $data['title'];
            }
            $code = $data['metaData']['code'] ?? null;
            if ($msg !== '' && $code !== null) {
                $msg .= ' (کد ' . $code . ')';
            }
        }
        if ($msg === '') {
            $msg = mb_substr($raw !== '' ? $raw : 'خطای نامشخص از پی‌پینگ', 0, 300);
        }
        // راهنمای رایج
        if (str_contains($msg, 'تایید نشده') || str_contains($msg, 'احراز هویت پذیرنده')) {
            $msg .= ' — یعنی حساب/درگاه پذیرنده در پی‌پینگ هنوز کاملاً تأیید (KYC) نشده یا توکن به درگاه تأییدشده وصل نیست. این محدودیت پنل پی‌پینگ است، نه باگ سایت.';
        }
        return $msg;
    }

    private static function httpPost(string $url, array $payload, string $token): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
                'X-Platform: donate-sudoshz',
                'X-Platform-Version: 1.0',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 45,
        ]);
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            return ['ok' => false, 'http' => $http, 'message' => $err ?: 'curl failed', 'data' => null, 'raw' => ''];
        }
        $data = json_decode($raw, true);
        return [
            'ok' => $http >= 200 && $http < 300,
            'http' => $http,
            'data' => $data,
            'raw' => $raw,
            'message' => null,
        ];
    }
}
