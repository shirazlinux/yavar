<?php
/**
 * نمونه تنظیمات یاور — کپی به config.php
 * هرگز config.php را در مخزن عمومی commit نکنید.
 */
return [
    'site_name'      => 'یاور',
    'tagline'        => 'پلتفرم حمایت از پروژه‌ها و جوامع نرم‌افزار آزاد',
    'site_url'       => 'https://donate.sudoshz.ir',
    'community_url'  => 'https://donate.sudoshz.ir',
    'source_code_url'=> 'https://codeberg.org/shirazlinux/yavar',
    'contact_email'  => 'info@example.org',
    'admin_email'    => 'admin@example.org',
    'admin_password_hash' => '',
    'admin_notify_email'  => 'admin@example.org',
    'db_path'        => __DIR__ . '/data/app.sqlite',
    'gateway'        => 'bank',
    'payping_token'  => '',
    'currency_label' => 'تومان',
    'min_amount'     => 10000,
    'max_amount'     => 500000000,
    'preset_amounts' => [50000, 100000, 200000, 500000, 1000000],
    'payment_description' => 'حمایت از نرم‌افزار آزاد — یاور',
    'mail_from'       => 'noreply@example.org',
    'mail_from_name'  => 'یاور',
    'mail_logo_url'   => 'https://donate.sudoshz.ir/assets/brand/logo-header.png',
    'smtp_host'       => 'mail.example.org',
    'smtp_port'       => 587,
    'smtp_secure'     => 'tls',
    'smtp_user'       => 'noreply@example.org',
    'smtp_pass'       => 'CHANGE_ME',
    'smtp_timeout'    => 25,
    'log_file'        => __DIR__ . '/data/donations.log',
    'telegram_bot_username' => 'yavar_notification_bot',
    'telegram_worker_secret' => '',
    'telegram_worker_ips' => '',
];
