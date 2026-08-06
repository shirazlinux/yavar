<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * ارسال ایمیل — SMTP با قالب HTML (لوگو + عنوان ساده).
 */
final class Mail
{
    public static function send(string $to, string $subject, string $bodyText): array
    {
        $cfg = app_config();
        $from = trim((string) ($cfg['mail_from'] ?? 'noreply@sudoshz.ir'));
        if ($from === '') {
            $from = 'noreply@sudoshz.ir';
        }
        $fromName = (string) ($cfg['mail_from_name'] ?? ($cfg['site_name'] ?? 'یاور'));
        $replyTo = trim((string) ($cfg['mail_reply_to'] ?? $from));
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'ایمیل نامعتبر'];
        }

        $host = trim((string) ($cfg['smtp_host'] ?? ''));
        $user = trim((string) ($cfg['smtp_user'] ?? ''));
        $pass = (string) ($cfg['smtp_pass'] ?? '');
        if ($host !== '' && $user !== '' && $pass !== '') {
            try {
                return self::sendSmtp($to, $subject, $bodyText, $from, $fromName, $replyTo, $cfg);
            } catch (Throwable $e) {
                error_log('Mail::sendSmtp failed: ' . $e->getMessage());
                return ['ok' => false, 'message' => 'ارسال SMTP ناموفق: ' . $e->getMessage()];
            }
        }

        if (!function_exists('mail')) {
            error_log('Mail::send: no SMTP config and mail() disabled');
            return ['ok' => false, 'message' => 'ارسال ایمیل پیکربندی نشده است'];
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'From: ' . self::encodeAddress($fromName, $from),
            'Reply-To: ' . $replyTo,
            'X-Mailer: yavar-mail',
        ];
        $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        try {
            $ok = @mail($to, $subj, $bodyText, implode("\r\n", $headers));
        } catch (Throwable $e) {
            error_log('Mail::send exception: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'ارسال ایمیل ناموفق'];
        }
        return $ok ? ['ok' => true] : ['ok' => false, 'message' => 'ارسال ایمیل ناموفق (mail)'];
    }

    private static function sendSmtp(
        string $to,
        string $subject,
        string $bodyText,
        string $from,
        string $fromName,
        string $replyTo,
        array $cfg
    ): array {
        $host = (string) $cfg['smtp_host'];
        $port = (int) ($cfg['smtp_port'] ?? 587);
        $user = (string) $cfg['smtp_user'];
        $pass = (string) $cfg['smtp_pass'];
        $secure = strtolower((string) ($cfg['smtp_secure'] ?? 'tls'));
        $timeout = (int) ($cfg['smtp_timeout'] ?? 25);

        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client(
            $remote . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'peer_name' => $host,
                    'SNI_enabled' => true,
                ],
            ])
        );
        if (!$fp) {
            throw new RuntimeException("اتصال SMTP ناموفق ($errno $errstr)");
        }
        stream_set_timeout($fp, $timeout);

        try {
            self::smtpExpect($fp, [220]);
            self::smtpCmd($fp, 'EHLO donate.sudoshz.ir', [250]);

            if ($secure === 'tls' || $secure === 'starttls') {
                self::smtpCmd($fp, 'STARTTLS', [220]);
                $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
                    throw new RuntimeException('STARTTLS ناموفق');
                }
                self::smtpCmd($fp, 'EHLO donate.sudoshz.ir', [250]);
            }

            self::smtpCmd($fp, 'AUTH LOGIN', [334]);
            self::smtpCmd($fp, base64_encode($user), [334]);
            self::smtpCmd($fp, base64_encode($pass), [235]);

            self::smtpCmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
            self::smtpCmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::smtpCmd($fp, 'DATA', [354]);

            $date = gmdate('D, d M Y H:i:s O');
            $msgId = sprintf('<%s@%s>', bin2hex(random_bytes(12)), 'sudoshz.ir');
            $boundary = 'yavar_' . bin2hex(random_bytes(8));
            $html = self::buildHtml($subject, $bodyText, $cfg);
            // base64 — سازگاری بهتر با Proton و یونیکد فارسی
            $plainB64 = rtrim(chunk_split(base64_encode($bodyText)));
            $htmlB64 = rtrim(chunk_split(base64_encode($html)));

            $headers = [
                'Date: ' . $date,
                'Message-ID: ' . $msgId,
                'From: ' . self::encodeAddress($fromName, $from),
                'To: <' . $to . '>',
                'Reply-To: ' . self::encodeAddress($fromName, $replyTo),
                'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
                'X-Mailer: yavar-mail/2',
                'Auto-Submitted: auto-generated',
            ];

            $bodyMime =
                '--' . $boundary . "\r\n" .
                "Content-Type: text/plain; charset=UTF-8\r\n" .
                "Content-Transfer-Encoding: base64\r\n\r\n" .
                $plainB64 . "\r\n" .
                '--' . $boundary . "\r\n" .
                "Content-Type: text/html; charset=UTF-8\r\n" .
                "Content-Transfer-Encoding: base64\r\n\r\n" .
                $htmlB64 . "\r\n" .
                '--' . $boundary . "--\r\n";

            $payload = implode("\r\n", $headers) . "\r\n\r\n" . $bodyMime;
            // dot-stuffing per SMTP
            $payload = preg_replace('/^\./m', '..', $payload) ?? $payload;
            // send in chunks
            $len = strlen($payload);
            $off = 0;
            while ($off < $len) {
                $n = fwrite($fp, substr($payload, $off, 8192));
                if ($n === false) {
                    throw new RuntimeException('SMTP write failed');
                }
                $off += $n;
            }
            fwrite($fp, "\r\n.\r\n");
            self::smtpExpect($fp, [250]);
            self::smtpCmd($fp, 'QUIT', [221, 250]);
        } finally {
            fclose($fp);
        }

        return ['ok' => true, 'message' => 'ارسال شد'];
    }

    private static function normalizeBody(string $bodyText): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $bodyText);
        return str_replace("\n", "\r\n", $body);
    }

    /** قالب HTML با لوگوی سایت (فعلاً آیکون شیرازلینوکس؛ بعداً لوگو اختصاصی یاور) */
    private static function buildHtml(string $subject, string $bodyText, array $cfg): string
    {
        $site = rtrim((string) ($cfg['site_url'] ?? 'https://donate.sudoshz.ir'), '/');
        $name = htmlspecialchars((string) ($cfg['site_name'] ?? 'یاور'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $logo = htmlspecialchars((string) ($cfg['mail_logo_url'] ?? 'https://sudoshz.ir/media/website/webicon320.png'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subj = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $htmlBody = nl2br(htmlspecialchars($bodyText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $siteEsc = htmlspecialchars($site, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0b1220;font-family:Tahoma,Arial,sans-serif;color:#e8eef9;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#0b1220;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" style="max-width:560px;background:#162033;border:1px solid rgba(148,163,184,.2);border-radius:16px;overflow:hidden;">
        <tr>
          <td style="padding:20px 24px;background:linear-gradient(135deg,rgba(241,89,45,.18),transparent);border-bottom:1px solid rgba(148,163,184,.15);">
            <table role="presentation" cellspacing="0" cellpadding="0"><tr>
              <td style="vertical-align:middle;padding-left:12px;">
                <img src="{$logo}" width="40" height="40" alt="{$name}" style="display:block;border-radius:10px;border:0;">
              </td>
              <td style="vertical-align:middle;">
                <div style="font-size:18px;font-weight:700;color:#fff;">{$name}</div>
                <div style="font-size:12px;color:#94a3b8;">شیرازلینوکس · بدون کارمزد</div>
              </td>
            </tr></table>
          </td>
        </tr>
        <tr>
          <td style="padding:24px;">
            <h1 style="margin:0 0 16px;font-size:18px;font-weight:700;color:#fff;">{$subj}</h1>
            <div style="font-size:15px;line-height:1.85;color:#e8eef9;">{$htmlBody}</div>
          </td>
        </tr>
        <tr>
          <td style="padding:16px 24px 22px;border-top:1px solid rgba(148,163,184,.12);font-size:12px;color:#94a3b8;">
            <a href="{$siteEsc}" style="color:#ff8a5c;text-decoration:none;">{$siteEsc}</a>
            · یاور · نرم‌افزار آزاد
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    /** @param resource $fp */
    private static function smtpCmd($fp, string $cmd, array $okCodes): void
    {
        fwrite($fp, $cmd . "\r\n");
        self::smtpExpect($fp, $okCodes);
    }

    /** @param resource $fp */
    private static function smtpExpect($fp, array $okCodes): string
    {
        $resp = '';
        while (($line = fgets($fp, 515)) !== false) {
            $resp .= $line;
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
            if (!preg_match('/^\d{3}-/', $line) && strlen($line) < 4) {
                break;
            }
        }
        $code = (int) substr($resp, 0, 3);
        if (!in_array($code, $okCodes, true)) {
            throw new RuntimeException('SMTP unexpected: ' . trim($resp));
        }
        return $resp;
    }

    private static function encodeAddress(string $name, string $email): string
    {
        $n = '=?UTF-8?B?' . base64_encode($name) . '?=';
        return $n . ' <' . $email . '>';
    }

    public static function notifyMember(string $email, string $name, string $subject, string $body): void
    {
        if ($email === '') {
            return;
        }
        try {
            $site = (string) (app_config()['site_url'] ?? '');
            self::send($email, $subject, "سلام {$name}\n\n{$body}\n\n— یاور\n{$site}");
        } catch (Throwable $e) {
            error_log('Mail::notifyMember failed: ' . $e->getMessage());
        }
    }
}
