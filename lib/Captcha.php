<?php
declare(strict_types=1);

/**
 * کپچای ریاضی ساده (نمایش LTR با ارقام لاتین تا در RTL جابه‌جا نشود؛ پاسخ لاتین/فارسی).
 *
 * الگوی استاندارد (مثل reCAPTCHA soft / session gate):
 * - یک بار در هر نشست موفق می‌شود (TTL)
 * - در همان نشست دیگر درخواست نمی‌شود
 * - فقط در صورت خطا یا انقضا دوباره نمایش داده می‌شود
 * - اعداد فارسی ورودی به انگلیسی نرمال می‌شوند
 */
final class Captcha
{
    /** اعتبار تأیید موفق در نشست (ثانیه) — حدود ۳۰ دقیقه */
    private const PASS_TTL = 1800;
    /** اعتبار خودِ چالش (ثانیه) */
    private const CHALLENGE_TTL = 900;

    private static function fa(int $n): string
    {
        return strtr((string) $n, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
    }

    /** ارقام فارسی/عربی → لاتین و حذف غیرعدد */
    public static function digitsEn(?string $s): string
    {
        $map = [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ];
        $raw = strtr((string) $s, $map);
        return preg_replace('/\D+/', '', $raw) ?? '';
    }

    public static function generate(): array
    {
        auth_start_session();
        $a = random_int(3, 15);
        $b = random_int(1, 9);
        $op = random_int(0, 1) === 0 ? 'plus' : 'minus';
        if ($op === 'minus' && $a < $b) {
            [$a, $b] = [$b, $a];
        }
        $answer = $op === 'plus' ? $a + $b : $a - $b;
        $_SESSION['captcha_answer'] = (string) $answer;
        $_SESSION['captcha_ts'] = time();
        // نمایش همیشه LTR با ارقام لاتین تا در صفحهٔ RTL جابه‌جا نشود
        $opSym = $op === 'plus' ? '+' : '−';
        $q = $a . ' ' . $opSym . ' ' . $b . ' = ?';
        $hint = $op === 'plus'
            ? 'حاصل جمع را با عدد انگلیسی بنویسید'
            : 'حاصل تفریق را با عدد انگلیسی بنویسید';
        $_SESSION['captcha_question'] = $q;
        $_SESSION['captcha_hint'] = $hint;
        $_SESSION['captcha_a'] = $a;
        $_SESSION['captcha_b'] = $b;
        $_SESSION['captcha_op'] = $op;
        return ['question' => $q, 'hint' => $hint, 'a' => $a, 'b' => $b, 'op' => $op];
    }

    public static function hasChallenge(): bool
    {
        auth_start_session();
        $expect = (string) ($_SESSION['captcha_answer'] ?? '');
        $ts = (int) ($_SESSION['captcha_ts'] ?? 0);
        return $expect !== '' && $ts >= time() - self::CHALLENGE_TTL;
    }

    public static function isPassed(): bool
    {
        auth_start_session();
        $until = (int) ($_SESSION['captcha_passed_until'] ?? 0);
        return $until > time();
    }

    public static function markPassed(): void
    {
        auth_start_session();
        $_SESSION['captcha_passed_until'] = time() + self::PASS_TTL;
        unset(
            $_SESSION['captcha_answer'],
            $_SESSION['captcha_ts'],
            $_SESSION['captcha_question'],
            $_SESSION['captcha_hint'],
            $_SESSION['captcha_a'],
            $_SESSION['captcha_b'],
            $_SESSION['captcha_op']
        );
    }

    public static function clearPassed(): void
    {
        auth_start_session();
        unset($_SESSION['captcha_passed_until']);
    }

    public static function currentQuestion(): string
    {
        auth_start_session();
        if (!self::hasChallenge()) {
            self::generate();
        }
        return (string) ($_SESSION['captcha_question'] ?? '');
    }

    public static function currentHint(): string
    {
        auth_start_session();
        if (!self::hasChallenge()) {
            self::generate();
        }
        return (string) ($_SESSION['captcha_hint'] ?? 'پاسخ را با عدد انگلیسی بنویسید (مثلاً 12)');
    }

    /**
     * تأیید پاسخ. در صورت موفقیت، نشست captcha_passed ست می‌شود.
     * @param bool $consume چالش را مصرف کند (پیش‌فرض true)
     */
    public static function verify(?string $input, bool $consume = true): bool
    {
        auth_start_session();
        if (self::isPassed()) {
            return true;
        }
        $expect = (string) ($_SESSION['captcha_answer'] ?? '');
        $ts = (int) ($_SESSION['captcha_ts'] ?? 0);
        if ($expect === '' || $ts < time() - self::CHALLENGE_TTL) {
            return false;
        }
        $got = self::digitsEn($input);
        $ok = $got !== '' && $got === $expect;
        if ($ok) {
            if ($consume) {
                self::markPassed();
            }
        }
        return $ok;
    }

    /**
     * برای فرم‌ها: اگر قبلاً در نشست پاس شده، OK؛ وگرنه پاسخ را چک کن.
     */
    public static function check(?string $input = null): bool
    {
        if (self::isPassed()) {
            return true;
        }
        return self::verify($input, true);
    }

    /** HTML کپچا — اگر قبلاً پاس شده، پیام تأیید */
    public static function renderBox(string $inputName = 'captcha', string $inputId = 'captcha'): string
    {
        if (self::isPassed()) {
            return <<<HTML
<div class="captcha-box captcha-box--ok" role="status" data-captcha-passed="1">
  <div class="captcha-box__head">
    <span class="captcha-box__badge captcha-box__badge--ok">تأیید شد</span>
    <span class="captcha-box__hint">کپچای این نشست قبول است — دوباره لازم نیست.</span>
  </div>
  <input type="hidden" name="{$inputName}" id="{$inputId}" value="ok">
</div>
HTML;
        }

        // ensure challenge exists
        self::currentQuestion();
        $hint = e(self::currentHint());
        $id = e($inputId);
        $name = e($inputName);
        $a = (int) ($_SESSION['captcha_a'] ?? 0);
        $b = (int) ($_SESSION['captcha_b'] ?? 0);
        $op = (string) ($_SESSION['captcha_op'] ?? 'plus');
        $opSym = $op === 'plus' ? '+' : '−';
        // اگر session قدیمی بدون a/b بود، از رشتهٔ question پارس نکن — regenerate
        if ($a < 1 || $b < 1) {
            $gen = self::generate();
            $a = (int) $gen['a'];
            $b = (int) $gen['b'];
            $opSym = $gen['op'] === 'plus' ? '+' : '−';
        }
        $aEsc = e((string) $a);
        $bEsc = e((string) $b);
        $opEsc = e($opSym);
        $aria = e($a . ' ' . $opSym . ' ' . $b . ' = ?');
        return <<<HTML
<div class="captcha-box" role="group" aria-label="کپچای امنیتی" data-captcha-passed="0" dir="rtl">
  <div class="captcha-box__head">
    <span class="captcha-box__badge">کپچا</span>
    <span class="captcha-box__hint">{$hint}</span>
  </div>
  <div class="captcha-box__challenge" dir="ltr" lang="en" role="img" aria-label="{$aria}">
    <span class="captcha-box__num">{$aEsc}</span>
    <span class="captcha-box__op" aria-hidden="true">{$opEsc}</span>
    <span class="captcha-box__num">{$bEsc}</span>
    <span class="captcha-box__eq" aria-hidden="true">=</span>
    <span class="captcha-box__q" aria-hidden="true">?</span>
  </div>
  <label class="captcha-box__label" for="{$id}">پاسخ را با عدد انگلیسی بنویسید *</label>
  <div class="captcha-box__input-wrap">
    <input class="captcha-box__input ltr-field" id="{$id}" name="{$name}" required inputmode="numeric" pattern="[0-9]*" autocomplete="off" dir="ltr" placeholder="مثلاً 12" data-digits-en="1">
  </div>
</div>
HTML;
    }
}
