<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/services.php';
require_once __DIR__ . '/lib/PayPing.php';

$cfg = app_config();
$planId = (string) ($_GET['plan'] ?? $_POST['plan'] ?? 'support-standard');
$service = service_get($planId) ?: service_get('support-standard');
$isCustom = !empty($service['custom']);

$activists = db()->query(
    "SELECT id, display_name, slug FROM users WHERE status='approved' AND is_admin=0 ORDER BY display_name ASC"
)->fetchAll();

layout_header('حمایت و پرداخت', $service['title'] . ' — بدون کارمزد پلتفرم');
$paypingReady = PayPing::isConnected();
?>
<section class="page-section">
  <div class="container">
    <h1 class="page-title">حمایت و پرداخت</h1>
    <p class="page-lead">
      <strong>بدون کارمزد پلتفرم</strong> — مبلغ حمایت به مسیر فعال انتخابی شما می‌رود.
    </p>

    <div class="hero-grid">
      <div class="card">
        <h2 style="margin:.35rem 0"><?= e($service['title']) ?></h2>
        <p class="hint"><?= e($service['description']) ?></p>
        <ul class="product-features">
          <?php foreach ($service['features'] as $f): ?>
            <li><?= e($f) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if (!$isCustom): ?>
          <p class="product-price" style="font-size:1.5rem"><?= e(money_fa((int) $service['amount'])) ?></p>
        <?php endif; ?>
        <p class="hint"><a href="/services.php">← بازگشت به مبالغ حمایت</a></p>
      </div>

      <aside class="card" id="checkout-box">
        <h2 style="margin-top:0;font-size:1.15rem">تکمیل حمایت</h2>
        <p class="sub">
          <?= $paypingReady
            ? 'پس از تأیید، به درگاه پرداخت امن منتقل می‌شوید.'
            : 'درگاه آنلاین موقتاً در دسترس نیست؛ در صورت نیاز مسیر واریز امن نمایش داده می‌شود.' ?>
        </p>

        <form id="donate-form" data-checkout="1" novalidate>
          <input type="hidden" id="cause" value="activists">
          <input type="hidden" id="plan_id" value="<?= e($service['id']) ?>">

          <?php if ($isCustom): ?>
            <label for="amount">مبلغ حمایت (تومان) *</label>
            <input type="number" id="amount" name="amount" min="<?= (int) $cfg['min_amount'] ?>" step="1000" value="50000" required>
          <?php else: ?>
            <input type="hidden" id="amount" value="<?= (int) $service['amount'] ?>">
            <p>مبلغ حمایت: <strong><?= e(money_fa((int) $service['amount'])) ?></strong></p>
          <?php endif; ?>

          <label for="activist_id">فعال دریافت‌کننده *</label>
          <select id="activist_id" name="activist_id" required>
            <option value="">— انتخاب کنید —</option>
            <?php foreach ($activists as $a): ?>
              <option value="<?= (int) $a['id'] ?>"><?= e($a['display_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (!$activists): ?>
            <p class="hint">فعلاً فعال تأییدشده‌ای نیست. <a href="/register.php">ثبت‌نام فعال</a></p>
          <?php endif; ?>

          <label for="name">نام شما (اختیاری)</label>
          <input type="text" id="name" maxlength="80" placeholder="نام و نام خانوادگی">

          <label for="message">پیام برای فعال (اختیاری)</label>
          <textarea id="message" rows="2" maxlength="500" placeholder="یادداشت کوتاه"></textarea>

          <div class="hp"><input type="text" id="website" tabindex="-1" autocomplete="off"></div>

          <button type="submit" class="btn btn-primary" id="submit-btn" <?= $activists ? '' : 'disabled' ?>>
            پرداخت و تکمیل حمایت
          </button>
          <p class="form-note">
            با ادامه، <a href="/about.php#policy">خط‌مشی</a> را می‌پذیرید.
            · <a href="/contact.php">تماس</a>
          </p>
          <div id="form-msg" class="form-msg" role="status"></div>
        </form>
      </aside>
    </div>
  </div>
</section>
<?php layout_footer(); ?>
