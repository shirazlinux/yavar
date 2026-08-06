<?php
declare(strict_types=1);
/**
 * OAuth callback در ریشه سایت — این آدرس را در اپ پی‌پینگ ثبت کنید:
 * https://donate.sudoshz.ir/payping-callback.php
 */
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/PayPing.php';

auth_require_admin();
auth_start_session();

$error = '';
$ok = false;
$debug = '';

if (!empty($_GET['error'])) {
    $error = (string) ($_GET['error_description'] ?? $_GET['error']);
} else {
    $code = (string) ($_GET['code'] ?? '');
    $state = (string) ($_GET['state'] ?? '');
    $expected = (string) ($_SESSION['payping_oauth_state'] ?? '');
    $verifier = (string) ($_SESSION['payping_code_verifier'] ?? '');

    if ($code === '') {
        $error = 'کد مجوز (code) از پی‌پینگ نیامد.';
    } elseif ($expected === '' || $state === '' || !hash_equals($expected, $state)) {
        $error = 'state نامعتبر است. از صفحه اتصال دوباره شروع کنید.';
    } elseif ($verifier === '') {
        $error = 'code_verifier در نشست نیست. کوکی/نشست را چک کنید و دوباره وصل شوید.';
    } else {
        $res = PayPing::exchangeCode($code, $verifier);
        if (!empty($res['ok'])) {
            $ok = true;
            unset($_SESSION['payping_oauth_state'], $_SESSION['payping_code_verifier']);
        } else {
            $error = $res['message'] ?? 'خطا در دریافت توکن';
            $debug = json_encode($res['raw'] ?? null, JSON_UNESCAPED_UNICODE);
        }
    }
}

layout_header('نتیجه اتصال پی‌پینگ');
?>
<section class="page-section">
  <div class="container narrow">
    <?php if ($ok): ?>
      <div class="form-msg show ok">
        ✅ اتصال پی‌پینگ با موفقیت انجام شد. پرداخت آنلاین فعال است.
      </div>
    <?php else: ?>
      <div class="form-msg show error">
        ❌ <?= e($error) ?>
        <?php if ($debug): ?><br><small dir="ltr"><?= e($debug) ?></small><?php endif; ?>
      </div>
      <div class="card" style="margin-top:1rem">
        <h3 style="margin-top:0">اگر خطای redirect_uri دیدید</h3>
        <p class="hint">دقیقاً این آدرس را در تنظیمات اپلیکیشن پی‌پینگ به‌عنوان Redirect URI ثبت کنید (کپی/پیست، بدون فاصله اضافه):</p>
        <p dir="ltr"><code><?= e(PayPing::redirectUri()) ?></code></p>
      </div>
    <?php endif; ?>
    <p style="margin-top:1rem">
      <a class="btn btn-primary" style="width:auto" href="/admin/payping-connect.php">بازگشت به اتصال</a>
      <a class="btn btn-ghost" href="/admin/">مدیریت</a>
    </p>
  </div>
</section>
<?php layout_footer(); ?>
