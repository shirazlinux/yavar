<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/profile.php';
$user = auth_require_login();
$errors = [];
$saved = false;

function normalize_url_field(string $u): string
{
    $u = trim($u);
    if ($u === '') return '';
    if (!preg_match('#^https?://#i', $u)) $u = 'https://' . $u;
    return filter_var($u, FILTER_VALIDATE_URL) ? mb_substr($u, 0, 300) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $errors[] = 'نشست منقضی شد.';
    }
    $display = trim((string) ($_POST['display_name'] ?? ''));
    $displayMode = normalize_display_mode($_POST['display_mode'] ?? 'personal');
    $platformName = trim((string) ($_POST['platform_name'] ?? ''));
    $slugIn = trim((string) ($_POST['slug'] ?? ''));
    $activity = trim((string) ($_POST['activity'] ?? ''));
    $bio = trim((string) ($_POST['bio'] ?? ''));
    $services = trim((string) ($_POST['services'] ?? ''));
    $gitUrl = normalize_url_field((string) ($_POST['git_url'] ?? ''));
    $websiteUrl = normalize_url_field((string) ($_POST['website_url'] ?? ''));
    $publicContact = trim((string) ($_POST['public_contact'] ?? ''));
    $socialJson = social_links_normalize($_POST);
    $showPublicDons = !empty($_POST['show_public_donations']) ? 1 : 0;
    $sheba = normalize_sheba((string) ($_POST['sheba'] ?? ''));
    $card = normalize_card((string) ($_POST['card_number'] ?? ''));
    $phone = Sms::normalizeMobile((string) ($_POST['phone'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    $passCurrent = (string) ($_POST['password_current'] ?? '');
    $avatarPreset = preg_replace('/[^a-z0-9_\-]/', '', (string) ($_POST['avatar_preset'] ?? '')) ?: '';

    if (mb_strlen($display) < 2) $errors[] = 'نام نمایشی نامعتبر است.';
    if (($displayMode === 'platform' || $displayMode === 'community') && mb_strlen($platformName) < 2) {
        $errors[] = $displayMode === 'community' ? 'نام جامعه را وارد کنید.' : 'نام پروژه را وارد کنید.';
    }
    if (mb_strlen($activity) < 20) $errors[] = 'توضیح فعالیت حداقل ۲۰ کاراکتر.';
    if (mb_strlen($services) < 10) $errors[] = 'کارهایی که حمایت می‌پذیرید را بنویسید.';
    // کارت/شبا اختیاری
    if ($sheba !== '' && !validate_sheba($sheba)) $errors[] = 'شبا نامعتبر.';
    if ($card !== '' && !validate_card($card)) $errors[] = 'کارت نامعتبر (۱۶ رقم).';
    if (!Sms::validMobile($phone)) $errors[] = 'موبایل معتبر وارد کنید.';
    if (user_phone_taken($phone, (int)$user['id'])) $errors[] = 'این شماره موبایل قبلاً برای حساب دیگری ثبت شده است.';
    if ($pass !== '' && preg_match('/[\x{0600}-\x{06FF}]/u', $pass)) $errors[] = 'رمز نباید فارسی باشد.';
    if ($pass !== '' && mb_strlen($pass) < 8) $errors[] = 'رمز حداقل ۸ کاراکتر.';
    // تغییر رمز فقط با رمز فعلی (جلوگیری از hijack نشست)
    if ($pass !== '') {
        if ($passCurrent === '' || !password_verify($passCurrent, (string) $user['password_hash'])) {
            $errors[] = 'برای تغییر رمز، رمز فعلی را درست وارد کنید.';
            security_log('pw_change_fail', 'uid=' . (int) $user['id']);
        }
    }

    $avatar = (string) ($user['avatar'] ?? 'preset:tux');
    if ($avatarPreset !== '') {
        $ids = array_column(avatar_presets(), 'id');
        if (in_array($avatarPreset, $ids, true)) {
            $avatar = 'preset:' . $avatarPreset;
        }
    }
    if (!empty($_FILES['avatar_file']['tmp_name']) && is_uploaded_file($_FILES['avatar_file']['tmp_name'])) {
        $tmpUp = $_FILES['avatar_file']['tmp_name'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpUp) ?: '';
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) {
            $errors[] = 'فرمت تصویر باید JPG/PNG/WebP/GIF باشد.';
        } elseif (($_FILES['avatar_file']['size'] ?? 0) > 1_500_000) {
            $errors[] = 'حجم تصویر حداکثر ۱٫۵ مگابایت.';
        } else {
            $dir = dirname(__DIR__) . '/assets/uploads';
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
            // re-encode با GD تا polyglot/PHP در تصویر حذف شود
            $safeName = null;
            if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
                $raw = @file_get_contents($tmpUp);
                $im = $raw !== false ? @imagecreatefromstring($raw) : false;
                if ($im !== false) {
                    $w = imagesx($im);
                    $h = imagesy($im);
                    if ($w > 0 && $h > 0 && $w <= 4000 && $h <= 4000) {
                        // محدود کردن ابعاد بزرگ
                        $maxDim = 800;
                        if ($w > $maxDim || $h > $maxDim) {
                            $scale = min($maxDim / $w, $maxDim / $h);
                            $nw = max(1, (int) round($w * $scale));
                            $nh = max(1, (int) round($h * $scale));
                            $dst = imagecreatetruecolor($nw, $nh);
                            imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
                            imagedestroy($im);
                            $im = $dst;
                        }
                        $name = 'u' . (int) $user['id'] . '_' . bin2hex(random_bytes(6)) . '.jpg';
                        $dest = $dir . '/' . $name;
                        if (@imagejpeg($im, $dest, 85)) {
                            @chmod($dest, 0644);
                            $safeName = $name;
                        }
                        imagedestroy($im);
                    } else {
                        if (is_resource($im) || $im instanceof GdImage) {
                            imagedestroy($im);
                        }
                    }
                }
            }
            if ($safeName === null && !function_exists('imagecreatefromstring')) {
                // فقط وقتی GD نیست: ذخیره مستقیم (پسوند از mime)
                $name = 'u' . (int) $user['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
                $dest = $dir . '/' . $name;
                if (move_uploaded_file($tmpUp, $dest)) {
                    $safeName = $name;
                }
            }
            if ($safeName !== null) {
                $oldAv = (string) ($user['avatar'] ?? '');
                if (str_starts_with($oldAv, 'upload:')) {
                    $oldFile = basename(substr($oldAv, 7));
                    if ($oldFile !== '' && is_file($dir . '/' . $oldFile)) {
                        @unlink($dir . '/' . $oldFile);
                    }
                }
                $avatar = 'upload:' . $safeName;
            } else {
                $errors[] = 'پردازش/آپلود تصویر ناموفق بود. فایل JPG/PNG دیگری امتحان کنید.';
            }
        }
    }

    if (!$errors) {
        $slug = unique_slug($slugIn !== '' ? $slugIn : $display, (int) $user['id']);
        $now = gmdate('c');
        if ($pass !== '') {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $st = db()->prepare('UPDATE users SET display_name=?, display_mode=?, platform_name=?, slug=?, bio=?, activity=?, services=?, git_url=?, website_url=?, public_contact=?, social_json=?, show_public_donations=?, card_number=?, sheba=?, phone=?, password_hash=?, avatar=?, updated_at=? WHERE id=?');
            $st->execute([$display, $displayMode, $platformName, $slug, $bio, $activity, $services, $gitUrl, $websiteUrl, $publicContact, $socialJson, $showPublicDons, $card, $sheba, $phone, $hash, $avatar, $now, $user['id']]);
        } else {
            $st = db()->prepare('UPDATE users SET display_name=?, display_mode=?, platform_name=?, slug=?, bio=?, activity=?, services=?, git_url=?, website_url=?, public_contact=?, social_json=?, show_public_donations=?, card_number=?, sheba=?, phone=?, avatar=?, updated_at=? WHERE id=?');
            $st->execute([$display, $displayMode, $platformName, $slug, $bio, $activity, $services, $gitUrl, $websiteUrl, $publicContact, $socialJson, $showPublicDons, $card, $sheba, $phone, $avatar, $now, $user['id']]);
        }
        $saved = true;
        $st = db()->prepare('SELECT * FROM users WHERE id=?');
        $st->execute([(int) $user['id']]);
        $user = $st->fetch() ?: $user;
    }
}

layout_header('ویرایش صفحه حمایت');
$presets = avatar_presets();
$curAvatar = (string) ($user['avatar'] ?? 'preset:tux');
$curPreset = str_starts_with($curAvatar, 'preset:') ? substr($curAvatar, 7) : '';
?>
<section class="page-section">
  <div class="container narrow">
    <h1 class="page-title">صفحه حمایت من</h1>
    <?php if ($saved): ?><div class="form-msg show ok">ذخیره شد.</div><?php endif; ?>
    <?php if ($errors): ?><div class="form-msg show error"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div><?php endif; ?>

    <form method="post" class="card form-card" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div style="display:flex;gap:1rem;align-items:center;margin-bottom:1rem">
        <img src="<?= e(member_avatar_url($user)) ?>" alt="" width="72" height="72" style="border-radius:16px;border:1px solid var(--border)">
        <div>
          <strong><?= e(member_public_name($user)) ?></strong>
          <div class="hint">نام عمومی فعلی</div>
        </div>
      </div>

      <h3 class="form-subhead" style="margin-top:0">نام نمایشی برای حمایت</h3>
      <label class="check-line"><input type="radio" name="display_mode" value="personal" <?= (($user['display_mode'] ?? 'personal') === 'personal') ? 'checked' : '' ?>> فعال (با نام خودم)</label>
      <label class="check-line"><input type="radio" name="display_mode" value="platform" <?= (($user['display_mode'] ?? '') === 'platform') ? 'checked' : '' ?>> پروژه</label>
      <label class="check-line"><input type="radio" name="display_mode" value="community" <?= (($user['display_mode'] ?? '') === 'community') ? 'checked' : '' ?>> جامعه</label>
      <label>نام شخصی *</label>
      <input name="display_name" required value="<?= e($user['display_name']) ?>">
      <label>نام پروژه / جامعه</label>
      <input name="platform_name" value="<?= e($user['platform_name'] ?? '') ?>" placeholder="برای حالت پروژه یا جامعه">
      <label>آدرس صفحه</label>
      <div class="input-prefix"><span>/u/</span><input name="slug" dir="ltr" class="ltr-field" value="<?= e($user['slug']) ?>"></div>

      <h3 class="form-subhead">پروفایل</h3>
      <label>انتخاب از نمونه‌های گنو/لینوکس و نرم‌افزار آزاد</label>
      <div class="avatar-grid">
        <?php foreach ($presets as $p): ?>
          <label class="avatar-option">
            <input type="radio" name="avatar_preset" value="<?= e($p['id']) ?>" <?= $curPreset === $p['id'] ? 'checked' : '' ?>>
            <img src="<?= e($p['url']) ?>" alt="" width="56" height="56">
            <span><?= e($p['label']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <label>یا آپلود تصویر خودتان (اختیاری)</label>
      <input type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp,image/gif">

      <label>معرفی کوتاه</label>
      <textarea name="bio" rows="3"><?= e($user['bio'] ?? '') ?></textarea>
      <label>فعالیت در نرم‌افزار آزاد *</label>
      <textarea name="activity" rows="3" required><?= e($user['activity'] ?? '') ?></textarea>
      <label>کارهایی که حمایت می‌پذیرید *</label>
      <textarea name="services" rows="3" required><?= e($user['services'] ?? '') ?></textarea>
      <label>مخزن کد (گیت‌هاب/کدبرگ/…)</label>
      <input name="git_url" dir="ltr" class="ltr-field" value="<?= e($user['git_url'] ?? '') ?>">
      <label>وب‌سایت</label>
      <input name="website_url" dir="ltr" class="ltr-field" value="<?= e($user['website_url'] ?? '') ?>">
      <label>تماس عمومی (اختیاری)</label>
      <input name="public_contact" value="<?= e($user['public_contact'] ?? '') ?>">

      <h3 class="form-subhead">نمایش حمایت‌ها در صفحه عمومی</h3>
      <label class="check-line">
        <input type="checkbox" name="show_public_donations" value="1" <?= !empty($user['show_public_donations']) ? 'checked' : '' ?>>
        فهرست حمایت‌های دریافتی (با نام/پیام حامی، در صورت اجازهٔ خودش) در صفحهٔ عمومی من نمایش داده شود
      </label>
      <p class="hint">اگر خاموش باشد، حتی حمایت‌هایی که حامی اجازه داده هم در صفحهٔ عمومی دیده نمی‌شوند. نام واقعی فقط برای شما در پنل قابل‌مشاهده است.</p>

      <h3 class="form-subhead">شبکه‌های اجتماعی (عمومی، اختیاری)</h3>
      <p class="hint">لینک پروفایل‌های آزاد و رایج — در صفحه عمومی نمایش داده می‌شود.</p>
      <?php
        $socialSaved = social_links_from_user($user);
        foreach (social_network_defs() as $sKey => $sMeta):
      ?>
        <label><?= e($sMeta['label']) ?></label>
        <input name="social_<?= e($sKey) ?>" dir="ltr" class="ltr-field"
               placeholder="<?= e($sMeta['placeholder']) ?>"
               value="<?= e($socialSaved[$sKey] ?? '') ?>">
      <?php endforeach; ?>

      <h3 class="form-subhead">اطلاع‌رسانی (محرمانه)</h3>
      <p class="hint">موبایل فقط برای اطلاع‌رسانی حمایت است و عمومی نمی‌شود.</p>
      <label>موبایل *</label>
      <input name="phone" dir="ltr" class="ltr-field" required value="<?= e($user['phone'] ?? '') ?>">
      <h3 class="form-subhead" id="settlement">تسویه (اختیاری، محرمانه)</h3>
      <p class="hint">بعد از اولین حمایت دریافتی، برای تسویه بهتر است یکی از این‌ها را وارد کنید. <strong>شماره کارت باید به نام خودتان باشد.</strong> در صفحه عمومی نمایش داده نمی‌شود.</p>
      <label>شبا (اختیاری)</label>
      <input name="sheba" dir="ltr" class="ltr-field" value="<?= e($user['sheba'] ?? '') ?>" placeholder="IRxxxxxxxxxxxxxxxxxxxxxxxx">
      <label>کارت به نام خودتان (اختیاری)</label>
      <input name="card_number" dir="ltr" class="ltr-field" value="<?= e($user['card_number'] ?? '') ?>" placeholder="6037…">
      <label>رمز فعلی (فقط اگر می‌خواهید رمز را عوض کنید)</label>
      <input type="password" name="password_current" dir="ltr" class="ltr-field password-field" autocomplete="current-password">
      <label>رمز جدید (اختیاری)</label>
      <input type="password" name="password" dir="ltr" class="ltr-field password-field" autocomplete="new-password">
      <p class="hint pass-warn" id="pass-warn" hidden>کیبورد را انگلیسی کنید — رمز فارسی پذیرفته نمی‌شود.</p>
      <p class="hint">تغییر رمز بدون وارد کردن رمز فعلی ممکن نیست.</p>

      <button class="btn btn-primary" type="submit">ذخیره</button>
      <p class="form-note"><a href="/dashboard/">پنل</a></p>
    </form>
  </div>
</section>
<script>
(function(){
  var el=document.querySelector('input[name=password]');
  if(!el) return;
  el.addEventListener('input', function(){
    var w=document.getElementById('pass-warn');
    if(w) w.hidden = !/[\u0600-\u06FF]/.test(el.value);
  });
})();
</script>
<?php layout_footer(); ?>
