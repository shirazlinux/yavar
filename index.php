<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/campaigns.php';
require_once __DIR__ . '/lib/profile.php';

$cfg = app_config();
campaign_refresh_status();

$view = $_GET['view'] ?? 'all';
if (!in_array($view, ['all', 'hamyar', 'platform', 'community'], true)) {
    $view = 'all';
}

$sql = "SELECT id, display_name, platform_name, display_mode, slug, bio, activity, avatar, services
        FROM users WHERE status='approved' AND is_admin=0 AND COALESCE(role,'hamyar')='hamyar'";
if ($view === 'hamyar') {
    $sql .= " AND COALESCE(display_mode,'personal')='personal'";
} elseif ($view === 'platform') {
    $sql .= " AND display_mode='platform'";
} elseif ($view === 'community') {
    $sql .= " AND display_mode='community'";
}
$sql .= " ORDER BY updated_at DESC LIMIT 60";
$people = db()->query($sql)->fetchAll();

$campaigns = db()->query(
    "SELECT c.*, u.display_name AS activist_name, u.platform_name, u.display_mode, u.slug AS activist_slug
     FROM campaigns c
     JOIN users u ON u.id = c.user_id
     WHERE c.status='active' AND u.status='approved'
     ORDER BY c.deadline ASC LIMIT 24"
)->fetchAll();

layout_header(
    'یاور',
    'یاور، یاری‌رسان حامیان نرم‌افزار آزاد — پلتفرم عام‌المنفعه برای حمایت مستقیم از فعالان، جوامع و پروژه‌های نرم‌افزار آزاد؛ بدون کارمزد پلتفرم.'
);
?>
<section class="hero">
  <div class="container hero-grid">
    <div>
      <div class="eyebrow">یاور · پلتفرم حمایت · بدون کارمزد</div>
      <h1>حمایت مستقیم از<br><em>پروژه‌ها و جوامع نرم‌افزار آزاد</em></h1>
      <p class="lead">
        یاور پلتفرم حمایت از پروژه‌ها و جوامع نرم‌افزار آزاد است؛ حمایت مستقیم و بدون واسطه.
        تمام مبلغ بدون کارمزد پلتفرم به دست اعضا می‌رسد.
      </p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="/register.php">ساخت حساب</a>
        <a class="btn btn-ghost" href="/login.php">ورود</a>
        <a class="btn btn-ghost" href="/#directory">فهرست</a>
      </div>
      <p class="hint" style="margin-top:1rem;max-width:36rem">
        حمایت بدون ورود امکان‌پذیر است. می‌توانید مستقیماً از صفحه هر فعال یا پروژه حمایت کنید.
      </p>
    </div>
    <aside class="card">
      <h2 style="margin-top:0">چطور شروع کنم؟</h2>
      <div class="steps">
        <div class="step"><strong>حمایت بدون ورود</strong> نیازی به ساخت حساب نیست. مستقیم برو سراغ صفحه فعال یا پروژه مورد علاقه‌ات و حمایت کن.</div>
        <div class="step"><strong>حساب کاربری (اختیاری)</strong> اگر دوست داری فعالان را دنبال کنی و از به‌روزرسانی‌ها باخبر بشی، یک حساب بساز.</div>
        <div class="step"><strong>اگر خودت حمایت دریافت می‌کنی</strong> صفحه خودت را بساز. هر وقت آماده بودی می‌تونی فعالش کنی.</div>
        <div class="step"><strong>هر دو نقش</strong> می‌تونی همزمان صفحه داشته باشی و از بقیه هم حمایت کنی. هیچ محدودیتی نیست.</div>
      </div>
    </aside>
  </div>
</section>

<?php if ($campaigns): ?>
<section id="campaigns">
  <div class="container">
    <h2 class="section-title">کمپین‌های حمایت</h2>
    <div class="product-grid">
      <?php foreach ($campaigns as $c):
        $prog = campaign_progress($c); ?>
        <article class="card product-card">
          <div class="hint"><?= e($c['display_mode']==='platform' && $c['platform_name'] ? $c['platform_name'] : $c['activist_name']) ?></div>
          <h3 class="product-title" style="font-size:1.05rem"><?= e($c['title']) ?></h3>
          <div class="product-price" style="font-size:1.05rem"><?= e(money_fa($prog['raised'])) ?> / <?= e(money_fa($prog['goal'])) ?></div>
          <div class="hint">مهلت: <?= e(gregorian_to_jalali_str($c['deadline'])) ?></div>
          <a class="btn btn-primary" href="/c/<?= e(rawurlencode($c['slug'])) ?>">حمایت کنید</a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php else: ?>
<section id="campaigns" hidden></section>
<?php endif; ?>

<section id="directory">
  <div class="container">
    <h2 class="section-title">فهرست پروژه‌ها و توسعه‌دهندگان</h2>
    <div class="filter-tabs" style="margin-bottom:1.25rem">
      <a class="<?= $view==='all'?'active':'' ?>" href="?view=all#directory">همه</a>
      <a class="<?= $view==='hamyar'?'active':'' ?>" href="?view=hamyar#directory">فعالان</a>
      <a class="<?= $view==='platform'?'active':'' ?>" href="?view=platform#directory">پروژه‌ها</a>
      <a class="<?= $view==='community'?'active':'' ?>" href="?view=community#directory">جامعه</a>
    </div>
    <p class="section-lead">
      <?php if ($view === 'hamyar'): ?>صفحه‌های فعالان نرم‌افزار آزاد
      <?php elseif ($view === 'platform'): ?>صفحه‌های پروژه‌های نرم‌افزار آزاد
      <?php elseif ($view === 'community'): ?>صفحه‌های جوامع نرم‌افزار آزاد
      <?php else: ?>صفحه‌های فعالان، پروژه‌ها و جوامع نرم‌افزار آزاد
      <?php endif; ?>
    </p>
    <?php if (!$people): ?>
      <div class="card"><p class="hint" style="margin:0">موردی در این فیلتر نیست. <a href="/register.php">اولین صفحه را بسازید</a>.</p></div>
    <?php else: ?>
      <div class="causes">
        <?php foreach ($people as $a):
          $tot = user_total_paid((int)$a['id']);
          $name = member_public_name($a);
          $badge = member_type_badge($a);
          ?>
          <article class="cause activist-card">
            <div style="display:flex;gap:.75rem;align-items:center">
              <img src="<?= e(member_avatar_url($a)) ?>" alt="" width="48" height="48" style="border-radius:12px">
              <div>
                <div class="hint"><?= e($badge) ?></div>
                <h3 style="margin:0"><?= e($name) ?></h3>
              </div>
            </div>
            <p><?= e(mb_substr($a['bio'] ?: $a['activity'], 0, 140)) ?><?= mb_strlen($a['bio'] ?: $a['activity']) > 140 ? '…' : '' ?></p>
            <p class="hint" style="margin:0">جمع حمایت: <strong><?= e(money_fa($tot['sum'])) ?></strong></p>
            <a class="btn btn-ghost" href="/u/<?= e(rawurlencode($a['slug'])) ?>">مشاهده و حمایت</a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section id="policy-home">
  <div class="container">
    <div class="policy-box">
      <h2>سیاست یاور</h2>
      <ul>
        <li>این بستر فقط برای حمایت از فعالان و پروژه‌های نرم‌افزار آزاد است.</li>
        <li>پروژه‌ها یا فعالیت‌های غیرمرتبط یا غیرازاد تأیید نمی‌شوند.</li>
        <li>هیچ کارمزدی از مبلغ حمایت کسر نمی‌شود.</li>
      </ul>
      <a href="/about.php">خط‌مشی کامل ←</a>
    </div>
  </div>
</section>
<?php layout_footer(); ?>
