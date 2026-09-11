<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/Captcha.php';
require_once __DIR__ . '/lib/Notify.php';
require_once __DIR__ . '/lib/profile.php';

$cfg = app_config();
$errors = [];
$ok = false;
auth_start_session();

// کپچا: فقط اگر پاس نشده و چالش معتبر نیست، بساز
if (!Captcha::isPassed() && !Captcha::hasChallenge()) {
    Captcha::generate();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/lib/RateLimit.php';
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $errors[] = 'نشست منقضی شد. دوباره تلاش کنید.';
    }
    $rlReg = RateLimit::hit('register', 6, 3600);
    if (!$rlReg['ok']) {
        security_log('register_rate_limit', '');
        $errors[] = 'تعداد ثبت‌نام زیاد است. یک ساعت بعد دوباره تلاش کنید.';
    }
    // یک بار در نشست کافی است (بعد از OTP یا قبلاً)
    if (!Captcha::check($_POST['captcha'] ?? null)) {
        $errors[] = 'پاسخ کپچا نادرست است. عدد را انگلیسی وارد کنید (مثلاً 12).';
        Captcha::generate();
    }

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');
    $display = trim((string) ($_POST['display_name'] ?? ''));
    $displayMode = normalize_display_mode($_POST['display_mode'] ?? 'personal');
    $platformName = trim((string) ($_POST['platform_name'] ?? ''));
    $slugIn = trim((string) ($_POST['slug'] ?? ''));
    $activity = trim((string) ($_POST['activity'] ?? ''));
    $bio = trim((string) ($_POST['bio'] ?? ''));
    $services = trim((string) ($_POST['services'] ?? ''));
    $presenceRaw = trim((string) ($_POST['presence_url'] ?? ''));
    $presenceUrl = normalize_url_field($presenceRaw);
    $presenceMeta = presence_link_meta($displayMode);
    $sheba = normalize_sheba((string) ($_POST['sheba'] ?? ''));
    $card = normalize_card((string) ($_POST['card_number'] ?? ''));
    $phone = Sms::normalizeMobile((string) ($_POST['phone'] ?? ''));
    $otp = Captcha::digitsEn((string) ($_POST['otp'] ?? ''));
    $policy = !empty($_POST['policy_accept']);
    $avatarPreset = preg_replace('/[^a-z0-9_\-]/', '', (string) ($_POST['avatar_preset'] ?? 'tux')) ?: 'tux';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'ایمیل معتبر وارد کنید.';
    }
    if (!Sms::validMobile($phone)) {
        $errors[] = 'شماره موبایل معتبر وارد کنید.';
    }
    // OTP verify — hash + سقف تلاش (سازگار با otp_code قدیمی در صورت وجود)
    $sessPhone = (string) ($_SESSION['otp_phone'] ?? '');
    $sessHash = (string) ($_SESSION['otp_hash'] ?? '');
    $sessCodeLegacy = (string) ($_SESSION['otp_code'] ?? '');
    $sessExp = (int) ($_SESSION['otp_exp'] ?? 0);
    $attempts = (int) ($_SESSION['otp_attempts'] ?? 0);
    if ($sessPhone === '' || ($sessHash === '' && $sessCodeLegacy === '') || time() > $sessExp) {
        $errors[] = 'ابتدا کد تأیید پیامکی را درخواست و وارد کنید.';
    } elseif ($sessPhone !== $phone) {
        $errors[] = 'شماره موبایل با شمارهٔ تأییدشده یکی نیست.';
    } elseif ($attempts >= 5) {
        unset($_SESSION['otp_phone'], $_SESSION['otp_hash'], $_SESSION['otp_code'], $_SESSION['otp_exp']);
        $errors[] = 'تلاش‌های کد تأیید زیاد بود. دوباره کد بگیرید.';
    } else {
        $_SESSION['otp_attempts'] = $attempts + 1;
        $okOtp = false;
        if ($sessHash !== '') {
            $okOtp = hash_equals($sessHash, hash_hmac('sha256', $otp, (string) session_id()));
        } elseif ($sessCodeLegacy !== '') {
            $okOtp = hash_equals($sessCodeLegacy, $otp);
        }
        if (!$okOtp) {
            $errors[] = 'کد تأیید ۶ رقمی نادرست است.';
        }
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'رمز عبور حداقل ۸ کاراکتر باشد.';
    }
    if (preg_match('/[\x{0600}-\x{06FF}]/u', $password)) {
        $errors[] = 'رمز عبور نباید شامل حروف فارسی باشد (کیبورد را انگلیسی کنید).';
    }
    if ($password !== $password2) {
        $errors[] = 'تکرار رمز عبور یکسان نیست.';
    }
    if (mb_strlen($display) < 2 || mb_strlen($display) > 80) {
        $errors[] = 'نام نمایشی بین ۲ تا ۸۰ کاراکتر باشد.';
    }
    if (($displayMode === 'platform' || $displayMode === 'community') && mb_strlen($platformName) < 2) {
        $errors[] = $displayMode === 'community'
            ? 'نام جامعه برای نمایش عمومی الزامی است.'
            : 'نام پروژه برای نمایش عمومی الزامی است.';
    }
    if ($presenceRaw === '' || $presenceUrl === '') {
        $errors[] = $presenceMeta['label'] . ' را به‌صورت یک آدرس معتبر وارد کنید.';
    }
    if (mb_strlen($activity) < 20) {
        $errors[] = 'توضیح فعالیت در نرم‌افزار آزاد حداقل ۲۰ کاراکتر باشد.';
    }
    if (mb_strlen($services) < 10) {
        $errors[] = 'بنویسید برای چه کارهایی حمایت می‌پذیرید.';
    }
    if (!$policy) {
        $errors[] = 'پذیرش خط‌مشی الزامی است.';
    }
    // کارت/شبا اختیاری — بعد از اولین دریافت برای تسویه پیشنهاد می‌شود
    if ($sheba !== '' && !validate_sheba($sheba)) {
        $errors[] = 'شبا نامعتبر است.';
    }
    if ($card !== '' && !validate_card($card)) {
        $errors[] = 'کارت نامعتبر است (۱۶ رقم).';
    }
    if (!empty($_POST['website'])) {
        $errors[] = 'خطا در ثبت.';
    }

    $validPresets = array_column(avatar_presets(), 'id');
    if (!in_array($avatarPreset, $validPresets, true)) {
        $avatarPreset = 'tux';
    }

    if (!$errors) {
        if (user_email_taken($email)) {
            $errors[] = 'این ایمیل قبلاً ثبت شده است.';
        }
        if (user_phone_taken($phone)) {
            $errors[] = 'این شماره موبایل قبلاً ثبت شده است.';
        }
    }

    if (!$errors) {
        $slug = unique_slug($slugIn !== '' ? $slugIn : $display);
        $now = gmdate('c');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $avatar = 'preset:' . $avatarPreset;
        $gitUrl = $presenceMeta['store'] === 'git_url' ? $presenceUrl : '';
        $websiteUrl = $presenceMeta['store'] === 'website_url' ? $presenceUrl : '';
        $ins = db()->prepare('INSERT INTO users (email, password_hash, display_name, slug, bio, activity, services, card_number, sheba, phone, status, is_admin, policy_accepted_at, created_at, updated_at, avatar, display_mode, platform_name, git_url, website_url) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([
            $email, $hash, $display, $slug, $bio, $activity, $services, $card, $sheba, $phone,
            'pending', 0, $now, $now, $now, $avatar, $displayMode, $platformName, $gitUrl, $websiteUrl,
        ]);
        unset($_SESSION['otp_phone'], $_SESSION['otp_code'], $_SESSION['otp_hash'], $_SESSION['otp_exp'], $_SESSION['otp_ok'], $_SESSION['otp_attempts']);
        Captcha::clearPassed();
        try {
            Notify::registered($phone, $email, $display, [
                'slug' => $slug,
                'activity' => $activity,
                'presence_url' => $presenceUrl,
            ]);
        } catch (Throwable $e) {
            error_log('register Notify::registered: ' . $e->getMessage());
        }
        $ok = true;
    }
}

layout_header('ثبت‌نام', 'ثبت‌نام در بستر یاور — صفحه حمایت اختیاری است');
$presets = avatar_presets();
?>
<section class="page-section">
  <div class="container narrow">
    
    <div class="policy-box" style="margin-bottom:1.25rem">
      <h2 style="margin-top:0;font-size:1.1rem">نقش شما در این بستر</h2>
      <ul>
        <li><strong>حمایت کردن ورود نمی‌خواهد</strong> — بدون حساب هم می‌توانید حمایت کنید.</li>
        <li>حساب برای <strong>دنبال‌کردن، علاقه‌مندی، هدف ماهانه و خبرخوان کمپین‌ها</strong> است.</li>
        <li>می‌توانید <strong>هم صفحه حمایت داشته باشید و هم حامی دیگران باشید</strong>.</li>
        <li>فعال‌سازی صفحه حمایت <strong>بعداً از پنل</strong> هم ممکن است.</li>
      </ul>
      <p style="margin:.75rem 0 0">الان چه می‌خواهید؟</p>
      <div class="btn-toolbar" style="margin-top:.6rem">
        <a class="btn btn-primary" href="#reg-form">صفحه حمایت می‌خواهم (فعال/پروژه)</a>
        <a class="btn btn-ghost" href="/register-supporter.php">فعلاً فقط حامی هستم</a>
      </div>
      <p class="hint" style="margin-top:.75rem;margin-bottom:0">اگر صفحه حمایت بسازید، همزمان می‌توانید از دیگران هم حمایت و دنبال‌شان کنید.</p>
    </div>

    <h1 class="page-title" id="reg-form">ثبت‌نام — صفحه حمایت</h1>
    <p class="page-lead">اگر در حوزهٔ نرم‌افزار آزاد فعالیت می‌کنید، صفحه حمایت بسازید و از جامعه حمایت بگیرید — <strong>بدون کارمزد پلتفرم</strong>.</p>
    <div class="policy-box" style="margin-bottom:1rem"><strong>سیاست یاور:</strong> فقط فعالان مرتبط با نرم‌افزار آزاد تأیید می‌شوند. پروژه یا خدمت غیرآزاد رد می‌شود.</div>

    <?php if ($ok): ?>
      <div class="form-msg show ok">
        عضویت ثبت شد و در <strong>انتظار تأیید</strong> است. پیامک و ایمیل اطلاع‌رسانی ارسال شد.
        <br><br><a class="btn btn-primary" href="/login.php">ورود</a>
      </div>
    <?php else: ?>
      <?php if ($errors): ?>
        <div class="form-msg show error"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
      <?php endif; ?>

      <form method="post" class="card form-card" id="register-form" novalidate>
        <input type="hidden" name="csrf" id="csrf" value="<?= e(csrf_token()) ?>">
        <div class="hp" aria-hidden="true"><label>Website</label><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

        <h3 class="form-subhead" style="margin-top:0">نام نمایشی برای دریافت حمایت</h3>
        <label class="check-line"><input type="radio" name="display_mode" value="personal" <?= (($_POST['display_mode'] ?? 'personal') === 'personal') ? 'checked' : '' ?>> فعال (حمایت با نام خودم)</label>
        <label class="check-line"><input type="radio" name="display_mode" value="platform" <?= (($_POST['display_mode'] ?? '') === 'platform') ? 'checked' : '' ?>> پروژه</label>
        <label class="check-line"><input type="radio" name="display_mode" value="community" <?= (($_POST['display_mode'] ?? '') === 'community') ? 'checked' : '' ?>> جامعه</label>
        <p class="hint">اگر پروژه یا جامعه بسازید، در صفحه عمومی فقط همان نام دیده می‌شود؛ نام شخصی‌تان زیر پروفایل نشان داده نمی‌شود.</p>
        <label for="display_name">نام شخصی (برای حساب) *</label>
        <input id="display_name" name="display_name" required maxlength="80" value="<?= e($_POST['display_name'] ?? '') ?>">
        <label for="platform_name">نام پروژه / جامعه (برای حالت پروژه یا جامعه)</label>
        <input id="platform_name" name="platform_name" maxlength="80" value="<?= e($_POST['platform_name'] ?? '') ?>" placeholder="مثلاً نام پروژه یا جامعه آزاد">

        <?php
          $regMode = normalize_display_mode($_POST['display_mode'] ?? 'personal');
          $regPresence = presence_link_meta($regMode);
        ?>
        <div id="presence-url-wrap">
          <label for="presence_url"><span id="presence-url-label"><?= e($regPresence['label']) ?></span> *</label>
          <input id="presence_url" name="presence_url" required dir="ltr" class="ltr-field" maxlength="300"
                 placeholder="<?= e($regPresence['placeholder']) ?>"
                 value="<?= e($_POST['presence_url'] ?? '') ?>">
          <p class="hint" id="presence-url-hint"><?= e($regPresence['hint']) ?> این لینک در صفحهٔ عمومی‌تان نمایش داده می‌شود.</p>
        </div>

        <label for="slug">آدرس صفحه (لاتین، اختیاری)</label>
        <div class="input-prefix"><span dir="ltr">donate.sudoshz.ir/u/</span>
          <input id="slug" name="slug" maxlength="40" pattern="[A-Za-z0-9\-_]*" value="<?= e($_POST['slug'] ?? '') ?>" placeholder="my-name" dir="ltr" class="ltr-field">
        </div>

        <label>تصویر پروفایل (از میان نمونه‌های گنو/لینوکس و نرم‌افزار آزاد)</label>
        <div class="avatar-grid">
          <?php foreach ($presets as $i => $p): ?>
            <label class="avatar-option">
              <input type="radio" name="avatar_preset" value="<?= e($p['id']) ?>" <?= (($_POST['avatar_preset'] ?? 'tux') === $p['id'] || ($i===0 && empty($_POST['avatar_preset']))) ? 'checked' : '' ?>>
              <img src="<?= e($p['url']) ?>" alt="<?= e($p['label']) ?>" width="56" height="56">
              <span><?= e($p['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <label for="email">ایمیل *</label>
        <input id="email" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" dir="ltr" class="ltr-field" autocomplete="email">

        <h3 class="form-subhead">تأیید موبایل</h3>
        <p class="hint" style="margin:0 0 .75rem">شماره فقط برای اطلاع‌رسانی حمایت است و در صفحه عمومی نمایش داده نمی‌شود.</p>

        <div class="reg-step" id="reg-step-phone">
          <div class="reg-step__title"><span class="reg-step__num">۱</span> شماره موبایل</div>
          <label for="phone">موبایل *</label>
          <input id="phone" type="tel" name="phone" required dir="ltr" class="ltr-field" inputmode="tel" maxlength="13" placeholder="0912xxxxxxx" autocomplete="tel" value="<?= e($_POST['phone'] ?? '') ?>">
        </div>

        <div class="reg-step" id="reg-step-captcha">
          <div class="reg-step__title"><span class="reg-step__num">۲</span> کپچای امنیتی</div>
          <p class="hint" style="margin:0 0 .5rem">
            قبل از «ارسال کد»، این جمع/تفریق را حل کنید.
            پاسخ را با <strong>عدد انگلیسی</strong> بنویسید (مثلاً <span dir="ltr">12</span>). اگر فارسی بزنید خودکار انگلیسی می‌شود.
            یک‌بار در این نشست کافی است.
          </p>
          <?= Captcha::renderBox() ?>
          <div class="otp-row" style="margin-top:.75rem">
            <button type="button" class="btn btn-primary" id="btn-send-otp">ارسال کد پیامکی</button>
          </div>
          <p class="hint" id="otp-status" role="status" aria-live="polite"></p>
          <div class="otp-timer" id="otp-timer" hidden dir="ltr" aria-live="polite">
            <span aria-hidden="true">⏱</span>
            <span id="otp-timer-text">03:00</span>
          </div>
        </div>

        <div class="reg-step" id="reg-step-otp">
          <div class="reg-step__title"><span class="reg-step__num">۳</span> کد تأیید پیامک</div>
          <label for="otp">کد ۶ رقمی *</label>
          <input id="otp" name="otp" required dir="ltr" class="ltr-field" inputmode="numeric" maxlength="6" placeholder="123456" autocomplete="one-time-code" value="<?= e($_POST['otp'] ?? '') ?>">
          <p class="hint" style="margin:.35rem 0 0">کد را از پیامک کپی کنید. اعتبار کد حدود ۵ دقیقه است؛ دکمه ارسال هر ۳ دقیقه یک‌بار فعال می‌شود.</p>
        </div>


        <div class="grid-2">
          <div>
            <label for="password">رمز عبور * (انگلیسی)</label>
            <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" dir="ltr" class="ltr-field password-field">
            <p class="hint pass-warn" id="pass-warn" hidden>به‌نظر می‌رسد کیبورد فارسی است — رمز را انگلیسی بنویسید.</p>
          </div>
          <div>
            <label for="password2">تکرار رمز *</label>
            <input id="password2" type="password" name="password2" required minlength="8" autocomplete="new-password" dir="ltr" class="ltr-field password-field">
          </div>
        </div>

        <label for="activity">فعالیت شما در نرم‌افزار آزاد *</label>
        <textarea id="activity" name="activity" rows="3" required minlength="20"><?= e($_POST['activity'] ?? '') ?></textarea>
        <label for="services">برای چه کارهایی حمایت می‌پذیرید؟ *</label>
        <textarea id="services" name="services" rows="3" required minlength="10"><?= e($_POST['services'] ?? '') ?></textarea>
        <label for="bio">معرفی کوتاه (اختیاری)</label>
        <textarea id="bio" name="bio" rows="2"><?= e($_POST['bio'] ?? '') ?></textarea>

        <h3 class="form-subhead">تسویه (اختیاری، محرمانه)</h3>
        <p class="hint">الان اجباری نیست. بعد از اولین حمایت دریافتی، برای تسویه بهتر است شبا یا کارت را وارد کنید. <strong>کارت باید به نام خودتان باشد.</strong></p>
        <label for="sheba">شبا (اختیاری)</label>
        <input id="sheba" name="sheba" dir="ltr" class="ltr-field" maxlength="26" value="<?= e($_POST['sheba'] ?? '') ?>" placeholder="IRxxxxxxxxxxxxxxxxxxxxxxxx">
        <label for="card_number">کارت به نام خودتان (اختیاری)</label>
        <input id="card_number" name="card_number" dir="ltr" class="ltr-field" maxlength="19" value="<?= e($_POST['card_number'] ?? '') ?>" placeholder="6037…">

        
        <label class="check-line">
          <input type="checkbox" name="policy_accept" value="1" <?= !empty($_POST['policy_accept']) ? 'checked' : '' ?> required>
          <span>خط‌مشی را می‌پذیرم: ویژهٔ نرم‌افزار آزاد، بدون کارمزد پلتفرم.</span>
        </label>

        <button type="submit" class="btn btn-primary btn-block">ثبت‌نام — صفحه حمایت</button>
        <p class="form-note">حساب دارید؟ <a href="/login.php">ورود</a></p>
      </form>
    <?php endif; ?>
  </div>
</section>
<script>
(function(){
  var meta = {
    personal: {
      label: 'لینک محل فعالیت',
      hint: 'جایی که به‌عنوان فعال نرم‌افزار آزاد شناخته می‌شوید (پروفایل گیت، وبلاگ، صفحه مشارکت‌ها، …). این لینک در صفحهٔ عمومی‌تان نمایش داده می‌شود.',
      placeholder: 'https://github.com/username'
    },
    platform: {
      label: 'لینک مخزن پروژه',
      hint: 'آدرس ریپوی پروژه (گیت‌هاب، کدبرگ، گیت‌لب، …) تا حامیان قبل از حمایت بررسی کنند. این لینک در صفحهٔ عمومی‌تان نمایش داده می‌شود.',
      placeholder: 'https://github.com/org/project'
    },
    community: {
      label: 'لینک صفحه جامعه',
      hint: 'وب‌سایت، ویکی، گروه یا صفحهٔ عمومی جامعه تا حامیان بتوانند آن را بررسی کنند. این لینک در صفحهٔ عمومی‌تان نمایش داده می‌شود.',
      placeholder: 'https://example.org/community'
    }
  };
  function sync() {
    var mode = (document.querySelector('input[name=display_mode]:checked') || {}).value || 'personal';
    var m = meta[mode] || meta.personal;
    var lab = document.getElementById('presence-url-label');
    var hint = document.getElementById('presence-url-hint');
    var inp = document.getElementById('presence_url');
    if (lab) lab.textContent = m.label;
    if (hint) hint.textContent = m.hint;
    if (inp) inp.placeholder = m.placeholder;
  }
  document.querySelectorAll('input[name=display_mode]').forEach(function(el){
    el.addEventListener('change', sync);
  });
  sync();
})();
</script>
<script src="/assets/js/register.js?v=5" defer></script>
<?php layout_footer(); ?>
