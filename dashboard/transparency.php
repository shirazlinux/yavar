<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/campaigns.php';
require_once dirname(__DIR__) . '/lib/Captcha.php';
require_once dirname(__DIR__) . '/lib/Transparency.php';
require_once dirname(__DIR__) . '/lib/RateLimit.php';

$user = auth_require_login();
if (($user['role'] ?? 'hamyar') === 'supporter' && empty($user['is_admin'])) {
    header('Location: /dashboard/supporter.php');
    exit;
}

$errors = [];
$flash = '';
$uid = (int) $user['id'];

function transparency_parse_amount(string $raw): int
{
    if (class_exists('Captcha')) {
        $raw = Captcha::digitsEn($raw);
    } else {
        $map = ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'];
        $raw = strtr($raw, $map);
    }
    $raw = preg_replace('/\D+/', '', $raw) ?? '';
    return (int) $raw;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $errors[] = 'نشست منقضی شد.';
    }
    $rl = RateLimit::hit('spend_edit', 40, 3600);
    if (!$rl['ok']) {
        $errors[] = 'تعداد تغییرات زیاد است. کمی بعد دوباره تلاش کنید.';
    }
    $action = (string) ($_POST['action'] ?? '');

    if (!$errors && $action === 'save_settings') {
        $show = !empty($_POST['show_transparency']) ? 1 : 0;
        $note = trim((string) ($_POST['transparency_note'] ?? ''));
        if (mb_strlen($note) > 500) {
            $errors[] = 'یادداشت حداکثر ۵۰۰ نویسه.';
        }
        if (!$errors) {
            db()->prepare('UPDATE users SET show_transparency=?, transparency_note=?, updated_at=? WHERE id=?')
                ->execute([$show, $note, gmdate('c'), $uid]);
            $st = db()->prepare('SELECT * FROM users WHERE id=?');
            $st->execute([$uid]);
            $user = $st->fetch() ?: $user;
            $flash = 'تنظیمات نمایش ذخیره شد.';
        }
    }

    if (!$errors && $action === 'add') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $amount = transparency_parse_amount((string) ($_POST['amount'] ?? ''));
        $spentOnRaw = trim((string) ($_POST['spent_on'] ?? ''));
        $spentOn = jalali_input_to_gregorian($spentOnRaw) ?? '';
        $note = trim((string) ($_POST['note'] ?? ''));
        if (mb_strlen($title) < 2 || mb_strlen($title) > 120) {
            $errors[] = 'عنوان هزینه بین ۲ تا ۱۲۰ نویسه باشد.';
        }
        if ($amount < 1000) {
            $errors[] = 'مبلغ هزینه حداقل ۱٬۰۰۰ تومان.';
        }
        if ($amount > 50_000_000_000) {
            $errors[] = 'مبلغ نامعتبر است.';
        }
        if ($spentOn === '') {
            $errors[] = 'تاریخ را به صورت شمسی معتبر وارد کنید (مثال: ۱۴۰۵/۰۶/۱۵).';
        }
        if (mb_strlen($note) > 400) {
            $errors[] = 'توضیح حداکثر ۴۰۰ نویسه.';
        }
        if (!$errors) {
            $now = gmdate('c');
            db()->prepare(
                'INSERT INTO spend_entries (user_id, title, amount, spent_on, note, created_at, updated_at) VALUES (?,?,?,?,?,?,?)'
            )->execute([$uid, $title, $amount, $spentOn, $note, $now, $now]);
            $flash = 'مورد هزینه اضافه شد.';
        }
    }

    if (!$errors && $action === 'delete') {
        $id = (int) ($_POST['entry_id'] ?? 0);
        if ($id > 0) {
            $st = db()->prepare('DELETE FROM spend_entries WHERE id=? AND user_id=?');
            $st->execute([$id, $uid]);
            $flash = $st->rowCount() > 0 ? 'حذف شد.' : 'موردی پیدا نشد.';
        }
    }

    if (!$errors && $action === 'update') {
        $id = (int) ($_POST['entry_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $amount = transparency_parse_amount((string) ($_POST['amount'] ?? ''));
        $spentOnRaw = trim((string) ($_POST['spent_on'] ?? ''));
        $spentOn = jalali_input_to_gregorian($spentOnRaw) ?? '';
        $note = trim((string) ($_POST['note'] ?? ''));
        if ($id < 1) {
            $errors[] = 'شناسه نامعتبر.';
        }
        if (mb_strlen($title) < 2 || mb_strlen($title) > 120) {
            $errors[] = 'عنوان هزینه بین ۲ تا ۱۲۰ نویسه باشد.';
        }
        if ($amount < 1000) {
            $errors[] = 'مبلغ هزینه حداقل ۱٬۰۰۰ تومان.';
        }
        if ($spentOn === '') {
            $errors[] = 'تاریخ را به صورت شمسی معتبر وارد کنید (مثال: ۱۴۰۵/۰۶/۱۵).';
        }
        if (mb_strlen($note) > 400) {
            $errors[] = 'توضیح حداکثر ۴۰۰ نویسه.';
        }
        if (!$errors) {
            $st = db()->prepare(
                'UPDATE spend_entries SET title=?, amount=?, spent_on=?, note=?, updated_at=? WHERE id=? AND user_id=?'
            );
            $st->execute([$title, $amount, $spentOn, $note, gmdate('c'), $id, $uid]);
            $flash = $st->rowCount() > 0 ? 'به‌روز شد.' : 'موردی پیدا نشد.';
        }
    }
}

$entries = Transparency::listForUser($uid);
$received = Transparency::receivedSum($uid);
$spent = Transparency::spentSum($uid);
$remaining = $received - $spent;
$editId = (int) ($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    foreach ($entries as $en) {
        if ((int) $en['id'] === $editId) {
            $editRow = $en;
            break;
        }
    }
}

$todayTehran = (new DateTime('now', new DateTimeZone('Asia/Tehran')))->format('Y-m-d');
$formSpentOn = $editRow
    ? gregorian_to_jalali_str((string) $editRow['spent_on'])
    : gregorian_to_jalali_str($todayTehran);
$formAmount = $editRow ? number_fa((int) $editRow['amount']) : '';
$formTitle = (string) ($editRow['title'] ?? '');
$formNote = (string) ($editRow['note'] ?? '');
// اگر POST ناموفق بود، ورودی کاربر را نگه دار
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array((string) ($_POST['action'] ?? ''), ['add', 'update'], true) && $errors) {
    $formSpentOn = trim((string) ($_POST['spent_on'] ?? $formSpentOn));
    $formAmount = trim((string) ($_POST['amount'] ?? $formAmount));
    $formTitle = trim((string) ($_POST['title'] ?? $formTitle));
    $formNote = trim((string) ($_POST['note'] ?? $formNote));
}

layout_header('شفافیت مالی');
?>
<section class="page-section">
  <div class="container dash-page">
    <h1 class="page-title">شفافیت مالی</h1>
    <?php dashboard_nav($user, 'transparency'); ?>
    <p class="page-lead">
      هزینه‌های حمایت دریافتی را ثبت کنید تا در صفحهٔ عمومی‌تان دیده شود.
      <?php if (($user['status'] ?? '') === 'approved' && !empty($user['slug'])): ?>
        <a href="/u/<?= e(rawurlencode((string) $user['slug'])) ?>#transparency" target="_blank" rel="noopener">مشاهده در صفحه عمومی</a>
      <?php endif; ?>
    </p>

    <?php if ($flash): ?><div class="form-msg show ok"><?= e($flash) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="form-msg show error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

    <div class="transparency-summary" style="margin-bottom:1.25rem">
      <div class="transparency-summary__item">
        <span class="transparency-summary__label">جمع حمایت تأییدشده</span>
        <strong class="transparency-summary__value"><?= e(money_fa($received)) ?></strong>
      </div>
      <div class="transparency-summary__item">
        <span class="transparency-summary__label">جمع هزینه‌ها</span>
        <strong class="transparency-summary__value"><?= e(money_fa($spent)) ?></strong>
      </div>
      <div class="transparency-summary__item transparency-summary__item--remain<?= $remaining < 0 ? ' is-negative' : '' ?>">
        <span class="transparency-summary__label">باقی‌مانده</span>
        <strong class="transparency-summary__value"><?= e(money_fa($remaining)) ?></strong>
      </div>
    </div>

    <form method="post" class="card form-card" style="margin-bottom:1.25rem">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_settings">
      <h2 class="form-subhead" style="margin-top:0">نمایش در صفحه عمومی</h2>
      <label class="check-line">
        <input type="checkbox" name="show_transparency" value="1" <?= !empty($user['show_transparency']) ? 'checked' : '' ?>>
        پنل شفافیت مالی برای عموم روی صفحهٔ من نمایش داده شود
      </label>
      <label for="transparency_note">یادداشت کوتاه بالای جدول (اختیاری)</label>
      <textarea id="transparency_note" name="transparency_note" rows="2" maxlength="500"><?= e((string) ($user['transparency_note'] ?? '')) ?></textarea>
      <p class="hint">مثلاً دورهٔ گزارش یا توضیح کلی. ارقام هزینه اظهار شماست.</p>
      <button type="submit" class="btn btn-primary">ذخیره تنظیمات نمایش</button>
    </form>

    <form method="post" class="card form-card" style="margin-bottom:1.25rem">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <?php if ($editRow): ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="entry_id" value="<?= (int) $editRow['id'] ?>">
        <h2 class="form-subhead" style="margin-top:0">ویرایش مورد هزینه</h2>
      <?php else: ?>
        <input type="hidden" name="action" value="add">
        <h2 class="form-subhead" style="margin-top:0">افزودن مورد هزینه</h2>
      <?php endif; ?>
      <label for="title">عنوان *</label>
      <input id="title" name="title" required maxlength="120" value="<?= e($formTitle) ?>" placeholder="مثلاً اجاره سرور ماهانه">
      <div class="grid-2">
        <div>
          <label for="spend-amount">مبلغ (تومان) *</label>
          <input id="spend-amount" name="amount" type="text" required dir="ltr" class="ltr-field money-input"
                 inputmode="numeric" placeholder="۵۰٬۰۰۰" autocomplete="off"
                 value="<?= e($formAmount) ?>">
          <p class="hint" style="margin:.35rem 0 0">با جداکننده هزارگان نوشته می‌شود</p>
        </div>
        <div>
          <label for="spent_on">تاریخ (تقویم شمسی) *</label>
          <input id="spent_on" name="spent_on" type="text" required dir="ltr" class="ltr-field jalali-date"
                 inputmode="numeric" placeholder="۱۴۰۵/۰۶/۱۵" autocomplete="off"
                 value="<?= e($formSpentOn) ?>">
          <p class="hint" style="margin:.35rem 0 0">مثال: ۱۴۰۵/۰۶/۱۵ — ارقام فارسی یا انگلیسی</p>
        </div>
      </div>
      <label for="note">توضیح کوتاه (اختیاری)</label>
      <input id="note" name="note" maxlength="400" value="<?= e($formNote) ?>">
      <div class="btn-toolbar" style="margin-top:1rem">
        <button type="submit" class="btn btn-primary"><?= $editRow ? 'ذخیره تغییرات' : 'افزودن' ?></button>
        <?php if ($editRow): ?>
          <a class="btn btn-ghost" href="/dashboard/transparency.php">انصراف</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="card">
      <h2 style="margin-top:0;font-size:1.1rem">فهرست هزینه‌ها</h2>
      <?php if (!$entries): ?>
        <p class="hint" style="margin:0">هنوز موردی ثبت نکرده‌اید. از فرم بالا اضافه کنید.</p>
      <?php else: ?>
        <div class="table-wrap transparency-table-wrap">
          <table class="transparency-table">
            <thead>
              <tr>
                <th>مورد</th>
                <th>تاریخ</th>
                <th>مبلغ</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($entries as $en): ?>
                <tr>
                  <td data-label="مورد">
                    <div class="transparency-title-cell"><?= e((string) $en['title']) ?></div>
                    <?php if (trim((string) ($en['note'] ?? '')) !== ''): ?>
                      <div class="hint" style="margin:.2rem 0 0"><?= e((string) $en['note']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td data-label="تاریخ" dir="ltr"><?= e(Transparency::formatSpentOn((string) $en['spent_on'])) ?></td>
                  <td data-label="مبلغ"><?= e(money_fa((int) $en['amount'])) ?></td>
                  <td class="transparency-actions" data-label="">
                    <a class="btn btn-ghost btn-sm" href="/dashboard/transparency.php?edit=<?= (int) $en['id'] ?>">ویرایش</a>
                    <form method="post" onsubmit="return confirm('حذف این مورد؟');">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="entry_id" value="<?= (int) $en['id'] ?>">
                      <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<script>
(function(){
  var el=document.getElementById('spent_on');
  if(!el) return;
  var map={'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9','٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'};
  function toEn(s){return String(s||'').replace(/[۰-۹٠-٩]/g,function(d){return map[d]||d;});}
  el.addEventListener('blur',function(){
    var v=toEn(el.value).replace(/[^0-9\/\-]/g,'');
    // keep as typed; server accepts both. Optionally format with /
    el.value=v;
  });
})();
</script>
<?php layout_footer(); ?>
