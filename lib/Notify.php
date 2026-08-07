<?php
declare(strict_types=1);

require_once __DIR__ . '/Sms.php';
require_once __DIR__ . '/Mail.php';

/**
 * اعلان‌های دوکاناله: پیامک + ایمیل (هر کدام اگر موجود باشد).
 * خطای یکی مسیر دیگر را متوقف نمی‌کند.
 * اعلان مدیریت جداگانه به admin_notify_email.
 */
final class Notify
{
    public static function registered(?string $phone, ?string $email, string $name, array $extra = []): void
    {
        $site = (string) (app_config()['site_url'] ?? 'https://yavar.sudoshz.ir');
        $body = "ثبت‌نام شما در یاور دریافت شد و در انتظار تأیید مدیران است.\nپس از تأیید، صفحه حمایت شما فعال می‌شود.\n{$site}";
        self::sms($phone, "سلام {$name}\n{$body}");
        self::mail($email, $name, 'ثبت‌نام در یاور', $body);

        $adminBody = "ثبت‌نام جدید (در انتظار تأیید)\n\n"
            . "نام: {$name}\n"
            . "ایمیل: " . trim((string) $email) . "\n"
            . "موبایل: " . trim((string) $phone) . "\n";
        if (!empty($extra['slug'])) {
            $adminBody .= "اسلاگ: " . $extra['slug'] . "\n";
        }
        if (!empty($extra['activity'])) {
            $adminBody .= "فعالیت: " . mb_substr((string) $extra['activity'], 0, 280) . "\n";
        }
        $adminBody .= "\nپنل مدیریت:\n{$site}/admin/";
        self::admin('ثبت‌نام جدید — در انتظار تأیید', $adminBody);
    }

    public static function supporterWelcome(?string $phone, ?string $email, string $name): void
    {
        $body = "حساب حامی شما ساخته شد.\nمی‌توانید فعالان را دنبال کنید، هدف ماهانه بگذارید و خبرخوان کمپین‌ها را ببینید.";
        self::sms($phone, "سلام {$name}\n{$body}");
        self::mail($email, $name, 'خوش آمدید — یاور', $body);

        $site = (string) (app_config()['site_url'] ?? 'https://yavar.sudoshz.ir');
        self::admin(
            'ثبت‌نام حامی جدید',
            "حساب حامی جدید ساخته شد.\n\nنام: {$name}\nایمیل: " . trim((string) $email) . "\nموبایل: " . trim((string) $phone) . "\n\n{$site}/admin/"
        );
    }

    public static function approved(?string $phone, ?string $email, string $name, string $slug): void
    {
        $cfg = app_config();
        $url = rtrim((string) $cfg['site_url'], '/') . '/u/' . rawurlencode($slug);
        $body = "حساب شما تأیید شد.\nصفحه حمایت شما:\n{$url}\nبدون کارمزد پلتفرم — یاور";
        self::sms($phone, "سلام {$name}\n{$body}");
        self::mail($email, $name, 'تأیید عضویت — یاور', $body);
    }

    public static function rejected(?string $phone, ?string $email, string $name, string $reason = ''): void
    {
        $site = (string) (app_config()['site_url'] ?? '');
        $body = "درخواست عضویت شما تأیید نشد.";
        if ($reason !== '') {
            $body .= "\nدلیل: " . mb_substr($reason, 0, 200);
        }
        if ($site !== '') {
            $body .= "\n{$site}";
        }
        self::sms($phone, "سلام {$name}\n{$body}");
        self::mail($email, $name, 'نتیجه بررسی عضویت — یاور', $body);
    }

    /** حمایت جدید در صف (در انتظار پرداخت/واریز) */
    public static function donationPending(
        ?string $phone,
        ?string $email,
        string $name,
        int $amount,
        string $refCode,
        string $donorName = ''
    ): void {
        $site = rtrim((string) (app_config()['site_url'] ?? ''), '/');
        $body = "حمایت جدید در صف واریز/پرداخت\nمبلغ: " . number_format($amount) . " تومان\nکد: {$refCode}";
        if ($donorName !== '') {
            $body .= "\nاز: {$donorName}";
        }
        if ($site !== '') {
            $body .= "\n{$site}/dashboard/";
        }
        self::sms($phone, "سلام {$name}\n{$body}");
        self::mail($email, $name, 'حمایت جدید در صف — یاور', $body);

        $adminBody = "حمایت جدید در صف پرداخت/واریز\n\n"
            . "فعال: {$name}\n"
            . "مبلغ: " . number_format($amount) . " تومان\n"
            . "کد: {$refCode}\n"
            . ($donorName !== '' ? "حامی: {$donorName}\n" : '')
            . "\nپنل:\n{$site}/admin/settlements.php";
        self::admin('حمایت جدید در صف', $adminBody);
    }

    /** حمایت پرداخت‌شده / تأییدشده */
    public static function donationPaid(
        ?string $phone,
        ?string $email,
        string $name,
        int $amount,
        string $ref = ''
    ): void {
        $site = rtrim((string) (app_config()['site_url'] ?? ''), '/');
        $body = "یک حمایت به مبلغ " . number_format($amount) . " تومان برای صفحه شما ثبت و تأیید شد.";
        if ($ref !== '') {
            $body .= "\nکد: {$ref}";
        }
        $body .= "\nپس از تأیید مدیریت برای تسویه، مبلغ به حساب شما واریز می‌شود.";
        if ($site !== '') {
            $body .= "\n{$site}/dashboard/";
        }
        self::sms($phone, "سلام {$name}\nیک حمایت به مبلغ " . number_format($amount) . " تومان برای صفحه شما ثبت و تأیید شد." . ($site !== '' ? "\n{$site}" : ''));
        self::mail($email, $name, 'حمایت جدید تأیید شد — یاور', $body);

        $adminBody = "پرداخت حمایت تأیید شد (آماده تسویه)\n\n"
            . "فعال: {$name}\n"
            . "مبلغ: " . number_format($amount) . " تومان\n"
            . ($ref !== '' ? "کد/پیگیری: {$ref}\n" : '')
            . "\nتسویه:\n{$site}/admin/settlements.php";
        self::admin('پرداخت حمایت تأیید شد', $adminBody);
    }

    /** تسویه یا بازگشت وجه */
    public static function settlement(
        ?string $phone,
        ?string $email,
        string $name,
        int $amount,
        string $kind // settled | refunded
    ): void {
        $label = $kind === 'refunded' ? 'بازگشت وجه' : 'تسویه';
        $body = "{$label} حمایت به مبلغ " . number_format($amount) . " تومان ثبت شد.";
        $site = rtrim((string) (app_config()['site_url'] ?? ''), '/');
        if ($site !== '') {
            $body .= "\n{$site}/dashboard/";
        }
        self::sms($phone, "سلام {$name}\n{$body}\nیاور");
        self::mail($email, $name, "{$label} حمایت — یاور", $body);
    }

    /** ایمیل به مدیر(ان) — جدا از پنل */
    public static function admin(string $subject, string $body): void
    {
        $cfg = app_config();
        $raw = (string) ($cfg['admin_notify_email'] ?? '');
        if ($raw === '') {
            $raw = (string) ($cfg['admin_email'] ?? '');
        }
        $emails = preg_split('/[\s,;]+/', $raw) ?: [];
        $emails = array_values(array_unique(array_filter(array_map('trim', $emails))));
        if (!$emails) {
            return;
        }
        $site = (string) ($cfg['site_url'] ?? '');
        $fullBody = trim($body) . "\n\n— یاور (اعلان مدیریت)\n" . $site;
        foreach ($emails as $to) {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            try {
                Mail::send($to, '[یاور] ' . $subject, $fullBody);
            } catch (Throwable $e) {
                error_log('Notify::admin failed: ' . $e->getMessage());
            }
        }
    }

    private static function sms(?string $phone, string $message): void
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return;
        }
        try {
            Sms::send($phone, $message);
        } catch (Throwable $e) {
            error_log('Notify::sms failed: ' . $e->getMessage());
        }
    }

    private static function mail(?string $email, string $name, string $subject, string $body): void
    {
        $email = trim((string) $email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        try {
            Mail::notifyMember($email, $name !== '' ? $name : 'کاربر', $subject, $body);
        } catch (Throwable $e) {
            error_log('Notify::mail failed: ' . $e->getMessage());
        }
    }
}
