# امنیت — چه کار شده و شما چه چک کنید

## هدف این مخزن عمومی

کد **قابل نصب و قابل بررسی** است، ولی **داده و secret محیط واقعی داخلش نیست.**

---

## چه چیزهایی از export حذف شده (انجام‌شده)

| مورد | وضعیت |
|------|--------|
| `config.php` با توکن/رمز/SMTP واقعی | حذف — فقط `config.sample.php` با placeholder |
| `*.sqlite` | حذف |
| `data/*.json`, `settings.json`, pending | حذف |
| `*.log`, `*.log.gz` | حذف |
| آپلودهای کاربران در `assets/uploads/` | حذف (فقط `.htaccess` + `.gitkeep`) |
| بکاپ‌های `.live` و endpoint تست | حذف |
| `.gitignore` برای جلوگیری از commit دوبارهٔ secret | هست |

اسکن روی درخت عمومی قبل از commit اولیه انجام شد (hash رمز، کلید کاوه‌نگار، client secret پی‌پینگ، و مشابه).

---

## دفاع‌های داخل خودِ کد (روی نمونهٔ زنده هم فعال است)

| موضوع | رفتار |
|------|--------|
| CSRF | فرم‌ها و APIهای state-changing |
| Rate limit | login، OTP، start پرداخت، forgot password، register، report-transfer |
| نشست | Secure + HttpOnly + SameSite + regenerate روی login |
| پرداخت | markPaid فقط pending→paid؛ payment_ref تکراری رد می‌شود |
| OTP | hash در نشست؛ سقف تلاش؛ محدودیت IP و شماره |
| آپلود | MIME + re-encode تصویر؛ PHP در uploads خاموش |
| مسیرها | `/data`، `/lib`، `config.php` از وب 403 |
| هدرها | HSTS، CSP، X-Frame، nosniff، … |

جزئیات فنی بیشتر در تاریخچهٔ توسعهٔ نمونهٔ شیرازلینوکس؛ برای نصب‌کننده همین جدول کافی است.

---

## شما **باید** این‌ها را خودتان چک کنید (۵ دقیقه)

روی **همین پوشهٔ گیت** قبل از push:

```bash
cd ~/Documents/github/yavar   # یا codeberg/yavar

# 1) config واقعی نباشد
test ! -f config.php && echo OK || echo "BAD: config.php exists"

# 2) دیتابیس/لاگ در فایل‌های track‌شده نباشد
git ls-files | grep -E '\.sqlite$|\.log$|config\.php$' && echo BAD || echo OK

# 3) چیزی شبیه کلید واقعی نباشد (نمونه‌ها با CHANGE_ME / خالی OK هستند)
grep -RInE 'smtp_pass|payping_token|kavenegar|password_hash' --include='*.php' . \
  | grep -v config.sample.php | grep -v '.git' || true
# خروجی باید خالی یا فقط کد برنامه باشد، نه مقدار secret
```

روی **سایت زنده‌ای که خودتان نصب می‌کنید** بعد از deploy:

```bash
# از بیرون (curl یا مرورگر) باید 403 باشند:
#   /config.php
#   /data/
#   /lib/auth.php
#   /data/app.sqlite
```

و یک‌بار:

- لاگین با رمز اشتباه چند بار → rate limit
- `POST /api/start.php` بدون CSRF → 403

---

## چه چیزی «امنیت ۱۰۰٪» نیست

- هیچ نرم‌افزاری بدون باگ تضمین نمی‌شود.
- secretها اگر روی سرور لو بروند (دسترسی SSH/هاست) جدا از این مخزن است.
- بعد از نصب، **بکاپ SQLite** و **رمز ادمین قوی** و **HTTPS** با شماست.

اگر باگ امنیتی در کد عمومی پیدا کردید: Issue عمومی با PoC مخرب نزنید؛ خصوصی اطلاع دهید.

---

## خلاصه

| سوال | جواب |
|------|------|
| secret و DB در این گیت هست؟ | **نه** (طبق export و اسکن) |
| باید خودم چک کنم؟ | **بله، همان ۳–۴ دستور بالا** قبل از push — ۲ دقیقه |
| برای production چه کار کنم؟ | `chmod 600 config.php`، HTTPS، بکاپ `data/`، مانیتور `/admin/security.php` |
