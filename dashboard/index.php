<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/campaigns.php';
$user = auth_require_login();
if (($user['role'] ?? 'hamyar') === 'supporter' && empty($user['is_admin'])) {
    header('Location: /dashboard/supporter.php');
    exit;
}
$cfg = app_config();
campaign_refresh_status();

$tot = user_total_paid((int) $user['id']);
$st = db()->prepare('SELECT COUNT(*) FROM campaigns WHERE user_id=?');
$st->execute([(int) $user['id']]);
$campCount = (int) $st->fetchColumn();

$st = db()->prepare('SELECT * FROM donations WHERE user_id = ? ORDER BY id DESC LIMIT 40');
$st->execute([(int) $user['id']]);
$dons = $st->fetchAll();

$statusMap = [
    'pending'  => ['در انتظار تأیید', 'warn'],
    'approved' => ['تأیید شده', 'ok'],
    'rejected' => ['رد شده', 'err'],
];
$sm = $statusMap[$user['status']] ?? ['نامشخص', 'warn'];
$publicUrl = rtrim($cfg['site_url'], '/') . '/u/' . rawurlencode($user['slug']);
$publicPath = '/u/' . $user['slug'];
$stLabel = ['pending' => 'در انتظار', 'paid' => 'پرداخت‌شده', 'failed' => 'ناموفق'];
$settleLabel = [
    'none' => '—',
    'ready' => 'آماده تسویه',
    'settled' => 'تسویه‌شده',
    'refund_pending' => 'در انتظار بازگشت',
    'refunded' => 'بازگشت داده شد',
];

layout_header('پنل من');
?>
<section class="page-section">
  <div class="container">
    <div class="dash-head">
      <div>
        <h1 class="page-title">سلام، <?= e($user['display_name']) ?></h1>
        <p class="page-lead">وضعیت حساب: <span class="badge badge-<?= e($sm[1]) ?>"><?= e($sm[0]) ?></span></p>
      </div>
      <div class="dash-actions">
        <a class="btn btn-ghost" href="/dashboard/profile.php">ویرایش صفحه</a>
        <a class="btn btn-ghost" href="/dashboard/campaigns.php">حمایت‌های هدفمند</a>
        <?php if ($user['status'] === 'approved'): ?>
          <a class="btn btn-primary" style="width:auto" href="<?= e($publicPath) ?>" target="_blank" rel="noopener">صفحه عمومی</a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($user['status'] === 'pending'): ?>
      <div class="form-msg show manual">حساب در صف بررسی است. فقط فعالیت نرم‌افزار آزاد تأیید می‌شود.</div>
    <?php elseif ($user['status'] === 'rejected'): ?>
      <div class="form-msg show error">تأیید نشد.<?php if ($user['reject_reason']): ?> دلیل: <?= e($user['reject_reason']) ?><?php endif; ?></div>
    <?php endif; ?>

    <?php
      $hasSettlement = user_has_settlement($user);
      $hasPaid = ((int) ($tot['count'] ?? 0)) > 0 || ((int) ($tot['sum'] ?? 0)) > 0;
      // حتی اگر فقط یک تراکنش paid/pending داشته باشد هم یادآوری
      if (!$hasPaid && $dons) {
          foreach ($dons as $d) {
              if (($d['status'] ?? '') === 'paid' || ($d['status'] ?? '') === 'pending') {
                  $hasPaid = true;
                  break;
              }
          }
      }
    ?>
    <?php if ($hasPaid && !$hasSettlement): ?>
      <div class="form-msg show manual" style="margin-top:1rem">
        <strong>برای تسویه:</strong> حداقل یک حمایت دریافت کرده‌اید. بهتر است
        <a href="/dashboard/profile.php#settlement">شبا یا کارت به نام خودتان</a>
        را وارد کنید تا مدیریت بتواند مبلغ را واریز کند.
      </div>
    <?php endif; ?>

    <div class="stats-grid">
      <div class="card stat"><div class="stat-label">جمع حمایت تأییدشده</div><div class="stat-value"><?= e(money_fa($tot['sum'])) ?></div></div>
      <div class="card stat"><div class="stat-label">تعداد حمایت پرداخت‌شده</div><div class="stat-value"><?= fa_digits((string) $tot['count']) ?></div></div>
      <div class="card stat"><div class="stat-label">تعداد کمپین حمایت</div><div class="stat-value"><?= fa_digits((string) $campCount) ?></div></div>
      <div class="card stat">
        <div class="stat-label">لینک صفحه</div>
        <div class="stat-value" style="font-size:.95rem;word-break:break-all">
          <?php if ($user['status'] === 'approved'): ?>
            <a href="<?= e($publicPath) ?>" dir="ltr"><?= e($publicUrl) ?></a>
          <?php else: ?>
            <span dir="ltr" style="color:var(--muted)"><?= e($publicUrl) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!empty($_GET['ok'])): ?>
      <div class="form-msg show ok" style="margin-top:1rem">ثبت شد.</div>
    <?php endif; ?>

    <div class="card" style="margin-top:1.25rem">
      <h2 style="margin-top:0">حمایت‌های دریافتی</h2>
      <p class="hint">برای واریز کارت‌به‌کارت: پس از دیدن مبلغ در حساب‌تان، «تأیید دریافت» بزنید. مدیریت بعداً تسویه/ثبت نهایی می‌کند. حمایت‌های درگاهی پس از تأیید خودکار درگاه paid می‌شوند.</p>
      <?php if (!$dons): ?>
        <p class="hint">هنوز حمایتی دریافت نشده.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>تاریخ</th><th>مبلغ</th><th>حامی</th><th>وضعیت</th><th>تسویه</th><th>کد</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($dons as $d): ?>
              <tr>
                <td><?= e(jdate_format($d['created_at'], 'Y/m/d H:i')) ?></td>
                <td><?= e(money_fa((int) $d['amount'])) ?></td>
                <td>
                  <?= e($d['donor_name'] ?: '—') ?>
                  <?php if (!empty($d['message'])): ?>
                    <br><small class="hint"><?= e(mb_substr((string)$d['message'], 0, 120)) ?></small>
                  <?php endif; ?>
                </td>
                <td><?= e($stLabel[$d['status']] ?? $d['status']) ?></td>
                <td><?= e($settleLabel[$d['settlement_status'] ?? 'none'] ?? ($d['settlement_status'] ?? '—')) ?></td>
                <td dir="ltr"><code><?= e($d['ref_id'] ?: '—') ?></code></td>
                <td>
                  <?php if (($d['status'] ?? '') === 'pending' && (($d['gateway'] ?? '') === 'bank' || ($d['gateway'] ?? '') === 'manual')): ?>
                    <form method="post" action="/api/confirm.php" style="display:inline" onsubmit="return confirm('مبلغ واقعاً به حساب‌تان رسیده؟');">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="donation_id" value="<?= (int)$d['id'] ?>">
                      <button class="btn btn-primary" style="width:auto;padding:.3rem .55rem;font-size:.75rem" name="action" value="confirm" type="submit">✓ تأیید دریافت</button>
                    </form>
                    <form method="post" action="/api/confirm.php" style="display:inline;margin-inline-start:.25rem" onsubmit="return confirm('رد شود؟');">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="donation_id" value="<?= (int)$d['id'] ?>">
                      <button class="btn btn-ghost" style="width:auto;padding:.3rem .55rem;font-size:.75rem" name="action" value="reject" type="submit">رد</button>
                    </form>
                  <?php elseif (($d['status'] ?? '') === 'paid' && ($d['settlement_status'] ?? '') === 'none'): ?>
                    <span class="hint">در صف بررسی ادمین</span>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php layout_footer(); ?>
