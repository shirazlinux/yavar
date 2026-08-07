<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/PayPing.php';

auth_require_admin();
auth_start_session();

$error = '';
$ok = false;

if (!empty($_GET['error'])) {
    $error = (string) ($_GET['error_description'] ?? $_GET['error']);
} else {
    $code = (string) ($_GET['code'] ?? '');
    $state = (string) ($_GET['state'] ?? '');
    $expected = (string) ($_SESSION['payping_oauth_state'] ?? '');
    if ($code === '' || $state === '' || $expected === '' || !hash_equals($expected, $state)) {
        $error = 'state یا code نامعتبر است.';
    } else {
        $res = PayPing::exchangeCode($code);
        if (!empty($res['ok'])) {
            $ok = true;
            unset($_SESSION['payping_oauth_state']);
        } else {
            $error = $res['message'] ?? 'خطا در دریافت توکن';
        }
    }
}

layout_header('نتیجه اتصال پی‌پینگ');
?>
<section class="page-section">
  <div class="container narrow">
    <?php if ($ok): ?>
      <div class="form-msg show ok">اتصال پی‌پینگ با موفقیت انجام شد. حالا پرداخت آنلاین فعال است.</div>
    <?php else: ?>
      <div class="form-msg show error"><?= e($error) ?></div>
    <?php endif; ?>
    <p><a class="btn btn-primary" href="/admin/payping-connect.php">بازگشت</a>
       <a class="btn btn-ghost" href="/admin/">مدیریت</a></p>
  </div>
</section>
<?php layout_footer(); ?>
