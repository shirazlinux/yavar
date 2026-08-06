<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/campaigns.php';
require_once dirname(__DIR__) . '/lib/Notify.php';

auth_require_admin();
campaign_refresh_status();

$flash = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['campaign_id'] ?? 0);
    if ($action === 'cancel' && $id > 0) {
        $res = campaign_cancel($id, null, true);
        if (!empty($res['ok'])) {
            $flash = $res['message'] ?? 'لغو شد.';
            $c = $res['campaign'] ?? null;
            if ($c) {
                Notify::admin(
                    'لغو کمپین توسط مدیریت',
                    "کمپین توسط ادمین لغو شد.\nعنوان: {$c['title']}\nصاحب user_id: {$c['user_id']}\n" .
                    (!empty($res['refund_count']) ? "بازگشت وجه: {$res['refund_count']} مورد\n" : '')
                );
            }
        } else {
            $err = $res['message'] ?? 'خطا';
        }
    }
}

$filter = $_GET['s'] ?? 'active';
if (!in_array($filter, ['active', 'draft', 'cancelled', 'successful', 'failed', 'all'], true)) {
    $filter = 'active';
}
if ($filter === 'all') {
    $rows = db()->query(
        "SELECT c.*, u.display_name, u.email, u.slug AS user_slug
         FROM campaigns c JOIN users u ON u.id=c.user_id
         ORDER BY c.id DESC LIMIT 200"
    )->fetchAll();
} else {
    $st = db()->prepare(
        "SELECT c.*, u.display_name, u.email, u.slug AS user_slug
         FROM campaigns c JOIN users u ON u.id=c.user_id
         WHERE c.status=? ORDER BY c.id DESC LIMIT 200"
    );
    $st->execute([$filter]);
    $rows = $st->fetchAll();
}

layout_header('مدیریت کمپین‌ها');
?>
<section class="page-section">
  <div class="container">
    <h1 class="page-title">مدیریت کمپین‌ها</h1>
    <p class="page-lead">
      <a href="/admin/">← پنل مدیریت</a>
      · <a href="/admin/settlements.php">تسویه</a>
    </p>

    <?php if ($flash): ?><div class="form-msg show ok"><?= e($flash) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="form-msg show error"><?= e($err) ?></div><?php endif; ?>

    <div class="filter-tabs" style="margin-bottom:1rem">
      <?php foreach ([
        'active' => 'جاری',
        'draft' => 'پیش‌نویس',
        'cancelled' => 'لغو شده',
        'successful' => 'تکمیل',
        'failed' => 'ناتمام',
        'all' => 'همه',
      ] as $k => $lab): ?>
        <a class="<?= $filter === $k ? 'active' : '' ?>" href="?s=<?= e($k) ?>"><?= e($lab) ?></a>
      <?php endforeach; ?>
    </div>

    <?php if (!$rows): ?>
      <div class="card"><p class="hint" style="margin:0">موردی نیست.</p></div>
    <?php else: ?>
      <div class="card">
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>عنوان</th>
                <th>فعال</th>
                <th>وضعیت</th>
                <th>جمع / هدف</th>
                <th>مهلت</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $c):
              $prog = campaign_progress($c); ?>
              <tr>
                <td>
                  <?= e($c['title']) ?><br>
                  <a href="/c/<?= e(rawurlencode($c['slug'])) ?>" dir="ltr">/c/<?= e($c['slug']) ?></a>
                </td>
                <td>
                  <?= e($c['display_name']) ?><br>
                  <a href="/u/<?= e(rawurlencode($c['user_slug'])) ?>" class="hint">صفحه</a>
                </td>
                <td><?= e(campaign_status_label($c['status'])) ?></td>
                <td><?= e(money_fa($prog['raised'])) ?> / <?= e(money_fa($prog['goal'])) ?></td>
                <td><?= e(gregorian_to_jalali_str($c['deadline'])) ?></td>
                <td>
                  <?php if (in_array($c['status'], ['draft', 'active'], true)): ?>
                    <form method="post" onsubmit="return confirm('لغو کمپین «<?= e(addslashes($c['title'])) ?>» توسط مدیریت؟');">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="cancel">
                      <input type="hidden" name="campaign_id" value="<?= (int) $c['id'] ?>">
                      <button type="submit" class="btn btn-ghost" style="width:auto;padding:.35rem .7rem;font-size:.82rem;border-color:rgba(251,113,133,.45);color:#fecdd3">لغو کمپین</button>
                    </form>
                  <?php else: ?>
                    <span class="hint">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php layout_footer(); ?>
