<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/feed.php';
$user = auth_require_login();
$cfg = app_config();
$uid = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf'] ?? null)) {
    if (($_POST['action'] ?? '') === 'budget') {
        $b = (int) preg_replace('/\D+/', '', (string)($_POST['monthly_budget'] ?? '0'));
        $feed = !empty($_POST['feed_enabled']) ? 1 : 0;
        db()->prepare('UPDATE users SET monthly_budget=?, feed_enabled=?, updated_at=? WHERE id=?')
            ->execute([max(0,$b), $feed, gmdate('c'), $uid]);
        $st = db()->prepare('SELECT * FROM users WHERE id=?');
        $st->execute([$uid]);
        $user = $st->fetch() ?: $user;
    }
}

// donations by this supporter (by email match on donor? we don't store donor user id)
// For now show likes + pledges + feed. Optionally store supporter_id on donations later.
$likes = db()->prepare('SELECT * FROM supporter_likes WHERE user_id=? ORDER BY id DESC');
$likes->execute([$uid]);
$likes = $likes->fetchAll();

$pledges = db()->prepare('SELECT * FROM monthly_pledges WHERE user_id=? AND active=1 ORDER BY id DESC');
$pledges->execute([$uid]);
$pledges = $pledges->fetchAll();

$feed = !empty($user['feed_enabled']) ? feed_list(30) : [];

$likedHamyars = [];
foreach ($likes as $L) {
    if ($L['target_type'] === 'hamyar') {
        $st = db()->prepare("SELECT id, display_name, platform_name, display_mode, slug, avatar FROM users WHERE id=? AND status='approved'");
        $st->execute([(int)$L['target_id']]);
        if ($r = $st->fetch()) $likedHamyars[] = $r;
    }
}

layout_header('پنل حامی');
require_once dirname(__DIR__) . '/lib/profile.php';
?>
<section class="page-section">
  <div class="container">
    <h1 class="page-title">سلام، <?= e($user['display_name']) ?></h1>
    <p class="page-lead">پنل حامی — علاقه‌مندی‌ها، هدف ماهانه، خبرخوان کمپین‌ها</p>
    <div class="policy-box" style="margin-bottom:1rem">
      <strong>نکته:</strong> برای حمایت کردن ورود لازم نیست. ورود برای دنبال‌کردن و خبرخوان است.
      می‌توانید <a href="/dashboard/enable-page.php">صفحه حمایت</a> هم فعال کنید و همزمان حامی دیگران باشید.
    </div>

    <div class="stats-grid">
      <div class="card stat"><div class="stat-label">بودجه ماهانه</div><div class="stat-value"><?= e(money_fa((int)($user['monthly_budget'] ?? 0))) ?></div></div>
      <div class="card stat"><div class="stat-label">علاقه‌مندی‌ها</div><div class="stat-value"><?= e(number_fa(count($likes))) ?></div></div>
      <div class="card stat"><div class="stat-label">هدف‌های ماهانه فعال</div><div class="stat-value"><?= e(number_fa(count($pledges))) ?></div></div>
    </div>

    <div class="card form-card" style="margin-top:1rem">
      <h2 style="margin-top:0">تنظیمات حمایت ماهانه</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="budget">
        <label>سقف / هدف حمایت ماهانه (تومان)</label>
        <input name="monthly_budget" class="ltr-field money-input" dir="ltr" value="<?= e(number_format((int)($user['monthly_budget']??0))) ?>">
        <label class="check-line"><input type="checkbox" name="feed_enabled" value="1" <?= !empty($user['feed_enabled'])?'checked':'' ?>> عضویت در خبرخوان کمپین‌های فعال</label>
        <button class="btn btn-primary" type="submit">ذخیره</button>
      </form>
    </div>

    <div class="card" style="margin-top:1rem">
      <h2 style="margin-top:0">فعالان / پروژه‌های مورد علاقه</h2>
      <?php if (!$likedHamyars): ?>
        <p class="hint">هنوز کسی را لایک نکرده‌اید. از صفحه فعالان دکمه «علاقه‌مندی» را بزنید.</p>
      <?php else: ?>
        <div class="causes">
          <?php foreach ($likedHamyars as $h): ?>
            <article class="cause">
              <h3 style="margin:0"><?= e(member_public_name($h)) ?></h3>
              <a class="btn btn-ghost" style="width:auto;margin-top:.5rem" href="/u/<?= e(rawurlencode($h['slug'])) ?>">مشاهده و حمایت</a>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card" style="margin-top:1rem">
      <h2 style="margin-top:0">اهداف ماهانه اختصاصی</h2>
      <?php if (!$pledges): ?>
        <p class="hint">برای هر فعال می‌توانید در صفحه‌اش مبلغ ماهانه هدف تعیین کنید.</p>
      <?php else: ?>
        <ul class="hint">
          <?php foreach ($pledges as $p): ?>
            <li><?= e($p['target_type']) ?> #<?= (int)$p['target_id'] ?> — <?= e(money_fa((int)$p['amount'])) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <?php if ($feed): ?>
    <div class="card" style="margin-top:1rem">
      <h2 style="margin-top:0">خبرخوان</h2>
      <?php foreach ($feed as $f): ?>
        <article class="post-item" style="margin:.75rem 0;padding:.75rem;border-right:3px solid var(--brand)">
          <strong><?= e($f['title']) ?></strong>
          <p class="hint" style="margin:.25rem 0 0"><?= e($f['body']) ?></p>
          <div class="hint"><?= e(jdate_format($f['created_at'], 'Y/m/d H:i')) ?></div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<script src="/assets/js/money.js?v=1" defer></script>
<?php layout_footer(); ?>
