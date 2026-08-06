# معماری یاور

## نمای کلی

```
مرورگر ──HTTPS──► Apache/LiteSpeed
                      │
                      ▼
                 PHP (index, api/*, dashboard, admin)
                      │
          ┌───────────┼───────────┐
          ▼           ▼           ▼
       SQLite      فایل pending   SMTP / SMS / درگاه
     (data/)      (data/pending)  (خارجی)
```

## درخت پوشه‌ها

```
yavar/
├── index.php              # فهرست عمومی
├── login.php / register*.php / forgot-password.php / reset-password.php
├── checkout.php / result.php / ways.php / about.php / contact.php
├── config.sample.php      # نمونه — config.php محلی (غیرگیت)
├── .htaccess              # امنیت + rewrite
├── api/
│   ├── start.php          # شروع پرداخت (CSRF + rate limit)
│   ├── callback.php       # بازگشت درگاه
│   ├── confirm.php        # تأیید/رد واریز توسط فعال/ادمین
│   ├── report-transfer.php# اعلام واریز حامی
│   ├── send-otp.php
│   ├── like.php / pledge.php
├── admin/                 # فقط is_admin
│   ├── index.php          # تأیید اعضا + آمار
│   ├── settlements.php    # تسویه + ادعای فعال
│   ├── security.php       # لاگ security_events
│   ├── campaigns.php
│   └── payping-*.php
├── dashboard/             # کاربر واردشده
│   ├── index.php          # حمایت‌های دریافتی + تأیید بانک
│   ├── profile.php
│   ├── campaigns.php
│   └── supporter.php
├── u/index.php            # صفحه عمومی فعال
├── c/index.php            # کمپین
├── lib/                   # هسته (از وب 403)
│   ├── auth.php           # نشست، CSRF، نقش‌ها
│   ├── db.php             # SQLite + migrate
│   ├── Gateway.php        # درگاه‌ها
│   ├── RateLimit.php
│   ├── Mail.php / Sms.php / Notify.php
│   ├── PayPing.php / Captcha.php / layout.php / …
├── assets/
│   ├── css/ style.css
│   ├── js/  main.js money.js register.js
│   ├── avatars/           # preset SVG
│   └── uploads/           # آپلود کاربر (خالی در گیت)
└── data/                  # runtime (خالی در گیت)
```

## نقش‌ها

| نقش | دسترسی |
|-----|--------|
| مهمان | حمایت، مشاهده صفحات عمومی |
| `supporter` | like، pledge، فید |
| `hamyar` (فعال) | صفحه، کمپین، تأیید دریافت بانک |
| `is_admin` | تأیید عضو، تسویه، امنیت، تنظیمات درگاه |

## جریان پرداخت بانک

1. `api/start` → donation `pending` + نمایش کارت/شبا  
2. حامی واریز می‌کند → اختیاری `report-transfer`  
3. فعال `confirm` → `paid` + `settlement=none`  
4. ادمین تأیید ادعا → `ready`  
5. ادمین بعد از واریز واقعی → `settled`

## جریان درگاه آنلاین

1. `api/start` → redirect درگاه  
2. `api/callback` → verify با API درگاه → `markPaid` → `ready`

## وابستگی خارجی

- **اختیاری:** پی‌پینگ / زرین‌پال / کاوه‌نگار / SMTP  
- **اجباری برای runtime:** فقط PHP + SQLite  

بدون Composer در نسخهٔ فعلی.
