<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
$cfg = app_config();
layout_header('ارتباط با ما', 'راه‌های ارتباط با یاور — پلتفرم عام‌المنفعه حمایت از فعالان و پروژه‌های نرم‌افزار آزاد.');
$phone = '09353554898';
$site = preg_replace('#^https?://#i', '', rtrim((string) ($cfg['site_url'] ?? 'https://yavar.sudoshz.ir'), '/'));
$email = (string) ($cfg['contact_email'] ?? $cfg['admin_notify_email'] ?? '');
?>
<section class="page-section">
  <div class="container narrow">
    <h1 class="page-title">تماس</h1>
    <p class="page-lead">برای پشتیبانی، ثبت‌نام فعالان و پیگیری پرداخت.</p>
    <div class="card form-card">
      <table class="data-table contact-table">
        <tr>
          <th>تلفن</th>
          <td dir="ltr"><a href="tel:+98<?= e(ltrim($phone, '0')) ?>"><?= e(fa_digits($phone)) ?></a></td>
        </tr>
        <tr>
          <th>نشانی پلتفرم</th>
          <td dir="ltr"><a href="https://<?= e($site) ?>"><?= e($site) ?></a></td>
        </tr>
        <?php if ($email !== ''): ?>
        <tr>
          <th>ایمیل</th>
          <td dir="ltr"><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></td>
        </tr>
        <?php endif; ?>
      </table>
    </div>
    <p style="margin-top:1rem">
      <a class="btn btn-primary" href="tel:+98<?= e(ltrim($phone, '0')) ?>">تماس تلفنی</a>
      <a class="btn btn-ghost" href="/">خانه</a>
    </p>
  </div>
</section>
<?php layout_footer(); ?>
