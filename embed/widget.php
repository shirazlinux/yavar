<?php
declare(strict_types=1);
/**
 * ویجت embed برای سایت / وبلاگ (iframe-friendly)
 * /embed/widget.php?slug=NAME&theme=dark|light&lang=fa|en
 */
require_once dirname(__DIR__) . '/lib/Embed.php';
require_once dirname(__DIR__) . '/lib/auth.php';

// اجازه iframe از هر origin
header('Content-Security-Policy: default-src \'self\'; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com; font-src \'self\' https://fonts.gstatic.com data:; img-src \'self\' data: https:; frame-ancestors *');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=120');
// حذف XFO اگر توسط PHP set شده
header_remove('X-Frame-Options');

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
    'support' => 'Support us',
    'via' => 'via Yavar · free software · no platform fee',
    'cta' => 'Support',
    'raised' => 'raised',
    'supports' => 'supports',
    'missing' => 'Page not found',
    'type' => 'Activist',
] : [
    'support' => 'از ما حمایت کنید',
    'via' => 'از طریق یاور · نرم‌افزار آزاد · بدون کارمزد',
    'cta' => 'حمایت کنید',
    'raised' => 'جمع حمایت',
    'supports' => 'حمایت',
    'missing' => 'صفحه یافت نشد',
    'type' => 'فعال',
];

$bg = match ($theme) {
    'light' => '#f8fafc',
    'brand' => 'linear-gradient(145deg,#1a0f0a 0%,#2a1810 50%,#1a1520 100%)',
    default => 'linear-gradient(160deg,#0f172a 0%,#111827 55%,#1a1525 100%)',
};
$fg = $theme === 'light' ? '#0f172a' : '#e8eef9';
$muted = $theme === 'light' ? '#64748b' : '#94a3b8';
$border = $theme === 'light' ? 'rgba(15,23,42,.12)' : 'rgba(148,163,184,.22)';
$cardBg = $theme === 'light' ? '#fff' : 'rgba(15,23,42,.65)';
$dir = $lang === 'fa' ? 'rtl' : 'ltr';
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
    body {
      margin: 0; font-family: Vazirmatn, Tahoma, system-ui, sans-serif;
      background: transparent; color: <?= $fg ?>;
    }
    .box {
      background: <?= $bg ?>;
      border: 1px solid <?= $border ?>;
      border-radius: 16px;
      padding: <?= $compact ? '0.85rem 1rem' : '1.15rem 1.2rem' ?>;
      box-shadow: 0 12px 40px rgba(0,0,0,.18);
      min-height: 100%;
    }
    .row { display: flex; gap: 0.85rem; align-items: <?= $compact ? 'center' : 'flex-start' ?>; }
    .av {
      width: <?= $compact ? '48px' : '56px' ?>; height: <?= $compact ? '48px' : '56px' ?>;
      border-radius: 14px; object-fit: cover; border: 1px solid <?= $border ?>;
      background: <?= $cardBg ?>; flex-shrink: 0;
    }
    .name { font-weight: 800; font-size: <?= $compact ? '1rem' : '1.1rem' ?>; margin: 0 0 .15rem; line-height: 1.3; }
    .meta { color: <?= $muted ?>; font-size: 0.78rem; margin: 0; }
    .bio { color: <?= $muted ?>; font-size: 0.85rem; margin: 0.55rem 0 0; line-height: 1.45; }
    .stats {
      display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.75rem;
    }
    .stat {
      background: <?= $theme === 'light' ? 'rgba(241,89,45,.08)' : 'rgba(241,89,45,.12)' ?>;
      border: 1px solid rgba(241,89,45,.25);
      border-radius: 10px; padding: 0.35rem 0.65rem; font-size: 0.78rem;
    }
    .stat strong { color: #F1592D; }
    .cta {
      display: inline-flex; align-items: center; justify-content: center;
      margin-top: 0.9rem; width: 100%;
      background: linear-gradient(135deg,#F1592D,#ff7a45);
      color: #1a0a04 !important; font-weight: 800; text-decoration: none !important;
      border-radius: 12px; padding: 0.7rem 1rem; font-size: 0.95rem;
      box-shadow: 0 10px 24px rgba(241,89,45,.28);
    }
    .cta:hover { filter: brightness(1.05); }
    .foot { margin-top: 0.65rem; font-size: 0.72rem; color: <?= $muted ?>; text-align: center; }
    .miss { padding: 1.25rem; text-align: center; color: <?= $muted ?>; }
    a.soft { color: <?= $muted ?>; }
  </style>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;800&display=swap" rel="stylesheet">
</head>
<body>
<?php if (!$ok || !$card): ?>
  <div class="box miss"><?= e($t['missing']) ?></div>
<?php else: ?>
  <div class="box">
    <div class="row">
      <img class="av" src="<?= e($card['avatar']) ?>" alt="" width="56" height="56" loading="lazy"
           onerror="this.style.display='none'">
      <div style="min-width:0;flex:1">
        <h1 class="name"><?= e($card['name']) ?></h1>
        <p class="meta"><?= e($card['type']) ?> · <?= e($t['via']) ?></p>
        <?php if (!$compact && $card['bio'] !== ''): ?>
          <p class="bio"><?= e($card['bio']) ?></p>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($card['count'] > 0): ?>
      <div class="stats">
        <span class="stat"><strong><?= e(number_format($card['count'])) ?></strong> <?= e($t['supports']) ?></span>
        <span class="stat"><strong><?= e(number_format($card['sum'])) ?></strong> <?= $lang === 'fa' ? 'تومان' : 'Toman' ?></span>
      </div>
    <?php endif; ?>
    <a class="cta" href="<?= e($card['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($t['cta']) ?></a>
    <div class="foot"><a class="soft" href="<?= e($card['site']) ?>" target="_blank" rel="noopener">yavar.sudoshz.ir</a></div>
  </div>
<?php endif; ?>
</body>
</html>
