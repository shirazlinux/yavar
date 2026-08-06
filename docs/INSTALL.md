# راه‌اندازی یاور

## پیش‌نیاز

| مورد | مقدار |
|------|--------|
| PHP | 8.0 یا بالاتر |
| افزونه‌ها | `pdo_sqlite`, `curl`, `mbstring`, `json`, `fileinfo`, `gd`, `openssl` |
| وب‌سرور | Apache / LiteSpeed با `AllowOverride All` |
| ماژول‌ها | `mod_rewrite` (لازم)، `mod_headers` (توصیه) |
| HTTPS | برای کوکی امن نشست لازم است |

```bash
php -v
php -m | grep -E 'pdo_sqlite|curl|mbstring|gd|openssl'
```

---

## ۱. دریافت کد

```bash
git clone https://codeberg.org/shirazlinux/yavar.git
cd yavar
```

یا ZIP را از Codeberg بگیرید و در مسیر وب (مثلاً `public_html/`) باز کنید.

---

## ۲. تنظیمات

```bash
cp config.sample.php config.php
chmod 600 config.php
```

حداقل این‌ها را در `config.php` پر کنید:

| کلید | کار |
|------|-----|
| `site_url` | آدرس کامل سایت با `https://` (بدون `/` آخر) |
| `admin_email` | ایمیل ادمین اولیه |
| `admin_password_hash` | خروجی دستور زیر |
| `admin_notify_email` | ایمیل اعلان‌های سیستمی |
| `db_path` | پیش‌فرض: `__DIR__ . '/data/app.sqlite'` |
| `gateway` | `bank` یا `payping` یا `auto` یا … |
| `smtp_*` | اگر `mail()` روی هاست کار نمی‌کند |
| `payping_token` | فقط اگر درگاه پی‌پینگ می‌خواهید |
| `kavenegar_api_key` | فقط اگر OTP پیامکی می‌خواهید |

ساخت hash رمز ادمین:

```bash
php -r "echo password_hash('رمز-قوی', PASSWORD_DEFAULT), PHP_EOL;"
```

`config.php` را **هرگز** در گیت commit نکنید (در `.gitignore` هست).

---

## ۳. پوشه‌های نوشتنی

```bash
mkdir -p data assets/uploads
chmod 750 data assets/uploads
```

فایل‌های `.htaccess` داخل `data/` و `assets/uploads/` را نگه دارید (دسترسی وب را می‌بندند).

---

## ۴. دامنه و Document Root

ریشهٔ وب باید جایی باشد که `index.php` و `.htaccess` هستند.

اگر زیرمسیر نصب می‌کنید (مثلاً `/yavar/`)، در `.htaccess` مقدار `RewriteBase` را به `/yavar/` تغییر دهید و `site_url` را مطابق همان بگذارید.

---

## ۵. اولین اجرا

1. سایت را با HTTPS باز کنید.
2. جداول SQLite خودکار ساخته می‌شوند.
3. با `admin_email` و رمزی که hash کردید وارد شوید: `/login.php`
4. پنل مدیریت: `/admin/`

مسیرهای مهم بعد از نصب:

| مسیر | کاربرد |
|------|--------|
| `/` | فهرست |
| `/register.php` | ثبت‌نام فعال |
| `/register-supporter.php` | ثبت‌نام حامی |
| `/dashboard/` | پنل کاربر |
| `/admin/` | مدیریت |
| `/admin/settlements.php` | تسویه |
| `/admin/security.php` | رویدادهای امنیتی |

---

## ۶. درگاه و سرویس‌های خارجی (اختیاری)

### فقط کارت‌به‌کارت

```php
'gateway' => 'bank',
```

فعال در پروفایل کارت/شبا می‌گذارد؛ حامی مستقیم واریز می‌کند.

### پی‌پینگ

```php
'gateway' => 'payping', // یا auto
'payping_token' => 'توکن از پنل پی‌پینگ',
```

Callback را برنامه خودش می‌سازد: `/api/callback.php`.

### پیامک (کاوه‌نگار)

برای OTP ثبت‌نام فعال:

```php
'kavenegar_api_key' => '…',
'kavenegar_sender'  => '…', // در صورت نیاز
```

### ایمیل SMTP

```php
'smtp_host'   => 'mail.example.org',
'smtp_port'   => 587,
'smtp_secure' => 'tls',
'smtp_user'   => 'noreply@example.org',
'smtp_pass'   => '…',
'mail_from'   => 'noreply@example.org',
```

---

## ۷. چک بعد از نصب

- [ ] `https://your-site/` باز می‌شود
- [ ] `https://your-site/config.php` → **403**
- [ ] `https://your-site/data/` → **403**
- [ ] `https://your-site/lib/` → **403**
- [ ] ورود ادمین کار می‌کند
- [ ] یک پرداخت تست (یا مسیر بانک) را یک‌بار امتحان کنید
- [ ] بکاپ از `data/app.sqlite` بگیرید

---

## ۸. به‌روزرسانی

```bash
cd /path/to/yavar
# بکاپ data/
cp -a data data-backup-$(date +%Y%m%d)
git pull
```

`config.php` و `data/` در گیت نیستند و با pull پاک نمی‌شوند.

---

## ۹. عیب‌یابی

| مشکل | بررسی |
|------|--------|
| صفحه سفید / 500 | لاگ PHP هاست؛ مجوز `data/`؛ نسخه PHP |
| CSRF مدام | فقط روی HTTPS کار کنید |
| ایمیل نمی‌رود | SMTP، پورت 587/465، فایروال |
| OTP نمی‌آید | کلید کاوه‌نگار؛ محدودیت نرخ (حدود ۸ درخواست/ساعت per IP) |
| ادمین ساخته نشد | `admin_email` + `admin_password_hash` را دوباره چک کنید؛ در صورت نیاز با DB تازه تست کنید |

---

## ساختار پوشه‌ها (خلاصه)

```
yavar/
  index.php, login.php, register.php, …
  config.sample.php   → کپی به config.php (محلی)
  api/                → start, callback, confirm, otp, …
  admin/              → مدیریت
  dashboard/          → پنل کاربر
  lib/                → هسته (از وب مسدود)
  assets/             → css, js, avatars, uploads
  data/               → sqlite و لاگ runtime (خالی در گیت)
  docs/               → همین مستندات
```

جزئیات بیشتر: [ARCHITECTURE.md](ARCHITECTURE.md).
