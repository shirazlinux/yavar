<?php
declare(strict_types=1);
/**
 * لاگ رویدادهای امنیتی — فقط ادمین
 */
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/RateLimit.php';
auth_require_admin();

$kind = (string) ($_GET['kind'] ?? '');
$kinds = [
    '' => 'همه',
    'login_fail' => 'ورود ناموفق',
    'login_rate_limit' => 'محدودیت ورود',
    'admin_login' => 'ورود ادمین',
    'start_csrf_fail' => 'CSRF پرداخت',
    'start_rate_limit' => 'محدودیت پرداخت',
    'otp_rate_ip' => 'OTP IP',
    'otp_rate_phone' => 'OTP موبایل',
    'report_transfer' => 'اعلام واریز',
    'forgot_rate_limit' => 'فراموشی رمز',
];

if ($kind !== '' && !isset($kinds[$kind])) {
    $kind = '';
}

if ($kind === '') {
    $rows = db()->query(
        'SELECT id, kind, ip, detail, created_at FROM security_events ORDER BY id DESC LIMIT 200'
    )->fetchAll();
} else {
    $st = db()->prepare(
        'SELECT id, kind, ip, detail, created_at FROM security_events WHERE kind=? ORDER BY id DESC LIMIT 200'
    );
    $st->execute([$kind]);
    $rows = $st->fetchAll();
}

$since = gmdate('c', time() - 7 * 86400);
$stc = db()->prepare(
    'SELECT kind, COUNT(*) AS c FROM security_events WHERE created_at >= ? GROUP BY kind ORDER BY c DESC'
);
$stc->execute([$since]);
$counts = $stc->fetchAll();

layout_header('رویدادهای امنیتی');
?>
<section class="page-section">
  <div class="container">
    <h1 class="page-title">رویدادهای امنیتی</h1>
    <p class="page-lead">
      <a href="/admin/">← پنل مدیریت</a>
      · <a href="/admin/settlements.php">تسویه</a>
    </p>

    <div class="card" style="margin-bottom:1rem">
      <h2 style="margin-top:0;font-size:1.05rem">۷ روز اخیر</h2>
      <?php if (!$counts): ?>
        <p class="hint">هنوز رویدادی ثبت نشده.</p>
      <?php else: ?>
        <ul class="hint" style="margin:0;padding-inline-start:1.2rem">
          <?php foreach ($counts as $c): ?>
            <li><code><?= e($c['kind']) ?></code>: <?= e(number_fa((int)$c['c'])) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="filter-tabs">
      <?php foreach ($kinds as $k => $lab): ?>
        <a class="<?= $kind === $k ? 'active' : '' ?>" href="?kind=<?= e(rawurlencode($k)) ?>"><?= e($lab) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="card" style="margin-top:1rem">
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>#</th><th>نوع</th><th>IP</th><th>جزئیات</th><th>زمان (UTC)</th></tr></thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="5" class="hint">موردی نیست.</td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><code><?= e($r['kind']) ?></code></td>
              <td dir="ltr"><code><?= e($r['ip']) ?></code></td>
              <td><?= e($r['detail']) ?></td>
              <td dir="ltr"><small><?= e($r['created_at']) ?></small></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>
<?php layout_footer(); ?>
