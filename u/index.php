<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/campaigns.php';
require_once dirname(__DIR__) . '/lib/profile.php';

$slug = $_GET['slug'] ?? '';
if ($slug === '' && !empty($_SERVER['REQUEST_URI']) && preg_match('#/u/([A-Za-z0-9\-_]+)#', $_SERVER['REQUEST_URI'], $m)) {
    $slug = $m[1];
}
$slug = slugify((string) $slug);
$st = db()->prepare('SELECT * FROM users WHERE slug = ? AND status = ? AND is_admin = 0');
$st->execute([$slug, 'approved']);
$user = $st->fetch();
if (!$user) {
    http_response_code(404);
    layout_header('یافت نشد');
    echo '<section class="page-section"><div class="container narrow"><h1>صفحه یافت نشد</h1><a href="/">خانه</a></div></section>';
    layout_footer();
    exit;
}

$cfg = app_config();
$presets = $cfg['preset_amounts'] ?? [50000, 100000, 200000];
$tot = user_total_paid((int) $user['id']);
$publicName = member_public_name($user);
$avatarUrl = member_avatar_url($user);

campaign_refresh_status();
$st = db()->prepare("SELECT * FROM campaigns WHERE user_id=? AND status IN ('active','successful','failed') ORDER BY id DESC LIMIT 20");
$st->execute([(int) $user['id']]);
$campaigns = $st->fetchAll();

$showPublicDons = !empty($user['show_public_donations']);
$posts = [];
if ($showPublicDons) {
    $st = db()->prepare(
        "SELECT donor_name, message, amount, paid_at, is_anonymous, is_public_post
         FROM donations
         WHERE user_id=? AND status='paid' AND is_public_post=1
         ORDER BY id DESC LIMIT 40"
    );
    $st->execute([(int) $user['id']]);
    $posts = $st->fetchAll();
}

$git = trim((string) ($user['git_url'] ?? ''));
$web = trim((string) ($user['website_url'] ?? ''));
$pubContact = trim((string) ($user['public_contact'] ?? ''));
$social = social_links_from_user($user);
$socialDefs = social_network_defs();
$services = trim((string) ($user['services'] ?? ''));

layout_header('حمایت از ' . $publicName, $user['activity']);
?>
<section class="page-section">
  <div class="container hero-grid">
    <div>
      <div class="eyebrow">فعال نرم‌افزار آزاد · حمایت · بدون کارمزد پلتفرم</div>
      <div style="display:flex;gap:1rem;align-items:center;margin-bottom:1rem">
        <img src="<?= e($avatarUrl) ?>" alt="" width="80" height="80" style="border-radius:18px;border:1px solid var(--border);object-fit:cover">
        <div>
          <h1 class="page-title" style="margin:0"><?= e($publicName) ?></h1>
          <?php
            $dm = (string) ($user['display_mode'] ?? 'personal');
            if (($dm === 'platform' || $dm === 'community') && trim((string)($user['display_name'] ?? '')) !== ''):
          ?>
            <p class="hint" style="margin:.25rem 0 0">فعال: <?= e($user['display_name']) ?></p>
          <?php endif; ?>
        </div>
      </div>
      
      <?php
        $me = auth_user();
        $liked = false;
        $pledgeAmt = 0;
        if ($me) {
          $st = db()->prepare("SELECT id FROM supporter_likes WHERE user_id=? AND target_type='hamyar' AND target_id=?");
          $st->execute([(int)$me['id'], (int)$user['id']]);
          $liked = (bool)$st->fetch();
          $st = db()->prepare("SELECT amount FROM monthly_pledges WHERE user_id=? AND target_type='hamyar' AND target_id=? AND active=1");
          $st->execute([(int)$me['id'], (int)$user['id']]);
          $pr = $st->fetch();
          $pledgeAmt = $pr ? (int)$pr['amount'] : 0;
        }
      ?>
      <?php if ($me): ?>
      <div class="card" style="box-shadow:none;margin:1rem 0">
        <button type="button" class="btn btn-ghost" style="width:auto" id="btn-like" data-id="<?= (int)$user['id'] ?>"><?= $liked ? '★ در علاقه‌مندی‌ها' : '☆ افزودن به علاقه‌مندی' ?></button>
        <div style="margin-top:.75rem">
          <label>هدف حمایت ماهانه من (تومان)</label>
          <div class="otp-row">
            <input id="pledge-amount" class="ltr-field money-input" dir="ltr" value="<?= e(number_fa($pledgeAmt)) ?>">
            <button type="button" class="btn btn-primary" style="width:auto" id="btn-pledge" data-id="<?= (int)$user['id'] ?>">ذخیره هدف</button>
          </div>
          <p class="hint" id="pledge-status"></p>
        </div>
      </div>
      <script>
      (function(){
        var csrf = <?= json_encode(csrf_token()) ?>;
        function digits(s){ return String(s||'').replace(/[۰-۹]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)}).replace(/\D+/g,''); }
        var bl=document.getElementById('btn-like');
        if(bl) bl.onclick=async function(){
          var r=await fetch('/api/like.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:csrf,target_type:'hamyar',target_id:bl.dataset.id})});
          var d=await r.json(); if(d.ok) bl.textContent = d.liked ? '★ در علاقه‌مندی‌ها' : '☆ افزودن به علاقه‌مندی';
        };
        var bp=document.getElementById('btn-pledge');
        if(bp) bp.onclick=async function(){
          var a=parseInt(digits(document.getElementById('pledge-amount').value),10)||0;
          var r=await fetch('/api/pledge.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:csrf,target_type:'hamyar',target_id:bp.dataset.id,amount:a,active:a>0?1:0})});
          var d=await r.json(); var st=document.getElementById('pledge-status');
          if(st) st.textContent = d.ok ? ('ذخیره شد: '+(d.amount_fa||a)) : (d.message||'خطا');
        };
      })();
      </script>
      <?php endif; ?>

      <p class="page-lead"><?= e($user['bio'] ?: 'صفحه حمایت فعال نرم‌افزار آزاد') ?></p>

      <div class="card" style="box-shadow:none;margin:1rem 0">
        <h2 style="margin-top:0;font-size:1.1rem">فعالیت در نرم‌افزار آزاد</h2>
        <p style="margin:0;color:var(--muted)"><?= nl2br(e($user['activity'])) ?></p>
      </div>
      <?php if ($services !== ''): ?>
      <div class="card" style="box-shadow:none;margin:1rem 0">
        <h2 style="margin-top:0;font-size:1.1rem">برای چه کارهایی حمایت می‌پذیرد؟</h2>
        <p style="margin:0;color:var(--muted)"><?= nl2br(e($services)) ?></p>
      </div>
      <?php endif; ?>
      <?php
        $linkItems = member_link_items($user);
        if ($linkItems):
      ?>
      <div class="card links-card" style="box-shadow:none;margin:1rem 0">
        <h2 style="margin-top:0;font-size:1.1rem">پیوندها و شبکه‌ها</h2>
        <div class="social-links-grid">
          <?php foreach ($linkItems as $item):
            $isLink = ($item['url'] ?? '') !== '';
            $cls = 'social-chip social-chip--' . preg_replace('/[^a-z0-9_\-]/', '', $item['key']);
          ?>
            <?php if ($isLink): ?>
              <a class="<?= e($cls) ?>" href="<?= e($item['url']) ?>" target="_blank" rel="noopener me" title="<?= e($item['label']) ?>">
                <span class="social-chip__icon"><?= social_icon_svg($item['key']) ?></span>
                <span class="social-chip__meta">
                  <span class="social-chip__label"><?= e($item['label']) ?></span>
                  <span class="social-chip__text" dir="ltr"><?= e($item['text']) ?></span>
                </span>
              </a>
            <?php else: ?>
              <div class="<?= e($cls) ?> social-chip--static" title="<?= e($item['label']) ?>">
                <span class="social-chip__icon"><?= social_icon_svg($item['key']) ?></span>
                <span class="social-chip__meta">
                  <span class="social-chip__label"><?= e($item['label']) ?></span>
                  <span class="social-chip__text"><?= e($item['text']) ?></span>
                </span>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="stats-grid" style="grid-template-columns:1fr 1fr">
        <div class="card stat"><div class="stat-label">تعداد حمایت</div><div class="stat-value" style="font-size:1.2rem"><?= fa_digits((string)$tot['count']) ?></div></div>
        <div class="card stat"><div class="stat-label">جمع حمایت</div><div class="stat-value" style="font-size:1.2rem"><?= e(money_fa($tot['sum'])) ?></div></div>
      </div>

      <?php if ($showPublicDons): ?>
      <h2 style="margin-top:1.5rem;font-size:1.15rem" id="supports">حمایت‌های دریافتی</h2>
      <?php if (!$posts): ?>
        <p class="hint">هنوز حمایت عمومی ثبت‌شده‌ای نیست.</p>
      <?php else: ?>
      <div class="post-list">
        <?php foreach ($posts as $p): ?>
          <article class="card post-item" style="box-shadow:none;margin:.6rem 0">
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:.5rem;align-items:baseline">
              <strong><?= e(donor_public_label($p)) ?></strong>
              <span class="hint"><?= e(money_fa((int)$p['amount'])) ?>
                <?php if (!empty($p['paid_at'])): ?> · <?= e(jdate_format($p['paid_at'], 'Y/m/d')) ?><?php endif; ?>
              </span>
            </div>
            <?php if (trim((string)($p['message'] ?? '')) !== ''): ?>
              <p style="margin:.45rem 0 0;color:var(--muted)"><?= e($p['message']) ?></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($campaigns): ?>
        <h2 style="margin-top:1.5rem;font-size:1.15rem">کمپین‌ها</h2>
        <div class="product-grid" style="margin-top:.75rem">
          <?php foreach ($campaigns as $c): $prog = campaign_progress($c); ?>
            <article class="card product-card">
              <div class="hint"><?= e(campaign_status_label($c['status'])) ?></div>
              <h3 class="product-title" style="font-size:1rem"><?= e($c['title']) ?></h3>
              <div class="product-price" style="font-size:1rem"><?= e(money_fa($prog['raised'])) ?> / <?= e(money_fa($prog['goal'])) ?></div>
              <a class="btn btn-ghost" href="/c/<?= e(rawurlencode($c['slug'])) ?>">مشاهده</a>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <aside class="card" id="donate">
      <h2 style="margin-top:0">حمایت از <?= e($publicName) ?></h2>
      <p class="sub">بدون کارمزد — برای پرداخت <strong>ورود لازم نیست</strong>.</p>
      <p class="hint" style="margin:0 0 1rem">
        نام و پیام شما، در صورت فعال بودن «نمایش حمایت‌های دریافتی» توسط این فعال، می‌تواند در صفحهٔ عمومی او نشان داده شود.
        اگر می‌خواهید ناشناس بمانید، تیک «انتشار به‌صورت ناشناس» را بزنید؛ در آن حالت به‌جای نام، <strong>حامی ناشناس</strong> نمایش داده می‌شود.
      </p>
      <form id="donate-form" data-activist="<?= (int)$user['id'] ?>" novalidate>
        <div class="amounts">
          <?php foreach ($presets as $i => $p): ?>
            <button type="button" class="amount-btn<?= $i===1?' active':'' ?>" data-amount="<?= (int)$p ?>"><?= number_fa((int)$p) ?></button>
          <?php endforeach; ?>
        </div>
        <label for="amount">مبلغ</label>
        <input type="number" id="amount" min="<?= (int)$cfg['min_amount'] ?>" step="1000" value="<?= (int)($presets[1]??100000) ?>" required>
        <label for="name">نام شما (اختیاری)</label>
        <input type="text" id="name" maxlength="80" placeholder="نام نمایشی در صورت انتشار">
        <label for="message">پیام کوتاه (اختیاری)</label>
        <textarea id="message" rows="3" maxlength="280" placeholder="حداکثر حدود ۲۸۰ نویسه"></textarea>
        <label class="check-line">
          <input type="checkbox" id="public_post" checked>
          اجازه می‌دهم نام/پیامم در فهرست حمایت‌های صفحهٔ فعال نمایش داده شود (اگر خودش نمایش را روشن کرده باشد)
        </label>
        <label class="check-line">
          <input type="checkbox" id="anonymous">
          انتشار به‌صورت ناشناس (نمایش به‌عنوان «حامی ناشناس»)
        </label>
        <div class="hp"><input type="text" id="website" tabindex="-1" autocomplete="off"></div>
        <input type="hidden" id="cause" value="activists">
        <input type="hidden" id="activist_id" value="<?= (int)$user['id'] ?>">
        <button type="submit" class="btn btn-primary" id="submit-btn">پرداخت و حمایت</button>
        <div id="form-msg" class="form-msg" role="status"></div>
      </form>
    </aside>
  </div>
</section>
<?php layout_footer(); ?>
