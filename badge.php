<?php
declare(strict_types=1);
/**
 * بج SVG برای GitHub README / Codeberg / GitLab
 *
 * سبک‌ها:
 *   flat | plastic | for-the-badge  — shields کلاسیک
 *   heart | sponsor                 — دکمهٔ قلب شبیه Sponsor گیت‌هاب
 *   heart-only                      — فقط آیکون قلب (کنار نام ریپو در README)
 *
 * مثال:
 *   /badge.php?slug=ali&style=heart
 *   /badge.php?slug=ali&style=heart&label=fa
 *   /badge/ali.svg?style=heart
 */
require_once __DIR__ . '/lib/Embed.php';

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');

$slug = (string) ($_GET['slug'] ?? $_GET['u'] ?? '');
$style = strtolower((string) ($_GET['style'] ?? 'flat'));
$label = (string) ($_GET['label'] ?? 'yavar');
$showStats = !isset($_GET['stats']) || $_GET['stats'] !== '0';

// aliasها
if (in_array($style, ['gh', 'github', 'sponsor-btn', 'donate-btn'], true)) {
    $style = 'heart';
}
if (in_array($style, ['icon', 'heart-icon', 'love'], true)) {
    $style = 'heart-only';
}

$user = Embed::loadActivist($slug);
$found = $user !== null;
$card = $found ? Embed::publicCard($user) : null;

$brand = '#B04040';
$brandSoft = '#e07070';
$dark = '#21262d'; // نزدیک به UI گیت‌هاب

// ——— فقط قلب (شبیه آیکون Sponsor) ———
if ($style === 'heart-only') {
    $size = max(16, min(64, (int) ($_GET['size'] ?? 28)));
    $fill = $found ? $brand : '#6b7280';
    $title = $found
        ? ('حمایت از ' . ($card['name'] ?? $slug) . ' — یاور')
        : 'Yavar';
    $titleEsc = Embed::escapeSvg($title);
    $s = (string) $size;
    // viewBox 0 0 24 24 heart
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" role="img" aria-label="' . $titleEsc . '">';
    echo '<title>' . $titleEsc . '</title>';
    echo '<path fill="' . Embed::escapeSvg($fill) . '" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>';
    echo '</svg>';
    exit;
}

// ——— دکمهٔ قلب + متن (شبیه Sponsor) ———
if ($style === 'heart' || $style === 'sponsor') {
    // متن دکمه
    if (!$found) {
        $text = 'Yavar';
        $sub = '';
        $fill = '#6b7280';
        $bg = '#30363d';
        $fg = '#e6edf3';
    } else {
        $fill = $brand;
        $bg = $dark;
        $fg = '#f0f6fc';
        $text = match ($label) {
            'fa', 'fa-support', 'حمایت', 'fa-heart' => 'حمایت',
            'donate' => 'Donate',
            'sponsor' => 'Sponsor',
            'support' => 'Support',
            'en' => 'Donate',
            default => 'Donate',
        };
        // اگر stats و حمایت وجود دارد، کوتاه نشان بده
        if ($showStats && (int) $card['count'] > 0 && isset($_GET['stats']) && $_GET['stats'] === '1') {
            $text .= ' · ' . number_format((int) $card['count']);
        }
    }

    $text = mb_substr(preg_replace('/[^\p{L}\p{N}\s\-_.·,]/u', '', $text) ?? 'Donate', 0, 28);
    $textEsc = Embed::escapeSvg($text);
    $titleEsc = Embed::escapeSvg(
        $found
            ? ('حمایت از ' . ($card['name'] ?? $slug) . ' — یاور')
            : 'Yavar support'
    );

    // اندازه‌ها — نزدیک به ارتفاع بج‌های گیت‌هاب (~28–32)
    $h = 28;
    $padX = 12;
    $heartW = 14;
    $gap = 7;
    $charW = preg_match('/[\x{0600}-\x{06FF}]/u', $text) ? 8.2 : 7.0;
    $textW = Embed::textWidth($text, $charW);
    $w = (int) ceil($padX + $heartW + $gap + $textW + $padX);
    if ($w < 88) {
        $w = 88;
    }

    $hx = $padX; // heart left
    $hy = ($h - $heartW) / 2;
    $tx = $padX + $heartW + $gap + $textW / 2;
    $ty = $h / 2;

    // scale heart path from 24x24 to heartW
    $scale = $heartW / 24;
    $heartTransform = sprintf('translate(%.2f,%.2f) scale(%.4f)', $hx, $hy, $scale);

    echo '<?xml version="1.0" encoding="UTF-8"?>';
    ?>
<svg xmlns="http://www.w3.org/2000/svg" width="<?= $w ?>" height="<?= $h ?>" role="img" aria-label="<?= $titleEsc ?>">
  <title><?= $titleEsc ?></title>
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="<?= Embed::escapeSvg($brandSoft) ?>"/>
      <stop offset="100%" stop-color="<?= Embed::escapeSvg($fill) ?>"/>
    </linearGradient>
  </defs>
  <!-- پس‌زمینه شبیه دکمهٔ GitHub -->
  <rect width="<?= $w ?>" height="<?= $h ?>" rx="14" fill="<?= Embed::escapeSvg($bg) ?>" stroke="#30363d" stroke-width="1"/>
  <!-- قلب -->
  <g transform="<?= Embed::escapeSvg($heartTransform) ?>">
    <path fill="url(#g)" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
  </g>
  <!-- متن -->
  <text x="<?= $tx ?>" y="<?= $ty ?>" dy=".35em" text-anchor="middle"
        font-family="Segoe UI,Ubuntu,Cantarell,Helvetica Neue,Arial,sans-serif"
        font-size="12" font-weight="600" fill="<?= Embed::escapeSvg($fg) ?>"><?= $textEsc ?></text>
</svg>
<?php
    exit;
}

// ——— shields کلاسیک (flat / plastic / for-the-badge) ———
if (!in_array($style, ['flat', 'plastic', 'for-the-badge'], true)) {
    $style = 'flat';
}

if (!$found) {
    $left = 'yavar';
    $right = 'not found';
    $color = '#6b7280';
} else {
    $left = match ($label) {
        'fa', 'fa-support', 'حمایت' => 'حمایت',
        'donate' => 'donate',
        'support' => 'support',
        'heart' => '♥ donate',
        default => 'یاور',
    };
    if ($showStats && $card['count'] > 0) {
        $right = number_format($card['count']) . ' · ' . number_format($card['sum']) . 't';
    } else {
        $right = match ($label) {
            'fa', 'fa-support', 'حمایت' => 'از ما حمایت کنید',
            'heart' => 'support us',
            default => 'support us',
        };
    }
    $color = $brand;
}

$left = mb_substr(preg_replace('/[^\p{L}\p{N}\s\-_.♥]/u', '', $left) ?? 'yavar', 0, 24);
$right = mb_substr(preg_replace('/[^\p{L}\p{N}\s\-_.·,]/u', '', $right) ?? '', 0, 40);

$pad = $style === 'for-the-badge' ? 14 : 10;
$fontSize = $style === 'for-the-badge' ? 12 : 11;
$h = $style === 'for-the-badge' ? 28 : 20;
$lw = Embed::textWidth($left, $style === 'for-the-badge' ? 8 : 6.8) + $pad * 2;
$rw = Embed::textWidth($right, $style === 'for-the-badge' ? 8 : 6.8) + $pad * 2;
$w = $lw + $rw;
$radius = $style === 'plastic' ? 4 : ($style === 'for-the-badge' ? 3 : 3);

$leftEsc = Embed::escapeSvg($left);
$rightEsc = Embed::escapeSvg($right);
$grad = $style === 'plastic'
    ? '<linearGradient id="s" x2="0" y2="100%"><stop offset="0" stop-color="#fff" stop-opacity=".15"/><stop offset="1" stop-opacity=".1"/></linearGradient>'
    : '';

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<svg xmlns="http://www.w3.org/2000/svg" width="<?= $w ?>" height="<?= $h ?>" role="img" aria-label="<?= $leftEsc ?>: <?= $rightEsc ?>">
  <title><?= $leftEsc ?>: <?= $rightEsc ?></title>
  <?= $grad ?>
  <clipPath id="r"><rect width="<?= $w ?>" height="<?= $h ?>" rx="<?= $radius ?>" fill="#fff"/></clipPath>
  <g clip-path="url(#r)">
    <rect width="<?= $lw ?>" height="<?= $h ?>" fill="#1f2937"/>
    <rect x="<?= $lw ?>" width="<?= $rw ?>" height="<?= $h ?>" fill="<?= Embed::escapeSvg($color) ?>"/>
    <?php if ($style === 'plastic'): ?><rect width="<?= $w ?>" height="<?= $h ?>" fill="url(#s)"/><?php endif; ?>
  </g>
  <g fill="#fff" text-anchor="middle" font-family="Verdana,DejaVu Sans,sans-serif" font-size="<?= $fontSize ?>"<?= $style === 'for-the-badge' ? ' font-weight="700"' : '' ?>>
    <text x="<?= $lw / 2 ?>" y="<?= $h / 2 ?>" dy=".35em"><?= $leftEsc ?></text>
    <text x="<?= $lw + $rw / 2 ?>" y="<?= $h / 2 ?>" dy=".35em"><?= $rightEsc ?></text>
  </g>
</svg>
