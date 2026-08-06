<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/campaigns.php';

$slug = $_GET['slug'] ?? '';
if ($slug === '' && !empty($_SERVER['REQUEST_URI'])) {
    if (preg_match('#/c/([A-Za-z0-9\-_]+)#', $_SERVER['REQUEST_URI'], $m)) {
        $slug = $m[1];
    }
}
$slug = slugify((string) $slug);

$st = db()->prepare('SELECT c.*, u.display_name, u.slug AS user_slug, u.status AS user_status FROM campaigns c JOIN users u ON u.id=c.user_id WHERE c.slug=?');
$st->execute([$slug]);
$c = $st->fetch();
if (!$c || $c['user_status'] !== 'approved') {
    http_response_code(404);
    layout_header('یافت نشد');
    echo '<section class="page-section"><div class="container narrow"><h1>یافت نشد</h1><a href="/">خانه</a></div></section>';
    layout_footer();
    exit;
}

campaign_refresh_status((int) $c['id']);
$st->execute([$slug]);
$c = $st->fetch();

$prog = campaign_progress($c);
$tiers = campaign_parse_tiers((string) $c['tiers_json']);
if (!$tiers) {
    $cfg = app_config();
    $tiers = $cfg['preset_amounts'] ?? [50000, 100000];
}
$canPay = $c['status'] === 'active';

layout_header($c['title'], mb_substr($c['description'], 0, 160));
?>
<section class="page-section">
  <div class="container hero-grid">
    <div>
      <div class="eyebrow">حمایت · <?= e(campaign_status_label($c['status'])) ?> · بدون کارمزد پلتفرم</div>
      <h1 class="page-title"><?= e($c['title']) ?></h1>
      <p class="page-lead">فعال: <a href="/u/<?= e(rawurlencode($c['user_slug'])) ?>"><?= e($c['display_name']) ?></a></p>
      <div class="card" style="box-shadow:none">
        <p style="margin:0;white-space:pre-wrap"><?= e($c['description']) ?></p>
      </div>
      <div class="stats-grid" style="grid-template-columns:1fr 1fr 1fr;margin-top:1rem">
        <div class="card stat"><div class="stat-label">جمع‌شده</div><div class="stat-value" style="font-size:1.1rem"><?= e(money_fa($prog['raised'])) ?></div></div>
        <div class="card stat"><div class="stat-label">هدف</div><div class="stat-value" style="font-size:1.1rem"><?= e(money_fa($prog['goal'])) ?></div></div>
        <div class="card stat"><div class="stat-label">مهلت</div><div class="stat-value" style="font-size:1.1rem"><?= e(jdate_format($c['deadline'] . 'T12:00:00Z', 'Y/m/d')) ?></div></div>
      </div>
      <p class="hint">پیشرفت: <?= fa_digits((string)$prog['percent']) ?>٪</p>
      <?php if ($c['status'] === 'failed'): ?>
        <div class="form-msg show error">مهلت تمام شد و هدف تکمیل نشد. حمایت‌ها در مسیر بازگشت وجه قرار دارند.</div>
      <?php elseif ($c['status'] === 'successful'): ?>
        <div class="form-msg show ok">هدف کمپین تکمیل شده است.</div>
      <?php endif; ?>
    </div>

    <aside class="card" id="donate">
      <h2 style="margin-top:0">حمایت از این هدف</h2>
      <?php if (!$canPay): ?>
        <p class="hint">در حال حاضر امکان پرداخت برای این کمپین وجود ندارد.</p>
      <?php else: ?>
        <p class="sub">مبالغ ثابت تعریف‌شده توسط فعال</p>
        <p class="hint">
          نام و پیام شما می‌تواند در صفحهٔ فعال نمایش داده شود (اگر خودش نمایش حمایت‌ها را روشن کرده باشد).
          برای ناشناس ماندن، تیک «انتشار به‌صورت ناشناس» را بزنید.
        </p>
        <form id="donate-form" data-campaign="<?= (int) $c['id'] ?>" data-activist="<?= (int) $c['user_id'] ?>" novalidate>
          <div class="amounts">
            <?php foreach ($tiers as $i => $p): ?>
              <button type="button" class="amount-btn<?= $i === 0 ? ' active' : '' ?>" data-amount="<?= (int) $p ?>"><?= number_format((int) $p) ?></button>
            <?php endforeach; ?>
          </div>
          <label for="amount">مبلغ (ثابت)</label>
          <input type="number" id="amount" name="amount" readonly value="<?= (int) $tiers[0] ?>" required>
          <label for="name">نام (اختیاری)</label>
          <input type="text" id="name" maxlength="80" placeholder="نام نمایشی در صورت انتشار">
          <label for="message">پیام (اختیاری)</label>
          <textarea id="message" rows="2" maxlength="500"></textarea>
          <label class="check-line"><input type="checkbox" id="public_post" checked> اجازهٔ نمایش در فهرست حمایت‌های صفحه</label>
          <label class="check-line"><input type="checkbox" id="anonymous"> انتشار به‌صورت ناشناس (حامی ناشناس)</label>
          <div class="hp"><input type="text" id="website" tabindex="-1" autocomplete="off"></div>
          <input type="hidden" id="cause" value="campaign">
          <input type="hidden" id="campaign_id" value="<?= (int) $c['id'] ?>">
          <input type="hidden" id="activist_id" value="<?= (int) $c['user_id'] ?>">
          <button type="submit" class="btn btn-primary" id="submit-btn">پرداخت و حمایت</button>
          <p class="form-note">بدون کارمزد</p>
          <div id="form-msg" class="form-msg" role="status"></div>
        </form>
      <?php endif; ?>
    </aside>
  </div>
</section>
<?php layout_footer(); ?>
