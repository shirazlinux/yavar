<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
$user = auth_require_login();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf'] ?? null)) {
    if (($_POST['action'] ?? '') === 'enable') {
        // upgrade to hamyar (support page owner); keep ability to support others
        $now = gmdate('c');
        $status = ($user['status'] ?? '') === 'approved' ? 'pending' : ($user['status'] ?? 'pending');
        // if already approved as supporter-only, need re-approval for public page
        if (($user['role'] ?? '') === 'supporter') {
            $status = 'pending';
        }
        db()->prepare("UPDATE users SET role='hamyar', status=?, updated_at=? WHERE id=?")
            ->execute([$status, $now, (int)$user['id']]);
        header('Location: /dashboard/profile.php?enabled=1');
        exit;
    }
}

layout_header('فعال‌سازی صفحه حمایت');
?>
<section class="page-section">
  <div class="container narrow">
    <h1 class="page-title">صفحه حمایت</h1>
    <div class="policy-box">
      <ul>
        <li>با فعال‌سازی، صفحه عمومی حمایت می‌گیرید (پس از تأیید در صورت نیاز).</li>
        <li><strong>همزمان می‌توانید حامی دیگران هم باشید</strong> (دنبال‌کردن، هدف ماهانه، خبرخوان).</li>
        <li>این تنظیم بعداً هم قابل مدیریت است.</li>
        <li>حمایت کردن از دیگران همچنان <strong>بدون ورود</strong> هم ممکن است.</li>
      </ul>
    </div>
    <form method="post" class="card form-card">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="enable">
      <button class="btn btn-primary btn-block" type="submit">بله، صفحه حمایت می‌خواهم</button>
      <p class="form-note"><a href="/dashboard/supporter.php">فعلاً نه — بازگشت به پنل حامی</a></p>
    </form>
  </div>
</section>
<?php layout_footer(); ?>
