# یاور (Yavar)

**بستر حمایت مستقیم از فعالان و پروژه‌های نرم‌افزار آزاد** — بدون کارمزد پلتفرم.

پروژه‌ای از جامعهٔ [شیرازلینوکس](https://sudoshz.ir).

| | |
|---|---|
| **مجوز** | [GNU Affero General Public License v3](LICENSE) (یا بعدتر) |
| **پشته** | PHP 8+ · SQLite · Apache/LiteSpeed (cPanel) |
| **درگاه‌ها** | کارت‌به‌کارت · پی‌پینگ · زرین‌پال · آیدی‌پی · پی‌آیر |
| **نسخهٔ زنده** | [donate.sudoshz.ir](https://donate.sudoshz.ir) |

---

## این مخزن چیست؟

کد منبع **عمومی و آزاد** یاور است:

- **بدون** `config.php` واقعی، رمز، توکن درگاه، یا کلید API  
- **بدون** دیتابیس SQLite و لاگ کاربران  
- **بدون** فایل‌های آپلود کاربران  

هر کسی می‌تواند یک نمونهٔ مستقل برای جامعهٔ خودش راه‌اندازی کند.

---

## امکانات

- صفحهٔ عمومی فعال / پروژه / جامعه (`/u/slug`)
- کمپین با مبلغ ثابت یا آزاد (`/c/slug`)
- حمایت مهمان (بدون ورود) یا با حساب حامی
- پرداخت آنلاین یا واریز کارت‌به‌کارت مستقیم به فعال
- پنل فعال: تأیید دریافت، کمپین، پروفایل، تسویه
- پنل ادمین: تأیید اعضا، تسویه، ادعای واریز، رویدادهای امنیتی
- OTP پیامکی + کپچا در ثبت‌نام
- بازنشانی رمز با ایمیل
- محدودیت نرخ (rate limit)، CSRF، هدرهای امنیتی، لاگ رویدادها

مستندات بیشتر:

- [docs/INSTALL.md](docs/INSTALL.md) — راه‌اندازی کامل  
- [docs/SECURITY.md](docs/SECURITY.md) — نکات امنیتی  
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — ساختار پوشه‌ها  

---

## شروع سریع (لوکال / هاست)

### پیش‌نیاز

- PHP **8.0+** با افزونه‌ها: `pdo_sqlite`, `curl`, `mbstring`, `json`, `gd` (برای re-encode آواتار), `openssl`
- وب‌سرور Apache یا LiteSpeed با `mod_rewrite` و `mod_headers` (توصیه)
- دسترسی نوشتن به پوشهٔ `data/` و `assets/uploads/`

### نصب

```bash
git clone https://codeberg.org/YOUR_USER/yavar.git
cd yavar
cp config.sample.php config.php
# config.php را ویرایش کنید (دامنه، ادمین، SMTP، درگاه، …)

# ساخت hash رمز ادمین
php -r "echo password_hash('YourStrongPassword', PASSWORD_DEFAULT), PHP_EOL;"
# خروجی را در admin_password_hash بگذارید

chmod 750 data assets/uploads
# روی cPanel معمولاً مالک فایل باید همان کاربر هاست باشد
```

Document root را روی **ریشهٔ همین پروژه** بگذارید (جایی که `index.php` و `.htaccess` هستند).

اولین بازدید، جداول SQLite را می‌سازد. اگر `admin_email` + `admin_password_hash` تنظیم شده باشد، کاربر ادمین ساخته می‌شود.

جزئیات کامل: **[docs/INSTALL.md](docs/INSTALL.md)**.

---

## امنیت — قبل از انتشار نمونهٔ خودتان

1. `config.php` را **هرگز** commit نکنید (در `.gitignore` هست).  
2. مطمئن شوید `/data/` و `/lib/` از وب مستقیم در دسترس نیستند (`.htaccess` همراه است).  
3. HTTPS اجباری؛ کوکی نشست `Secure` + `HttpOnly` + `SameSite=Lax` است.  
4. توکن پی‌پینگ / کلید کاوه‌نگار / رمز SMTP را فقط در `config.php` یا `data/settings.json` (خارج از گیت) نگه دارید.  
5. بعد از نصب، `admin/security.php` را برای رویدادهای مشکوک بررسی کنید.

جزئیات: **[docs/SECURITY.md](docs/SECURITY.md)**.

---

## انتشار روی Codeberg

```bash
cd yavar
git remote add origin git@codeberg.org:YOUR_USER/yavar.git
# یا: https://codeberg.org/YOUR_USER/yavar.git
git push -u origin main
```

سپس در `config.php` نمونهٔ زنده‌تان:

```php
'source_code_url' => 'https://codeberg.org/YOUR_USER/yavar',
```

این لینک در **فوتر** سایت («کد منبع یاور») نمایش داده می‌شود.

---

## مجوز

کپی‌رایت © جامعهٔ نرم‌افزار آزاد شیرازلینوکس و مشارکت‌کنندگان.

این برنامه نرم‌افزار آزاد است؛ تحت **GNU Affero General Public License نسخهٔ ۳** (یا بعدتر) منتشر می‌شود.  
اگر نسخهٔ تغییر یافته را روی شبکه ارائه می‌دهید، باید کد منبع متناظر را در دسترس کاربران بگذارید — همان هدف AGPL.

---

## مشارکت

Issue و Pull Request روی Codeberg خوش‌آمد است. لطفاً:

- secret و دیتابیس واقعی نفرستید  
- برای باگ امنیتی ترجیحاً خصوصی (private issue / ایمیل نگه‌دارنده)  
- کد را با `declare(strict_types=1)` و prepared statement نگه دارید  

---

## نام

**یاور** یعنی کسی که یاری می‌رساند — حمایت مستقیم جامعه از فعالان نرم‌افزار آزاد.
