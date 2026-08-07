<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $cfg = app_config();
    $path = $cfg['db_path'] ?? (dirname(__DIR__) . '/data/app.sqlite');
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    db_migrate($pdo);
    @chmod($path, 0640);
    return $pdo;
}

function db_migrate(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  display_name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  bio TEXT NOT NULL DEFAULT '',
  activity TEXT NOT NULL DEFAULT '',
  card_number TEXT NOT NULL DEFAULT '',
  sheba TEXT NOT NULL DEFAULT '',
  phone TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'pending',
  is_admin INTEGER NOT NULL DEFAULT 0,
  policy_accepted_at TEXT NOT NULL,
  reject_reason TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS donations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  amount INTEGER NOT NULL,
  donor_name TEXT NOT NULL DEFAULT '',
  message TEXT NOT NULL DEFAULT '',
  authority TEXT NOT NULL DEFAULT '',
  ref_id TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'pending',
  gateway TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL,
  paid_at TEXT,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS campaigns (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  title TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  description TEXT NOT NULL DEFAULT '',
  goal_amount INTEGER NOT NULL,
  deadline TEXT NOT NULL,
  tiers_json TEXT NOT NULL DEFAULT '[]',
  status TEXT NOT NULL DEFAULT 'draft',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  closed_at TEXT,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
CREATE INDEX IF NOT EXISTS idx_users_slug ON users(slug);
CREATE INDEX IF NOT EXISTS idx_donations_user ON donations(user_id);
CREATE INDEX IF NOT EXISTS idx_campaigns_user ON campaigns(user_id);
CREATE INDEX IF NOT EXISTS idx_campaigns_status ON campaigns(status);
CREATE TABLE IF NOT EXISTS supporter_likes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  target_type TEXT NOT NULL,
  target_id INTEGER NOT NULL,
  created_at TEXT NOT NULL,
  UNIQUE(user_id, target_type, target_id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS monthly_pledges (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  target_type TEXT NOT NULL,
  target_id INTEGER NOT NULL,
  amount INTEGER NOT NULL DEFAULT 0,
  active INTEGER NOT NULL DEFAULT 1,
  updated_at TEXT NOT NULL,
  UNIQUE(user_id, target_type, target_id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS feed_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kind TEXT NOT NULL,
  ref_type TEXT NOT NULL DEFAULT '',
  ref_id INTEGER NOT NULL DEFAULT 0,
  title TEXT NOT NULL DEFAULT '',
  body TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS password_resets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  token_hash TEXT NOT NULL UNIQUE,
  expires_at TEXT NOT NULL,
  used_at TEXT,
  created_at TEXT NOT NULL,
  request_ip TEXT NOT NULL DEFAULT '',
  FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE INDEX IF NOT EXISTS idx_password_resets_user ON password_resets(user_id);
CREATE INDEX IF NOT EXISTS idx_password_resets_exp ON password_resets(expires_at);
CREATE TABLE IF NOT EXISTS rate_limits (
  rate_key TEXT PRIMARY KEY,
  window_start INTEGER NOT NULL,
  hit_count INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS security_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kind TEXT NOT NULL,
  ip TEXT NOT NULL DEFAULT '',
  detail TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS telegram_links (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL UNIQUE,
  chat_id TEXT NOT NULL DEFAULT '',
  chat_type TEXT NOT NULL DEFAULT '',
  chat_title TEXT NOT NULL DEFAULT '',
  connect_token_hash TEXT NOT NULL DEFAULT '',
  connect_expires_at TEXT,
  enabled INTEGER NOT NULL DEFAULT 0,
  linked_at TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS telegram_outbox (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  chat_id TEXT NOT NULL,
  text TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  tries INTEGER NOT NULL DEFAULT 0,
  last_error TEXT NOT NULL DEFAULT '',
  donation_id INTEGER,
  created_at TEXT NOT NULL,
  sent_at TEXT,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE INDEX IF NOT EXISTS idx_tg_outbox_status ON telegram_outbox(status, id);
CREATE INDEX IF NOT EXISTS idx_tg_links_token ON telegram_links(connect_token_hash);
SQL);

    // ستون‌های جدا برای PV / گروه + متن CTA قابل تنظیم
    try {
        $tgCols = $pdo->query('PRAGMA table_info(telegram_links)')->fetchAll();
        $tgNames = array_column($tgCols, 'name');
        foreach ([
            'private_chat_id' => "TEXT NOT NULL DEFAULT ''",
            'group_chat_id' => "TEXT NOT NULL DEFAULT ''",
            'group_chat_type' => "TEXT NOT NULL DEFAULT ''",
            'group_chat_title' => "TEXT NOT NULL DEFAULT ''",
            'announce_cta' => "TEXT NOT NULL DEFAULT ''",
            'notify_private' => 'INTEGER NOT NULL DEFAULT 1',
        ] as $col => $def) {
            if (!in_array($col, $tgNames, true)) {
                $pdo->exec("ALTER TABLE telegram_links ADD COLUMN {$col} {$def}");
            }
        }
        // مهاجرت از chat_id قدیمی
        $pdo->exec(
            "UPDATE telegram_links SET group_chat_id = chat_id, group_chat_type = chat_type, group_chat_title = chat_title
             WHERE chat_id <> '' AND chat_type IN ('group','supergroup','channel')
               AND (group_chat_id IS NULL OR group_chat_id = '')"
        );
        $pdo->exec(
            "UPDATE telegram_links SET private_chat_id = chat_id
             WHERE chat_id <> '' AND chat_type = 'private'
               AND (private_chat_id IS NULL OR private_chat_id = '')"
        );
    } catch (Throwable $e) {
        error_log('tg migrate: ' . $e->getMessage());
    }

    $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll();
    $names = array_column($cols, 'name');
    if (!in_array('phone', $names, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone TEXT NOT NULL DEFAULT ''");
    }
    foreach ([
        'git_url' => "TEXT NOT NULL DEFAULT ''",
        'website_url' => "TEXT NOT NULL DEFAULT ''",
        'public_contact' => "TEXT NOT NULL DEFAULT ''",
        'services' => "TEXT NOT NULL DEFAULT ''",
        'avatar' => "TEXT NOT NULL DEFAULT ''",
        'display_mode' => "TEXT NOT NULL DEFAULT 'personal'",
        'platform_name' => "TEXT NOT NULL DEFAULT ''",
        'role' => "TEXT NOT NULL DEFAULT 'hamyar'",
        'monthly_budget' => "INTEGER NOT NULL DEFAULT 0",
        'feed_enabled' => "INTEGER NOT NULL DEFAULT 1",
        'social_json' => "TEXT NOT NULL DEFAULT '{}'",
        'show_public_donations' => "INTEGER NOT NULL DEFAULT 0",
    ] as $col => $def) {
        if (!in_array($col, $names, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN {$col} {$def}");
            $names[] = $col;
        }
    }

    $dcols = $pdo->query('PRAGMA table_info(donations)')->fetchAll();
    $dnames = array_column($dcols, 'name');
    if (!in_array('campaign_id', $dnames, true)) {
        $pdo->exec('ALTER TABLE donations ADD COLUMN campaign_id INTEGER');
    }
    if (!in_array('settlement_status', $dnames, true)) {
        $pdo->exec("ALTER TABLE donations ADD COLUMN settlement_status TEXT NOT NULL DEFAULT 'none'");
    }
    if (!in_array('is_public_post', $dnames, true)) {
        $pdo->exec("ALTER TABLE donations ADD COLUMN is_public_post INTEGER NOT NULL DEFAULT 0");
    }
    if (!in_array('is_anonymous', $dnames, true)) {
        $pdo->exec("ALTER TABLE donations ADD COLUMN is_anonymous INTEGER NOT NULL DEFAULT 0");
    }
    if (!in_array('settled_at', $dnames, true)) {
        $pdo->exec("ALTER TABLE donations ADD COLUMN settled_at TEXT");
    }
    if (!in_array('settlement_note', $dnames, true)) {
        $pdo->exec("ALTER TABLE donations ADD COLUMN settlement_note TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('payment_ref', $dnames, true)) {
        $pdo->exec("ALTER TABLE donations ADD COLUMN payment_ref TEXT NOT NULL DEFAULT ''");
    }
    // unique-ish index for payment replay protection (empty allowed multiple times in SQLite unless filtered)
    try {
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_donations_payment_ref_nz ON donations(payment_ref) WHERE payment_ref <> \'\'');
    } catch (Throwable $e) {
        // ignore if unsupported
    }

    $cfg = app_config();
    $adminEmail = strtolower(trim((string) ($cfg['admin_email'] ?? '')));
    if ($adminEmail === '') {
        return;
    }
    $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $st->execute([$adminEmail]);
    if ($st->fetch()) {
        return;
    }
    $hash = (string) ($cfg['admin_password_hash'] ?? '');
    if ($hash === '') {
        return;
    }
    $now = gmdate('c');
    $ins = $pdo->prepare('INSERT INTO users (email, password_hash, display_name, slug, bio, activity, phone, status, is_admin, policy_accepted_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $ins->execute([
        $adminEmail, $hash, 'مدیر صندوق', 'admin',
        'حساب مدیریت پلتفرم', 'ادمین', '', 'approved', 1, $now, $now, $now,
    ]);
}
