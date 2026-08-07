<?php
declare(strict_types=1);
/**
 * API داخلی برای worker خارج (تلگرام).
 * فقط با هدر:
 *   Authorization: Bearer <telegram_worker_secret>
 *   یا X-Yavar-Secret: <secret>
 *
 * GET  ?action=pull
 * POST action=ack|link|ping
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/auth.php';
require_once dirname(__DIR__, 2) . '/lib/Telegram.php';
require_once dirname(__DIR__, 2) . '/lib/RateLimit.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

// rate limit brute-force روی این endpoint
$rl = RateLimit::hit('tg_internal', 120, 60);
if (!$rl['ok']) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'message' => 'rate limit'], JSON_UNESCAPED_UNICODE);
    exit;
}

$auth = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';
$alt = isset($_SERVER['HTTP_X_YAVAR_SECRET']) ? (string) $_SERVER['HTTP_X_YAVAR_SECRET'] : null;

if (!Telegram::workerIpAllowed() || !Telegram::verifyWorkerAuth($auth, $alt)) {
    security_log('tg_worker_auth_fail', RateLimit::clientIp());
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? '');

if ($method === 'GET' && ($action === 'pull' || $action === '')) {
    $items = Telegram::pullOutbox(25);
    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    // سقف بدنه
    if (strlen($raw) > 20000) {
        http_response_code(413);
        echo json_encode(['ok' => false, 'message' => 'payload too large'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $in = json_decode($raw, true);
    if (!is_array($in)) {
        $in = $_POST;
    }
    $action = (string) ($in['action'] ?? $action);

    if ($action === 'ping') {
        echo json_encode([
            'ok' => true,
            'ts' => gmdate('c'),
            'bot' => Telegram::botUsername(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'ack') {
        $id = (int) ($in['id'] ?? 0);
        $ok = !empty($in['ok']);
        $err = Telegram::sanitizeText((string) ($in['error'] ?? ''), 500);
        if ($id < 1) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'id'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        Telegram::ackOutbox($id, $ok, $err);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'link') {
        $token = (string) ($in['token'] ?? '');
        $chatId = (string) ($in['chat_id'] ?? '');
        $chatType = (string) ($in['chat_type'] ?? 'group');
        $chatTitle = (string) ($in['chat_title'] ?? '');
        $res = Telegram::completeLink($token, $chatId, $chatType, $chatTitle);
        if (empty($res['ok'])) {
            security_log('tg_link_fail', mb_substr($chatId, 0, 40));
        }
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

http_response_code(400);
echo json_encode(['ok' => false, 'message' => 'bad request'], JSON_UNESCAPED_UNICODE);
