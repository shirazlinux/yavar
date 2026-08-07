<?php
declare(strict_types=1);

/**
 * نقشه سایت پویا برای موتورهای جستجو.
 */
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/db.php';

$cfg = app_config();
$base = rtrim((string) ($cfg['site_url'] ?? 'https://donate.sudoshz.ir'), '/');

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: public, max-age=3600');

$urls = [];

$add = static function (string $loc, string $changefreq, string $priority, ?string $lastmod = null) use (&$urls, $base): void {
    if ($loc === '' || $loc[0] !== '/') {
        return;
    }
    $urls[] = [
        'loc' => $base . $loc,
        'changefreq' => $changefreq,
        'priority' => $priority,
        'lastmod' => $lastmod,
    ];
};

$add('/', 'daily', '1.0');
$add('/about.php', 'monthly', '0.8');
$add('/ways.php', 'monthly', '0.8');
$add('/contact.php', 'monthly', '0.6');
$add('/services.php', 'monthly', '0.5');

try {
    $people = db()->query(
        "SELECT slug, updated_at FROM users
         WHERE status='approved' AND is_admin=0 AND COALESCE(role,'hamyar')='hamyar'
           AND slug IS NOT NULL AND slug <> ''
         ORDER BY updated_at DESC LIMIT 2000"
    )->fetchAll();
    foreach ($people as $p) {
        $slug = rawurlencode((string) $p['slug']);
        $lm = !empty($p['updated_at']) ? date('c', strtotime((string) $p['updated_at']) ?: time()) : null;
        $add('/u/' . $slug, 'weekly', '0.7', $lm);
    }

    $campaigns = db()->query(
        "SELECT c.slug, c.updated_at
         FROM campaigns c
         JOIN users u ON u.id = c.user_id
         WHERE u.status='approved' AND c.slug IS NOT NULL AND c.slug <> ''
           AND c.status IN ('active','successful')
         ORDER BY c.id DESC LIMIT 2000"
    )->fetchAll();
    foreach ($campaigns as $c) {
        $slug = rawurlencode((string) $c['slug']);
        $lm = !empty($c['updated_at']) ? date('c', strtotime((string) $c['updated_at']) ?: time()) : null;
        $add('/c/' . $slug, 'weekly', '0.6', $lm);
    }
} catch (Throwable $e) {
    // اگر DB موقتاً در دسترس نبود، حداقل صفحات ثابت را بده
    error_log('sitemap: ' . $e->getMessage());
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></loc>
<?php if (!empty($u['lastmod'])): ?>
    <lastmod><?= htmlspecialchars((string) $u['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></lastmod>
<?php endif; ?>
    <changefreq><?= htmlspecialchars($u['changefreq'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></changefreq>
    <priority><?= htmlspecialchars($u['priority'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
