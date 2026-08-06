<?php
declare(strict_types=1);

/**
 * تبدیل تاریخ میلادی به شمسی (جلالی) — بدون وابستگی خارجی
 */
function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
        + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function fa_digits(string $s): string
{
    return strtr($s, [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
    ]);
}

/**
 * @param string|int|null $datetime ISO8601، timestamp، یا تاریخ MySQL/SQLite
 */
function jdate_format($datetime, string $format = 'Y/m/d H:i'): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    if (is_numeric($datetime)) {
        $ts = (int) $datetime;
    } else {
        $ts = strtotime((string) $datetime);
        if ($ts === false) {
            return fa_digits((string) $datetime);
        }
    }
    // نمایش به وقت تهران
    try {
        $dt = new DateTime('@' . $ts);
        $dt->setTimezone(new DateTimeZone('Asia/Tehran'));
    } catch (Throwable $e) {
        $dt = new DateTime('@' . $ts);
    }
    $gy = (int) $dt->format('Y');
    $gm = (int) $dt->format('n');
    $gd = (int) $dt->format('j');
    [$jy, $jm, $jd] = gregorian_to_jalali($gy, $gm, $gd);
    $h = $dt->format('H');
    $i = $dt->format('i');
    $s = $dt->format('s');

    $months = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
        5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
        9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    $out = $format;
    $out = str_replace('Y', (string) $jy, $out);
    $out = str_replace('m', str_pad((string) $jm, 2, '0', STR_PAD_LEFT), $out);
    $out = str_replace('d', str_pad((string) $jd, 2, '0', STR_PAD_LEFT), $out);
    $out = str_replace('F', $months[$jm] ?? '', $out);
    $out = str_replace('H', $h, $out);
    $out = str_replace('i', $i, $out);
    $out = str_replace('s', $s, $out);
    return fa_digits($out);
}

/**
 * تبدیل شمسی به میلادی
 * @return array{0:int,1:int,2:int} [gy,gm,gd]
 */
function jalali_to_gregorian(int $jy, int $jm, int $jd): array
{
    $jy += 1595;
    $days = -355668 + (365 * $jy) + intdiv($jy, 33) * 8 + intdiv(($jy % 33) + 3, 4) + $jd
        + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30 + 186));
    $gy = 400 * intdiv($days, 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * intdiv(--$days, 36524);
        $days %= 36524;
        if ($days >= 365) {
            $days++;
        }
    }
    $gy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal_a = [0, 31, (($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $gm = 0;
    for ($gm = 1; $gm <= 12 && $gd > $sal_a[$gm]; $gm++) {
        $gd -= $sal_a[$gm];
    }
    return [$gy, $gm, $gd];
}

/** ورودی YYYY/MM/DD یا YYYY-MM-DD شمسی → Y-m-d میلادی یا null */
function jalali_input_to_gregorian(?string $input): ?string
{
    $input = trim((string) $input);
    if ($input === '') {
        return null;
    }
    $map = ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'];
    $input = strtr($input, $map);
    if (!preg_match('/^(\d{3,4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $input, $m)) {
        return null;
    }
    $jy = (int) $m[1];
    $jm = (int) $m[2];
    $jd = (int) $m[3];
    if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) {
        return null;
    }
    [$gy, $gm, $gd] = jalali_to_gregorian($jy, $jm, $jd);
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

function gregorian_to_jalali_str(string $ymd): string
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m)) {
        return fa_digits($ymd);
    }
    [$jy, $jm, $jd] = gregorian_to_jalali((int)$m[1], (int)$m[2], (int)$m[3]);
    return fa_digits(sprintf('%04d/%02d/%02d', $jy, $jm, $jd));
}
