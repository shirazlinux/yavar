<?php
declare(strict_types=1);
/**
 * کدهای embed / بج / گیت‌هاب برای فعال
 */
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/Embed.php';
require_once dirname(__DIR__) . '/lib/profile.php';

$user = auth_require_login();
if (($user['role'] ?? 'hamyar') === 'supporter' && empty($user['is_admin'])) {
    header('Location: /dashboard/supporter.php');
    exit;
}

$cfg = app_config();
$base = rtrim((string) ($cfg['site_url'] ?? 'https://donate.sudoshz.ir'), '/');
$slug = (string) $user['slug'];
$pageUrl = $base . '/u/' . rawurlencode($slug);
$badgeUrl = $base . '/badge.php?slug=' . rawurlencode($slug);
$badgeFa = $base . '/badge.php?slug=' . rawurlencode($slug) . '&label=fa';
$badgeFlat = $base . '/badge.php?slug=' . rawurlencode($slug) . '&style=for-the-badge&label=support';
$badgeHeart = $base . '/badge.php?slug=' . rawurlencode($slug) . '&style=heart&label=donate';
$badgeHeartFa = $base . '/badge.php?slug=' . rawurlencode($slug) . '&style=heart&label=fa';
$badgeHeartOnly = $base . '/badge.php?slug=' . rawurlencode($slug) . '&style=heart-only&size=28';
$widgetUrl = $base . '/embed/widget.php?slug=' . rawurlencode($slug) . '&theme=dark&lang=fa';
$widgetLight = $base . '/embed/widget.php?slug=' . rawurlencode($slug) . '&theme=light&lang=fa';
$loaderUrl = $base . '/embed/loader.js';

$mdBadgeHeart = '[![Donate](' . $badgeHeart . ')](' . $pageUrl . ')';
$mdBadgeHeartFa = '[![حمایت](' . $badgeHeartFa . ')](' . $pageUrl . ')';
$mdBadgeHeartOnly = '[![Donate](' . $badgeHeartOnly . ')](' . $pageUrl . ')';
$mdBadge = '[![Support on Yavar](' . $badgeUrl . ')](' . $pageUrl . ')';
$mdBadgeFa = '[![حمایت با یاور](' . $badgeFa . ')](' . $pageUrl . ')';
$mdSection = "## حمایت از این پروژه

"
    . "اگر کارمان برایتان مفید است، از طریق **یاور** حمایت کنید (بدون کارمزد پلتفرم):

"
    . $mdBadgeHeart . "

"
    . '[صفحه حمایت](' . $pageUrl . ")
";

$htmlIframe = '<div style="display:flex;justify-content:center;width:100%">'
    . '<iframe src="' . $widgetUrl . '" title="حمایت با یاور" loading="lazy" '
    . 'referrerpolicy="strict-origin-when-cross-origin" '
    . 'style="width:100%;max-width:420px;height:280px;border:0;border-radius:16px;overflow:hidden;display:block;margin:0 auto" '
    . 'sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>'
    . '</div>';

$htmlScript = '<div class="yavar-support" data-slug="' . $slug . '" data-base="' . $base . '" data-theme="dark" data-lang="fa" data-height="280" data-width="420"></div>' . "\n"
    . '<script src="' . $loaderUrl . '" async></script>';

$htmlButton = '<p style="text-align:center;margin:0">'
    . '<a href="' . $pageUrl . '" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;justify-content:center;gap:.5rem;background:linear-gradient(135deg,#B04040,#e07070);color:#fff;font-weight:800;text-decoration:none;padding:.7rem 1.2rem;border-radius:12px;font-family:Tahoma,sans-serif;font-size:.95rem">از ما حمایت کنید · یاور</a>'
    . '</p>';

$approved = ($user['status'] ?? '') === 'approved';

layout_header('ابزارک‌ها و بج', 'Embed برای سایت و GitHub');
?>
<section class="page-section">
  <div class="container dash-page">
    <h1 class="page-title">ابزارک‌ها و بج</h1>
    <?php dashboard_nav($user, 'widgets'); ?>
    <p class="page-lead">
      کد آماده برای <strong>گیت‌هاب / گیت‌لب / کدبرگ</strong> و برای <strong>سایت شخصی</strong>.
      لینک صفحه شما: <a href="<?= e($pageUrl) ?>" dir="ltr" target="_blank" rel="noopener"><?= e($pageUrl) ?></a>
    </p>

    <?php if (!$approved): ?>
      <div class="form-msg show manual">پس از تأیید حساب، بج و ویجت برای عموم فعال می‌شوند. کدها از الان قابل کپی‌اند.</div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:1rem">
      <h2 style="margin-top:0;font-size:1.1rem">پیش‌نمایش</h2>
      <p class="hint">بج قلب (شبیه Sponsor گیت‌هاب) — برای README:</p>
      <div class="embed-preview-center" style="gap:1rem">
        <a href="<?= e($pageUrl) ?>" target="_blank" rel="noopener" title="Donate heart"><img src="<?= e($badgeHeart) ?>" alt="Donate" style="vertical-align:middle;height:28px"></a>
        <a href="<?= e($pageUrl) ?>" target="_blank" rel="noopener" title="حمایت"><img src="<?= e($badgeHeartFa) ?>" alt="حمایت" style="vertical-align:middle;height:28px"></a>
        <a href="<?= e($pageUrl) ?>" target="_blank" rel="noopener" title="فقط قلب"><img src="<?= e($badgeHeartOnly) ?>" alt="♥" style="vertical-align:middle;height:28px"></a>
      </div>
      <p class="hint" style="margin-top:.85rem">بج‌های کلاسیک:</p>
      <div class="embed-preview-center">
        <a href="<?= e($pageUrl) ?>" target="_blank" rel="noopener"><img src="<?= e($badgeUrl) ?>" alt="Yavar badge" style="vertical-align:middle"></a>
        &nbsp;
        <a href="<?= e($pageUrl) ?>" target="_blank" rel="noopener"><img src="<?= e($badgeFa) ?>" alt="حمایت" style="vertical-align:middle"></a>
        &nbsp;
        <a href="<?= e($pageUrl) ?>" target="_blank" rel="noopener"><img src="<?= e($badgeFlat) ?>" alt="support" style="vertical-align:middle"></a>
      </div>
      <p class="hint" style="margin-top:1rem">ویجت (iframe):</p>
      <div class="embed-preview-center">
        <iframe class="yavar-widget-frame" src="<?= e($widgetUrl) ?>" title="widget preview" loading="lazy" scrolling="no"
                style="width:100%;max-width:420px;min-height:220px;height:300px;border:0;border-radius:16px;background:transparent;display:block;margin:0 auto;overflow:hidden"
                sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>
      </div>
      <p class="hint" style="margin-top:1rem">ویجت (loader اسکریپت — همان افزونه):</p>
      <div class="embed-preview-center" style="overflow:visible">
        <div class="yavar-support" data-slug="<?= e($slug) ?>" data-base="<?= e($base) ?>" data-theme="dark" data-lang="fa" data-height="auto" data-width="420"></div>
      </div>
      <script src="<?= e($loaderUrl) ?>?v=3" async></script>
      <script>
        // ارتفاع خودکار پیش‌نمایش iframe (same-origin)
        (function () {
          function apply(h, source) {
            if (!h || h < 80) return;
            document.querySelectorAll('iframe.yavar-widget-frame').forEach(function (f) {
              try {
                if (!source || f.contentWindow === source) {
                  f.style.height = h + 'px';
                  f.style.minHeight = h + 'px';
                }
              } catch (e) {}
            });
          }
          window.addEventListener('message', function (ev) {
            if (ev.data && ev.data.type === 'yavar-embed-resize' && ev.data.height) {
              apply(parseInt(ev.data.height, 10), ev.source);
            }
          });
        })();
      </script>
    </div>

    <div class="card" style="margin-bottom:1rem">
      <h2 style="margin-top:0;font-size:1.1rem">۱) قلب حمایت برای GitHub / Codeberg / GitLab</h2>
      <p class="hint">
        مثل دکمهٔ <strong>Sponsor</strong> گیت‌هاب: یک <strong>قلب</strong> در README که به صفحهٔ حمایت شما لینک می‌شود.
        این کد را در <code dir="ltr">README.md</code> بگذارید (بالای صفحه یا بخش Support).
      </p>

      <label>دکمهٔ قلب + Donate (پیشنهادی)</label>
      <div class="embed-preview-center" style="margin:.4rem 0 .5rem;justify-content:flex-start">
        <img src="<?= e($badgeHeart) ?>" alt="Donate" height="28">
      </div>
      <textarea class="code-box" readonly rows="2" dir="ltr" onclick="this.select()"><?= e($mdBadgeHeart) ?></textarea>

      <label style="margin-top:.85rem">دکمهٔ قلب + «حمایت» (فارسی)</label>
      <div class="embed-preview-center" style="margin:.4rem 0 .5rem;justify-content:flex-start">
        <img src="<?= e($badgeHeartFa) ?>" alt="حمایت" height="28">
      </div>
      <textarea class="code-box" readonly rows="2" dir="ltr" onclick="this.select()"><?= e($mdBadgeHeartFa) ?></textarea>

      <label style="margin-top:.85rem">فقط آیکون قلب (جمع‌وجور)</label>
      <div class="embed-preview-center" style="margin:.4rem 0 .5rem;justify-content:flex-start">
        <img src="<?= e($badgeHeartOnly) ?>" alt="heart" height="28">
      </div>
      <textarea class="code-box" readonly rows="2" dir="ltr" onclick="this.select()"><?= e($mdBadgeHeartOnly) ?></textarea>

      <label style="margin-top:.85rem">بخش آماده برای README</label>
      <textarea class="code-box" readonly rows="8" onclick="this.select()"><?= e($mdSection) ?></textarea>

      <p class="hint" style="margin-top:1rem">بج‌های کلاسیک (shields):</p>
      <textarea class="code-box" readonly rows="2" dir="ltr" onclick="this.select()"><?= e($mdBadge) ?></textarea>
      <textarea class="code-box" readonly rows="2" dir="ltr" style="margin-top:.5rem" onclick="this.select()"><?= e($mdBadgeFa) ?></textarea>
    </div>

    <div class="card" style="margin-bottom:1rem">
      <h2 style="margin-top:0;font-size:1.1rem">۲) ویجت برای سایت / وبلاگ</h2>
      <p class="hint">با iframe (ساده‌ترین روش):</p>
      <textarea class="code-box" readonly rows="5" dir="ltr" onclick="this.select()"><?= e($htmlIframe) ?></textarea>
      <p class="hint" style="margin-top:.85rem">با اسکریپت loader (افزونه):</p>
      <textarea class="code-box" readonly rows="4" dir="ltr" onclick="this.select()"><?= e($htmlScript) ?></textarea>
      <p class="hint" style="margin-top:.85rem">تم روشن: <code dir="ltr">theme=light</code> · فشرده: <code dir="ltr">compact=1</code> · انگلیسی: <code dir="ltr">lang=en</code></p>
      <textarea class="code-box" readonly rows="3" dir="ltr" onclick="this.select()"><?= e('<div style="display:flex;justify-content:center"><iframe src="' . $widgetLight . '&compact=1" style="width:100%;max-width:420px;min-height:160px;height:200px;border:0;border-radius:16px;margin:0 auto" loading="lazy"></iframe></div>') ?></textarea>
    </div>

    <div class="card" style="margin-bottom:1rem">
      <h2 style="margin-top:0;font-size:1.1rem">۳) دکمه ساده HTML</h2>
      <p class="hint">یک لینک دکمه‌ای بدون iframe:</p>
      <div style="margin:.5rem 0 1rem"><?= $htmlButton ?></div>
      <textarea class="code-box" readonly rows="4" dir="ltr" onclick="this.select()"><?= e($htmlButton) ?></textarea>
    </div>

    <div class="card">
      <h2 style="margin-top:0;font-size:1.1rem">۴) لینک مستقیم</h2>
      <p dir="ltr"><code><?= e($pageUrl) ?></code></p>
      <p class="hint">بج قلب: <span dir="ltr"><?= e($badgeHeart) ?></span></p>
      <p class="hint">آدرس بج کلاسیک: <span dir="ltr"><?= e($badgeUrl) ?></span></p>
      <p class="hint">آدرس ویجت: <span dir="ltr"><?= e($widgetUrl) ?></span></p>
      <p class="hint">آدرس loader: <span dir="ltr"><?= e($loaderUrl) ?></span></p>
    </div>
  </div>
</section>
<style>
  .code-box {
    width: 100%;
    box-sizing: border-box;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.8rem;
    line-height: 1.45;
    padding: 0.75rem;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: #0b1220;
    color: #e2e8f0;
    resize: vertical;
    direction: ltr;
    text-align: left;
  }
  .card label { display: block; font-weight: 600; margin-bottom: 0.35rem; font-size: 0.9rem; }
  .embed-preview-center {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: flex-start;
    gap: 0.75rem;
    width: 100%;
    text-align: center;
    overflow: visible;
  }
  .embed-preview-center iframe,
  .embed-preview-center .yavar-support {
    margin-left: auto;
    margin-right: auto;
    max-width: 100%;
  }
</style>
<?php layout_footer(); ?>
