<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * محدودیت نرخ ساده مبتنی بر SQLite (sliding window ثابت).
 */
final class RateLimit
{
    public static function clientIp(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        // فقط IP مستقیم — بدون اعتماد به X-Forwarded-For روی هاست اشتراکی مگر پیکربندی مشخص
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        return '0.0.0.0';
    }

    /**
     * محدودیت per-IP (پیش‌فرض).
     * @return array{ok:bool, remaining:int, retry_after:int}
     */
    public static function hit(string $bucket, int $max, int $windowSeconds): array
    {
        return self::hitRaw($bucket . ':' . self::clientIp(), $max, $windowSeconds);
    }

    /**
     * محدودیت با کلید دلخواه (مثلاً شماره موبایل یا hash حساب — بدون IP).
     * @return array{ok:bool, remaining:int, retry_after:int}
     */
    public static function hitKey(string $key, int $max, int $windowSeconds): array
    {
        $key = preg_replace('/[^\w:\.\-@+]/', '_', $key) ?? $key;
        return self::hitRaw(mb_substr($key, 0, 180), $max, $windowSeconds);
    }

    /**
     * @return array{ok:bool, remaining:int, retry_after:int}
     */
    private static function hitRaw(string $key, int $max, int $windowSeconds): array
    {
        $now = time();
        $windowStart = $now - ($now % max(1, $windowSeconds));

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT window_start, hit_count FROM rate_limits WHERE rate_key = ?');
            $st->execute([$key]);
            $row = $st->fetch();
            if (!$row || (int) $row['window_start'] !== $windowStart) {
                $pdo->prepare(
                    'INSERT INTO rate_limits (rate_key, window_start, hit_count) VALUES (?,?,1)
                     ON CONFLICT(rate_key) DO UPDATE SET window_start=excluded.window_start, hit_count=1'
                )->execute([$key, $windowStart]);
                $pdo->commit();
                return ['ok' => true, 'remaining' => max(0, $max - 1), 'retry_after' => $windowSeconds];
            }
            $count = (int) $row['hit_count'];
            if ($count >= $max) {
                $pdo->commit();
                $retry = max(1, $windowStart + $windowSeconds - $now);
                return ['ok' => false, 'remaining' => 0, 'retry_after' => $retry];
            }
            $pdo->prepare('UPDATE rate_limits SET hit_count = hit_count + 1 WHERE rate_key = ?')->execute([$key]);
            $pdo->commit();
            return ['ok' => true, 'remaining' => max(0, $max - $count - 1), 'retry_after' => $windowSeconds];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // در خطا اجازه بده تا سایت نخوابد، ولی لاگ کن
            error_log('RateLimit::hit failed: ' . $e->getMessage());
            return ['ok' => true, 'remaining' => $max, 'retry_after' => 0];
        }
    }

    public static function cleanup(int $olderThanSeconds = 86400): void
    {
        $cut = time() - $olderThanSeconds;
        try {
            db()->prepare('DELETE FROM rate_limits WHERE window_start < ?')->execute([$cut]);
        } catch (Throwable $e) {
            // ignore
        }
    }
}
