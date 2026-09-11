<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/pages.php';
require_once dirname(__DIR__) . '/lib/Notify.php';
$admin = auth_require_admin();
pages_ensure_backfill(db());

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf'] ?? null)) {
    $id = (int) ($_POST['page_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $reason = trim((string) ($_POST['reject_reason'] ?? ''));
    if ($id && in_array($action, ['approve', 'reject', 'pending'], true)) {
        $status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'pending');
        $p = page_by_id($id);
        if ($p && (int) ($p['is_primary'] ?? 0) === 0) {
            page_set_status($id, $status, $reason);
            $st = db()->prepare('SELECT display_name, phone, email FROM users WHERE id=?');
            $st->execute([(int) $p['user_id']]);
            $u = $st->fetch();
            if ($u) {
                $name = (string) $u['display_name'];
                $title = (string) $p['title'];
                $slug = (string) $p['slug'];
                if ($action === 'approve') {
                    Notify::approved(
                        (string) ($u['phone'] ?? ''),
                        (string) ($u['email'] ?? ''),
                        $name,
                        $slug
                    );
                } elseif ($action === 'reject') {
                    Notify::rejected(
                        (string) ($u['phone'] ?? ''),
                        (string) ($u['email'] ?? ''),
                        $name,
                        $reason !== '' ? $reason : ('صفحه «' . $title . '» تأیید نشد.')
                    );
                }
            }
        }
    }
    $back = (string) ($_POST['back_status'] ?? 'pending');
    if (!in_array($back, ['pending', 'approved', 'rejected', 'archived', 'all'], true)) {
        $back = 'pending';
    }
    header('Location: /admin/pages.php?status=' . rawurlencode($back));
    exit;
}

$filter = $_GET['status'] ?? 'pending';
if (!in_array($filter, ['pending', 'approved', 'rejected', 'archived', 'all'], true)) {
    $filter = 'pending';
}
$list = pages_admin_list($filter);
$pendingN = pages_pending_extra_count();
$cfg = app_config();
layout_header('مدیریت صفحات');
?>
<section class="page-section">
  <div class="container">
    <h1 class="page-title">تأیید صفحات پروژه</h1>
    <p class="page-lead">
      <a href="/admin/">← پنل مدیریت</a>
      · صفحات اضافی هر حساب (صفحهٔ اصلی با تأیید حساب می‌آید)
    </p>
    <?php if ($pendingN > 0): ?>
      <div class="form-msg show manual"><?= e(fa_digits((string) $pendingN)) ?> صفحه در انتظار تأیید است.</div>
    <?php endif; ?>
    <div class="filter-tabs">
      <?php foreach (['pending' => 'در انتظار', 'approved' => 'تأییدشده', 'rejected' => 'ردشده', 'archived' => 'آرشیو', 'all' => 'همه'] as $k => $lab): ?>
        <a class="<?= $filter === $k ? 'active' : '' ?>" href="?status=<?= e($k) ?>"><?= e($lab) ?></a>
      <?php endforeach; ?>
    </div>
    <?php if (!$list): ?>
      <div class="card"><p class="hint">موردی نیست.</p></div>
    <?php endif; ?>
    <?php foreach ($list as $p):
      $badge = $p['status'] === 'approved' ? 'ok' : ($p['status'] === 'rejected' ? 'err' : 'warn');
    ?>
      <article class="card" style="margin-bottom:1rem">
        <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap">
          <div>
            <div class="hint"><?= e(page_kind_label($p['kind'])) ?> · صاحب: <?= e($p['owner_display_name']) ?> (<?= e($p['owner_slug']) ?>)</div>
            <h2 style="margin:.2rem 0;font-size:1.1rem"><?= e($p['title']) ?></h2>
            <div class="hint" dir="ltr">/u/<?= e($p['slug']) ?></div>
            <p style="margin:.6rem 0 0"><?= e(mb_substr((string) $p['activity'], 0, 280)) ?></p>
            <?php if (trim((string) $p['git_url']) !== ''): ?>
              <p class="hint" dir="ltr"><a href="<?= e($p['git_url']) ?>" target="_blank" rel="noopener"><?= e($p['git_url']) ?></a></p>
            <?php endif; ?>
            <?php if (trim((string) $p['website_url']) !== ''): ?>
              <p class="hint" dir="ltr"><a href="<?= e($p['website_url']) ?>" target="_blank" rel="noopener"><?= e($p['website_url']) ?></a></p>
            <?php endif; ?>
          </div>
          <span class="badge badge-<?= e($badge) ?>"><?= e(page_status_label($p['status'])) ?></span>
        </div>
        <form method="post" class="admin-actions" style="margin-top:.85rem">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="page_id" value="<?= (int) $p['id'] ?>">
          <input type="hidden" name="back_status" value="<?= e($filter) ?>">
          <input name="reject_reason" placeholder="دلیل رد (اختیاری)" value="" class="admin-actions__reason">
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
