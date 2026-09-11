<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/Telegram.php';

$user = auth_require_login();
if (($user['role'] ?? 'hamyar') === 'supporter' && empty($user['is_admin'])) {
    header('Location: /dashboard/supporter.php');
    exit;
}

$flash = '';
$flashErr = '';
$newConnect = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    if (($action === 'connect' || $action === 'relink') && Telegram::isConfigured()) {
        try {
            $newConnect = Telegram::createConnectToken((int) $user['id']);
            $flash = 'کد ساخته شد (۱۵ دقیقه). برای گروه: /connect در گروه. برای اطلاع خصوصی: Start با لینک.';
            security_log('tg_connect_start', 'uid=' . (int) $user['id']);
        } catch (Throwable $e) {
            $flashErr = $e->getMessage();
        }
    } elseif ($action === 'save_settings' && Telegram::isConfigured()) {
        $cta = (string) ($_POST['announce_cta'] ?? '');
        $np = !empty($_POST['notify_private']);
        Telegram::saveSettings((int) $user['id'], $cta, $np);
        $flash = 'تنظیمات ذخیره شد.';
    } elseif ($action === 'disconnect') {
        Telegram::disconnect((int) $user['id'], 'all');
        $flash = 'همه اتصال‌های تلگرام قطع شد.';
        security_log('tg_disconnect', 'uid=' . (int) $user['id']);
    } elseif ($action === 'disconnect_group') {
        Telegram::disconnect((int) $user['id'], 'group');
        $flash = 'اتصال گروه/کانال قطع شد.';
    } elseif ($action === 'disconnect_private') {
        Telegram::disconnect((int) $user['id'], 'private');
        $flash = 'اتصال چت خصوصی قطع شد.';
    } elseif (($action === 'test' || $action === 'sample') && Telegram::isConfigured()) {
        require_once dirname(__DIR__) . '/lib/RateLimit.php';
        $key = $action === 'sample' ? 'tg_sample:' : 'tg_test:';
        $max = $action === 'sample' ? 3 : 5;
        $rl = RateLimit::hitKey($key . (int) $user['id'], $max, 3600);
        if (!$rl['ok']) {
            $flashErr = 'تعداد درخواست زیاد است. کمی بعد دوباره تلاش کنید.';
        } else {
            $link = Telegram::getLink((int) $user['id']);
            $hasAny = $link && (Telegram::hasGroup($link) || Telegram::hasPrivate($link)
                || (!empty($link['enabled']) && ($link['chat_id'] ?? '') !== ''));
            if (!$hasAny) {
                $flashErr = 'ابتدا گروه یا چت خصوصی را وصل کنید.';
            } else {
                if ($action === 'sample') {
                    $ok = Telegram::enqueueDonationPaid(
                        (int) $user['id'],
                        150000,
                        'DON-' . strtoupper(bin2hex(random_bytes(3))),
                        0,
                        false,
                        'سارا.م',
                        'زنده باد نرم‌افزار آزاد'
                    );
                    $flash = $ok
                        ? 'نمونه حمایت به صف رفت: در گروه متن عمومی، در PV (اگر وصل باشد) اطلاع شخصی.'
                        : 'ارسال نمونه ناموفق بود.';
                } else {
                    $ok = Telegram::enqueueDonationPaid(
                        (int) $user['id'],
                        10000,
                        'TEST-' . strtoupper(bin2hex(random_bytes(2))),
                        0,
                        true,
                        '',
                        'پیام تست'
                    );
                    $flash = $ok ? 'پیام تست در صف است.' : 'صف‌بندی ناموفق بود.';
                }
            }
        }
    }
}

$link = Telegram::getLink((int) $user['id']);
$hasGroup = $link && Telegram::hasGroup($link);
$hasPrivate = $link && Telegram::hasPrivate($link);
$bot = Telegram::botUsername();
$configured = Telegram::isConfigured();
$ctaVal = $link ? (string) ($link['announce_cta'] ?? '') : '';
$notifyPrivate = !$link || !empty($link['notify_private']);

layout_header('اتصال تلگرام', 'اعلان حمایت در گروه و اطلاع در چت خصوصی');
?>
<section class="page-section">
  <div class="container dash-page">
    <h1 class="page-title">اعلان تلگرام</h1>
    <?php dashboard_nav($user, 'telegram'); ?>
    <p class="page-lead">
      <strong>گروه/کانال:</strong> اعلام عمومی حمایت‌ها برای مخاطبان.<br>
      <strong>چت خصوصی با ربات:</strong> اطلاع‌رسانی شخصی برای خودتان (دریافت، کد پیگیری، …).
    </p>

    <?php if ($flash): ?><div class="form-msg show ok"><?= e($flash) ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="form-msg show error"><?= e($flashErr) ?></div><?php endif; ?>

    <?php if (!$configured): ?>
      <div class="form-msg show error">اعلان تلگرام روی سرور پیکربندی نشده است.</div>
    <?php else: ?>

    <div class="card" style="margin-bottom:1rem">
      <h2 style="margin-top:0;font-size:1.1rem">وضعیت اتصال</h2>
      <p>
        گروه/کانال:
        <?php if ($hasGroup): ?>
          <span class="badge badge-ok">متصل</span>
          <strong><?= e($link['group_chat_title'] ?: $link['group_chat_id']) ?></strong>
          <span class="hint" dir="ltr">(<?= e($link['group_chat_type'] ?? '') ?>)</span>
        <?php else: ?>
          <span class="badge badge-warn">وصل نیست</span>
        <?php endif; ?>
      </p>
      <p>
        چت خصوصی (اطلاع شخصی):
        <?php if ($hasPrivate): ?>
          <span class="badge badge-ok">متصل</span>
          <span class="hint" dir="ltr">id <?= e((string) $link['private_chat_id']) ?></span>
        <?php else: ?>
          <span class="badge badge-warn">وصل نیست</span>
        <?php endif; ?>
      </p>
      <div class="btn-toolbar" role="group" aria-label="عملیات تلگرام">
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="connect">
          <button class="btn btn-primary btn-sm" type="submit">ساخت / تمدید کد اتصال</button>
        </form>
        <?php if ($hasGroup || $hasPrivate): ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="sample">
            <button class="btn btn-ghost btn-sm" type="submit">نمونه حمایت</button>
          </form>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="test">
            <button class="btn btn-ghost btn-sm" type="submit">پیام تست</button>
          </form>
        <?php endif; ?>
        <?php if ($hasGroup): ?>
          <form method="post" onsubmit="return confirm('قطع گروه؟');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="disconnect_group">
            <button class="btn btn-danger-ghost btn-sm" type="submit">قطع گروه</button>
          </form>
        <?php endif; ?>
        <?php if ($hasPrivate): ?>
          <form method="post" onsubmit="return confirm('قطع PV؟');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="disconnect_private">
            <button class="btn btn-danger-ghost btn-sm" type="submit">قطع PV</button>
          </form>
        <?php endif; ?>
        <?php if ($hasGroup || $hasPrivate): ?>
          <form method="post" onsubmit="return confirm('قطع همه؟');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="disconnect">
            <button class="btn btn-danger-ghost btn-sm" type="submit">قطع همه</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="card" style="margin-bottom:1rem">
      <h2 style="margin-top:0;font-size:1.1rem">متن دعوت در گروه (CTA)</h2>
      <p class="hint">در پیام عمومی گروه، بعد از مبلغ/حامی/پیام حامی، این متن و سپس لینک صفحه شما می‌آید. خالی = متن پیش‌فرض.</p>
      <form method="post" class="tg-settings-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_settings">
        <label for="announce_cta">متن دعوت</label>
        <input id="announce_cta" name="announce_cta" maxlength="280"
               placeholder="<?= e(Telegram::defaultCta()) ?>"
               value="<?= e($ctaVal) ?>">
        <label class="check-line" style="margin-top:.75rem">
          <input type="checkbox" name="notify_private" value="1" <?= $notifyPrivate ? 'checked' : '' ?>>
          در چت خصوصی ربات هم اطلاع دریافت حمایت بفرست (با کد پیگیری)
        </label>
        <div class="btn-toolbar" style="margin-top:1rem">
          <button class="btn btn-primary btn-sm" type="submit">ذخیره تنظیمات</button>
        </div>
      </form>
      <div class="hint" style="margin-top:1rem">
        <strong>نمونه پیام گروه:</strong>
        <pre style="white-space:pre-wrap;background:#0b1220;padding:.75rem;border-radius:8px;margin-top:.35rem">💚 حمایت جدید

مبلغ: 150,000 تومان
حامی: سارا.م
پیام: «زنده باد نرم‌افزار آزاد»

<?= e($ctaVal !== '' ? $ctaVal : Telegram::defaultCta()) ?>
https://yavar.sudoshz.ir/u/…</pre>
      </div>
    </div>

    <?php if ($newConnect): ?>
    <div class="card form-msg show manual" style="margin-bottom:1rem">
      <h2 style="margin-top:0;font-size:1.1rem">مراحل اتصال</h2>
      <ol style="line-height:1.9">
        <li><strong>اطلاع شخصی (اختیاری):</strong>
          <a href="<?= e($newConnect['deep_link']) ?>" target="_blank" rel="noopener" dir="ltr">Start با ربات</a>
          — فقط برای خودتان.
        </li>
        <li><strong>اعلام در گروه (اصلی):</strong> ربات <span dir="ltr">@<?= e($bot) ?></span> را ادمین گروه کنید، سپس <strong>داخل گروه</strong> بفرستید:
          <pre dir="ltr" style="background:#0b1220;padding:.75rem;border-radius:8px;user-select:all">/connect <?= e($newConnect['token']) ?></pre>
        </li>
      </ol>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </div>
</section>
<?php layout_footer(); ?>
