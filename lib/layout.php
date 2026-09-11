<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/**
 * منوی مشترک پنل فعال/پروژه — در همه صفحات داشبورد یکسان.
 * @param array<string,mixed>|null $user
 */
function dashboard_nav(?array $user = null, ?string $active = null): void
{
    $user = $user ?? auth_user();
    if (!$user) {
        return;
    }
    // پنل حامی منوی جدا دارد
    if (($user['role'] ?? 'hamyar') === 'supporter' && empty($user['is_admin'])) {
        return;
    }

    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $detect = match ($script) {
        'index.php' => 'home',
        'profile.php' => 'pages',
        'pages.php' => 'pages',
        'transparency.php' => 'transparency',
        'campaigns.php' => 'campaigns',
        'telegram.php' => 'telegram',
        'widgets.php' => 'widgets',
        default => '',
    };
    $active = $active ?? $detect;

    $items = [
        ['id' => 'home', 'href' => '/dashboard/', 'label' => 'پنل من'],
        ['id' => 'pages', 'href' => '/dashboard/pages.php', 'label' => 'صفحات من'],
        ['id' => 'transparency', 'href' => '/dashboard/transparency.php', 'label' => 'شفافیت مالی'],
        ['id' => 'campaigns', 'href' => '/dashboard/campaigns.php', 'label' => 'حمایت‌های هدفمند'],
        ['id' => 'telegram', 'href' => '/dashboard/telegram.php', 'label' => 'اعلان تلگرام'],
        ['id' => 'widgets', 'href' => '/dashboard/widgets.php', 'label' => 'ابزارک و بج'],
    ];

    $publicPath = '';
    if (($user['status'] ?? '') === 'approved' && !empty($user['slug'])) {
        $publicPath = '/u/' . rawurlencode((string) $user['slug']);
    }

    $currentLabel = 'پنل';
    foreach ($items as $it) {
        if ($active === $it['id']) {
            $currentLabel = $it['label'];
            break;
        }
    }

    echo '<nav class="dash-nav" aria-label="منوی پنل">';
    // open پیش‌فرض برای دسکتاپ؛ روی موبایل nav.js جمع می‌کند
    echo '<details class="dash-nav__details" open>';
    echo '<summary class="dash-nav__summary">';
    echo '<span class="dash-nav__summary-title">منوی پنل</span>';
    echo '<span class="dash-nav__summary-current">' . e($currentLabel) . '</span>';
    echo '<span class="dash-nav__summary-chevron" aria-hidden="true"></span>';
    echo '</summary>';
    echo '<div class="dash-nav__scroll">';
    foreach ($items as $it) {
        $isActive = $active === $it['id'];
        $cls = 'btn btn-ghost dash-nav__link' . ($isActive ? ' is-active' : '');
        $aria = $isActive ? ' aria-current="page"' : '';
        echo '<a class="' . e($cls) . '" href="' . e($it['href']) . '"' . $aria . '>' . e($it['label']) . '</a>';
    }
    if ($publicPath !== '') {
        echo '<a class="btn btn-primary dash-nav__link dash-nav__public" href="' . e($publicPath) . '" target="_blank" rel="noopener">صفحه عمومی</a>';
    }
    echo '</div></details></nav>';
}

function layout_header(string $title, string $desc = '', array $opts = []): void
{
    $cfg = app_config();
    $user = auth_user();
    $site = (string) ($cfg['site_name'] ?? 'یاور');
    $tagline = (string) ($cfg['tagline'] ?? 'پلتفرم حمایت از پروژه‌ها و جوامع نرم‌افزار آزاد');
    $base = rtrim((string) ($cfg['site_url'] ?? 'https://donate.sudoshz.ir'), '/');

    $isHome = (trim($title) === '' || trim($title) === trim($site));
    if ($isHome) {
        $full = e($site . ' | ' . $tagline);
        $ogTitle = e($site . ' — ' . $tagline);
    } else {
        $full = e($title . ' | ' . $site);
        $ogTitle = e($title);
    }

    $rawDesc = $desc !== ''
        ? $desc
        : ($site . ' — ' . $tagline . ' · یاری‌رسان حامیان نرم‌افزار آزاد · بدون کارمزد پلتفرم · عام‌المنفعه');
    // meta description ~150–160 chars ideal
    if (function_exists('mb_substr')) {
        $rawDesc = trim(preg_replace('/\s+/u', ' ', $rawDesc) ?? $rawDesc);
        if (mb_strlen($rawDesc) > 180) {
            $rawDesc = mb_substr($rawDesc, 0, 177) . '…';
        }
    }
    $descEsc = e($rawDesc);

    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/') ?: '/';
    }
    $canonical = $base . ($path === '/' ? '/' : $path);

    $defaultOg = $base . '/assets/brand/og-default.jpg';
    $ogImage = trim((string) ($opts['image'] ?? ''));
    if ($ogImage === '' || !preg_match('#^https?://#i', $ogImage)) {
        $ogImage = $defaultOg;
    }
    $ogImageAlt = trim((string) ($opts['image_alt'] ?? ($site . ' — ' . $tagline)));
    $ogType = trim((string) ($opts['type'] ?? 'website'));
    if (!in_array($ogType, ['website', 'article', 'profile'], true)) {
        $ogType = 'website';
    }
    $ogW = (int) ($opts['image_width'] ?? ($ogImage === $defaultOg ? 1200 : 0));
    $ogH = (int) ($opts['image_height'] ?? ($ogImage === $defaultOg ? 630 : 0));

    // صفحات خصوصی را ایندکس نکن
    $noindex = !empty($opts['noindex']);
    if (!$noindex) {
        foreach (['/dashboard', '/admin', '/api/', '/login', '/logout', '/register', '/checkout', '/result', '/forgot-password', '/reset-password', '/payping-callback'] as $p) {
            if ($path === $p || str_starts_with($path, $p) || str_starts_with($path, $p . '.')) {
                $noindex = true;
                break;
            }
        }
        if (preg_match('#^/(login|logout|register|checkout|result|forgot-password|reset-password|payping-callback)\.php$#', $path)) {
            $noindex = true;
        }
    }

    $logo = $base . '/assets/brand/logo-header.png';
    $favicon = $base . '/assets/brand/favicon-32.png';

    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $base . '/#organization',
                'name' => $site,
                'alternateName' => 'Yavar',
                'url' => $base . '/',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $base . '/assets/brand/icon-512.png',
                    'width' => 512,
                    'height' => 512,
                ],
                'image' => $defaultOg,
                'description' => $tagline . ' — یاری‌رسان حامیان نرم‌افزار آزاد؛ عام‌المنفعه و بدون کارمزد پلتفرم.',
                'sameAs' => array_values(array_filter([
                    trim((string) ($cfg['source_code_url'] ?? '')),
                ])),
            ],
            [
                '@type' => 'WebSite',
                '@id' => $base . '/#website',
                'url' => $base . '/',
                'name' => $site,
                'description' => $tagline,
                'inLanguage' => 'fa-IR',
                'publisher' => ['@id' => $base . '/#organization'],
            ],
            [
                '@type' => 'WebPage',
                '@id' => $canonical . '#webpage',
                'url' => $canonical,
                'name' => html_entity_decode($isHome ? $site . ' | ' . $tagline : $title . ' | ' . $site, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'description' => $rawDesc,
                'isPartOf' => ['@id' => $base . '/#website'],
                'inLanguage' => 'fa-IR',
                'primaryImageOfPage' => [
                    '@type' => 'ImageObject',
                    'url' => $ogImage,
                ],
            ],
        ],
    ];
    if (!empty($opts['jsonld']) && is_array($opts['jsonld'])) {
        $jsonLd['@graph'][] = $opts['jsonld'];
    }
    $jsonLdFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_HEX_TAG')) {
        $jsonLdFlags |= JSON_HEX_TAG | JSON_HEX_AMP;
    }
    $jsonLdStr = json_encode($jsonLd, $jsonLdFlags) ?: '{}';

    ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $full ?></title>
  <meta name="description" content="<?= $descEsc ?>">
  <meta name="application-name" content="<?= e($site) ?>">
  <meta name="theme-color" content="#B04040">
  <meta name="color-scheme" content="dark light">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<?php if ($noindex): ?>
  <meta name="robots" content="noindex, nofollow">
<?php else: ?>
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
  <meta name="googlebot" content="index, follow">
<?php endif; ?>

  <!-- Open Graph — پیش‌نمایش لینک در تلگرام / شبکه‌های اجتماعی -->
  <meta property="og:title" content="<?= $ogTitle ?>">
  <meta property="og:description" content="<?= $descEsc ?>">
  <meta property="og:type" content="<?= e($ogType) ?>">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:site_name" content="<?= e($site) ?>">
  <meta property="og:locale" content="fa_IR">
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <meta property="og:image:secure_url" content="<?= e($ogImage) ?>">
  <meta property="og:image:alt" content="<?= e($ogImageAlt) ?>">
  <meta property="og:image:type" content="<?= e(str_ends_with(strtolower(parse_url($ogImage, PHP_URL_PATH) ?: ''), '.jpg') || str_ends_with(strtolower(parse_url($ogImage, PHP_URL_PATH) ?: ''), '.jpeg') ? 'image/jpeg' : 'image/png') ?>">
<?php if ($ogW > 0 && $ogH > 0): ?>
  <meta property="og:image:width" content="<?= (int) $ogW ?>">
  <meta property="og:image:height" content="<?= (int) $ogH ?>">
<?php endif; ?>

  <!-- Twitter / X Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= $ogTitle ?>">
  <meta name="twitter:description" content="<?= $descEsc ?>">
  <meta name="twitter:image" content="<?= e($ogImage) ?>">
  <meta name="twitter:image:alt" content="<?= e($ogImageAlt) ?>">

  <link rel="canonical" href="<?= e($canonical) ?>">
  <link rel="image_src" href="<?= e($ogImage) ?>">
  <link rel="alternate" hreflang="fa" href="<?= e($canonical) ?>">
  <link rel="alternate" hreflang="x-default" href="<?= e($canonical) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=50">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= e($favicon) ?>">
  <link rel="icon" type="image/png" sizes="192x192" href="<?= e($base . '/assets/brand/icon-192.png') ?>">
  <link rel="apple-touch-icon" href="<?= e($base . '/assets/brand/apple-touch-icon.png') ?>">
  <link rel="manifest" href="/site.webmanifest">
  <script type="application/ld+json"><?= $jsonLdStr ?></script>
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="/">
        <img class="brand-logo" src="<?= e($logo) ?>" width="42" height="42" alt="یاور">
        <span class="brand-text">
          <strong>یاور</strong>
          <span>پلتفرم حمایت</span>
        </span>
      </a>
      <div class="header-end">
        <button type="button" class="nav-toggle" id="nav-toggle"
                aria-expanded="false" aria-controls="site-nav" aria-label="باز و بسته کردن منو">
          <span class="nav-toggle-box" aria-hidden="true">
            <span class="nav-toggle-bar"></span>
            <span class="nav-toggle-bar"></span>
            <span class="nav-toggle-bar"></span>
          </span>
        </button>
        <nav class="nav" id="site-nav" aria-label="ناوبری اصلی">
          <a href="/">خانه</a>
          <a href="/ways.php">روش‌های حمایت</a>
          <a href="/about.php">خط‌مشی</a>
          <a href="/contact.php">ارتباط با ما</a>
          <?php if ($user): ?>
            <a href="/dashboard/">پنل من</a>
            <?php if (!empty($user['is_admin'])): ?>
              <a href="/admin/">مدیریت</a>
            <?php endif; ?>
            <a href="/dashboard/telegram.php">اعلان تلگرام</a>
            <a href="/dashboard/widgets.php">ابزارک و بج</a>
            <a class="nav-logout" href="/logout.php">خروج</a>
          <?php else: ?>
            <a href="/login.php">ورود</a>
            <a class="nav-cta" href="/register.php">ثبت‌نام</a>
          <?php endif; ?>
        </nav>
      </div>
    </div>
  </header>
  <div class="nav-backdrop" id="nav-backdrop" hidden></div>
  <main>
<?php
}

function layout_footer(): void
{
    require_once __DIR__ . '/Umami.php';
    $cfg = app_config();
    $site = (string) ($cfg['site_name'] ?? 'یاور');
    $tagline = (string) ($cfg['tagline'] ?? 'پلتفرم حمایت از پروژه‌ها و جوامع نرم‌افزار آزاد');
    $base = rtrim((string) ($cfg['site_url'] ?? 'https://yavar.sudoshz.ir'), '/');
    $footSite = preg_replace('#^https?://#i', '', $base);
    ?>
  </main>
  <footer class="site-footer">
    <div class="container footer-grid">
      <div class="footer-about">
        <div class="footer-brand">یاور</div>
        <p class="footer-muted">یاور بستری برای گردآوردن <strong>فعالان، جوامع و پروژه‌های نرم‌افزار آزاد</strong> است تا بتوانند شفاف، مستقیم و به‌سادگی حمایت دریافت کنند.</p>
        <p class="footer-muted">این پلتفرم کاملاً <strong>عام‌المنفعه</strong> است؛ هزینه‌های اجرای سرویس از سوی اسپانسرها تأمین می‌شود و از مبلغ حمایت‌ها کارمزدی کسر نمی‌گردد. هدف نهایی، <strong>توانمندسازی</strong> فعالان جامعه نرم‌افزار آزاد است.</p>
      </div>
      <div>
        <div class="footer-heading">دسترسی</div>
        <div class="footer-links-col">
          <a href="/register.php">ثبت صفحه حمایت</a>
          <a href="/ways.php">روش‌های حمایت</a>
          <a href="/about.php">خط‌مشی یاور</a>
          <a href="/contact.php">ارتباط با ما</a>
          <?php
            $srcUrl = trim((string) ($cfg['source_code_url'] ?? ''));
            if ($srcUrl !== '' && preg_match('#^https?://#i', $srcUrl)):
          ?>
            <a href="<?= e($srcUrl) ?>" target="_blank" rel="noopener noreferrer">کد منبع</a>
          <?php endif; ?>
        </div>
      </div>
      <div>
        <div class="footer-heading">نرم‌افزار آزاد</div>
        <div class="footer-links-col">
          <a href="https://www.gnu.org/philosophy/free-sw.fa.html" target="_blank" rel="noopener">نرم‌افزار آزاد چیست؟</a>
          <a href="https://www.gnu.org/gnu/manifesto.fa.html" target="_blank" rel="noopener">مانیفست گنو</a>
        </div>
      </div>
      <div>
        <div class="footer-heading">ارتباط با ما</div>
        <ul class="footer-contact">
          <?php $footPhone = '09353554898'; ?>
          <li><strong>تلفن:</strong> <a href="tel:+98<?= e(ltrim($footPhone, '0')) ?>" dir="ltr"><?= e(fa_digits($footPhone)) ?></a></li>
          <li><strong>نشانی:</strong> <a href="<?= e($base) ?>" dir="ltr"><?= e($footSite) ?></a></li>
          <?php if (!empty($cfg['contact_email'])): ?>
            <li><strong>ایمیل:</strong> <a href="mailto:<?= e((string)$cfg['contact_email']) ?>" dir="ltr"><?= e((string)$cfg['contact_email']) ?></a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>

    <div class="container footer-meta-row">
      <div class="footer-sponsors">
        <div class="footer-meta-label">حامی</div>
        <a href="https://shirazweb.net/?ref=yavar" target="_blank" rel="noopener" title="شیرازوب">
          <img src="https://sudoshz.ir/media/posts/130/shirazweb-logo-transparent-background.png" alt="شیرازوب — حامی" width="150" height="auto" loading="lazy">
        </a>
      </div>
      <div class="footer-trust">
        <div class="footer-meta-label">نماد اعتماد</div>
        <a referrerpolicy="origin" target="_blank" rel="noopener" href="https://trustseal.enamad.ir/?id=651002&amp;Code=lsuiBjkKBPPqeHFnxv8Q1a5AdfRTcqah" title="نماد اعتماد الکترونیکی">
          <img referrerpolicy="origin" src="https://trustseal.enamad.ir/logo.aspx?id=651002&amp;Code=lsuiBjkKBPPqeHFnxv8Q1a5AdfRTcqah" alt="اینماد — نماد اعتماد الکترونیکی" width="125" height="auto" loading="lazy" style="cursor:pointer" code="lsuiBjkKBPPqeHFnxv8Q1a5AdfRTcqah">
        </a>
      </div>
    </div>

    <div class="container footer-bottom">
      <span>© <?= e(fa_digits((string) (int) date('Y'))) ?> — یاور</span>
      <span>
        <?= e($tagline) ?> · بدون کارمزد
        <?php
          $srcUrl = trim((string) ($cfg['source_code_url'] ?? ''));
          if ($srcUrl !== '' && preg_match('#^https?://#i', $srcUrl)):
        ?>
          · <a href="<?= e($srcUrl) ?>" target="_blank" rel="noopener noreferrer" dir="ltr">Source</a>
        <?php endif; ?>
      </span>
    </div>
  </footer>
  <script src="/assets/js/money.js?v=2" defer></script>
  <script src="/assets/js/main.js?v=13" defer></script>
  <script src="/assets/js/nav.js?v=2" defer></script>
  <?= Umami::renderScriptTag() ?>
</body>
</html>
<?php
}
