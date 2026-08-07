<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/Captcha.php';
require_once __DIR__ . '/lib/RateLimit.php';

if (auth_user()) {
    $u = auth_user();
    if (($u['role'] ?? 'hamyar') === 'supporter' && empty($u['is_admin'])) {
        header('Location: /dashboard/supporter.php');
    } else {
        header('Location: /dashboard/');
    }
    exit;
}

auth_start_session();

/** تعداد ورود ناموفق در این نشست */
function login_fail_count(): int
{
    return max(0, (int) ($_SESSION['login_failures'] ?? 0));
}

/** از تلاش دوم به بعد کپچا لازم است */
function login_need_captcha(): bool
{
    return login_fail_count() >= 1;
}

function login_note_fail(): void
{
    auth_start_session();
    $_SESSION['login_failures'] = login_fail_count() + 1;
    // کپچای قبلی (مثلاً ثبت‌نام) را باطل کن تا برای ورود دوباره حل شود
    Captcha::clearPassed();
    Captcha::generate();
}

function login_clear_fails(): void
{
    auth_start_session();
    unset($_SESSION['login_failures']);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $errors[] = 'نشست منقضی شد.';
    }
    $rl = RateLimit::hit('login', 12, 900);
    if (!$rl['ok']) {
        security_log('login_rate_limit', '');
        $errors[] = 'تلاش‌های ورود زیاد بود. چند دقیقه صبر کنید.';
    }

    // بعد از ۱ بار اشتباه: کپچا اجباری
    if (!$errors && login_need_captcha()) {
        // isPassed از فرم‌های دیگر را قبول نکن — فقط پاسخ همین چالش
        Captcha::clearPassed();
        if (!Captcha::hasChallenge()) {
            Captcha::generate();
        }
        if (!Captcha::verify($_POST['captcha'] ?? null, true)) {
            security_log('login_captcha_fail', '');
            $errors[] = 'پاسخ کپچا نادرست است. دوباره تلاش کنید.';
            Captcha::generate();
        }
    }

    $loginId = trim((string) ($_POST['login'] ?? $_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($loginId === '') {
        $errors[] = 'ایمیل یا نام‌کاربری را وارد کنید.';
    }
    // محدودیت per-account (distributed brute-force)
    $acctKey = 'login_acct:' . hash('sha256', mb_strtolower($loginId));
    if ($loginId !== '' && !$errors) {
        $rlAcct = RateLimit::hitKey($acctKey, 8, 900);
        if (!$rlAcct['ok']) {
            security_log('login_acct_rate', mb_substr($loginId, 0, 80));
            $errors[] = 'تلاش‌های ورود برای این حساب زیاد بود. ۱۵ دقیقه صبر کنید.';
        }
    }
    if (!$errors) {
        $u = null;
        if (str_contains($loginId, '@')) {
            $st = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $st->execute([strtolower($loginId)]);
            $u = $st->fetch() ?: null;
        }
        if (!$u) {
            $slug = slugify($loginId);
            if ($slug !== '') {
                $st = db()->prepare('SELECT * FROM users WHERE lower(slug) = lower(?) LIMIT 1');
                $st->execute([$slug]);
                $u = $st->fetch() ?: null;
            }
        }
        if (!$u && !str_contains($loginId, '@')) {
            $st = db()->prepare('SELECT * FROM users WHERE lower(email) = lower(?) LIMIT 1');
            $st->execute([$loginId]);
            $u = $st->fetch() ?: null;
        }
        // همیشه password_verify (hash ساختگی معتبر) برای کاهش timing oracle
        $dummy = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWX01234';
        $hashCheck = is_array($u) ? (string) ($u['password_hash'] ?? $dummy) : $dummy;
        $passOk = password_verify($password, $hashCheck);
        if (!$u || !$passOk) {
            security_log('login_fail', mb_substr($loginId, 0, 80));
            login_note_fail();
            $errors[] = 'ایمیل/نام‌کاربری یا رمز عبور نادرست است.';
            if (login_need_captcha()) {
                $errors[] = 'برای تلاش بعدی، حل کپچا الزامی است.';
            }
        } else {
            login_clear_fails();
            auth_login($u);
            if (!empty($u['is_admin'])) {
                security_log('admin_login', (string) $u['email']);
                try {
                    require_once __DIR__ . '/lib/Notify.php';
                    Notify::admin('ورود مدیر به پنل', "ورود موفق ادمین.\nایمیل: {$u['email']}\nزمان: " . gmdate('c'));
                } catch (Throwable $e) {
                    // ignore
                }
            }
            $fallback = (($u['role'] ?? 'hamyar') === 'supporter' && empty($u['is_admin']))
                ? '/dashboard/supporter.php'
                : '/dashboard/';
            $next = safe_internal_path($_GET['next'] ?? null, $fallback);
            header('Location: ' . $next);
            exit;
        }
    }
}

// برای نمایش: اگر لازم است کپچا آماده باشد
$showCaptcha = login_need_captcha();
if ($showCaptcha && !Captcha::hasChallenge() && !Captcha::isPassed()) {
    Captcha::generate();
}

layout_header('ورود', 'ورود به یاور');
?>
<section class="page-section">
  <div class="container narrow">
    <h1 class="page-title">ورود</h1>

    <div class="policy-box" style="margin-bottom:1.25rem">
      <h2 style="margin-top:0;font-size:1.1rem">قبل از ورود بدانید</h2>
      <ul>
        <li><strong>حمایت کردن نیاز به ورود ندارد.</strong> می‌توانید مستقیم از صفحه فعال یا پروژه پرداخت کنید.</li>
        <li>ورود برای این‌هاست: <strong>دنبال‌کردن / علاقه‌مندی</strong>، هدف ماهانه، خبرخوان کمپین‌ها و دیدن سوابق.</li>
        <li>می‌توانید <strong>هم صفحه حمایت داشته باشید و هم حامی دیگران باشید</strong> — هر دو با هم ممکن است.</li>
        <li>اگر الان صفحه نمی‌خواهید، بعداً از پنل قابل فعال‌سازی است.</li>
      </ul>
    </div>

    <?php if ($errors): ?>
      <div class="form-msg show error"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="post" class="card form-card" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label for="login">ایمیل یا نام‌کاربری</label>
      <input id="login" type="text" name="login" required dir="ltr" class="ltr-field"
             value="<?= e($_POST['login'] ?? $_POST['email'] ?? '') ?>"
             autocomplete="username" placeholder="email@example.com یا my-slug">
      <p class="hint" style="margin:.25rem 0 .75rem">نام‌کاربری همان آدرس صفحه شماست (مثلاً <span dir="ltr">ali</span> در <span dir="ltr">/u/ali</span>).</p>
      <label for="password">رمز عبور</label>
      <input id="password" type="password" dir="ltr" class="ltr-field password-field" name="password" required autocomplete="current-password">
      <p class="hint" style="margin:.35rem 0 .75rem"><a href="/forgot-password.php">رمز را فراموش کرده‌اید؟</a></p>

      <?php if ($showCaptcha): ?>
        <div style="margin:1rem 0 .75rem">
          <p class="hint" style="margin:0 0 .5rem">به‌دلیل ورود ناموفق قبلی، حل کپچا لازم است.</p>
          <?= Captcha::renderBox('captcha', 'login-captcha') ?>
        </div>
      <?php endif; ?>

      <button class="btn btn-primary btn-block" type="submit">ورود</button>
    </form>

    <div class="card" style="margin-top:1rem">
      <h2 style="margin-top:0;font-size:1.1rem">حساب ندارید؟</h2>
      <p class="hint">یک حساب می‌سازید؛ در همان‌جا می‌پرسیم آیا صفحه حمایت می‌خواهید یا فعلاً فقط حامی هستید.</p>
      <div class="btn-toolbar" style="margin-top:.75rem">
        <a class="btn btn-primary" href="/register.php">ثبت‌نام (با انتخاب نقش)</a>
        <a class="btn btn-ghost" href="/">بازگشت به حمایت بدون ورود</a>
      </div>
    </div>
  </div>
</section>
<?php layout_footer(); ?>
