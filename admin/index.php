<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/Notify.php';
require_once dirname(__DIR__) . '/lib/PayPing.php';
require_once dirname(__DIR__) . '/lib/campaigns.php';
require_once dirname(__DIR__) . '/lib/pages.php';
$admin = auth_require_admin();
pages_ensure_backfill(db());
campaign_refresh_status();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    $reason = trim((string) ($_POST['reject_reason'] ?? ''));

    // تأیید/رد صفحات اضافی (غیر از صفحهٔ اصلی حساب)
    $pageId = (int) ($_POST['page_id'] ?? 0);
    if ($pageId > 0 && in_array($action, ['approve_page', 'reject_page', 'pending_page'], true)) {
        $status = $action === 'approve_page' ? 'approved' : ($action === 'reject_page' ? 'rejected' : 'pending');
        $p = page_by_id($pageId);
        if ($p && (int) ($p['is_primary'] ?? 0) === 0) {
            page_set_status($pageId, $status, $reason);
            $st = db()->prepare('SELECT display_name, phone, email FROM users WHERE id=? AND is_admin=0');
            $st->execute([(int) $p['user_id']]);
            $u = $st->fetch();
            if ($u) {
                $name = (string) $u['display_name'];
                $title = (string) ($p['title'] ?? '');
                $slug = (string) ($p['slug'] ?? '');
                if ($action === 'approve_page') {
                    Notify::approved(
                        (string) ($u['phone'] ?? ''),
                        (string) ($u['email'] ?? ''),
                        $name,
                        $slug
                    );
                } elseif ($action === 'reject_page') {
                    Notify::rejected(
                        (string) ($u['phone'] ?? ''),
                        (string) ($u['email'] ?? ''),
                        $name,
                        $reason !== '' ? $reason : ('صفحه «' . $title . '» تأیید نشد.')
                    );
                }
            }
        }
        header('Location: /admin/?focus=pages');
        exit;
    }

    $id = (int) ($_POST['user_id'] ?? 0);
    if ($id && in_array($action, ['approve', 'reject', 'pending'], true)) {
        $status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'pending');
        $st = db()->prepare('UPDATE users SET status=?, reject_reason=?, updated_at=? WHERE id=? AND is_admin=0');
        $st->execute([$status, $action === 'reject' ? $reason : '', gmdate('c'), $id]);
        pages_approve_primary_for_user($id, $status);

        $st = db()->prepare('SELECT display_name, phone, slug, email FROM users WHERE id=?');
        $st->execute([$id]);
        $u = $st->fetch();
        if ($u) {
            if ($action === 'approve') {
                Notify::approved(
                    (string) ($u['phone'] ?? ''),
                    (string) ($u['email'] ?? ''),
                    (string) $u['display_name'],
                    (string) $u['slug']
                );
            } elseif ($action === 'reject') {
                Notify::rejected(
                    (string) ($u['phone'] ?? ''),
                    (string) ($u['email'] ?? ''),
                    (string) $u['display_name'],
                    $reason
                );
            }
        }
    }
    header('Location: /admin/');
    exit;
}

// --- Stats ---
$pdo = db();
$stat = [
    'users_total' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin=0")->fetchColumn(),
    'hamyar_approved' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin=0 AND status='approved' AND COALESCE(role,'hamyar')='hamyar'")->fetchColumn(),
    'pending' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin=0 AND status='pending'")->fetchColumn(),
    'pages_pending' => pages_pending_extra_count(),
    'supporters' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin=0 AND role='supporter'")->fetchColumn(),
    'campaigns_active' => (int) $pdo->query("SELECT COUNT(*) FROM campaigns WHERE status='active'")->fetchColumn(),
    'paid_sum' => (int) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='paid'")->fetchColumn(),
    'paid_count' => (int) $pdo->query("SELECT COUNT(*) FROM donations WHERE status='paid'")->fetchColumn(),
    'ready_sum' => (int) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='paid' AND settlement_status='ready'")->fetchColumn(),
    'ready_count' => (int) $pdo->query("SELECT COUNT(*) FROM donations WHERE status='paid' AND settlement_status='ready'")->fetchColumn(),
    'refund_sum' => (int) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='paid' AND settlement_status='refund_pending'")->fetchColumn(),
    'settled_sum' => (int) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='paid' AND settlement_status='settled'")->fetchColumn(),
];

$filter = $_GET['status'] ?? 'pending';
if (!in_array($filter, ['pending', 'approved', 'rejected', 'all'], true)) {
    $filter = 'pending';
}
if ($filter === 'all') {
    $users = $pdo->query('SELECT * FROM users WHERE is_admin=0 ORDER BY id DESC')->fetchAll();
} else {
    $st = $pdo->prepare('SELECT * FROM users WHERE is_admin=0 AND status=? ORDER BY id DESC');
    $st->execute([$filter]);
    $users = $st->fetchAll();
}

$cfg = app_config();
layout_header('مدیریت — آمار و تأیید');
?>
<section class="page-section">
  <div class="container">
    <h1 class="page-title">پنل مدیریت</h1>
    <p class="page-lead">
      <a href="/admin/settlements.php">تسویه و مبالغ تسویه‌نشده ←</a>
      · <a href="/admin/pages.php"><strong>تأیید صفحات اضافی<?= $stat['pages_pending'] ? ' (' . e(fa_digits((string) $stat['pages_pending'])) . ')' : '' ?></strong></a>
      · <a href="/admin/campaigns.php">کمپین‌ها</a>
      · <a href="/admin/payping-connect.php">پی‌پینگ</a>
      · <a href="/admin/security.php">رویدادهای امنیتی</a>
    </p>

    <div class="stats-grid">
      <div class="card stat"><div class="stat-label">کل حساب‌ها</div><div class="stat-value"><?= e(number_fa($stat['users_total'])) ?></div></div>
      <div class="card stat"><div class="stat-label">فعال/پروژه تأییدشده</div><div class="stat-value"><?= e(number_fa($stat['hamyar_approved'])) ?></div></div>
      <div class="card stat"><div class="stat-label">حساب در انتظار</div><div class="stat-value"><?= e(number_fa($stat['pending'])) ?></div></div>
      <div class="card stat"><div class="stat-label">صفحهٔ اضافی در انتظار</div><div class="stat-value" style="<?= $stat['pages_pending'] ? 'color:#fcd34d' : '' ?>"><?= e(number_fa($stat['pages_pending'])) ?></div></div>
      <div class="card stat"><div class="stat-label">حامیان</div><div class="stat-value"><?= e(number_fa($stat['supporters'])) ?></div></div>
      <div class="card stat"><div class="stat-label">کمپین فعال</div><div class="stat-value"><?= e(number_fa($stat['campaigns_active'])) ?></div></div>
      <div class="card stat"><div class="stat-label">جمع پرداخت‌های موفق</div><div class="stat-value" style="font-size:1.1rem"><?= e(money_fa($stat['paid_sum'])) ?></div></div>
      <div class="card stat"><div class="stat-label">تسویه‌نشده (آماده)</div><div class="stat-value" style="font-size:1.1rem;color:#fcd34d"><?= e(money_fa($stat['ready_sum'])) ?></div><div class="hint"><?= e(number_fa($stat['ready_count'])) ?> تراکنش</div></div>
      <div class="card stat"><div class="stat-label">در صف بازگشت وجه</div><div class="stat-value" style="font-size:1.1rem"><?= e(money_fa($stat['refund_sum'])) ?></div></div>
      <div class="card stat"><div class="stat-label">تسویه‌شده تا کنون</div><div class="stat-value" style="font-size:1.1rem"><?= e(money_fa($stat['settled_sum'])) ?></div></div>
    </div>

    <?php if ($stat['ready_sum'] > 0): ?>
      <div class="form-msg show manual" style="margin-top:1rem">
        مبلغ <strong><?= e(money_fa($stat['ready_sum'])) ?></strong> آماده تسویه است.
        <a href="/admin/settlements.php?s=ready">رفتن به تسویه و تیک‌زدن ←</a>
      </div>
    <?php endif; ?>

    <?php
      $pendingPages = pages_admin_list('pending');
    ?>
    <h2 id="pages" style="margin-top:2rem">تأیید صفحات اضافی</h2>
    <p class="hint">صفحاتی که کاربر علاوه بر صفحهٔ اصلی‌اش ساخته؛ تا تأیید نشوند عمومی نمی‌شوند.
      <a href="/admin/pages.php">مدیریت کامل صفحات ←</a>
    </p>
    <?php if (!$pendingPages): ?>
      <div class="card"><p class="hint" style="margin:0">صفحهٔ اضافی در انتظار نیست.</p></div>
    <?php else: ?>
      <?php foreach ($pendingPages as $p): ?>
        <article class="card" style="margin-bottom:1rem;border-color:rgba(251,191,36,.35)">
          <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div>
              <div class="hint"><?= e(page_kind_label($p['kind'] ?? '')) ?> · صاحب: <?= e((string) ($p['owner_display_name'] ?? '')) ?> (<?= e((string) ($p['owner_slug'] ?? '')) ?>)</div>
              <h3 style="margin:.25rem 0;font-size:1.1rem"><?= e((string) ($p['title'] ?? '')) ?></h3>
              <div class="hint" dir="ltr"><a href="/u/<?= e(rawurlencode((string) ($p['slug'] ?? ''))) ?>" target="_blank" rel="noopener">/u/<?= e((string) ($p['slug'] ?? '')) ?></a></div>
              <p style="margin:.55rem 0 0"><?= e(mb_substr((string) ($p['activity'] ?? ''), 0, 280)) ?></p>
              <?php if (trim((string) ($p['git_url'] ?? '')) !== ''): ?>
                <p class="hint" dir="ltr"><a href="<?= e((string) $p['git_url']) ?>" target="_blank" rel="noopener"><?= e((string) $p['git_url']) ?></a></p>
              <?php endif; ?>
              <?php if (trim((string) ($p['website_url'] ?? '')) !== ''): ?>
                <p class="hint" dir="ltr"><a href="<?= e((string) $p['website_url']) ?>" target="_blank" rel="noopener"><?= e((string) $p['website_url']) ?></a></p>
              <?php endif; ?>
            </div>
            <span class="badge badge-warn">در انتظار</span>
          </div>
          <form method="post" class="admin-actions" style="margin-top:.85rem">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="page_id" value="<?= (int) $p['id'] ?>">
            <input type="text" name="reject_reason" placeholder="دلیل رد (اختیاری)" class="admin-actions__reason">
            <div class="admin-actions__row">
              <button class="btn btn-primary admin-btn" name="action" value="approve_page" type="submit">تأیید صفحه</button>
              <button class="btn btn-danger-ghost admin-btn" name="action" value="reject_page" type="submit">رد صفحه</button>
            </div>
          </form>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>

    <h2 style="margin-top:2rem">تأیید حساب‌ها</h2>
    <div class="filter-tabs">
      <?php foreach (['pending' => 'در انتظار', 'approved' => 'تأییدشده', 'rejected' => 'ردشده', 'all' => 'همه'] as $k => $lab): ?>
        <a class="<?= $filter === $k ? 'active' : '' ?>" href="?status=<?= e($k) ?>"><?= e($lab) ?></a>
      <?php endforeach; ?>
    </div>

    <?php if (!$users): ?>
      <div class="card"><p class="hint">موردی نیست.</p></div>
    <?php endif; ?>

    <?php foreach ($users as $u):
        $pageUrl = rtrim($cfg['site_url'], '/') . '/u/' . rawurlencode($u['slug']);
        $pagePath = '/u/' . $u['slug'];
        $role = ($u['role'] ?? 'hamyar') === 'supporter' ? 'حامی' : 'فعال/پروژه';
        $stSum = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM donations WHERE user_id=? AND status='paid' AND settlement_status='ready'");
        $stSum->execute([(int)$u['id']]);
        $unpaid = (int) $stSum->fetchColumn();
    ?>
      <article class="card admin-user">
        <div class="admin-user-head">
          <div>
            <h2 style="margin:0"><?= e($u['display_name']) ?>
              <span class="badge badge-<?= $u['status'] === 'approved' ? 'ok' : ($u['status'] === 'rejected' ? 'err' : 'warn') ?>"><?= e($u['status']) ?></span>
              <span class="hint">(<?= e($role) ?>)</span>
            </h2>
            <div class="hint">
              <span dir="ltr"><?= e($u['email']) ?></span>
              <?php if (!empty($u['phone'])): ?> · <span dir="ltr"><?= e($u['phone']) ?></span><?php endif; ?>
              · <a href="<?= e($pagePath) ?>" target="_blank" rel="noopener" dir="ltr"><?= e($pageUrl) ?></a>
            </div>
            <?php if ($unpaid > 0): ?>
              <div class="hint" style="color:#fcd34d">تسویه‌نشده: <?= e(money_fa($unpaid)) ?></div>
            <?php endif; ?>
          </div>
          <div class="hint">ثبت: <?= e(jdate_format($u['created_at'], 'Y/m/d H:i')) ?></div>
        </div>
        <p><strong>فعالیت:</strong><br><?= nl2br(e($u['activity'] ?? '')) ?></p>
        <?php if (!empty($u['bio'])): ?><p><strong>بیو:</strong><br><?= nl2br(e($u['bio'])) ?></p><?php endif; ?>
        <?php
          require_once dirname(__DIR__) . '/lib/profile.php';
          $admPresence = member_primary_presence_link($u);
        ?>
        <?php if ($admPresence): ?>
          <p><strong><?= e($admPresence['label']) ?>:</strong>
            <a href="<?= e($admPresence['url']) ?>" target="_blank" rel="noopener" dir="ltr"><?= e($admPresence['text']) ?></a>
          </p>
        <?php else: ?>
          <p class="hint">لینک محل فعالیت / مخزن ثبت نشده.</p>
        <?php endif; ?>
        <div class="admin-bank">
          <div>شبا: <code dir="ltr"><?= e($u['sheba'] ?: '—') ?></code></div>
          <div>کارت: <code dir="ltr"><?= e($u['card_number'] ?: '—') ?></code></div>
        </div>
        <form method="post" class="admin-actions">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
          <input type="text" name="reject_reason" placeholder="دلیل رد (اختیاری)" class="admin-actions__reason">
          <div class="admin-actions__row">
            <button class="btn btn-primary admin-btn" name="action" value="approve" type="submit">تأیید</button>
            <button class="btn btn-ghost admin-btn" name="action" value="pending" type="submit">در انتظار</button>
            <button class="btn btn-danger-ghost admin-btn" name="action" value="reject" type="submit">رد</button>
          </div>
        </form>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php layout_footer(); ?>
