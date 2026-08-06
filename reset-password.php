<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';

if (auth_user()) {
    header('Location: /dashboard/');
    exit;
}

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$token = preg_replace('/[^a-f0-9]/i', '', $token) ?? '';
$errors = [];
$ok = false;
$valid = false;
$userId = 0;

if (strlen($token) === 64) {
    $hash = hash('sha256', $token);
    $st = db()->prepare(
        'SELECT id, user_id, expires_at, used_at FROM password_resets WHERE token_hash = ? LIMIT 1'
    );
    $st->execute([$hash]);
    $row = $st->fetch();
    if ($row && empty($row['used_at']) && strtotime((string) $row['expires_at']) > time()) {
        $valid = true;
        $userId = (int) $row['user_id'];
        $resetId = (int) $row['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $errors[] = 'نشست منقضی شد.';
    }
    $pass = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password2'] ?? '');
    if (mb_strlen($pass) < 8) {
        $errors[] = 'رمز جدید حداقل ۸ کاراکتر باشد.';
    }
    if (preg_match('/[\x{0600}-\x{06FF}]/u', $pass)) {
        $errors[] = 'رمز را با کیبورد انگلیسی بنویسید.';
    }
    if ($pass !== $pass2) {
        $errors[] = 'تکرار رمز یکسان نیست.';
    }
    if (!$errors) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $now = gmdate('c');
        db()->prepare('UPDATE users SET password_hash=?, updated_at=? WHERE id=?')
            ->execute([$hash, $now, $userId]);
        db()->prepare('UPDATE password_resets SET used_at=? WHERE id=?')
            ->execute([$now, $resetId]);
        // باطل کردن بقیه توکن‌های باز این کاربر
        db()->prepare("UPDATE password_resets SET used_at=? WHERE user_id=? AND used_at IS NULL")
            ->execute([$now, $userId]);
        $ok = true;
        $valid = false;
    }
}

layout_header('رمز جدید', 'تعیین رمز عبور جدید — یاور');
?>
<section class="page-section">
  <div class="container narrow">
    <h1 class="page-title">تعیین رمز جدید</h1>

    <?php if ($ok): ?>
      <div class="form-msg show ok">رمز با موفقیت تغییر کرد. حالا می‌توانید وارد شوید.</div>
      <p style="margin-top:1rem">
        <a class="btn btn-primary" style="width:auto" href="/login.php">ورود</a>
      </p>
    <?php elseif (!$valid): ?>
      <div class="form-msg show error">
        لینک نامعتبر یا منقضی است. دوباره از صفحهٔ فراموشی رمز درخواست دهید.
      </div>
      <p style="margin-top:1rem">
        <a class="btn btn-primary" style="width:auto" href="/forgot-password.php">درخواست لینک تازه</a>
      </p>
    <?php else: ?>
      <p class="page-lead">رمز جدید را وارد کنید (حداقل ۸ نویسه، انگلیسی).</p>
      <?php if ($errors): ?>
        <div class="form-msg show error"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div>
      <?php endif; ?>
      <form method="post" class="card form-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label for="password">رمز جدید</label>
        <input id="password" type="password" name="password" required minlength="8" dir="ltr"
               class="ltr-field password-field" autocomplete="new-password">
        <label for="password2">تکرار رمز</label>
        <input id="password2" type="password" name="password2" required minlength="8" dir="ltr"
               class="ltr-field password-field" autocomplete="new-password">
        <button class="btn btn-primary" type="submit">ذخیره رمز جدید</button>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php layout_footer(); ?>
