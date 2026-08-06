<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/Captcha.php';
require_once __DIR__ . '/lib/Notify.php';

$errors = [];
$ok = false;
auth_start_session();
if (!Captcha::isPassed() && !Captcha::hasChallenge()) {
    Captcha::generate();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/lib/RateLimit.php';
    if (!csrf_verify($_POST['csrf'] ?? null)) $errors[] = 'نشست منقضی شد.';
    $rlReg = RateLimit::hit('register_sup', 8, 3600);
    if (!$rlReg['ok']) {
        security_log('register_sup_rate', '');
        $errors[] = 'تعداد ثبت‌نام زیاد است. یک ساعت بعد دوباره تلاش کنید.';
    }
    if (!Captcha::check($_POST['captcha'] ?? null)) {
        $errors[] = 'کپچا نادرست است. عدد را انگلیسی وارد کنید (مثلاً 12).';
        Captcha::generate();
    }

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');
    $display = trim((string)($_POST['display_name'] ?? ''));
    $phone = Sms::normalizeMobile((string)($_POST['phone'] ?? ''));
    $budget = (int) digits_en((string)($_POST['monthly_budget'] ?? '0'));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'ایمیل نامعتبر.';
    if (mb_strlen($display) < 2) $errors[] = 'نام نمایشی کوتاه است.';
    if (mb_strlen($password) < 8) $errors[] = 'رمز حداقل ۸ کاراکتر.';
    if (preg_match('/[\x{0600}-\x{06FF}]/u', $password)) $errors[] = 'رمز را انگلیسی بنویسید.';
    if ($password !== $password2) $errors[] = 'تکرار رمز یکسان نیست.';
    if ($phone !== '' && !Sms::validMobile($phone)) $errors[] = 'موبایل نامعتبر.';
    if (!empty($_POST['website'])) $errors[] = 'خطا.';

    if (!$errors) {
        if (user_email_taken($email)) $errors[] = 'این ایمیل قبلاً ثبت شده است.';
        if ($phone !== '' && user_phone_taken($phone)) $errors[] = 'این شماره موبایل قبلاً ثبت شده است.';
    }
    if (!$errors) {
        $slug = unique_slug('h-' . preg_replace('/[^a-z0-9]+/i', '-', $display));
        $now = gmdate('c');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = db()->prepare('INSERT INTO users (email, password_hash, display_name, slug, bio, activity, services, phone, status, is_admin, policy_accepted_at, created_at, updated_at, role, monthly_budget, feed_enabled, avatar) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([
            $email, $hash, $display, $slug, '', 'حامی جامعه نرم‌افزار آزاد', '', $phone,
            'approved', 0, $now, $now, $now, 'supporter', max(0, $budget), 1, 'preset:share',
        ]);
        Captcha::clearPassed();
        try {
            Notify::supporterWelcome($phone !== '' ? $phone : null, $email, $display);
        } catch (Throwable $e) {
            error_log('register-supporter Notify: ' . $e->getMessage());
        }
        $ok = true;
    }
}

layout_header('ثبت‌نام حامی', 'ثبت‌نام حامیان برای پیگیری حمایت، علاقه‌مندی و خبرخوان');
?>
<section class="page-section">
  <div class="container narrow">
    <h1 class="page-title">ثبت‌نام حامی</h1>
    <p class="page-lead">حساب حامی برای دنبال‌کردن، علاقه‌مندی، هدف ماهانه و خبرخوان کمپین‌هاست.</p>
    <div class="policy-box" style="margin-bottom:1rem">
      <ul style="margin:0">
        <li><strong>برای حمایت کردن ورود لازم نیست</strong> — مستقیم از صفحه فعال پرداخت کنید.</li>
        <li>می‌توانید بعداً از پنل، <strong>صفحه حمایت</strong> هم فعال کنید.</li>
        <li>هم صفحه داشتن و هم حامی بودن همزمان ممکن است.</li>
      </ul>
    </div>
    <?php if ($ok): ?>
      <div class="form-msg show ok">ثبت شد. <a href="/login.php">ورود</a></div>
    <?php else: ?>
      <?php if ($errors): ?><div class="form-msg show error"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div><?php endif; ?>
      <form method="post" class="card form-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="hp" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
        <label>نام نمایشی *</label>
        <input name="display_name" required value="<?= e($_POST['display_name'] ?? '') ?>">
        <label>ایمیل *</label>
        <input type="email" name="email" required dir="ltr" class="ltr-field" value="<?= e($_POST['email'] ?? '') ?>">
        <label>موبایل (اختیاری — اطلاع‌رسانی)</label>
        <input name="phone" dir="ltr" class="ltr-field" value="<?= e($_POST['phone'] ?? '') ?>">
        <div class="grid-2">
          <div>
            <label>رمز *</label>
            <input type="password" name="password" required minlength="8" dir="ltr" class="ltr-field password-field">
          </div>
          <div>
            <label>تکرار رمز *</label>
            <input type="password" name="password2" required minlength="8" dir="ltr" class="ltr-field password-field">
          </div>
        </div>
        <label>بودجهٔ حمایت ماهانه (تومان، اختیاری)</label>
        <input name="monthly_budget" dir="ltr" class="ltr-field money-input" inputmode="numeric" placeholder="۵۰۰٬۰۰۰" value="<?= e($_POST['monthly_budget'] ?? '') ?>">
        <?= Captcha::renderBox() ?>
        <button class="btn btn-primary" type="submit">ثبت‌نام حامی</button>
        <p class="form-note">صفحه حمایت هم می‌خواهید؟ <a href="/register.php">ثبت با صفحه حمایت</a> · <a href="/login.php">ورود</a></p>
      </form>
    <?php endif; ?>
  </div>
</section>
<script src="/assets/js/money.js?v=1" defer></script>
<?php layout_footer(); ?>
