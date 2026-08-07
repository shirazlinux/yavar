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
$base = rtrim((string) ($cfg['site_url'] ?? 'https://yavar.sudoshz.ir'), '/');
$slug = (string) $user['slug'];
$pageUrl = $base . '/u/' . rawurlencode($slug);
$badgeUrl = $base . '/badge.php?slug=' . rawurlencode($slug);
$badgeFa = $base . '/badge.php?slug=' . rawurlencode($slug) . '&label=fa';
$badgeFlat = $base . '/badge.php?slug=' . rawurlencode($slug) . '&style=for-the-badge&label=support';
$widgetUrl = $base . '/embed/widget.php?slug=' . rawurlencode($slug) . '&theme=dark&lang=fa';
$widgetLight = $base . '/embed/widget.php?slug=' . rawurlencode($slug) . '&theme=light&lang=fa';
$loaderUrl = $base . '/embed/loader.js';

$mdBadge = '[![Support on Yavar](' . $badgeUrl . ')](' . $pageUrl . ')';
$mdBadgeFa = '[![حمایت با یاور](' . $badgeFa . ')](' . $pageUrl . ')';
$mdSection = <<<MD
## حمایت از این پروژه

اگر کارمان برایتان مفید است، می‌توانید از طریق **یاور** (بدون کارمزد پلتفرم) حمایت کنید:

{$mdBadge}

[صفحه حمایت]({$pageUrl})
MD;

$htmlIframe = '<iframe src="' . e($widgetUrl) . '" title="حمایت با یاور" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" style="width:100%;max-width:420px;height:240px;border:0;border-radius:16px;overflow:hidden" sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>';

$htmlScript = '<div class="yavar-support" data-slug="' . e($slug) . '" data-theme="dark" data-lang="fa" data-height="240"></div>' . "\n"
    . '<script src="' . e($loaderUrl) . '" async></script>';

$htmlButton = '<a href="' . e($pageUrl) . '" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:.5rem;background:linear-gradient(135deg,#F1592D,#ff7a45);color:#1a0a04;font-weight:800;text-decoration:none;padding:.65rem 1.1rem;border-radius:12px;font-family:Tahoma,sans-serif;font-size:.95rem">از ما حمایت کنید · یاور</a>';

$approved = ($user['status'] ?? '') === 'approved';

layout_header('ابزارک‌ها و بج', 'Embed برای سایت و GitHub');
?>
<section class="page-section">
  <div class="container narrow">
    <h1 class="page-title">ابزارک‌ها و بج</h1>
    <p class="page-lead">
      کد آماده برای <strong>گیت‌هاب / گیت‌لب / کدبرگ</strong> و برای <strong>سایت شخصی</strong>.
      لینک صفحه شما: <a href="<?= e($pageUrl) ?>" dir="ltr" target="_blank" rel="noopener"><?= e($pageUrl) ?></a>
    </p>
    <p class="hint"><a href="/dashboard/">← پنل</a></p>

    <?php if (!$approved): ?>
      <div class="form-msg show manual">پس از تأیید حساب، بج و ویجت برای عموم فعال می‌شوند. کدها از الان قابل کپی‌اند.</div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:1rem">
      <h2 style="margin-top:0;font-size:1.1rem">پیش‌نمایش</h2>
      <p class="hint">بج:</p>
      <p>
        <a href="<?= e($pageUrl) ?>" target="_blank" rel="noopener"><img src="<?= e($badgeUrl) ?>" alt="Yavar badge" style="vertical-align:middle"></a>
        &nbsp;
        <a href="<?= e($pageUrl) ?>" target="_blank" rel="noopener"><img src="<?= e($badgeFa) ?>" alt="حمایت" style="vertical-align:middle"></a>
        &nbsp;
        <a href="<?= e($pageUrl) ?>" target="_blank" rel="noopener"><img src="<?= e($badgeFlat) ?>" alt="support" style="vertical-align:middle"></a>
      </p>
      <p class="hint" style="margin-top:1rem">ویجت:</p>
      <iframe src="<?= e($widgetUrl) ?>" title="widget preview" loading="lazy"
              style="width:100%;max-width:420px;height:240px;border:0;border-radius:16px;background:transparent"
              sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>
    </div>

    <div class="card" style="margin-bottom:1rem">
      <h2 style="margin-top:0;font-size:1.1rem">۱) بج برای README گیت‌هاب / Codeberg</h2>
      <p class="hint">در فایل README.md پروژه بگذارید:</p>
      <label>Markdown (انگلیسی)</label>
      <textarea class="code-box" readonly rows="2" dir="ltr" onclick="this.select()"><?= e($mdBadge) ?></textarea>
      <label style="margin-top:.75rem">Markdown (فارسی)</label>
      <textarea class="code-box" readonly rows="2" dir="ltr" onclick="this.select()"><?= e($mdBadgeFa) ?></textarea>
      <label style="margin-top:.75rem">بخش آماده برای README</label>
      <textarea class="code-box" readonly rows="8" onclick="this.select()"><?= e($mdSection) ?></textarea>
    </div>

    <div class="card" style="margin-bottom:1rem">
      <h2 style="margin-top:0;font-size:1.1rem">۲) ویجت برای سایت / وبلاگ</h2>
      <p class="hint">با iframe (ساده‌ترین روش):</p>
      <textarea class="code-box" readonly rows="4" dir="ltr" onclick="this.select()"><?= e($htmlIframe) ?></textarea>
      <p class="hint" style="margin-top:.85rem">با اسکریپت loader:</p>
      <textarea class="code-box" readonly rows="3" dir="ltr" onclick="this.select()"><?= e($htmlScript) ?></textarea>
      <p class="hint" style="margin-top:.85rem">تم روشن: <code dir="ltr">theme=light</code> · فشرده: <code dir="ltr">compact=1</code> · انگلیسی: <code dir="ltr">lang=en</code></p>
      <textarea class="code-box" readonly rows="2" dir="ltr" onclick="this.select()"><?= e('<iframe src="' . $widgetLight . '&compact=1" style="width:100%;max-width:420px;height:170px;border:0;border-radius:16px" loading="lazy"></iframe>') ?></textarea>
    </div>

    <div class="card" style="margin-bottom:1rem">
      <h2 style="margin-top:0;font-size:1.1rem">۳) دکمه ساده HTML</h2>
      <p class="hint">یک لینک دکمه‌ای بدون iframe:</p>
      <div style="margin:.5rem 0 1rem"><?= $htmlButton ?></div>
      <textarea class="code-box" readonly rows="3" dir="ltr" onclick="this.select()"><?= e($htmlButton) ?></textarea>
    </div>

    <div class="card">
      <h2 style="margin-top:0;font-size:1.1rem">۴) لینک مستقیم</h2>
      <p dir="ltr"><code><?= e($pageUrl) ?></code></p>
      <p class="hint">آدرس بج: <span dir="ltr"><?= e($badgeUrl) ?></span></p>
      <p class="hint">آدرس ویجت: <span dir="ltr"><?= e($widgetUrl) ?></span></p>
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
</style>
<?php layout_footer(); ?>
