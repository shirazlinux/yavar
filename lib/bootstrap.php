<?php
declare(strict_types=1);

function app_settings_path(): string
{
    return dirname(__DIR__) . '/data/settings.json';
}

function app_config(): array
{
    static $cfg;
    if ($cfg === null) {
        $path = dirname(__DIR__) . '/config.php';
        if (!is_file($path)) {
            http_response_code(500);
            exit('config.php missing');
        }
        $cfg = require $path;
        $sp = app_settings_path();
        if (is_file($sp)) {
            $over = json_decode((string) file_get_contents($sp), true);
            if (is_array($over)) {
                foreach ($over as $k => $v) {
                    if (is_string($k) && $v !== null && $v !== '') {
                        $cfg[$k] = $v;
                    }
                }
            }
        }
    }
    return $cfg;
}

/** بازنشانی کش config بعد از ذخیره settings */
function app_config_reload(): array
{
    // reset static by re-reading — use a trick via $GLOBALS
    $path = dirname(__DIR__) . '/config.php';
    $cfg = require $path;
    $sp = app_settings_path();
    if (is_file($sp)) {
        $over = json_decode((string) file_get_contents($sp), true);
        if (is_array($over)) {
            foreach ($over as $k => $v) {
                if (is_string($k) && $v !== null && $v !== '') {
                    $cfg[$k] = $v;
                }
            }
        }
    }
    return $cfg;
}

function app_settings_save(array $patch): void
{
    $sp = app_settings_path();
    $dir = dirname($sp);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $cur = [];
    if (is_file($sp)) {
        $cur = json_decode((string) file_get_contents($sp), true) ?: [];
    }
    foreach ($patch as $k => $v) {
        if ($v === null || $v === '') {
            unset($cur[$k]);
        } else {
            $cur[$k] = $v;
        }
    }
    file_put_contents($sp, json_encode($cur, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    @chmod($sp, 0600);
}

function app_json(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function app_log_donation(array $row): void
{
    $cfg = app_config();
    $file = $cfg['log_file'];
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $row['logged_at'] = gmdate('c');
    @file_put_contents($file, json_encode($row, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    @chmod($file, 0640);
}

function app_sanitize_name(?string $s): string
{
    $s = trim((string) $s);
    $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '';
    return mb_substr($s, 0, 80);
}

function app_sanitize_message(?string $s): string
{
    $s = trim((string) $s);
    $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '';
    return mb_substr($s, 0, 500);
}

function app_amount_valid(int $amount): bool
{
    $cfg = app_config();
    return $amount >= (int) $cfg['min_amount'] && $amount <= (int) $cfg['max_amount'];
}

function app_http_json(string $url, array $payload, array $headers = [], int $timeout = 30): array
{
    $ch = curl_init($url);
    $hdrs = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $hdrs,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) {
        return ['ok' => false, 'http' => $code, 'error' => $err, 'data' => null];
    }
    $data = json_decode($body, true);
    return ['ok' => true, 'http' => $code, 'error' => null, 'data' => $data, 'raw' => $body];
}
