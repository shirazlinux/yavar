<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/PayPing.php';

auth_require_admin();
auth_start_session();

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_token') {
        $token = trim((string) ($_POST['payping_token'] ?? ''));
        app_settings_save(['payping_token' => $token]);
        if ($token === '') {
            $msg = 'توکن پاک شد. پرداخت آنلاین غیرفعال است (واریز کارت‌به‌کارت همچنان کار می‌کند).';
            $msgType = 'manual';
        } else {
            $test = PayPing::testToken($token);
            if (!empty($test['ok'])) {
                $msg = '✅ توکن ذخیره و با API v3 (مثل افزونه ووکامرس) تأیید شد. درگاه آنلاین فعال است.';
                $msgType = 'ok';
                // switch gateway preference to auto so online is tried first
                app_settings_save(['payping_token' => $token, 'gateway' => 'auto']);
            } else {
                $msg = 'توکن ذخیره شد ولی تست ناموفق بود: ' . ($test['message'] ?? '');
                $msgType = 'error';
                app_settings_save(['payping_token' => $token]);
            }
        }
    }
    if ($action === 'test_token') {
        $test = PayPing::testToken();
        $msg = $test['message'] ?? '';
        $msgType = !empty($test['ok']) ? 'ok' : 'error';
    }
}

$settings = is_file(app_settings_path())
    ? (json_decode((string) file_get_contents(app_settings_path()), true) ?: [])
    : [];
$savedToken = (string) ($settings['payping_token'] ?? '');
$connected = PayPing::isConnected();

layout_header('اتصال پی‌پینگ');
?>
<section class="page-section">
  <div class="container narrow">
    <h1 class="page-title">پی‌پینگ — مثل افزونه ووکامرس</h1>

    <?php if ($msg): ?>
      <div class="form-msg show <?= e($msgType) ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="policy-box">
      <strong>واقعیت فنی (از کد افزونه رسمی ووکامرس پی‌پینگ):</strong>
      <ul>
        <li>افزونه‌ها فقط فیلد <strong>«توکن»</strong> می‌خواهند (Bearer).</li>
        <li>API: <code>https://api.payping.ir/v3/pay</code></li>
        <li>اگر پی‌پینگ بگوید «توکن احراز هویت پذیرنده تایید نشده» یعنی
          <strong>احراز هویت / تأیید درگاه در خودِ پی‌پینگ کامل نیست</strong>
          — حتی ووکامرس هم با همین توکن پرداخت واقعی نمی‌گیرد.</li>
      </ul>
    </div>

    <?php if ($connected): ?>
      <div class="form-msg show ok">توکن در سیستم هست. می‌توانی تست بگیری یا پرداخت آنلاین را امتحان کنی.</div>
    <?php else: ?>
      <div class="form-msg show manual">هنوز توکن نداریم → فعلاً سایت با واریز کارت‌به‌کارت کار می‌کند.</div>
    <?php endif; ?>

    <div class="card form-card">
      <h2 style="margin-top:0;font-size:1.15rem">توکن درگاه را اینجا بگذار</h2>
      <p class="hint">
        در پنل پی‌پینگ (همان جایی که برای ووکامرس توکن کپی می‌کنی):
        <br>معمولاً: <strong>اپلیکیشن‌ها / توسعه‌دهندگان / توکن</strong>
        <br>نه Client ID و نه Client Secret — یک رشتهٔ «توکن» جداست.
      </p>
      <p class="hint">
        راهنمای رسمی پی‌پینگ درباره پلاگین‌ها و «نحوه دریافت توکن»:
        <a href="https://payping.io/help/fa/category/3025tt/" target="_blank" rel="noopener">payping.io/help … پلاگین‌ها</a>
      </p>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_token">
        <label for="payping_token">توکن (Bearer) — مثل فیلد افزونه ووکامرس</label>
        <textarea id="payping_token" name="payping_token" rows="4" dir="ltr" placeholder="توکن را اینجا بچسبان…"><?= e($savedToken) ?></textarea>
        <button class="btn btn-primary btn-block" type="submit">ذخیره و تست خودکار</button>
      </form>
      <div class="btn-toolbar" style="margin-top:.5rem">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="test_token">
        <button class="btn btn-ghost" type="submit">فقط تست توکن ذخیره‌شده</button>
      </form>
      </div>
    </div>

    <div class="card" style="margin-top:1rem">
      <h3 style="margin-top:0">الان سایت چه وضعی دارد؟</h3>
      <ul class="hint" style="margin:0;padding-right:1.1rem">
        <li>بدون توکن: <strong>واریز کارت‌به‌کارت به فعال</strong> (کار می‌کند)</li>
        <li>با توکن معتبر: اول <strong>درگاه آنلاین پی‌پینگ v3</strong>، اگر نشد دوباره کارت‌به‌کارت</li>
        <li>کاوه‌نگار: پیامک‌ها فعال است</li>
      </ul>
    </div>

    <p class="form-note"><a href="/admin/">بازگشت به مدیریت</a></p>
  </div>
</section>
<?php layout_footer(); ?>
