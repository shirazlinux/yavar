<?php
declare(strict_types=1);

/**
 * شفافیت مالی — هزینه‌های اعلام‌شده توسط صاحب صفحه.
 */
final class Transparency
{
    public static function userEnabled(?array $user): bool
    {
        return is_array($user) && !empty($user['show_transparency']);
    }

    /** @return list<array<string,mixed>> */
    public static function listForUser(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }
        $st = db()->prepare(
            'SELECT id, user_id, title, amount, spent_on, note, created_at, updated_at
             FROM spend_entries WHERE user_id=? ORDER BY spent_on DESC, id DESC'
        );
        $st->execute([$userId]);
        return $st->fetchAll() ?: [];
    }

    public static function spentSum(int $userId): int
    {
        if ($userId < 1) {
            return 0;
        }
        $st = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM spend_entries WHERE user_id=?');
        $st->execute([$userId]);
        return (int) $st->fetchColumn();
    }

    public static function receivedSum(int $userId): int
    {
        $tot = user_total_paid($userId);
        return (int) ($tot['sum'] ?? 0);
    }

    public static function formatSpentOn(string $spentOn): string
    {
        $spentOn = trim($spentOn);
        if ($spentOn === '') {
            return '—';
        }
        // تاریخ روز (Y-m-d میلادی ذخیره‌شده) → نمایش شمسی
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $spentOn)) {
            return gregorian_to_jalali_str($spentOn);
        }
        return jdate_format($spentOn, 'Y/m/d');
    }

    /**
     * رندر HTML بخش عمومی.
     * @param list<array<string,mixed>> $entries
     */
    public static function renderPublicPanel(
        int $receivedSum,
        int $spentSum,
        array $entries,
        string $note = ''
    ): string {
        $remaining = $receivedSum - $spentSum;
        $remainClass = $remaining < 0 ? ' is-negative' : '';
        $note = trim($note);
        $noteHtml = $note !== '' ? '<p class="transparency-note">' . e($note) . '</p>' : '';

        $rows = '';
        if (!$entries) {
            $rows = '<tr><td colspan="3"><p class="hint" style="margin:0">هنوز مورد هزینه‌ای ثبت نشده است.</p></td></tr>';
        } else {
            foreach ($entries as $row) {
                $title = e((string) ($row['title'] ?? ''));
                $amount = e(money_fa((int) ($row['amount'] ?? 0)));
                $dateLabel = e(self::formatSpentOn((string) ($row['spent_on'] ?? '')));
                $noteRow = trim((string) ($row['note'] ?? ''));
                $noteLine = $noteRow !== ''
                    ? '<div class="hint" style="margin:.2rem 0 0">' . e($noteRow) . '</div>'
                    : '';
                $rows .= '<tr>'
                    . '<td data-label="مورد هزینه"><div class="transparency-title-cell">' . $title . '</div>' . $noteLine . '</td>'
                    . '<td data-label="تاریخ" dir="ltr">' . $dateLabel . '</td>'
                    . '<td data-label="مبلغ">' . $amount . '</td>'
                    . '</tr>';
            }
        }

        $receivedFa = e(money_fa($receivedSum));
        $spentFa = e(money_fa($spentSum));
        $remainingFa = e(money_fa($remaining));

        return <<<HTML
<section class="transparency-panel card" id="transparency" style="box-shadow:none;margin:1.25rem 0" aria-labelledby="transparency-title">
  <h2 id="transparency-title" style="margin:0 0 .35rem;font-size:1.15rem">شفافیت مالی</h2>
  <p class="hint" style="margin:0 0 .85rem">
    صاحب این صفحه اعلام کرده حمایت‌های دریافتی صرف چه مواردی شده است.
    ارقام هزینه اظهار خودِ فعال است؛ یاور صحت هزینه‌ها را تضمین نمی‌کند.
  </p>
  {$noteHtml}
  <div class="transparency-summary">
    <div class="transparency-summary__item">
      <span class="transparency-summary__label">جمع حمایت تأییدشده</span>
      <strong class="transparency-summary__value">{$receivedFa}</strong>
    </div>
    <div class="transparency-summary__item">
      <span class="transparency-summary__label">جمع هزینه‌های ثبت‌شده</span>
      <strong class="transparency-summary__value">{$spentFa}</strong>
    </div>
    <div class="transparency-summary__item transparency-summary__item--remain{$remainClass}">
      <span class="transparency-summary__label">باقی‌مانده</span>
      <strong class="transparency-summary__value">{$remainingFa}</strong>
    </div>
  </div>
  <div class="table-wrap transparency-table-wrap">
    <table class="transparency-table">
      <thead>
        <tr>
          <th scope="col">مورد هزینه</th>
          <th scope="col">تاریخ</th>
          <th scope="col">مبلغ</th>
        </tr>
      </thead>
      <tbody>
        {$rows}
      </tbody>
    </table>
  </div>
</section>
HTML;
    }
}
