# راه‌اندازی کامل یاور

## ۱. پیش‌نیاز سرور

| مورد | حداقل |
|------|--------|
| PHP | 8.0 (پیشنهاد 8.1+) |
| افزونه‌ها | `pdo_sqlite`, `curl`, `mbstring`, `json`, `fileinfo`, `gd`, `openssl` |
| وب‌سرور | Apache 2.4 / LiteSpeed با `AllowOverride All` |
| ماژول‌ها | `mod_rewrite`, `mod_headers` (توصیه) |
| SSL | HTTPS (Let's Encrypt یا گواهی هاست) |
| ایمیل | SMTP (اگر `mail()` روی هاست خاموش است) |

بررسی سریع:

```bash
php -v
php -m | grep -E 'pdo_sqlite|curl|mbstring|gd|openssl'
```

---

## ۲. دریافت کد

```bash
git clone https://codeberg.org/YOUR_USER/yavar.git
cd yavar
```

یا ZIP را از Codeberg دانلود و در `public_html/yavar` (یا دامنهٔ جدا) باز کنید.

---

## ۳. تنظیمات

```bash
cp config.sample.php config.php
chmod 600 config.php   # فقط مالک بخواند
```

فیلدهای مهم `config.php`:

| کلید | توضیح |
|------|--------|
| `site_url` | آدرس کامل با `https://` بدون اسلش انتهایی |
| `source_code_url` | لینک مخزن عمومی (فوتر) |
| `admin_email` | ایمیل ورود اولین ادمین |
| `admin_password_hash` | خروجی `password_hash` |
| `admin_notify_email` | اعلان‌های سیستمی |
| `db_path` | مسیر SQLite (پیش‌فرض `data/app.sqlite`) |
| `gateway` | `bank` / `payping` / `auto` / … |
| `payping_token` | توکن Bearer از پنل پی‌پینگ |
| `kavenegar_api_key` | برای OTP (اختیاری ولی برای ثبت‌نام فعال لازم) |
| `smtp_*` | ارسال ایمیل |

ساخت hash رمز:

```bash
php -r "echo password_hash('رمز-قوی-اینجا', PASSWORD_DEFAULT), PHP_EOL;"
```

---

## ۴. مجوز پوشه‌ها

```bash
mkdir -p data assets/uploads
chmod 750 data assets/uploads
# اگر وب‌سرور با کاربر دیگری اجرا می‌شود، مالکیت را درست کنید
# chown -R USER:USER .
```

فایل‌های `.htaccess` داخل `data/` و `assets/uploads/` را نگه دارید.

---

## ۵. دامنه و Document Root

### الف) دامنهٔ اختصاصی (پیشنهاد)

مثلاً `donate.example.org` → Document Root = ریشهٔ پروژه (`index.php` همین‌جا).

### ب) زیرپوشه

اگر روی `example.org/yavar/` نصب می‌کنید، در `.htaccess` مقدار `RewriteBase` را به `/yavar/` تغییر دهید و `site_url` را مطابق تنظیم کنید.

---

## ۶. اولین اجرا

1. `https://your-domain/` را باز کنید.  
2. جداول SQLite خودکار ساخته می‌شوند.  
3. با `admin_email` / رمزی که hash کردید وارد شوید.  
4. اگر ادمین ساخته نشد: hash خالی بوده یا ایمیل تکراری است — لاگ PHP را ببینید.

مسیرهای مهم:

| مسیر | نقش |
|------|-----|
| `/` | فهرست فعالان |
| `/register.php` | ثبت‌نام با صفحه حمایت |
| `/register-supporter.php` | فقط حامی |
| `/login.php` | ورود |
| `/admin/` | مدیریت |
| `/admin/settlements.php` | تسویه و ادعاها |
| `/admin/security.php` | رویدادهای امنیتی |
| `/dashboard/` | پنل فعال |

---

## ۷. درگاه پرداخت

### کارت‌به‌کارت (`gateway = bank` یا fallback)

فعال باید کارت/شبا در پروفایل داشته باشد. حامی مستقیم واریز می‌کند → اعلام واریز → تأیید فعال → بررسی ادمین → تسویه.

### پی‌پینگ

1. از پنل پی‌پینگ **توکن** بگیرید (مثل افزونه ووکامرس).  
2. در `config.php`: `'gateway' => 'payping'` یا `'auto'` و `payping_token`.  
3. Callback: `https://YOUR_SITE/api/callback.php?g=payping&la=...` (خود برنامه می‌سازد).

### زرین‌پال / آیدی‌پی / پی‌آیر

کلیدها را در `config.php` بگذارید و `gateway` را تنظیم کنید. Sandbox برای تست.

---

## ۸. پیامک (کاوه‌نگار)

برای OTP ثبت‌نام فعال لازم است:

```php
'kavenegar_api_key' => '…',
'kavenegar_sender'  => '1000…', // در صورت نیاز
```

بدون SMS، مسیر ثبت‌نام حامی (بدون OTP اجباری) همچنان کار می‌کند.

---

## ۹. ایمیل (SMTP)

```php
'smtp_host'   => 'mail.example.org',
'smtp_port'   => 587,
'smtp_secure' => 'tls',
'smtp_user'   => 'noreply@example.org',
'smtp_pass'   => '…',
'mail_from'   => 'noreply@example.org',
```

بازنشانی رمز و اعلان‌های ادمین از همین مسیر می‌روند.

---

## ۱۰. چک‌لیست بعد از نصب

- [ ] HTTPS کار می‌کند  
- [ ] `config.php` از وب 403 است  
- [ ] `/data/app.sqlite` از وب 403 است  
- [ ] ثبت‌نام + OTP (اگر SMS دارید)  
- [ ] ورود ادمین  
- [ ] یک حمایت تستی (sandbox یا مبلغ کم)  
- [ ] `source_code_url` در فوتر درست است  
- [ ] بکاپ منظم از `data/app.sqlite`  

---

## ۱۱. به‌روزرسانی از گیت

```bash
cd /path/to/yavar
git pull
# config.php و data/ دست‌نخورده می‌مانند (gitignore)
```

قبل از pull روی production یک بکاپ از `data/` بگیرید.

---

## ۱۲. عیب‌یابی

| مشکل | کار |
|------|-----|
| صفحه سفید | `php.error.log` هاست؛ `display_errors` موقت |
| 500 بعد از نصب | مجوز `data/`؛ نسخه PHP |
| CSRF مدام | کوکی Secure روی HTTP؛ فقط HTTPS استفاده کنید |
| ایمیل نمی‌رود | SMTP و فایروال پورت 587/465 |
| OTP نمی‌آید | کلید کاوه‌نگار؛ rate limit (۸/ساعت IP) |
| ادمین نیست | `admin_password_hash` و `admin_email` را دوباره تنظیم و DB تازه تست کنید |

---

## پشتیبانی جامعه

برای نمونهٔ شیرازلینوکس: [sudoshz.ir](https://sudoshz.ir)  
باگ‌ها و PR: مخزن Codeberg همین پروژه.
