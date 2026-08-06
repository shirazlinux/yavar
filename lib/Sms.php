<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

final class Sms
{
    public static function normalizeMobile(string $m): string
    {
        // ارقام فارسی/عربی → لاتین
        $map = [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ];
        $m = preg_replace('/\D+/', '', strtr($m, $map)) ?? '';
        if (str_starts_with($m, '98') && strlen($m) === 12) {
            $m = '0' . substr($m, 2);
        }
        if (str_starts_with($m, '9') && strlen($m) === 10) {
            $m = '0' . $m;
        }
        return $m;
    }

    public static function validMobile(string $m): bool
    {
        $m = self::normalizeMobile($m);
        return (bool) preg_match('/^09\d{9}$/', $m);
    }

    public static function send(string $receptor, string $message): array
    {
        $cfg = app_config();
        $apiKey = trim((string) ($cfg['kavenegar_api_key'] ?? ''));
        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'Kavenegar API key not set', 'skipped' => true];
        }
        $receptor = self::normalizeMobile($receptor);
        if (!self::validMobile($receptor)) {
            return ['ok' => false, 'message' => 'invalid mobile'];
        }
        $sender = trim((string) ($cfg['kavenegar_sender'] ?? ''));
        $url = 'https://api.kavenegar.com/v1/' . rawurlencode($apiKey) . '/sms/send.json';
        $params = [
            'receptor' => $receptor,
            'message'  => $message,
        ];
        if ($sender !== '') {
            $params['sender'] = $sender;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_TIMEOUT        => 20,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false) {
            return ['ok' => false, 'message' => $err ?: 'curl failed', 'http' => $code];
        }
        $data = json_decode($body, true);
        $status = (int) ($data['return']['status'] ?? 0);
        if ($status === 200) {
            return ['ok' => true, 'data' => $data];
        }
        return [
            'ok' => false,
            'message' => $data['return']['message'] ?? 'sms failed',
            'http' => $code,
            'data' => $data,
        ];
    }

    public static function notifyRegistered(string $mobile, string $name): void
    {
        $msg = "سلام {$name}\nثبت‌نام شما در یاور دریافت شد و در انتظار تأیید است.\n" . (app_config()['site_url'] ?? '');
        self::send($mobile, $msg);
    }

    public static function notifyApproved(string $mobile, string $name, string $slug): void
    {
        $cfg = app_config();
        $url = rtrim((string) $cfg['site_url'], '/') . '/u/' . rawurlencode($slug);
        $msg = "سلام {$name}\nحساب شما تأیید شد. صفحه حمایت:\n{$url}\nبدون کارمزد — شیرازلینوکس";
        self::send($mobile, $msg);
    }

    public static function notifyRejected(string $mobile, string $name, string $reason = ''): void
    {
        $msg = "سلام {$name}\nدرخواست عضویت شما تأیید نشد.";
        if ($reason !== '') {
            $msg .= "\nدلیل: " . mb_substr($reason, 0, 120);
        }
        $msg .= "\n" . (app_config()['site_url'] ?? '');
        self::send($mobile, $msg);
    }

    public static function notifyDonationPaid(string $mobile, string $name, int $amount): void
    {
        $msg = "سلام {$name}\nیک حمایت به مبلغ " . number_format($amount) . " تومان برای صفحه شما ثبت و تأیید شد.\n" . (app_config()['site_url'] ?? '');
        self::send($mobile, $msg);
    }

    public static function sendOtp(string $mobile, string $code): array
    {
        $msg = "کد تأیید عضویت یاور:\n{$code}\nاین کد ۵ دقیقه اعتبار دارد.";
        return self::send($mobile, $msg);
    }
}
