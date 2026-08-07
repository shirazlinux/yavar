<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/profile.php';
require_once __DIR__ . '/RateLimit.php';

/**
 * اعلان تلگرام از طریق worker خارج.
 * - گروه/کانال: اعلام عمومی حمایت (متن کوتاه + CTA قابل تنظیم)
 * - چت خصوصی با ربات: اطلاع‌رسانی شخصی (دریافت، کد پیگیری، …)
 */
final class Telegram
{
    public static function defaultCta(): string
    {
        return 'برای حمایت از همین‌جا اقدام کنید:';
    }

    public static function botUsername(): string
    {
        $cfg = app_config();
        $u = trim((string) ($cfg['telegram_bot_username'] ?? 'yavar_notification_bot'));
        return ltrim($u, '@');
    }

    public static function workerSecret(): string
    {
        return trim((string) (app_config()['telegram_worker_secret'] ?? ''));
    }

    public static function isConfigured(): bool
    {
        return self::workerSecret() !== '' && self::botUsername() !== '';
    }

    public static function getLink(int $userId): ?array
    {
        $st = db()->prepare('SELECT * FROM telegram_links WHERE user_id = ?');
        $st->execute([$userId]);
        $row = $st->fetch();
        if (!$row) {
            return null;
        }
        // سازگاری با فیلدهای قدیمی
        if (empty($row['group_chat_id']) && !empty($row['chat_id'])
            && in_array(($row['chat_type'] ?? ''), ['group', 'supergroup', 'channel'], true)) {
            $row['group_chat_id'] = $row['chat_id'];
            $row['group_chat_type'] = $row['chat_type'];
            $row['group_chat_title'] = $row['chat_title'] ?? '';
        }
        if (empty($row['private_chat_id']) && !empty($row['chat_id']) && ($row['chat_type'] ?? '') === 'private') {
            $row['private_chat_id'] = $row['chat_id'];
        }
        return $row;
    }

    public static function hasGroup(array $link): bool
    {
        return trim((string) ($link['group_chat_id'] ?? '')) !== '';
    }

    public static function hasPrivate(array $link): bool
    {
        return trim((string) ($link['private_chat_id'] ?? '')) !== '';
    }

    public static function createConnectToken(int $userId): array
    {
        $rl = RateLimit::hitKey('tg_connect:' . $userId, 6, 3600);
        if (!$rl['ok']) {
            throw new RuntimeException('تعداد درخواست کد زیاد است. یک ساعت بعد دوباره تلاش کنید.');
        }

        $token = bin2hex(random_bytes(16));
        $hash = hash('sha256', $token);
        $now = gmdate('c');
        $exp = gmdate('c', time() + 900);

        $ex = self::getLink($userId);
        if ($ex) {
            db()->prepare(
                'UPDATE telegram_links SET connect_token_hash=?, connect_expires_at=?, updated_at=? WHERE user_id=?'
            )->execute([$hash, $exp, $now, $userId]);
        } else {
            db()->prepare(
                'INSERT INTO telegram_links (
                    user_id, chat_id, chat_type, chat_title, private_chat_id, group_chat_id, group_chat_type, group_chat_title,
                    announce_cta, notify_private, connect_token_hash, connect_expires_at, enabled, linked_at, created_at, updated_at
                 ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,NULL,?,?)'
            )->execute([
                $userId, '', '', '', '', '', '', '', '', 1, $hash, $exp, $now, $now,
            ]);
        }

        $bot = self::botUsername();
        return [
            'token' => $token,
            'expires_at' => $exp,
            'deep_link' => 'https://t.me/' . $bot . '?start=' . $token,
            'bot_username' => $bot,
        ];
    }

    public static function saveSettings(int $userId, string $announceCta, bool $notifyPrivate): void
    {
        $cta = self::sanitizeText($announceCta, 280);
        $now = gmdate('c');
        $ex = self::getLink($userId);
        if (!$ex) {
            db()->prepare(
                'INSERT INTO telegram_links (
                    user_id, chat_id, chat_type, chat_title, private_chat_id, group_chat_id, group_chat_type, group_chat_title,
                    announce_cta, notify_private, connect_token_hash, connect_expires_at, enabled, linked_at, created_at, updated_at
                 ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,NULL,?,?)'
            )->execute([
                $userId, '', '', '', '', '', '', '', $cta, $notifyPrivate ? 1 : 0, '', null, $now, $now,
            ]);
            return;
        }
        db()->prepare(
            'UPDATE telegram_links SET announce_cta=?, notify_private=?, updated_at=? WHERE user_id=?'
        )->execute([$cta, $notifyPrivate ? 1 : 0, $now, $userId]);
    }

    public static function disconnect(int $userId, string $which = 'all'): void
    {
        $now = gmdate('c');
        if ($which === 'group') {
            db()->prepare(
                "UPDATE telegram_links SET group_chat_id='', group_chat_type='', group_chat_title='',
                 chat_id=CASE WHEN chat_type IN ('group','supergroup','channel') THEN '' ELSE chat_id END,
                 updated_at=? WHERE user_id=?"
            )->execute([$now, $userId]);
            return;
        }
        if ($which === 'private') {
            db()->prepare(
                "UPDATE telegram_links SET private_chat_id='',
                 chat_id=CASE WHEN chat_type='private' THEN '' ELSE chat_id END,
                 updated_at=? WHERE user_id=?"
            )->execute([$now, $userId]);
            return;
        }
        db()->prepare(
            "UPDATE telegram_links SET enabled=0, chat_id='', chat_type='', chat_title='',
             private_chat_id='', group_chat_id='', group_chat_type='', group_chat_title='',
             connect_token_hash='', connect_expires_at=NULL, updated_at=? WHERE user_id=?"
        )->execute([$now, $userId]);
    }

    /**
     * @return array{ok:bool, message:string, user_id?:int, target?:string}
     */
    public static function completeLink(string $rawToken, string $chatId, string $chatType, string $chatTitle): array
    {
        $rawToken = preg_replace('/[^a-f0-9]/i', '', $rawToken) ?? '';
        $chatId = trim($chatId);
        if (strlen($rawToken) < 16 || !preg_match('/^-?\d{5,20}$/', $chatId)) {
            return ['ok' => false, 'message' => 'کد یا chat_id نامعتبر'];
        }
        $chatType = mb_substr(preg_replace('/[^a-z_]/', '', strtolower($chatType)) ?? '', 0, 32);
        if (!in_array($chatType, ['private', 'group', 'supergroup', 'channel'], true)) {
            $chatType = 'group';
        }
        $chatTitle = self::sanitizeText($chatTitle, 200);
        $isPrivate = $chatType === 'private';

        $hash = hash('sha256', strtolower($rawToken));
        $st = db()->prepare('SELECT * FROM telegram_links WHERE connect_token_hash = ? LIMIT 1');
        $st->execute([$hash]);
        $row = $st->fetch();
        if (!$row) {
            return ['ok' => false, 'message' => 'کد اتصال یافت نشد یا منقضی/مصرف شده'];
        }
        $exp = (string) ($row['connect_expires_at'] ?? '');
        if ($exp === '' || strtotime($exp) < time()) {
            return ['ok' => false, 'message' => 'کد منقضی شده — از پنل کد تازه بگیرید'];
        }

        $now = gmdate('c');
        $uid = (int) $row['user_id'];

        // آزاد کردن chat از کاربر دیگر
        if ($isPrivate) {
            db()->prepare(
                "UPDATE telegram_links SET private_chat_id='' WHERE private_chat_id=? AND user_id<>?"
            )->execute([$chatId, $uid]);
            // برای PV توکن را نگه می‌داریم تا همان کد برای گروه هم استفاده شود
            db()->prepare(
                "UPDATE telegram_links SET private_chat_id=?, enabled=1, linked_at=COALESCE(linked_at, ?),
                 chat_id=CASE WHEN group_chat_id='' OR group_chat_id IS NULL THEN ? ELSE chat_id END,
                 chat_type=CASE WHEN group_chat_id='' OR group_chat_id IS NULL THEN 'private' ELSE chat_type END,
                 updated_at=? WHERE user_id=?"
            )->execute([$chatId, $now, $chatId, $now, $uid]);
            $target = 'private';
        } else {
            db()->prepare(
                "UPDATE telegram_links SET group_chat_id='', enabled=0 WHERE group_chat_id=? AND user_id<>?"
            )->execute([$chatId, $uid]);
            // گروه: توکن مصرف می‌شود
            db()->prepare(
                "UPDATE telegram_links SET group_chat_id=?, group_chat_type=?, group_chat_title=?,
                 chat_id=?, chat_type=?, chat_title=?, enabled=1, linked_at=?,
                 connect_token_hash='', connect_expires_at=NULL, updated_at=? WHERE user_id=?"
            )->execute([
                $chatId, $chatType, $chatTitle,
                $chatId, $chatType, $chatTitle,
                $now, $now, $uid,
            ]);
            $target = 'group';
        }

        if (function_exists('security_log')) {
            security_log('tg_linked', 'uid=' . $uid . ' target=' . $target);
        }

        return [
            'ok' => true,
            'message' => 'اتصال برقرار شد',
            'user_id' => $uid,
            'target' => $target,
        ];
    }

    public static function sanitizeText(string $s, int $max = 3500): string
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
        return mb_substr(trim($s), 0, $max);
    }

    private static function pushOutbox(int $userId, string $chatId, string $text, ?int $donationId): void
    {
        $now = gmdate('c');
        db()->prepare(
            'INSERT INTO telegram_outbox (user_id, chat_id, text, status, tries, last_error, donation_id, created_at, sent_at)
             VALUES (?,?,?,?,0,?,?,?,NULL)'
        )->execute([
            $userId,
            $chatId,
            $text,
            'pending',
            '',
            $donationId,
            $now,
        ]);
    }

    /**
     * متن عمومی برای گروه/کانال — بدون کد پیگیری و بدون توضیح اضافه.
     */
    public static function formatGroupAnnounce(
        string $amountFa,
        bool $anonymous,
        string $donorName,
        string $message,
        string $cta,
        string $url
    ): string {
        $lines = ['💚 حمایت جدید', ''];
        $lines[] = 'مبلغ: ' . $amountFa . ' تومان';
        if (!$anonymous && $donorName !== '') {
            $lines[] = 'حامی: ' . $donorName;
        } elseif ($anonymous || $donorName === '') {
            $lines[] = 'حامی: ناشناس';
        }
        if ($message !== '') {
            $lines[] = 'پیام: «' . $message . '»';
        }
        $lines[] = '';
        $lines[] = $cta !== '' ? $cta : self::defaultCta();
        $lines[] = $url;
        return implode("\n", $lines);
    }

    /**
     * متن خصوصی برای خود فعال — اطلاع‌رسانی دریافت.
     */
    public static function formatPrivateNotice(
        string $amountFa,
        bool $anonymous,
        string $donorName,
        string $message,
        string $ref,
        string $dashboardUrl
    ): string {
        $lines = [
            '✅ حمایت جدید دریافت شد',
            '',
            'مبلغ: ' . $amountFa . ' تومان',
        ];
        if (!$anonymous && $donorName !== '') {
            $lines[] = 'حامی: ' . $donorName;
        } else {
            $lines[] = 'حامی: ناشناس';
        }
        if ($message !== '') {
            $lines[] = 'پیام: «' . $message . '»';
        }
        if ($ref !== '') {
            $lines[] = 'کد پیگیری: ' . $ref;
        }
        $lines[] = '';
        $lines[] = 'جزئیات در پنل:';
        $lines[] = $dashboardUrl;
        return implode("\n", $lines);
    }

    /**
     * بعد از پرداخت: گروه = اعلام عمومی، PV = اطلاع شخصی (اگر فعال باشد).
     */
    public static function enqueueDonationPaid(
        int $userId,
        int $amount,
        string $ref,
        int $donationId = 0,
        bool $anonymous = false,
        string $donorName = '',
        string $message = ''
    ): bool {
        if (!self::isConfigured()) {
            return false;
        }
        $link = self::getLink($userId);
        if (!$link) {
            return false;
        }

        $hasGroup = self::hasGroup($link);
        $hasPrivate = self::hasPrivate($link) && !empty($link['notify_private']);
        if (!$hasGroup && !$hasPrivate) {
            // fallback: chat_id قدیمی
            if (!empty($link['enabled']) && trim((string) ($link['chat_id'] ?? '')) !== '') {
                $hasGroup = in_array(($link['chat_type'] ?? ''), ['group', 'supergroup', 'channel'], true);
                $hasPrivate = ($link['chat_type'] ?? '') === 'private';
            } else {
                return false;
            }
        }

        if ($donationId > 0) {
            $dup = db()->prepare(
                "SELECT id FROM telegram_outbox WHERE donation_id=? AND status IN ('pending','sent') LIMIT 1"
            );
            $dup->execute([$donationId]);
            if ($dup->fetch()) {
                return false;
            }
        }

        $rl = RateLimit::hitKey('tg_out:' . $userId, 40, 3600);
        if (!$rl['ok']) {
            error_log('Telegram enqueue rate limited user=' . $userId);
            return false;
        }

        $st = db()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$userId]);
        $u = $st->fetch();
        if (!$u) {
            return false;
        }

        $cfg = app_config();
        $slug = (string) ($u['slug'] ?? '');
        $url = rtrim((string) ($cfg['site_url'] ?? ''), '/') . '/u/' . rawurlencode($slug);
        $dash = rtrim((string) ($cfg['site_url'] ?? ''), '/') . '/dashboard/';
        $amount = max(0, min(500000000, $amount));
        $amountFa = number_format($amount);
        $ref = self::sanitizeText($ref, 80);
        $donorName = self::sanitizeText($donorName, 80);
        $message = self::sanitizeText($message, 200);
        $cta = self::sanitizeText((string) ($link['announce_cta'] ?? ''), 280);
        if ($cta === '') {
            $cta = self::defaultCta();
        }
        $donId = $donationId > 0 ? $donationId : null;
        $queued = false;

        if ($hasGroup) {
            $gid = (string) ($link['group_chat_id'] ?: $link['chat_id']);
            $gText = self::formatGroupAnnounce($amountFa, $anonymous, $donorName, $message, $cta, $url);
            self::pushOutbox($userId, $gid, $gText, $donId);
            $queued = true;
            // فقط یک بار donation_id برای جلوگیری از duplicate روی PV
            $donId = null;
        }

        if ($hasPrivate) {
            $pid = (string) ($link['private_chat_id'] ?: ((!$hasGroup) ? $link['chat_id'] : ''));
            if ($pid !== '') {
                $pText = self::formatPrivateNotice($amountFa, $anonymous, $donorName, $message, $ref, $dash);
                self::pushOutbox($userId, $pid, $pText, $donId);
                $queued = true;
            }
        }

        return $queued;
    }

    public static function pullOutbox(int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $st = db()->prepare(
            "SELECT id, chat_id, text, tries FROM telegram_outbox WHERE status='pending' AND tries < 8 ORDER BY id ASC LIMIT {$limit}"
        );
        $st->execute();
        return $st->fetchAll() ?: [];
    }

    public static function ackOutbox(int $id, bool $ok, string $error = ''): void
    {
        $now = gmdate('c');
        if ($ok) {
            db()->prepare(
                "UPDATE telegram_outbox SET status='sent', sent_at=?, last_error='' WHERE id=? AND status='pending'"
            )->execute([$now, $id]);
            return;
        }
        db()->prepare(
            "UPDATE telegram_outbox SET tries = tries + 1, last_error=?,
             status = CASE WHEN tries + 1 >= 8 THEN 'failed' ELSE 'pending' END
             WHERE id=? AND status='pending'"
        )->execute([mb_substr($error, 0, 500), $id]);
    }

    public static function verifyWorkerAuth(?string $header, ?string $altHeader = null): bool
    {
        $secret = self::workerSecret();
        if ($secret === '' || strlen($secret) < 32) {
            return false;
        }
        $got = '';
        if (is_string($header) && str_starts_with($header, 'Bearer ')) {
            $got = trim(substr($header, 7));
        } elseif (is_string($altHeader) && $altHeader !== '') {
            $got = trim($altHeader);
        }
        return $got !== '' && hash_equals($secret, $got);
    }

    public static function workerIpAllowed(): bool
    {
        $cfg = app_config();
        $list = trim((string) ($cfg['telegram_worker_ips'] ?? ''));
        if ($list === '') {
            return true;
        }
        $ip = RateLimit::clientIp();
        $allowed = array_filter(array_map('trim', explode(',', $list)));
        return in_array($ip, $allowed, true);
    }
}
