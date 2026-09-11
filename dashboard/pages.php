<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/profile.php';
require_once dirname(__DIR__) . '/lib/pages.php';
require_once dirname(__DIR__) . '/lib/Notify.php';

$user = auth_require_login();
if (($user['role'] ?? 'hamyar') === 'supporter' && empty($user['is_admin'])) {
    header('Location: /dashboard/enable-page.php');
    exit;
}

pages_ensure_backfill(db());
if (!page_primary((int) $user['id'])) {
    page_create_primary_from_user($user);
}

$errors = [];
$ok = false;
$okMsg = '';
$showCreate = isset($_GET['new']) || (($_POST['action'] ?? '') === 'create');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'archive') {
        $res = page_archive_owned((int) ($_POST['page_id'] ?? 0), (int) $user['id']);
        if (!empty($res['ok'])) {
            $ok = true;
            $okMsg = 'صفحه آرشیو شد.';
        } else {
            $errors[] = $res['message'] ?? 'آرشیو ناموفق بود.';
        }
    }
    if ($action === 'create') {
        $kind = page_normalize_kind($_POST['kind'] ?? 'project');
        $gitUrl = normalize_url_field((string) ($_POST['git_url'] ?? ''));
        $websiteUrl = normalize_url_field((string) ($_POST['website_url'] ?? ''));
        $extraErr = project_links_validate_post($_POST);
        if ($extraErr !== null) {
            $errors[] = $extraErr;
        }
        $res = page_create_extra($user, [
            'kind' => $kind,
            'title' => (string) ($_POST['title'] ?? ''),
            'slug' => (string) ($_POST['slug'] ?? ''),
            'bio' => (string) ($_POST['bio'] ?? ''),
            'activity' => (string) ($_POST['activity'] ?? ''),
            'services' => (string) ($_POST['services'] ?? ''),
            'git_url' => $gitUrl,
            'website_url' => $websiteUrl,
            'project_links' => project_links_normalize_from_post($_POST, $gitUrl, $websiteUrl),
            'avatar' => (string) ($user['avatar'] ?? 'preset:tux'),
        ]);
        if (!empty($res['ok'])) {
            $ok = true;
            $okMsg = 'صفحه ساخته شد و در انتظار تأیید است.';
            $showCreate = false;
            try {
                $p = $res['page'];
                Notify::admin(
                    'صفحهٔ پروژهٔ جدید',
                    "کاربر «{$user['display_name']}» صفحهٔ جدیدی ثبت کرد.\n"
                    . 'نوع: ' . page_kind_label($p['kind'] ?? '') . "\n"
                    . 'نام: ' . ($p['title'] ?? '') . "\n"
                    . 'آدرس: /u/' . ($p['slug'] ?? '') . "\n"
                    . "https://donate.sudoshz.ir/admin/pages.php"
                );
            } catch (Throwable $e) {
                error_log('page create notify: ' . $e->getMessage());
            }
        } else {
            $errors[] = $res['message'] ?? 'ساخت صفحه ناموفق بود.';
            $showCreate = true;
        }
    }
}

$pages = pages_list_for_user((int) $user['id']);
$canCreate = pages_active_count((int) $user['id']) < PAGES_MAX_ACTIVE;
$cfg = app_config();
$createKind = page_normalize_kind($_POST['kind'] ?? 'project');
$createMeta = presence_link_meta(page_kind_to_display_mode($createKind));

layout_header('صفحات من');
?>
<section class="page-section">
  <div class="container dash-page">
    <h1 class="page-title">صفحات حمایت</h1>
    <p class="page-lead">با همین حساب می‌توانید برای هر پروژه یا جامعه یک صفحهٔ جدا بسازید. تسویه همه روی حساب شماست.</p>
    <?php dashboard_nav($user, 'pages'); ?>

    <?php if (!empty($_GET['enabled'])): ?>
      <div class="form-msg show ok">صفحه حمایت فعال شد. می‌توانید صفحهٔ پروژهٔ جدید هم اضافه کنید.</div>
    <?php endif; ?>
    <?php if ($ok): ?><div class="form-msg show ok"><?= e($okMsg !== '' ? $okMsg : 'انجام شد.') ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="form-msg show error"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div><?php endif; ?>

    <div class="page-list">
      <?php foreach ($pages as $p):
        $tot = page_total_paid((int) $p['id']);
        $st = (string) $p['status'];
        $badge = $st === 'approved' ? 'ok' : ($st === 'rejected' ? 'err' : 'warn');
        $public = $st === 'approved' ? '/u/' . rawurlencode((string) $p['slug']) : '';
      ?>
        <article class="card page-card">
          <div class="page-card__top">
            <div>
              <div class="hint"><?= e(page_kind_label($p['kind'])) ?><?= !empty($p['is_primary']) ? ' · صفحه اصلی' : '' ?></div>
              <h2 style="margin:.15rem 0 .35rem;font-size:1.05rem"><?= e($p['title']) ?></h2>
              <div class="hint" dir="ltr">/u/<?= e($p['slug']) ?></div>
            </div>
            <span class="badge badge-<?= e($badge) ?>"><?= e(page_status_label($st)) ?></span>
          </div>
          <p class="hint" style="margin:.5rem 0 0">جمع حمایت این صفحه: <strong><?= e(money_fa($tot['sum'])) ?></strong></p>
          <?php if ($st === 'rejected' && trim((string) ($p['reject_reason'] ?? '')) !== ''): ?>
            <p class="hint" style="margin:.35rem 0 0">دلیل رد: <?= e($p['reject_reason']) ?></p>
          <?php endif; ?>
          <div class="btn-toolbar" style="margin-top:.75rem">
            <a class="btn btn-ghost btn-sm" href="/dashboard/profile.php?id=<?= (int) $p['id'] ?>">ویرایش</a>
            <?php if ($public): ?>
              <a class="btn btn-ghost btn-sm" href="<?= e($public) ?>" target="_blank" rel="noopener">مشاهده</a>
            <?php endif; ?>
            <?php if (empty($p['is_primary'])): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('این صفحه آرشیو شود؟');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="archive">
                <input type="hidden" name="page_id" value="<?= (int) $p['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit">آرشیو</button>
              </form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($canCreate && !$showCreate): ?>
      <p style="margin-top:1.25rem">
        <a class="btn btn-primary" href="/dashboard/pages.php?new=1">+ صفحهٔ پروژه / جامعه جدید</a>
      </p>
      <p class="hint">صفحهٔ جدید تا تأیید مدیر در فهرست عمومی دیده نمی‌شود.</p>
    <?php elseif (!$canCreate): ?>
      <p class="hint">به سقف <?= e((string) PAGES_MAX_ACTIVE) ?> صفحه رسیده‌اید.</p>
    <?php endif; ?>

    <?php if ($showCreate && $canCreate): ?>
      <h2 style="margin-top:1.75rem;font-size:1.15rem">صفحه جدید</h2>
      <form method="post" class="card form-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <label class="check-line"><input type="radio" name="kind" value="project" <?= $createKind === 'project' ? 'checked' : '' ?>> پروژه</label>
        <label class="check-line"><input type="radio" name="kind" value="community" <?= $createKind === 'community' ? 'checked' : '' ?>> جامعه</label>
        <label class="check-line"><input type="radio" name="kind" value="personal" <?= $createKind === 'personal' ? 'checked' : '' ?>> فعال (صفحه جدا با نام خودم)</label>
        <?php yavar_type_help_html($createKind); ?>
        <label>نام صفحه *</label>
        <input name="title" required maxlength="80" value="<?= e($_POST['title'] ?? '') ?>" placeholder="نام پروژه یا جامعه">
        <label>آدرس صفحه</label>
        <div class="input-prefix"><span>/u/</span><input name="slug" dir="ltr" class="ltr-field" maxlength="40" value="<?= e($_POST['slug'] ?? '') ?>" placeholder="my-project"></div>
        <label>معرفی کوتاه</label>
        <textarea name="bio" rows="2"><?= e($_POST['bio'] ?? '') ?></textarea>
        <label>فعالیت در نرم‌افزار آزاد *</label>
        <textarea name="activity" rows="3" required><?= e($_POST['activity'] ?? '') ?></textarea>
        <label>کارهایی که حمایت می‌پذیرید *</label>
        <textarea name="services" rows="3" required><?= e($_POST['services'] ?? '') ?></textarea>
        <label>مخزن کد / ریپو پروژه</label>
        <div class="link-plus-row">
          <input name="git_url" dir="ltr" class="ltr-field" value="<?= e($_POST['git_url'] ?? '') ?>" placeholder="https://github.com/org/project">
          <button type="button" class="link-plus-btn" id="add-project-link" title="افزودن لینک دیگر">+</button>
        </div>
        <label>وب‌سایت / صفحه جامعه / محل فعالیت</label>
        <input name="website_url" dir="ltr" class="ltr-field" value="<?= e($_POST['website_url'] ?? '') ?>" placeholder="https://…">
        <div id="extra-project-links" class="extra-links-list">
          <?php foreach (project_links_posted($_POST) as $extraUrl): ?>
            <div class="extra-link-row">
              <input name="project_links[]" dir="ltr" class="ltr-field" maxlength="300" value="<?= e($extraUrl) ?>">
              <button type="button" class="link-plus-btn link-plus-btn--remove" aria-label="حذف">×</button>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="hint">بعد از ثبت، صفحه در صف بررسی ادمین می‌رود.</p>
        <button class="btn btn-primary btn-block" type="submit">ثبت صفحه برای بررسی</button>
        <p class="form-note"><a href="/dashboard/pages.php">انصراف</a></p>
      </form>
      <script src="/assets/js/extra-links.js?v=1"></script>
      <script>
      (function(){
        if (window.initExtraProjectLinks) {
          initExtraProjectLinks({ listId: 'extra-project-links', addBtnId: 'add-project-link', max: 10 });
        }
        function syncHelp(){
          var mode = (document.querySelector('input[name=kind]:checked') || {}).value || 'project';
          document.querySelectorAll('#type-help .type-help__item').forEach(function(el){
            el.classList.toggle('is-current', el.getAttribute('data-type') === mode);
          });
        }
        document.querySelectorAll('input[name=kind]').forEach(function(el){ el.addEventListener('change', syncHelp); });
        syncHelp();
      })();
      </script>
    <?php endif; ?>
  </div>
</section>
<?php layout_footer(); ?>
