<?php
declare(strict_types=1);
/**
 * بج SVG برای GitHub README / سایت
 * مثال: /badge.php?slug=abbasdp&style=flat&label=support
 */
require_once __DIR__ . '/lib/Embed.php';

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');
// بج داخل img است؛ frame لازم نیست
header('Access-Control-Allow-Origin: *');

$slug = (string) ($_GET['slug'] ?? $_GET['u'] ?? '');
$style = (string) ($_GET['style'] ?? 'flat'); // flat | plastic | for-the-badge
$label = (string) ($_GET['label'] ?? 'yavar');
$showStats = !isset($_GET['stats']) || $_GET['stats'] !== '0';

$user = Embed::loadActivist($slug);
if (!$user) {
    $left = 'yavar';
    $right = 'not found';
    $color = '#6b7280';
} else {
    $card = Embed::publicCard($user);
    $left = match ($label) {
        'fa', 'fa-support', 'حمایت' => 'حمایت',
        'donate' => 'donate',
        'support' => 'support',
        default => 'یاور',
    };
    if ($showStats && $card['count'] > 0) {
        $right = number_format($card['count']) . ' · ' . number_format($card['sum']) . 't';
    } else {
        $right = match ($label) {
            'fa', 'fa-support', 'حمایت' => 'از ما حمایت کنید',
            default => 'support us',
        };
    }
    $color = '#F1592D';
}

// sanitize display
$left = mb_substr(preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $left) ?? 'yavar', 0, 24);
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
