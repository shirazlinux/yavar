<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** @return list<int> */
function campaign_parse_tiers(string $json): array
{
    $arr = json_decode($json, true);
    if (!is_array($arr)) {
        return [];
    }
    $out = [];
    foreach ($arr as $t) {
        if (is_array($t) && isset($t['amount'])) {
            $n = (int) $t['amount'];
        } else {
            $n = (int) $t;
        }
        if ($n > 0) {
            $out[] = $n;
        }
    }
    return array_values(array_unique($out));
}

function campaign_raised(int $campaignId): int
{
    $st = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM donations WHERE campaign_id=? AND status=?');
    $st->execute([$campaignId, 'paid']);
    return (int) $st->fetchColumn();
}

function campaign_progress(array $c): array
{
    $raised = campaign_raised((int) $c['id']);
    $goal = max(1, (int) $c['goal_amount']);
    $pct = min(100, (int) round(100 * $raised / $goal));
    return ['raised' => $raised, 'goal' => (int) $c['goal_amount'], 'percent' => $pct];
}

function campaign_refresh_status(?int $campaignId = null): void
{
    $now = gmdate('Y-m-d');
    if ($campaignId) {
        $st = db()->prepare("SELECT * FROM campaigns WHERE id=? AND status='active'");
        $st->execute([$campaignId]);
        $rows = $st->fetchAll();
    } else {
        $rows = db()->query("SELECT * FROM campaigns WHERE status='active'")->fetchAll();
    }
    foreach ($rows as $c) {
        $deadline = substr((string) $c['deadline'], 0, 10);
        if ($deadline === '' || $deadline >= $now) {
            // still open — check early success
            $raised = campaign_raised((int) $c['id']);
            if ($raised >= (int) $c['goal_amount']) {
                $u = db()->prepare("UPDATE campaigns SET status='successful', closed_at=?, updated_at=? WHERE id=?");
                $u->execute([gmdate('c'), gmdate('c'), $c['id']]);
            }
            continue;
        }
        // deadline passed
        $raised = campaign_raised((int) $c['id']);
        if ($raised >= (int) $c['goal_amount']) {
            $u = db()->prepare("UPDATE campaigns SET status='successful', closed_at=?, updated_at=? WHERE id=?");
            $u->execute([gmdate('c'), gmdate('c'), $c['id']]);
        } else {
            $u = db()->prepare("UPDATE campaigns SET status='failed', closed_at=?, updated_at=? WHERE id=?");
            $u->execute([gmdate('c'), gmdate('c'), $c['id']]);
            // mark paid donations for refund
            $r = db()->prepare("UPDATE donations SET settlement_status='refund_pending' WHERE campaign_id=? AND status='paid' AND settlement_status IN ('none','ready')");
            $r->execute([(int) $c['id']]);
        }
    }
}

function campaign_status_label(string $s): string
{
    return match ($s) {
        'draft' => 'پیش‌نویس',
        'active' => 'جاری',
        'successful' => 'تکمیل‌شده',
        'failed' => 'ناتمام',
        'cancelled' => 'لغو شده',
        default => $s,
    };
}

/**
 * لغو کمپین توسط صاحب یا ادمین.
 * اگر جاری باشد و حمایت پرداخت‌شده داشته باشد → صف بازگشت وجه.
 */
function campaign_cancel(int $campaignId, ?int $byUserId = null, bool $asAdmin = false): array
{
    if ($asAdmin) {
        $st = db()->prepare('SELECT * FROM campaigns WHERE id=?');
        $st->execute([$campaignId]);
    } else {
        $st = db()->prepare('SELECT * FROM campaigns WHERE id=? AND user_id=?');
        $st->execute([$campaignId, (int) $byUserId]);
    }
    $c = $st->fetch();
    if (!$c) {
        return ['ok' => false, 'message' => 'کمپین یافت نشد.'];
    }
    if (!in_array($c['status'], ['draft', 'active'], true)) {
        return ['ok' => false, 'message' => 'این کمپین دیگر قابل لغو نیست (وضعیت: ' . campaign_status_label((string) $c['status']) . ').'];
    }
    $now = gmdate('c');
    $wasActive = $c['status'] === 'active';
    db()->prepare("UPDATE campaigns SET status='cancelled', closed_at=?, updated_at=? WHERE id=?")
        ->execute([$now, $now, $campaignId]);

    $refundCount = 0;
    if ($wasActive) {
        $r = db()->prepare(
            "UPDATE donations SET settlement_status='refund_pending'
             WHERE campaign_id=? AND status='paid' AND settlement_status IN ('none','ready')"
        );
        $r->execute([$campaignId]);
        $refundCount = $r->rowCount();
    }

    return [
        'ok' => true,
        'message' => $refundCount > 0
            ? ('کمپین لغو شد. ' . $refundCount . ' حمایت پرداخت‌شده در صف بازگشت وجه قرار گرفت.')
            : 'کمپین لغو شد.',
        'refund_count' => $refundCount,
        'campaign' => $c,
    ];
}

function user_total_paid(int $userId): array
{
    $st = db()->prepare('SELECT COALESCE(SUM(amount),0) s, COUNT(*) c FROM donations WHERE user_id=? AND status=?');
    $st->execute([$userId, 'paid']);
    $r = $st->fetch() ?: ['s' => 0, 'c' => 0];
    return ['sum' => (int) $r['s'], 'count' => (int) $r['c']];
}
