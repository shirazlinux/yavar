# امنیت یاور

این سند برای **نصب‌کننده‌ها و مشارکت‌کننده‌ها** است. مخزن عمومی عمداً **بدون secret و بدون دیتابیس** است.

---

## آنچه در این کد تعبیه شده

| لایه | جزئیات |
|------|--------|
| CSRF | همهٔ POSTهای حساس + API با `hash_equals` |
| نشست | `HttpOnly`, `Secure`, `SameSite=Lax`, strict mode, regenerate on login |
| Rate limit | ورود، فراموشی رمز، شروع پرداخت، OTP (IP + شماره)، ثبت‌نام، اعلام واریز |
| پرداخت | `markPaid` فقط `pending→paid`؛ `payment_ref` یکتا؛ انقضای pending |
| کارت‌به‌کارت | ادعای فعال ≠ تسویه نهایی؛ ادمین `ready` می‌کند |
| OTP | HMAC در نشست (نه plaintext)؛ سقف تلاش |
| آپلود | MIME + re-encode GD + PHP engine off در uploads |
| XSS | `e()` در PHP؛ escape در JS برای فیلدهای بانک |
| Redirect | `safe_internal_path` بعد از login؛ whitelist درگاه در JS |
| هدرها | HSTS, CSP, X-Frame-Options, nosniff, … |
| مسیرها | `/data`, `/lib`, `config.php` از وب مسدود |

---

## آنچه نباید عمومی شود

هرگز در گیت یا Issue عمومی نگذارید:

- `config.php`
- `data/*.sqlite` و بکاپ‌ها
- `data/settings.json` / توکن‌ها
- `data/donations.log` / `callback.log`
- `assets/uploads/*` کاربران
- خروجی واقعی `password_hash` ادمین production
- کلید SMTP، کاوه‌نگار، پی‌پینگ

`.gitignore` این‌ها را پوشش می‌دهد؛ قبل از `git push` با `git status` چک کنید.

---

## سخت‌سازی پیشنهادی production

1. **HTTPS اجباری** + تمدید خودکار گواهی  
2. `chmod 600 config.php`  
3. بکاپ رمزنگاری‌شدهٔ SQLite  
4. رمز قوی ادمین + در آینده 2FA  
5. محدود کردن پنل ادمین با IP (اختیاری، سطح هاست/فایروال)  
6. مانیتور `/admin/security.php`  
7. به‌روزرسانی PHP و بررسی دوره‌ای وابستگی‌ها  

---

## گزارش آسیب‌پذیری

اگر باگ امنیتی در کد عمومی یافتید:

1. **عمومی نکنید** (حداقل تا رفع).  
2. به نگه‌دارندگان پروژه (شیرازلینوکس / Issue خصوصی Codeberg) اطلاع دهید.  
3. PoC حداقلی و بدون دادهٔ واقعی کاربران کافی است.

---

## AGPL و امنیت

AGPL اجازهٔ بررسی عمومی کد را می‌دهد — این **شفافیت** است، نه ضعف.  
امنیت با «مخفی کردن کد» به‌دست نمی‌آید؛ با طراحی درست، secret جدا، و به‌روزرسانی به‌دست می‌آید.
