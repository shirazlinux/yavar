<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function layout_header(string $title, string $desc = ''): void
{
    $cfg = app_config();
    $user = auth_user();
    $site = (string)($cfg['site_name'] ?? 'یاور');
    if (trim($title) === '' || trim($title) === trim($site)) {
        $full = e($site . ' | شیرازلینوکس');
    } else {
        $full = e($title . ' | ' . $site);
    }
    $desc = e($desc !== '' ? $desc : 'یاور — حمایت مستقیم از فعالان و پروژه‌های نرم‌افزار آزاد · بدون کارمزد');
    $logo = 'https://sudoshz.ir/media/website/webicon320.png';
    ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $full ?></title>
  <meta name="description" content="<?= $desc ?>">
  <meta property="og:title" content="<?= $full ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= e($cfg['site_url'] ?? 'https://donate.sudoshz.ir') ?>">
  <meta property="og:site_name" content="شیرازلینوکس">
  <meta property="og:locale" content="fa_IR">
  <meta name="theme-color" content="#F1592D">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <link rel="canonical" href="<?= e(rtrim($cfg['site_url'], '/') . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=14">
  <link rel="icon" href="<?= e($logo) ?>">
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div class="header-start">
        <a class="brand" href="/">
          <img class="brand-logo" src="<?= e($logo) ?>" width="42" height="42" alt="یاور">
          <span class="brand-text">
            <strong>یاور</strong>
            <span>شیرازلینوکس</span>
          </span>
        </a>
        <nav class="nav" aria-label="ناوبری اصلی">
          <a href="/">خانه</a>
          <a href="/ways.php">روش‌های حمایت</a>
          <a href="/about.php">خط‌مشی</a>
          <a href="/contact.php">ارتباط با ما</a>
          <?php if ($user): ?>
            <a href="/dashboard/">پنل من</a>
            <?php if (!empty($user['is_admin'])): ?>
              <a href="/admin/">مدیریت</a>
            <?php endif; ?>
            <a href="/logout.php">خروج</a>
          <?php else: ?>
            <a href="/login.php">ورود</a>
            <a class="nav-cta" href="/register.php">ثبت‌نام</a>
          <?php endif; ?>
        </nav>
      </div>
    </div>
  </header>
  <main>
<?php
}

function layout_footer(): void
{
    $cfg = app_config();
    // contact shown below (phone + site host)
    ?>
  </main>
  <footer class="site-footer">
    <div class="container footer-grid">
      <div>
        <div class="footer-brand">یاور</div>
        <p class="footer-muted">پروژه‌ای از شیرازلینوکس برای ترویج و حمایت مستقیم از فعالان و پروژه‌های نرم‌افزار آزاد.</p>
        <p class="footer-muted"><strong>بدون کارمزد</strong> · فقط نرم‌افزار آزاد</p>
      </div>
      <div>
        <div class="footer-heading">دسترسی</div>
        <div class="footer-links-col">
          <a href="/register.php">ثبت صفحه حمایت</a>
          <a href="/ways.php">روش‌های حمایت</a>
          <a href="/about.php">خط‌مشی یاور</a>
          <a href="/contact.php">ارتباط با ما</a>
          <a href="https://sudoshz.ir/donate/" target="_blank" rel="noopener">حمایت از شیرازلینوکس</a>
        </div>
      </div>
      <div>
        <div class="footer-heading">نرم‌افزار آزاد</div>
        <div class="footer-links-col">
          <a href="https://sudoshz.ir/what-is-free-software/" target="_blank" rel="noopener">نرم‌افزار آزاد چیه؟</a>
          <a href="https://sudoshz.ir/manifesto/" target="_blank" rel="noopener">مانیفست گنو</a>
          <a href="https://sudoshz.ir/fsf-history-redirect/" target="_blank" rel="noopener">تاریخچه نرم‌افزار آزاد</a>
          <a href="https://free.sudoshz.ir/" target="_blank" rel="noopener">جنبش نرم‌افزار آزاد</a>
          <?php
            $srcUrl = trim((string) ($cfg['source_code_url'] ?? ''));
            if ($srcUrl !== '' && preg_match('#^https?://#i', $srcUrl)):
          ?>
            <a href="<?= e($srcUrl) ?>" target="_blank" rel="noopener noreferrer">کد منبع یاور (آزاد)</a>
          <?php endif; ?>
        </div>
      </div>
      <div>
        <div class="footer-heading">ارتباط با ما</div>
        <ul class="footer-contact">
          <?php
            $footPhone = '09353554898';
            $footSite = preg_replace('#^https?://#i', '', rtrim((string)($cfg['site_url'] ?? 'donate.sudoshz.ir'), '/'));
          ?>
          <li><strong>تلفن:</strong> <a href="tel:+98<?= e(ltrim($footPhone, '0')) ?>" dir="ltr"><?= e(fa_digits($footPhone)) ?></a></li>
          <li><strong>نشانی:</strong> <a href="https://<?= e($footSite) ?>" dir="ltr"><?= e($footSite) ?></a></li>
          <li><a href="https://sudoshz.ir/" target="_blank" rel="noopener">sudoshz.ir</a></li>
        </ul>
      </div>
    </div>

    <div class="container footer-sponsors">
      <div class="sponsor-label">حامی</div>
      <a href="https://shirazweb.net/?ref=sudoshz.ir" target="_blank" rel="noopener" title="شیرازوب">
        <img src="https://sudoshz.ir/media/posts/130/shirazweb-logo-transparent-background.png" alt="شیرازوب — حامی" width="150" height="auto" loading="lazy">
      </a>
      <a referrerpolicy="origin" target="_blank" rel="noopener" href="https://trustseal.enamad.ir/?id=651002&amp;Code=lsuiBjkKBPPqeHFnxv8Q1a5AdfRTcqah" title="نماد اعتماد الکترونیکی">
        <img referrerpolicy="origin" src="https://trustseal.enamad.ir/logo.aspx?id=651002&amp;Code=lsuiBjkKBPPqeHFnxv8Q1a5AdfRTcqah" alt="اینماد" width="125" height="auto" loading="lazy" style="cursor:pointer" code="lsuiBjkKBPPqeHFnxv8Q1a5AdfRTcqah">
      </a>
    </div>

    <div class="container footer-bottom">
      <span>© <?= e(fa_digits((string) (int) date('Y'))) ?> — <a href="https://sudoshz.ir">شیرازلینوکس</a></span>
      <span>
        یاور · نرم‌افزار آزاد
        <?php
          $srcUrl = trim((string) ($cfg['source_code_url'] ?? ''));
          if ($srcUrl !== '' && preg_match('#^https?://#i', $srcUrl)):
        ?>
          · <a href="<?= e($srcUrl) ?>" target="_blank" rel="noopener noreferrer" dir="ltr">Source</a>
        <?php endif; ?>
      </span>
    </div>
  </footer>
  <script src="/assets/js/money.js?v=1" defer></script>
  <script src="/assets/js/main.js?v=12" defer></script>
</body>
</html>
<?php
}
