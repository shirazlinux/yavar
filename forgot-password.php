<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/Mail.php';

if (auth_user()) {
    header('Location: /dashboard/');
    exit;
}

$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $errors[] = 'نشست منقضی شد. دوباره تلاش کنید.';
    }
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'ایمیل معتبر وارد کنید.';
    }

    auth_start_session();
    require_once __DIR__ . '/lib/RateLimit.php';
    // محدودیت IP: ۵ در ساعت + نشست
    $rl = RateLimit::hit('forgot_pw', 5, 3600);
    if (!$rl['ok']) {
        security_log('forgot_rate_limit', '');
        $errors[] = 'تعداد درخواست زیاد است. یک ساعت بعد دوباره تلاش کنید.';
    }
    $hist = $_SESSION['pw_reset_times'] ?? [];
    if (!is_array($hist)) {
        $hist = [];
    }
    $now = time();
    $hist = array_values(array_filter($hist, static fn ($t) => is_int($t) && $t > $now - 900));
    if (count($hist) >= 3) {
        $errors[] = 'تعداد درخواست زیاد است. چند دقیقه بعد دوباره تلاش کنید.';
    }

    if (!$errors) {
        $hist[] = $now;
        $_SESSION['pw_reset_times'] = $hist;

        $st = db()->prepare('SELECT id, display_name, email FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $u = $st->fetch();

        // همیشه پیام یکسان (لو نرفتن وجود/عدم وجود حساب)
        $done = true;

        if ($u) {
            // باطل کردن توکن‌های قبلی مصرف‌نشده
            db()->prepare("UPDATE password_resets SET used_at=? WHERE user_id=? AND used_at IS NULL")
                ->execute([gmdate('c'), (int) $u['id']]);

            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            $exp = gmdate('c', time() + 3600); // ۱ ساعت
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            db()->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at, used_at, created_at, request_ip) VALUES (?,?,?,?,?,?)'
            )->execute([(int) $u['id'], $hash, $exp, null, gmdate('c'), $ip]);

            $cfg = app_config();
            $base = rtrim((string) ($cfg['site_url'] ?? 'https://donate.sudoshz.ir'), '/');
            $link = $base . '/reset-password.php?token=' . rawurlencode($token);
            $name = (string) ($u['display_name'] ?: 'کاربر');
            $body = "درخواست بازنشانی رمز عبور برای حساب شما ثبت شد.\n\n"
                . "اگر خودتان این درخواست را نداده‌اید، این پیام را نادیده بگیرید.\n\n"
                . "برای تعیین رمز جدید (حداکثر ۱ ساعت معتبر است) روی لینک زیر بزنید:\n"
                . $link . "\n\n"
                . "— یاور\n" . $base;

            try {
                Mail::send((string) $u['email'], 'بازنشانی رمز عبور', "سلام {$name}\n\n{$body}");
            } catch (Throwable $e) {
                error_log('forgot-password mail: ' . $e->getMessage());
            }
        }
    }
}

layout_header('فراموشی رمز', 'بازنشانی رمز عبور با ایمیل');
?>
<section class="page-section">
  <div class="container narrow">
    <h1 class="page-title">فراموشی رمز عبور</h1>
    <p class="page-lead">ایمیل حساب‌تان را وارد کنید. اگر حسابی با آن باشد، لینک بازنشانی می‌فرستیم.</p>

    <?php if ($done): ?>
      <div class="form-msg show ok">
        اگر ایمیلی با این نشانی ثبت شده باشد، لینک بازنشانی رمز تا چند دقیقه دیگر می‌رسد.
        پوشهٔ اسپم را هم بررسی کنید. لینک حدود <strong>۱ ساعت</strong> معتبر است.
      </div>
      <p style="margin-top:1rem">
        <a class="btn btn-primary" style="width:auto" href="/login.php">بازگشت به ورود</a>
      </p>
    <?php else: ?>
      <?php if ($errors): ?>
        <div class="form-msg show error"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div>
      <?php endif; ?>
      <form method="post" class="card form-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label for="email">ایمیل حساب</label>
        <input id="email" type="email" name="email" required dir="ltr" class="ltr-field" autocomplete="email"
               value="<?= e($_POST['email'] ?? '') ?>">
        <button class="btn btn-primary" type="submit">ارسال لینک بازنشانی</button>
        <p class="form-note"><a href="/login.php">بازگشت به ورود</a></p>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php layout_footer(); ?>
