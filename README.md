# یاور (Yavar)

کد منبع بستر حمایت از فعالان و پروژه‌های نرم‌افزار آزاد (PHP + SQLite).

**مجوز:** [GNU AGPL v3](LICENSE)  
**نسخهٔ نمونهٔ زنده:** https://donate.sudoshz.ir

این مخزن **عمومی** است و عمداً شامل موارد زیر **نیست**:

- فایل `config.php` واقعی (رمز، توکن درگاه، SMTP، API)
- دیتابیس SQLite و بکاپ‌ها
- لاگ تراکنش / callback
- فایل‌های آپلود کاربران

---

## راه‌اندازی سریع

پیش‌نیاز: PHP 8.0+ با `pdo_sqlite`, `curl`, `mbstring`, `json`, `fileinfo`, `gd`, `openssl` و وب‌سرور با `mod_rewrite`.

```bash
git clone https://codeberg.org/YOUR_USER/yavar.git
cd yavar
cp config.sample.php config.php
# config.php را ویرایش کنید

php -r "echo password_hash('YOUR_ADMIN_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
# خروجی را در admin_password_hash بگذارید

mkdir -p data assets/uploads
chmod 750 data assets/uploads
chmod 600 config.php
```

Document root را روی ریشهٔ همین پروژه بگذارید. اولین بازدید جداول را می‌سازد.

**راهنمای کامل:** [docs/INSTALL.md](docs/INSTALL.md)  
**امنیت:** [docs/SECURITY.md](docs/SECURITY.md)  
**ساختار کد:** [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)

---

## قبل از push عمومی (چک سریع)

```bash
# نباید چیزی از این‌ها در گیت باشد:
git status
test ! -f config.php && echo "OK: no config.php"
git ls-files | grep -E 'config\.php$|\.sqlite$|\.log$|uploads/.+\.(jpg|png)' && echo "FAIL" || echo "OK: clean tree"
```

---

## مشارکت

Issue / PR روی Codeberg. secret و دیتابیس واقعی نفرستید. جزئیات: [CONTRIBUTING.md](CONTRIBUTING.md).
