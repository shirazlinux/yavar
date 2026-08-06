<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/campaigns.php';
require_once dirname(__DIR__) . '/lib/Notify.php';
auth_require_admin();
campaign_refresh_status();

function settlement_mark(array $ids, string $status, string $note = ''): int
{
    if (!$ids) return 0;
    $n = 0;
    $now = gmdate('c');
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id < 1) continue;
        if ($status === 'settled') {
            $st = db()->prepare("UPDATE donations SET settlement_status='settled', settled_at=?, settlement_note=? WHERE id=? AND status='paid' AND settlement_status IN ('ready','refund_pending')");
            $st->execute([$now, $note, $id]);
        } elseif ($status === 'refunded') {
            $st = db()->prepare("UPDATE donations SET settlement_status='refunded', settled_at=?, settlement_note=? WHERE id=? AND status='paid'");
            $st->execute([$now, $note, $id]);
        } else {
            continue;
        }
        if ($st->rowCount() > 0) {
            $n++;
            $q = db()->prepare('SELECT d.amount, u.display_name, u.phone, u.email FROM donations d JOIN users u ON u.id=d.user_id WHERE d.id=?');
            $q->execute([$id]);
            $row = $q->fetch();
            if ($row) {
                Notify::settlement(
                    (string) ($row['phone'] ?? ''),
                    (string) ($row['email'] ?? ''),
                    (string) $row['display_name'],
                    (int) $row['amount'],
                    $status === 'refunded' ? 'refunded' : 'settled'
                );
            }
        }
    }
    return $n;
}

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    $note = trim((string) ($_POST['note'] ?? ''));

    if ($action === 'settle_one' || $action === 'refund_one') {
        $id = (int) ($_POST['donation_id'] ?? 0);
        $n = settlement_mark([$id], $action === 'settle_one' ? 'settled' : 'refunded', $note);
        $flash = $n ? 'ثبت شد و اطلاع‌رسانی ارسال شد.' : 'موردی به‌روز نشد.';
    }

    // تأیید ادعای فعال (paid + settlement none → ready) یا رد ادعا (paid → failed)
    if ($action === 'approve_claim' || $action === 'reject_claim') {
        $id = (int) ($_POST['donation_id'] ?? 0);
        if ($id > 0) {
            if ($action === 'approve_claim') {
                $st = db()->prepare(
                    "UPDATE donations SET settlement_status='ready'
                     WHERE id=? AND status='paid' AND settlement_status='none'"
                );
                $st->execute([$id]);
                $flash = $st->rowCount() > 0 ? 'ادعا تأیید و آمادهٔ تسویه شد.' : 'موردی به‌روز نشد.';
                if ($st->rowCount() > 0) {
                    security_log('admin_approve_claim', 'id=' . $id);
                    $q = db()->prepare('SELECT d.amount, u.display_name, u.phone, u.email, d.ref_id FROM donations d JOIN users u ON u.id=d.user_id WHERE d.id=?');
                    $q->execute([$id]);
                    $row = $q->fetch();
                    if ($row) {
                        Notify::donationPaid(
                            (string) ($row['phone'] ?? ''),
                            (string) ($row['email'] ?? ''),
                            (string) $row['display_name'],
                            (int) $row['amount'],
                            (string) ($row['ref_id'] ?? '')
                        );
                    }
                }
            } else {
                $st = db()->prepare(
                    "UPDATE donations SET status='failed', settlement_status='none', settlement_note=?
                     WHERE id=? AND status='paid' AND settlement_status='none'"
                );
                $st->execute([$note !== '' ? $note : 'ادعای دریافت رد شد', $id]);
                $flash = $st->rowCount() > 0 ? 'ادعا رد شد.' : 'موردی به‌روز نشد.';
                security_log('admin_reject_claim', 'id=' . $id);
            }
        }
    }

    if ($action === 'settle_batch' || $action === 'refund_batch') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $n = settlement_mark($ids, $action === 'settle_batch' ? 'settled' : 'refunded', $note);
        $flash = $n ? (number_fa($n) . ' مورد ثبت و اطلاع‌رسانی شد.') : 'موردی انتخاب نشده بود.';
    }

    if ($action === 'settle_user') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        $st = db()->prepare("SELECT id FROM donations WHERE user_id=? AND status='paid' AND settlement_status='ready'");
        $st->execute([$uid]);
        $ids = array_column($st->fetchAll(), 'id');
        $n = settlement_mark($ids, 'settled', $note !== '' ? $note : 'تسویه گروهی فعال');
        $flash = $n ? ('تسویه ' . number_fa($n) . ' تراکنش برای این فعال ثبت شد.') : 'تراکنش آماده‌ای نبود.';
    }

    // auto: mark all ready older than N days — simple automation
    if ($action === 'auto_settle_old') {
        $days = max(1, min(90, (int) ($_POST['days'] ?? 7)));
        $cut = gmdate('c', time() - $days * 86400);
        $st = db()->prepare("SELECT id FROM donations WHERE status='paid' AND settlement_status='ready' AND paid_at IS NOT NULL AND paid_at <= ?");
        $st->execute([$cut]);
        $ids = array_column($st->fetchAll(), 'id');
        $n = settlement_mark($ids, 'settled', "تسویه خودکار پس از {$days} روز");
        $flash = $n
            ? (number_fa($n) . " تراکنش قدیمی‌تر از {$days} روز تسویه و اطلاع‌رسانی شد.")
            : 'تراکنش قدیمی آماده‌ای نبود.';
    }

    header('Location: /admin/settlements.php?msg=' . rawurlencode($flash));
    exit;
}

$filter = $_GET['s'] ?? 'ready';
if (!in_array($filter, ['ready', 'claims', 'refund_pending', 'settled', 'refunded', 'all'], true)) {
    $filter = 'ready';
}

$stats = [
    'ready_sum' => (int) db()->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='paid' AND settlement_status='ready'")->fetchColumn(),
    'ready_count' => (int) db()->query("SELECT COUNT(*) FROM donations WHERE status='paid' AND settlement_status='ready'")->fetchColumn(),
    'claims_count' => (int) db()->query("SELECT COUNT(*) FROM donations WHERE status='paid' AND settlement_status='none'")->fetchColumn(),
    'claims_sum' => (int) db()->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='paid' AND settlement_status='none'")->fetchColumn(),
    'refund_sum' => (int) db()->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='paid' AND settlement_status='refund_pending'")->fetchColumn(),
    'settled_sum' => (int) db()->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='paid' AND settlement_status='settled'")->fetchColumn(),
];

// group ready by user for quick settle
$byUser = db()->query(
    "SELECT u.id, u.display_name, u.sheba, u.card_number, u.phone, u.email,
            COUNT(d.id) AS cnt, SUM(d.amount) AS total
     FROM donations d
     JOIN users u ON u.id=d.user_id
     WHERE d.status='paid' AND d.settlement_status='ready'
     GROUP BY u.id
     ORDER BY total DESC"
)->fetchAll();

if ($filter === 'all') {
    $rows = db()->query(
        "SELECT d.*, u.display_name, u.sheba, u.card_number, u.phone, c.title AS campaign_title
         FROM donations d
         JOIN users u ON u.id=d.user_id
         LEFT JOIN campaigns c ON c.id=d.campaign_id
         WHERE d.status='paid'
         ORDER BY d.id DESC LIMIT 300"
    )->fetchAll();
} elseif ($filter === 'claims') {
    $rows = db()->query(
        "SELECT d.*, u.display_name, u.sheba, u.card_number, u.phone, c.title AS campaign_title
         FROM donations d
         JOIN users u ON u.id=d.user_id
         LEFT JOIN campaigns c ON c.id=d.campaign_id
         WHERE d.status='paid' AND d.settlement_status='none'
         ORDER BY d.id DESC LIMIT 300"
    )->fetchAll();
} else {
    $st = db()->prepare(
        "SELECT d.*, u.display_name, u.sheba, u.card_number, u.phone, c.title AS campaign_title
         FROM donations d
         JOIN users u ON u.id=d.user_id
         LEFT JOIN campaigns c ON c.id=d.campaign_id
         WHERE d.status='paid' AND d.settlement_status=?
         ORDER BY d.id DESC LIMIT 300"
    );
    $st->execute([$filter]);
    $rows = $st->fetchAll();
}

$msg = (string) ($_GET['msg'] ?? '');
layout_header('تسویه حمایت‌ها');
?>
<section class="page-section">
  <div class="container">
    <h1 class="page-title">تسویه و مبالغ تسویه‌نشده</h1>
    <p class="page-lead"><a href="/admin/">← آمار و تأیید حساب‌ها</a></p>

    <?php if ($msg !== ''): ?>
      <div class="form-msg show ok"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="stats-grid">
      <div class="card stat"><div class="stat-label">آماده تسویه</div><div class="stat-value" style="font-size:1.15rem;color:#fcd34d"><?= e(money_fa($stats['ready_sum'])) ?></div><div class="hint"><?= e(number_fa($stats['ready_count'])) ?> تراکنش</div></div>
      <div class="card stat"><div class="stat-label">ادعای فعال (بررسی)</div><div class="stat-value" style="font-size:1.15rem"><?= e(money_fa($stats['claims_sum'])) ?></div><div class="hint"><?= e(number_fa($stats['claims_count'])) ?> مورد · <a href="?s=claims">مشاهده</a></div></div>
      <div class="card stat"><div class="stat-label">بازگشت وجه</div><div class="stat-value" style="font-size:1.15rem"><?= e(money_fa($stats['refund_sum'])) ?></div></div>
      <div class="card stat"><div class="stat-label">تسویه‌شده</div><div class="stat-value" style="font-size:1.15rem"><?= e(money_fa($stats['settled_sum'])) ?></div></div>
    </div>

    <?php if ($byUser): ?>
    <div class="card" style="margin-top:1.25rem">
      <h2 style="margin-top:0">تسویه گروهی به‌ازای فعال</h2>
      <p class="hint">بعد از واریز بانکی به شبا/کارت، «تسویه همه» را بزنید تا تیک بخورد و به فعال پیامک/ایمیل برود.</p>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>فعال</th><th>تعداد</th><th>جمع</th><th>شبا/کارت</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($byUser as $g): ?>
            <tr>
              <td><?= e($g['display_name']) ?><br><small dir="ltr"><?= e($g['phone'] ?: $g['email']) ?></small></td>
              <td><?= e(number_fa((int)$g['cnt'])) ?></td>
              <td><strong><?= e(money_fa((int)$g['total'])) ?></strong></td>
              <td dir="ltr"><small><?= e($g['sheba'] ?: $g['card_number'] ?: '—') ?></small></td>
              <td>
                <form method="post" onsubmit="return confirm('تسویه همهٔ تراکنش‌های این فعال ثبت شود؟');">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="settle_user">
                  <input type="hidden" name="user_id" value="<?= (int)$g['id'] ?>">
                  <button class="btn btn-primary" style="width:auto;padding:.35rem .7rem;font-size:.8rem" type="submit">✓ تسویه همه</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <div class="card" style="margin-top:1.25rem">
      <h2 style="margin-top:0">خودکارسازی سبک</h2>
      <p class="hint">تراکنش‌های «آماده تسویه» که از N روز پیش پرداخت شده‌اند را یکجا تسویه و اطلاع‌رسانی می‌کند (بعد از اینکه واقعاً واریز کرده‌اید).</p>
      <form method="post" class="otp-row" onsubmit="return confirm('تسویه خودکار انجام شود؟');">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="auto_settle_old">
        <label style="margin:0">قدیمی‌تر از</label>
        <input type="number" name="days" min="1" max="90" value="7" style="width:5rem" class="ltr-field" dir="ltr">
        <span>روز</span>
        <button class="btn btn-ghost" type="submit" style="width:auto">اجرای تسویه خودکار</button>
      </form>
    </div>

    <div class="filter-tabs" style="margin-top:1.5rem">
      <?php foreach (['ready'=>'آماده تسویه','claims'=>'ادعای فعال','refund_pending'=>'بازگشت وجه','settled'=>'تسویه‌شده','refunded'=>'بازگشت‌شده','all'=>'همه'] as $k=>$lab): ?>
        <a class="<?= $filter===$k?'active':'' ?>" href="?s=<?= e($k) ?>"><?= e($lab) ?></a>
      <?php endforeach; ?>
    </div>

    <?php if (!$rows): ?>
      <div class="card"><p class="hint">موردی نیست.</p></div>
    <?php else: ?>
      <form id="batch-form" method="post"></form>
      <div class="card" style="margin-top:.75rem">
        <input type="hidden" form="batch-form" name="csrf" value="<?= e(csrf_token()) ?>">
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.75rem;align-items:center">
          <button form="batch-form" class="btn btn-primary" style="width:auto" name="action" value="settle_batch" type="submit" onclick="return confirm('موارد تیک‌خورده تسویه شوند؟');">✓ تسویه موارد انتخابی</button>
          <button form="batch-form" class="btn btn-ghost" style="width:auto" name="action" value="refund_batch" type="submit">بازگشت موارد انتخابی</button>
          <input form="batch-form" name="note" placeholder="یادداشت تسویه (اختیاری)" style="flex:1;min-width:12rem">
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th><input type="checkbox" id="check-all" title="انتخاب همه"></th>
                <th>فعال</th><th>مبلغ</th><th>کمپین</th><th>کد</th><th>شبا/کارت</th><th>وضعیت</th><th>تاریخ پرداخت</th><th>تیک تسویه</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $d): ?>
              <tr>
                <td>
                  <?php if (in_array($d['settlement_status'], ['ready','refund_pending'], true)): ?>
                    <input form="batch-form" type="checkbox" name="ids[]" value="<?= (int)$d['id'] ?>" class="row-check">
                  <?php endif; ?>
                </td>
                <td><?= e($d['display_name']) ?><br><small dir="ltr"><?= e($d['phone'] ?: '') ?></small></td>
                <td><?= e(money_fa((int)$d['amount'])) ?></td>
                <td><?= e($d['campaign_title'] ?: 'حمایت') ?></td>
                <td dir="ltr"><code><?= e($d['ref_id']) ?></code></td>
                <td dir="ltr"><small><?= e($d['sheba'] ?: $d['card_number'] ?: '—') ?></small></td>
                <td><?= e($d['settlement_status']) ?><?php if (!empty($d['settled_at'])): ?><br><small><?= e(jdate_format($d['settled_at'], 'Y/m/d')) ?></small><?php endif; ?></td>
                <td><?= e(jdate_format($d['paid_at'] ?? $d['created_at'], 'Y/m/d H:i')) ?></td>
                <td>
                  <?php if (($d['settlement_status'] ?? '') === 'none' && ($d['status'] ?? '') === 'paid'): ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('ادعای دریافت تأیید و آماده تسویه شود؟');">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="approve_claim">
                      <input type="hidden" name="donation_id" value="<?= (int)$d['id'] ?>">
                      <button class="btn btn-primary" style="width:auto;padding:.3rem .5rem;font-size:.75rem" type="submit">✓ تأیید ادعا</button>
                    </form>
                    <form method="post" style="display:inline" onsubmit="return confirm('ادعا رد شود؟');">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="reject_claim">
                      <input type="hidden" name="donation_id" value="<?= (int)$d['id'] ?>">
                      <button class="btn btn-ghost" style="width:auto;padding:.3rem .5rem;font-size:.75rem" type="submit">رد</button>
                    </form>
                  <?php elseif (($d['settlement_status'] ?? '') === 'ready'): ?>
                    <form method="post" onsubmit="return confirm('تسویه این مورد؟');">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="settle_one">
                      <input type="hidden" name="donation_id" value="<?= (int)$d['id'] ?>">
                      <button class="btn btn-primary" style="width:auto;padding:.3rem .5rem;font-size:.75rem" type="submit">✓ تسویه</button>
                    </form>
                  <?php elseif (($d['settlement_status'] ?? '') === 'refund_pending'): ?>
                    <form method="post">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="refund_one">
                      <input type="hidden" name="donation_id" value="<?= (int)$d['id'] ?>">
                      <button class="btn btn-ghost" style="width:auto;padding:.3rem .5rem;font-size:.75rem" type="submit">بازگشت</button>
                    </form>
                  <?php else: ?>
                    ✓
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <script>
      (function(){
        var all=document.getElementById('check-all');
        if(!all) return;
        all.addEventListener('change', function(){
          document.querySelectorAll('.row-check').forEach(function(c){ c.checked = all.checked; });
        });
      })();
      </script>
    <?php endif; ?>
  </div>
</section>
<?php layout_footer(); ?>
