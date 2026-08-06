<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
$cfg = app_config();
layout_header('خط‌مشی حمایت', 'خط‌مشی یاور — حمایت از فعالان نرم‌افزار آزاد');
?>
<section class="page-section">
  <div class="container">
    <h1 class="page-title">خط‌مشی حمایت</h1>
    <p class="page-lead">
      <strong>یاور</strong> بخشی از جامعه
      <a href="https://sudoshz.ir">شیرازلینوکس</a> است؛
      جایی برای حمایت مستقیم از فعالان و پروژه‌های نرم‌افزار آزاد.
    </p>

    <div class="causes" style="margin-bottom:1.5rem">
      <article class="cause">
        <h3>هدف</h3>
        <p>
          اعضا بتوانند صفحه عمومی بسازند، از جامعه حمایت بگیرند و مسیر تسویه کاملاً شفاف باشد — بدون هیچ کارمزد پلتفرم.
        </p>
      </article>
      <article class="cause">
        <h3>بدون کارمزد پلتفرم</h3>
        <p>
          از مبلغ حمایت هیچ کارمزدی برنمی‌داریم. تنها هزینه احتمالی، کارمزد درگاه بانکی است که مربوط به بانک است.
        </p>
      </article>
      <article class="cause">
        <h3>چه کسانی تأیید می‌شوند؟</h3>
        <p>
          فقط کسانی که در زمینه نرم‌افزار آزاد فعالیت می‌کنند. پروژه یا خدمت غیرازاد و غیرمرتبط تأیید نمی‌شود.
        </p>
      </article>
    </div>

    <div class="policy-box" id="policy">
      <h2>سیاست شیرازلینوکس</h2>
      <ul>
        <li>این بستر ویژه فعالان و توسعه‌دهندگان نرم‌افزار آزاد است.</li>
        <li>کاربران بدون فعالیت مرتبط یا حمایت برای پروژه غیرازاد تأیید نمی‌شوند.</li>
        <li>مدیریت حق رد یا لغو تأیید را دارد.</li>
        <li>اطلاعات بانکی عضو در صفحه عمومی نمایش داده نمی‌شود.</li>
      </ul>
    </div>

    <p style="margin-top:1.5rem">
      <a class="btn btn-primary" style="width:auto" href="/register.php">ثبت‌نام</a>
      <a class="btn btn-ghost" href="/">خانه</a>
    </p>
  </div>
</section>
<?php layout_footer(); ?>
