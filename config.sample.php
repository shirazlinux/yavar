<?php
/**
 * نمونه تنظیمات یاور — این فایل را به config.php کپی کنید و مقادیر را عوض کنید.
 *
 *   cp config.sample.php config.php
 *
 * ⚠️  هرگز config.php را در مخزن عمومی commit نکنید.
 */
return [
    'site_name'      => 'یاور',
    'site_url'       => 'https://donate.example.org',
    'community_url'  => 'https://example.org',

    // اختیاری: آدرس مخزن کد (اگر بخواهید جایی در UI لینک بدهید)
    'source_code_url' => '',

    // تماس (برای نمایش / الزامات درگاه)
    'contact_email'    => 'info@example.org',
    'contact_phone'    => '',
    'contact_address'  => '',
    'contact_postal'    => '',
    'contact_hours'    => '',
    'contact_org'      => '',

    // ادمین اولیه (فقط اولین بار اگر کاربری با این ایمیل نباشد ساخته می‌شود)
    // رمز را با: php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
    'admin_email'         => 'admin@example.org',
    'admin_password_hash' => '',
    // ایمیل دریافت اعلان‌های مدیریتی (ثبت‌نام، پرداخت، ادعا و …)
    'admin_notify_email'  => 'admin@example.org',

    'db_path'        => __DIR__ . '/data/app.sqlite',

    // bank | payping | zarinpal | idpay | payir | auto
    'gateway'        => 'bank',

    // پی‌پینگ — فقط توکن Bearer از پنل (مثل افزونه ووکامرس)
    'payping_token'  => '',

    'zarinpal_merchant_id' => '',
    'zarinpal_sandbox'     => true,
    'idpay_api_key'        => '',
    'idpay_sandbox'        => true,
    'payir_api_key'        => '',

    // کاوه‌نگار (OTP و اعلان پیامکی)
    'kavenegar_api_key' => '',
    'kavenegar_sender'  => '',

    'currency_label' => 'تومان',
    'min_amount'     => 10000,
    'max_amount'     => 500000000,
    'preset_amounts' => [50000, 100000, 200000, 500000, 1000000],
    'payment_description' => 'حمایت از فعال نرم‌افزار آزاد',

    'mail_from'       => 'noreply@example.org',
    'mail_from_name'  => 'یاور',
    'mail_reply_to'   => 'noreply@example.org',
    'mail_logo_url'   => '',

    // SMTP (اگر mail() روی هاست غیرفعال است)
    'smtp_host'       => 'mail.example.org',
    'smtp_port'       => 587,
    'smtp_secure'     => 'tls', // tls | ssl | ''
    'smtp_user'       => 'noreply@example.org',
    'smtp_pass'       => 'CHANGE_ME',
    'smtp_timeout'    => 25,

    'log_file'       => __DIR__ . '/data/donations.log',

    // اختیاری — نمایش دستی (در صورت نیاز)
    'manual_card'    => '',
    'manual_sheba'   => '',
    'manual_owner'   => '',
    'manual_note'    => '',
];
