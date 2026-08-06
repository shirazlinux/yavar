<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/campaigns.php';
require_once dirname(__DIR__) . '/lib/feed.php';

$user = auth_require_login();
$errors = [];
$ok = false;
$formTitle = '';
$formDesc = '';
$formGoal = '';
$formDeadline = '';
$formTiers = '';
$formPublish = true;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? 'create';
    if ($action === 'create') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $desc = trim((string) ($_POST['description'] ?? ''));
        $goalRaw = trim((string) ($_POST['goal_amount'] ?? ''));
        $goal = (int) preg_replace('/\D+/', '', $goalRaw);
        $deadlineRaw = trim((string) ($_POST['deadline'] ?? ''));
        $deadline = jalali_input_to_gregorian($deadlineRaw) ?? '';
        $tiersRaw = trim((string) ($_POST['tiers'] ?? ''));
        $publish = !empty($_POST['publish']);
        // حفظ ورودی‌ها در صورت خطا
        $formTitle = $title;
        $formDesc = $desc;
        $formGoal = $goalRaw !== '' ? $goalRaw : ($goal > 0 ? number_fa($goal) : '');
        $formDeadline = $deadlineRaw;
        $formTiers = $tiersRaw;
        $formPublish = $publish;

        if ($user['status'] !== 'approved') {
            $errors[] = 'پس از تأیید حساب می‌توانید کمپین بسازید.';
        } else {

            $tiers = [];
            foreach (preg_split('/[\s,،]+/u', $tiersRaw) ?: [] as $p) {
                $n = (int) preg_replace('/\D/', '', $p);
                if ($n > 0) $tiers[] = $n;
            }
            $tiers = array_values(array_unique($tiers));

            if (mb_strlen($title) < 5) $errors[] = 'عنوان کوتاه است.';
            if (mb_strlen($desc) < 30) $errors[] = 'توضیح کمپین حداقل ۳۰ کاراکتر.';
            if ($goal < 10000) $errors[] = 'هدف مالی نامعتبر است.';
            if ($deadline === '' || $deadline < gmdate('Y-m-d')) {
                $errors[] = 'مهلت را به صورت شمسی معتبر وارد کنید (مثال: ۱۴۰۵/۰۶/۱۵).';
            }
            if (count($tiers) < 1) $errors[] = 'حداقل یک مبلغ ثابت وارد کنید (مثلاً 50000,100000).';

            if (!$errors) {
                $slug = unique_slug($title);
                // ensure unique among campaigns
                $base = $slug;
                $i = 0;
                while (true) {
                    $st = db()->prepare('SELECT id FROM campaigns WHERE slug=?');
                    $st->execute([$slug]);
                    if (!$st->fetch()) break;
                    $i++;
                    $slug = $base . '-' . $i;
                }
                $now = gmdate('c');
                $status = $publish ? 'active' : 'draft';
                $ins = db()->prepare('INSERT INTO campaigns (user_id, title, slug, description, goal_amount, deadline, tiers_json, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
                $ins->execute([
                    (int) $user['id'], $title, $slug, $desc, $goal, $deadline,
                    json_encode($tiers, JSON_UNESCAPED_UNICODE), $status, $now, $now,
                ]);
                if ($status === 'active') {
                    feed_push('campaign', $title, mb_substr($desc, 0, 160), 'campaign', (int) db()->lastInsertId());
                }
                $ok = true;
                // پاک کردن فرم فقط بعد از موفقیت
                $formTitle = $formDesc = $formGoal = $formDeadline = $formTiers = '';
                $formPublish = true;
            }
        }
    }
    if ($action === 'publish' || $action === 'cancel') {
        $id = (int) ($_POST['campaign_id'] ?? 0);
        $st = db()->prepare('SELECT * FROM campaigns WHERE id=? AND user_id=?');
        $st->execute([$id, (int) $user['id']]);
        $c = $st->fetch();
        if ($c) {
            if ($action === 'publish' && $c['status'] === 'draft' && $user['status'] === 'approved') {
                db()->prepare("UPDATE campaigns SET status='active', updated_at=? WHERE id=?")->execute([gmdate('c'), $id]);
                feed_push('campaign', (string)$c['title'], 'کمپین منتشر شد', 'campaign', $id);
                $ok = true;
            }
            if ($action === 'cancel') {
                $res = campaign_cancel($id, (int) $user['id'], false);
                if (!empty($res['ok'])) {
                    $ok = true;
                    if (!empty($res['message'])) {
                        // show via ok msg
                    }
                    require_once dirname(__DIR__) . '/lib/Notify.php';
                    Notify::admin(
                        'لغو کمپین توسط کاربر',
                        "کاربر «{$user['display_name']}» کمپین را لغو کرد.\nعنوان: {$c['title']}\nوضعیت قبلی: {$c['status']}\n" .
                        (!empty($res['refund_count']) ? "بازگشت وجه: {$res['refund_count']} مورد\n" : '') .
                        "https://donate.sudoshz.ir/admin/campaigns.php"
                    );
                } else {
                    $errors[] = $res['message'] ?? 'لغو ناموفق بود.';
                }
            }
        } elseif ($action === 'cancel') {
            $errors[] = 'کمپین یافت نشد.';
        }
    }
}

campaign_refresh_status();
$st = db()->prepare('SELECT * FROM campaigns WHERE user_id=? ORDER BY id DESC');
$st->execute([(int) $user['id']]);
$list = $st->fetchAll();

layout_header('کمپین‌های حمایت');
?>
<section class="page-section">
  <div class="container">
    <h1 class="page-title">کمپین‌های حمایت</h1>
    <p class="page-lead">هدف مالی، مهلت، و مبالغ ثابت تعریف کنید. اگر تا مهلت به هدف نرسید، مسیر بازگشت وجه فعال می‌شود.</p>
    <p><a href="/dashboard/">← پنل</a></p>

    <?php if ($ok): ?><div class="form-msg show ok">انجام شد.</div><?php endif; ?>
    <?php if ($errors): ?><div class="form-msg show error"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div><?php endif; ?>
    <p class="hint">برای لغو کمپین جاری یا پیش‌نویس، از دکمه «لغو کمپین» در جدول پایین استفاده کنید. اگر حمایتی پرداخت شده باشد، در صف بازگشت وجه قرار می‌گیرد.</p>

        <div class="card form-card" style="margin-bottom:1.5rem" id="campaign-form">
      <h2 style="margin-top:0">تعریف کمپین حمایت</h2>
      <form method="post" action="#campaign-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <label>عنوان</label>
        <input name="title" required placeholder="مثلاً: طراحی سیستم‌عامل آزاد — نیاز به حمایت" value="<?= e($formTitle) ?>">
        <label>توضیح پروژه (چرا نرم‌افزار آزاد است؟)</label>
        <textarea name="description" rows="5" required placeholder="شرح پروژه، مجوز آزاد، و هدف هزینه"><?= e($formDesc) ?></textarea>
        <label>هدف مالی (تومان)</label>
        <input type="text" name="goal_amount" required dir="ltr" class="ltr-field money-input" placeholder="۵۰٬۰۰۰٬۰۰۰" inputmode="numeric" value="<?= e($formGoal) ?>">
        <label>مهلت (تقویم شمسی)</label>
        <input type="text" name="deadline" required dir="ltr" class="ltr-field jalali-date" placeholder="۱۴۰۵/۰۶/۱۵" inputmode="numeric" value="<?= e($formDeadline) ?>">
        <label>مبالغ ثابت (با ویرگول)</label>
        <input name="tiers" dir="ltr" class="ltr-field" required placeholder="۵۰٬۰۰۰, ۱۰۰٬۰۰۰, ۵۰۰٬۰۰۰" value="<?= e($formTiers) ?>">
        <label style="display:flex;gap:.5rem;align-items:center;margin-top:.75rem">
          <input type="checkbox" name="publish" value="1" <?= $formPublish ? 'checked' : '' ?>> انتشار فوری
        </label>
        <button class="btn btn-primary" type="submit" style="margin-top:1rem">ثبت</button>
      </form>
    </div>

    <div class="card">
      <h2 style="margin-top:0">فهرست</h2>
      <?php if (!$list): ?>
        <p class="hint">هنوز هنوز موردی ثبت نکرده‌اید.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>عنوان</th><th>وضعیت</th><th>جمع / هدف</th><th>مهلت</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($list as $c):
              $prog = campaign_progress($c); ?>
              <tr>
                <td><?= e($c['title']) ?><br><a href="/c/<?= e(rawurlencode($c['slug'])) ?>" dir="ltr">/c/<?= e($c['slug']) ?></a></td>
                <td><?= e(campaign_status_label($c['status'])) ?></td>
                <td><?= e(money_fa($prog['raised'])) ?> / <?= e(money_fa($prog['goal'])) ?></td>
                <td><?= e(gregorian_to_jalali_str($c['deadline'])) ?></td>
                <td style="white-space:nowrap">
                  <?php if ($c['status'] === 'draft'): ?>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="publish">
                      <input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>">
                      <button class="btn btn-primary" style="width:auto;padding:.35rem .7rem;font-size:.82rem" type="submit">انتشار</button>
                    </form>
                  <?php endif; ?>
                  <?php if (in_array($c['status'], ['draft','active'], true)): ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('کمپین «<?= e(addslashes($c['title'])) ?>» لغو شود؟\nاگر حمایتی پرداخت شده باشد، در صف بازگشت وجه می‌رود.');">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="cancel">
                      <input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>">
                      <button class="btn btn-ghost" style="width:auto;padding:.35rem .7rem;font-size:.82rem;border-color:rgba(251,113,133,.45);color:#fecdd3" type="submit">لغو کمپین</button>
                    </form>
                  <?php else: ?>
                    <span class="hint">—</span>
                  <?php endif; ?>
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
<?php layout_footer(); ?>
