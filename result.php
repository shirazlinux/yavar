<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$cfg = app_config();
$ok = ($_GET['ok'] ?? '') === '1';
$ref = htmlspecialchars($_GET['ref'] ?? '', ENT_QUOTES, 'UTF-8');
$amount = (int) ($_GET['amount'] ?? 0);
$msg = htmlspecialchars($_GET['msg'] ?? '', ENT_QUOTES, 'UTF-8');
$title = $ok ? 'پرداخت موفق' : 'پرداخت ناموفق';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $title ?> — <?= htmlspecialchars($cfg['site_name'], ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="/assets/css/style.css?v=50">
  <meta name="robots" content="noindex">
</head>
<body>
  <div class="result-wrap">
    <div class="result-card <?= $ok ? 'ok' : 'fail' ?>">
      <div class="result-icon"><?= $ok ? '✓' : '!' ?></div>
      <h1><?= $title ?></h1>
      <?php if ($msg): ?><p class="lead"><?= $msg ?></p><?php endif; ?>
      <?php if ($ok && $amount): ?>
        <p>مبلغ: <strong><?= number_format($amount) ?></strong> <?= htmlspecialchars($cfg['currency_label'], ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
      <?php if ($ok && $ref): ?>
        <p class="ref">کد پیگیری: <code><?= $ref ?></code></p>
      <?php endif; ?>
      <div class="result-actions">
        <a class="btn btn-primary" href="/">بازگشت به یاور</a>
        <a class="btn btn-ghost" href="<?= htmlspecialchars($cfg['community_url'], ENT_QUOTES, 'UTF-8') ?>">سایت جامعه</a>
      </div>
    </div>
  </div>
</body>
</html>
