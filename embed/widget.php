<?php
declare(strict_types=1);
/**
 * ویجت embed برای سایت / وبلاگ (iframe-friendly)
 * /embed/widget.php?slug=NAME&theme=dark|light|brand&lang=fa|en&compact=1
 */
require_once dirname(__DIR__) . '/lib/Embed.php';
require_once dirname(__DIR__) . '/lib/auth.php';

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: https:; frame-ancestors *");
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=60');
header_remove('X-Frame-Options');
if (function_exists('header_remove')) {
    @header_remove('Cross-Origin-Opener-Policy');
}

$slug = (string) ($_GET['slug'] ?? $_GET['u'] ?? '');
$theme = (string) ($_GET['theme'] ?? 'dark');
if (!in_array($theme, ['dark', 'light', 'brand'], true)) {
    $theme = 'dark';
}
$lang = (string) ($_GET['lang'] ?? 'fa');
if (!in_array($lang, ['fa', 'en'], true)) {
    $lang = 'fa';
}
$compact = !empty($_GET['compact']);

$user = Embed::loadActivist($slug);
$ok = $user !== null;
$card = $ok ? Embed::publicCard($user) : null;

$t = $lang === 'en' ? [
    'via' => 'Yavar · free software · no fee',
    'cta' => 'Support',
    'supports' => 'supports',
    'missing' => 'Page not found',
    'toman' => 'Toman',
] : [
    'via' => 'یاور · نرم‌افزار آزاد · بدون کارمزد',
    'cta' => 'حمایت کنید',
    'supports' => 'حمایت',
    'missing' => 'صفحه یافت نشد',
    'toman' => 'تومان',
];

$bg = match ($theme) {
    'light' => '#f8fafc',
    'brand' => 'linear-gradient(145deg,#1a0f0a 0%,#2a1810 50%,#1a1520 100%)',
    default => 'linear-gradient(160deg,#0f172a 0%,#111827 55%,#1a1525 100%)',
};
$fg = $theme === 'light' ? '#0f172a' : '#e8eef9';
$muted = $theme === 'light' ? '#64748b' : '#94a3b8';
$border = $theme === 'light' ? 'rgba(15,23,42,.12)' : 'rgba(148,163,184,.22)';
$cardBg = $theme === 'light' ? '#ffffff' : 'rgba(15,23,42,.72)';
$statBg = $theme === 'light' ? 'rgba(176,64,64,.08)' : 'rgba(176,64,64,.14)';
$dir = $lang === 'fa' ? 'rtl' : 'ltr';
$siteHost = $card
    ? (string) preg_replace('#^https?://#i', '', rtrim((string) $card['site'], '/'))
    : 'donate.sudoshz.ir';

// بیو کوتاه‌تر تا در ویجت بریده نشود
$bio = '';
if ($ok && !$compact) {
    $bio = trim((string) ($card['bio'] ?? ''));
    if (function_exists('mb_strlen') && mb_strlen($bio) > 90) {
        $bio = mb_substr($bio, 0, 88) . '…';
    } elseif (strlen($bio) > 90) {
        $bio = substr($bio, 0, 88) . '…';
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= $ok ? e($card['name']) : 'Yavar' ?></title>
  <style>
    * { box-sizing: border-box; }
    html, body {
      margin: 0;
      padding: 0;
      /* ارتفاع خودکار — نه 100% که محتوا را فشرده/بریده کند */
      height: auto;
      background: transparent;
      color: <?= $fg ?>;
      font-family: Vazirmatn, Tahoma, system-ui, sans-serif;
      -webkit-font-smoothing: antialiased;
    }
    body {
      display: block;
      padding: 0;
      overflow: visible;
    }
    .box {
      width: 100%;
      max-width: 420px;
      margin: 0 auto;
      background: <?= $bg ?>;
      border: 1px solid <?= $border ?>;
      border-radius: 16px;
      padding: <?= $compact ? '0.85rem 0.95rem' : '1rem 1.1rem' ?>;
      box-shadow: 0 10px 32px rgba(0,0,0,.16);
      text-align: start;
    }
    /* ردیف بالا: آواتار + نام — ترتیب طبیعی RTL/LTR */
    .head {
      display: flex;
      flex-direction: row;
      align-items: center;
      gap: 0.75rem;
      width: 100%;
    }
    .av {
      width: <?= $compact ? '48px' : '56px' ?>;
      height: <?= $compact ? '48px' : '56px' ?>;
      border-radius: 14px;
      object-fit: cover;
      border: 1px solid <?= $border ?>;
      background: <?= $cardBg ?>;
      flex-shrink: 0;
      display: block;
    }
    .head-text {
      min-width: 0;
      flex: 1 1 auto;
      text-align: start;
    }
    .name {
      font-weight: 800;
      font-size: <?= $compact ? '0.98rem' : '1.08rem' ?>;
      margin: 0;
      line-height: 1.35;
      word-wrap: break-word;
      overflow-wrap: anywhere;
    }
    .type {
      display: inline-block;
      margin-top: 0.25rem;
      font-size: 0.72rem;
      font-weight: 700;
      color: #e07070;
      background: <?= $statBg ?>;
      border: 1px solid rgba(176,64,64,.28);
      border-radius: 999px;
      padding: 0.12rem 0.5rem;
      line-height: 1.4;
    }
    .meta {
      color: <?= $muted ?>;
      font-size: 0.75rem;
      margin: 0.55rem 0 0;
      line-height: 1.45;
      text-align: start;
    }
    .bio {
      color: <?= $muted ?>;
      font-size: 0.82rem;
      margin: 0.45rem 0 0;
      line-height: 1.5;
      text-align: start;
      display: -webkit-box;
      -webkit-box-orient: vertical;
      -webkit-line-clamp: 2;
      overflow: hidden;
    }
    .stats {
      display: flex;
      flex-wrap: wrap;
      gap: 0.4rem;
      margin-top: 0.7rem;
      justify-content: flex-start;
      width: 100%;
    }
    .stat {
      background: <?= $statBg ?>;
      border: 1px solid rgba(176,64,64,.28);
      border-radius: 10px;
      padding: 0.3rem 0.6rem;
      font-size: 0.76rem;
      line-height: 1.35;
      white-space: nowrap;
    }
    .stat strong { color: #e07070; font-weight: 800; }
    .cta {
      display: flex;
      align-items: center;
      justify-content: center;
      margin-top: 0.8rem;
      width: 100%;
      background: linear-gradient(135deg,#B04040,#e07070);
      color: #fff !important;
      font-weight: 800;
      text-decoration: none !important;
      border-radius: 12px;
      padding: 0.7rem 1rem;
      font-size: 0.92rem;
      box-shadow: 0 8px 20px rgba(176,64,64,.25);
      text-align: center;
    }
    .cta:hover { filter: brightness(1.06); }
    .foot {
      margin-top: 0.55rem;
      font-size: 0.7rem;
      color: <?= $muted ?>;
      text-align: center;
      width: 100%;
      line-height: 1.3;
    }
    .miss {
      padding: 1.1rem;
      text-align: center;
      color: <?= $muted ?>;
      width: 100%;
    }
    a.soft { color: <?= $muted ?>; text-decoration: none; }
    a.soft:hover { text-decoration: underline; }
  </style>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;800&display=swap" rel="stylesheet">
</head>
<body>
<?php if (!$ok || !$card): ?>
  <div class="box miss" id="yavar-widget-root"><?= e($t['missing']) ?></div>
<?php else: ?>
  <div class="box" id="yavar-widget-root">
    <div class="head">
      <img class="av" src="<?= e($card['avatar']) ?>" alt="" width="56" height="56" loading="eager"
           onerror="this.style.visibility='hidden'">
      <div class="head-text">
        <h1 class="name"><?= e($card['name']) ?></h1>
        <span class="type"><?= e($card['type']) ?></span>
      </div>
    </div>
    <p class="meta"><?= e($t['via']) ?></p>
    <?php if ($bio !== ''): ?>
      <p class="bio"><?= e($bio) ?></p>
    <?php endif; ?>
    <?php if ($card['count'] > 0): ?>
      <div class="stats">
        <span class="stat"><strong><?= e(number_format($card['count'])) ?></strong> <?= e($t['supports']) ?></span>
        <span class="stat"><strong><?= e(number_format($card['sum'])) ?></strong> <?= e($t['toman']) ?></span>
      </div>
    <?php endif; ?>
    <a class="cta" href="<?= e($card['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($t['cta']) ?></a>
    <div class="foot">
      <a class="soft" href="<?= e($card['site']) ?>" target="_blank" rel="noopener" dir="ltr"><?= e($siteHost) ?></a>
    </div>
  </div>
<?php endif; ?>
<script>
(function () {
  function measure() {
    var root = document.getElementById('yavar-widget-root') || document.body;
    var h = Math.ceil(Math.max(
      root.offsetHeight || 0,
      root.scrollHeight || 0,
      document.documentElement.scrollHeight || 0,
      document.body.scrollHeight || 0
    ));
    // کمی فضای امن برای سایه/border
    h = Math.max(120, h + 4);
    try {
      parent.postMessage({ type: 'yavar-embed-resize', height: h, source: 'yavar-widget' }, '*');
    } catch (e) {}
    return h;
  }
  function tick() {
    measure();
    // بعد از فونت/تصویر
    setTimeout(measure, 120);
    setTimeout(measure, 450);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tick);
  } else {
    tick();
  }
  window.addEventListener('load', measure);
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(measure).catch(function () {});
  }
})();
</script>
</body>
</html>
