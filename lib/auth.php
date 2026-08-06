<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jdate.php';
require_once __DIR__ . '/Sms.php';

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    // سخت‌گیری نشست: فقط کوکی، strict mode، جلوگیری از session fixation
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_trans_sid', '0');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_secure', '1');
    @ini_set('session.cookie_samesite', 'Lax');
    session_name('donate_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    auth_start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_verify(?string $token): bool
{
    auth_start_session();
    return is_string($token)
        && $token !== ''
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

/**
 * مسیر نسبی امن برای redirect بعد از login (جلوگیری از //evil.com).
 */
function safe_internal_path(?string $next, string $fallback = '/dashboard/'): string
{
    if (!is_string($next) || $next === '') {
        return $fallback;
    }
    $next = trim($next);
    // فقط path نسبی همان origin
    if ($next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '\\')) {
        return $fallback;
    }
    if (preg_match('#[\x00-\x1F\x7F]#', $next)) {
        return $fallback;
    }
    // جلوگیری از scheme-relative و کاراکترهای خطرناک
    if (!preg_match('#^/[A-Za-z0-9/_\-.\?\&=%]*$#', $next)) {
        return $fallback;
    }
    return $next;
}

function security_log(string $kind, string $detail = ''): void
{
    try {
        require_once __DIR__ . '/RateLimit.php';
        db()->prepare('INSERT INTO security_events (kind, ip, detail, created_at) VALUES (?,?,?,?)')
            ->execute([$kind, RateLimit::clientIp(), mb_substr($detail, 0, 500), gmdate('c')]);
    } catch (Throwable $e) {
        error_log('security_log: ' . $e->getMessage());
    }
}

function auth_user(): ?array
{
    auth_start_session();
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([(int) $_SESSION['uid']]);
    $u = $st->fetch();
    return $u ?: null;
}

function auth_login(array $user): void
{
    auth_start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
}

function auth_logout(): void
{
    auth_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

function auth_require_login(): array
{
    $u = auth_user();
    if (!$u) {
        header('Location: /login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/dashboard/'));
        exit;
    }
    return $u;
}

function auth_role(?array $u = null): string
{
    $u = $u ?? auth_user();
    if (!$u) return '';
    return (string) ($u['role'] ?? 'hamyar');
}

function auth_is_supporter(?array $u = null): bool
{
    return auth_role($u) === 'supporter';
}

function auth_is_hamyar(?array $u = null): bool
{
    $r = auth_role($u);
    return $r === 'hamyar' || $r === '' || ($u && !empty($u['is_admin']));
}

function auth_require_admin(): array
{
    $u = auth_require_login();
    if (empty($u['is_admin'])) {
        http_response_code(403);
        exit('دسترسی فقط برای مدیر');
    }
    return $u;
}

function slugify(string $s): string
{
    $s = trim(mb_strtolower($s));
    $s = preg_replace('/\s+/u', '-', $s) ?? '';
    $s = preg_replace('/[^a-z0-9\-_]/', '', $s) ?? '';
    $s = trim($s, '-_');
    if ($s === '') {
        $s = 'user-' . bin2hex(random_bytes(3));
    }
    return mb_substr($s, 0, 40);
}

function unique_slug(string $base, ?int $ignoreId = null): string
{
    $slug = slugify($base);
    $i = 0;
    while (true) {
        $try = $i === 0 ? $slug : $slug . '-' . $i;
        $sql = 'SELECT id FROM users WHERE slug = ?';
        $params = [$try];
        if ($ignoreId) {
            $sql .= ' AND id != ?';
            $params[] = $ignoreId;
        }
        $st = db()->prepare($sql);
        $st->execute($params);
        if (!$st->fetch()) {
            return $try;
        }
        $i++;
        if ($i > 50) {
            return $slug . '-' . bin2hex(random_bytes(2));
        }
    }
}

/** ارقام فارسی/عربی → لاتین */
function digits_en(string $s): string
{
    $map = [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ];
    return preg_replace('/\D+/', '', strtr($s, $map)) ?? '';
}

function normalize_sheba(string $s): string
{
    $s = strtoupper(preg_replace('/\s+/', '', $s) ?? '');
    // IR + ارقام (فارسی یا لاتین)
    if (str_starts_with($s, 'IR')) {
        $digits = digits_en(substr($s, 2));
        return $digits === '' ? 'IR' : ('IR' . $digits);
    }
    $digits = digits_en($s);
    if (preg_match('/^\d{24}$/', $digits)) {
        return 'IR' . $digits;
    }
    return $s;
}

function validate_sheba(string $s): bool
{
    $s = normalize_sheba($s);
    return (bool) preg_match('/^IR\d{24}$/', $s);
}

function normalize_card(string $s): string
{
    return digits_en($s);
}

function validate_card(string $s): bool
{
    $s = normalize_card($s);
    return $s === '' || (bool) preg_match('/^\d{16}$/', $s);
}

/** آیا کاربر اطلاعات تسویه (کارت یا شبا) دارد؟ */
function user_has_settlement(array $user): bool
{
    $card = normalize_card((string) ($user['card_number'] ?? ''));
    $sheba = normalize_sheba((string) ($user['sheba'] ?? ''));
    return ($card !== '' && validate_card($card)) || ($sheba !== '' && validate_sheba($sheba));
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money_fa(int $n): string
{
    return fa_digits(number_format($n, 0, '.', ',')) . ' تومان';
}

/** فقط عدد با جداکننده هزارگان فارسی */
function number_fa(int $n): string
{
    return fa_digits(number_format($n, 0, '.', ','));
}

/** ایمیل قبلاً ثبت شده؟ */
function user_email_taken(string $email, ?int $ignoreUserId = null): bool
{
    $email = strtolower(trim($email));
    if ($email === '') return false;
    if ($ignoreUserId) {
        $st = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $st->execute([$email, $ignoreUserId]);
    } else {
        $st = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
    }
    return (bool) $st->fetch();
}

/** موبایل قبلاً ثبت شده؟ (خالی نادیده) */
function user_phone_taken(string $phone, ?int $ignoreUserId = null): bool
{
    $phone = Sms::normalizeMobile($phone);
    if ($phone === '' || !Sms::validMobile($phone)) {
        return false;
    }
    if ($ignoreUserId) {
        $st = db()->prepare("SELECT id FROM users WHERE phone = ? AND phone <> '' AND id <> ? LIMIT 1");
        $st->execute([$phone, $ignoreUserId]);
    } else {
        $st = db()->prepare("SELECT id FROM users WHERE phone = ? AND phone <> '' LIMIT 1");
        $st->execute([$phone]);
    }
    return (bool) $st->fetch();
}
