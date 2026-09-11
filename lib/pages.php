<?php
declare(strict_types=1);

require_once __DIR__ . '/profile.php';

const PAGES_MAX_ACTIVE = 10;

function page_normalize_kind(?string $kind): string
{
    $kind = (string) $kind;
    if ($kind === 'platform') {
        return 'project';
    }
    return in_array($kind, ['personal', 'project', 'community'], true) ? $kind : 'personal';
}

function page_kind_to_display_mode(?string $kind): string
{
    return match (page_normalize_kind($kind)) {
        'project' => 'platform',
        'community' => 'community',
        default => 'personal',
    };
}

function display_mode_to_page_kind(?string $mode): string
{
    return match (normalize_display_mode($mode)) {
        'platform' => 'project',
        'community' => 'community',
        default => 'personal',
    };
}

function page_kind_label(?string $kind): string
{
    return match (page_normalize_kind($kind)) {
        'project' => 'پروژه',
        'community' => 'جامعه',
        default => 'فعال',
    };
}

function page_status_label(?string $status): string
{
    return match ((string) $status) {
        'approved' => 'تأییدشده',
        'rejected' => 'ردشده',
        'archived' => 'آرشیو',
        'draft' => 'پیش‌نویس',
        default => 'در انتظار تأیید',
    };
}

function page_title_from_user(array $u): string
{
    $kind = display_mode_to_page_kind($u['display_mode'] ?? 'personal');
    if ($kind !== 'personal') {
        $p = trim((string) ($u['platform_name'] ?? ''));
        if ($p !== '') {
            return mb_substr($p, 0, 80);
        }
    }
    $n = trim((string) ($u['display_name'] ?? ''));
    return $n !== '' ? mb_substr($n, 0, 80) : 'صفحه حمایت';
}

function pages_active_count(int $userId): int
{
    $st = db()->prepare("SELECT COUNT(*) FROM pages WHERE user_id=? AND status <> 'archived'");
    $st->execute([$userId]);
    return (int) $st->fetchColumn();
}

function page_primary(int $userId): ?array
{
    $st = db()->prepare('SELECT * FROM pages WHERE user_id=? AND is_primary=1 LIMIT 1');
    $st->execute([$userId]);
    $row = $st->fetch();
    return $row ?: null;
}

function page_by_id(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM pages WHERE id=?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function page_owned(int $id, int $userId): ?array
{
    $p = page_by_id($id);
    if (!$p || (int) $p['user_id'] !== $userId) {
        return null;
    }
    return $p;
}

/** @return list<array<string,mixed>> */
function pages_list_for_user(int $userId, bool $includeArchived = false): array
{
    $sql = 'SELECT * FROM pages WHERE user_id=?';
    if (!$includeArchived) {
        $sql .= " AND status <> 'archived'";
    }
    $sql .= ' ORDER BY is_primary DESC, id ASC';
    $st = db()->prepare($sql);
    $st->execute([$userId]);
    return $st->fetchAll();
}

function page_find_public_by_slug(string $slug): ?array
{
    $slug = slugify($slug);
    if ($slug === '') {
        return null;
    }
    $st = db()->prepare(
        "SELECT p.*, u.status AS owner_status, u.is_admin AS owner_is_admin,
                u.display_name AS owner_display_name, u.slug AS owner_slug, u.id AS owner_id
         FROM pages p
         JOIN users u ON u.id = p.user_id
         WHERE p.slug = ? AND p.status = 'approved' AND u.status = 'approved'
           AND u.is_admin = 0"
    );
    $st->execute([$slug]);
    $row = $st->fetch();
    return $row ?: null;
}

/**
 * فهرست عمومی صفحات تأییدشده.
 * $view: all | hamyar | platform | community
 * @return list<array<string,mixed>>
 */
function pages_list_public(string $view = 'all', int $limit = 60): array
{
    $limit = max(1, min(120, $limit));
    $sql = "SELECT p.*, u.display_name AS owner_display_name, u.slug AS owner_slug
            FROM pages p
            JOIN users u ON u.id = p.user_id
            WHERE p.status='approved' AND u.status='approved' AND u.is_admin=0
              AND COALESCE(u.role,'hamyar')='hamyar'";
    if ($view === 'hamyar') {
        $sql .= " AND p.kind='personal'";
    } elseif ($view === 'platform') {
        $sql .= " AND p.kind='project'";
    } elseif ($view === 'community') {
        $sql .= " AND p.kind='community'";
    }
    $sql .= " ORDER BY COALESCE(p.page_views, 0) DESC, p.updated_at DESC LIMIT {$limit}";
    return db()->query($sql)->fetchAll();
}

function page_total_paid(int $pageId): array
{
    $st = db()->prepare(
        "SELECT COALESCE(SUM(amount),0) s, COUNT(*) c FROM donations
         WHERE page_id=? AND status=? AND COALESCE(is_external,0)=0"
    );
    $st->execute([$pageId, 'paid']);
    $r = $st->fetch() ?: ['s' => 0, 'c' => 0];
    return ['sum' => (int) $r['s'], 'count' => (int) $r['c']];
}

/**
 * صفحه + صاحب → آرایهٔ سازگار با helperهای فعلی پروفایل /u/
 */
function page_to_view_user(array $page, array $owner): array
{
    $kind = page_normalize_kind($page['kind'] ?? 'personal');
    $mode = page_kind_to_display_mode($kind);
    $u = $owner;
    $u['page_id'] = (int) $page['id'];
    $u['page_kind'] = $kind;
    $u['page_is_primary'] = !empty($page['is_primary']);
    $u['display_mode'] = $mode;
    $u['slug'] = (string) $page['slug'];
    $u['bio'] = (string) ($page['bio'] ?? '');
    $u['activity'] = (string) ($page['activity'] ?? '');
    $u['services'] = (string) ($page['services'] ?? '');
    $u['git_url'] = (string) ($page['git_url'] ?? '');
    $u['website_url'] = (string) ($page['website_url'] ?? '');
    $u['project_links'] = (string) ($page['project_links'] ?? '[]');
    $u['public_contact'] = (string) ($page['public_contact'] ?? '');
    $u['social_json'] = (string) ($page['social_json'] ?? '{}');
    $u['avatar'] = (string) ($page['avatar'] ?? '');
    $u['show_public_donations'] = $page['show_public_donations'] ?? 0;
    $u['show_page_views'] = $page['show_page_views'] ?? 1;
    $u['page_views'] = $page['page_views'] ?? 0;
    $u['public_donations_limit'] = $page['public_donations_limit'] ?? 10;
    $u['public_donations_sort'] = $page['public_donations_sort'] ?? 'newest';
    $u['show_transparency'] = $page['show_transparency'] ?? 0;
    $u['transparency_note'] = $page['transparency_note'] ?? '';
    $u['owner_display_name'] = (string) ($owner['display_name'] ?? '');
    $u['owner_slug'] = (string) ($owner['slug'] ?? '');
    $title = trim((string) ($page['title'] ?? ''));
    if ($kind === 'personal') {
        $u['display_name'] = $title !== '' ? $title : (string) ($owner['display_name'] ?? '');
        $u['platform_name'] = '';
    } else {
        $u['platform_name'] = $title;
    }
    return $u;
}

function unique_page_slug(string $base, ?int $ignorePageId = null, ?int $ignoreUserId = null): string
{
    $slug = slugify($base);
    $i = 0;
    while (true) {
        $try = $i === 0 ? $slug : $slug . '-' . $i;
        if (!slug_is_taken($try, $ignoreUserId, $ignorePageId)) {
            return $try;
        }
        $i++;
        if ($i > 50) {
            return $slug . '-' . bin2hex(random_bytes(2));
        }
    }
}

function page_row_from_user(array $u, bool $primary = true): array
{
    $kind = display_mode_to_page_kind($u['display_mode'] ?? 'personal');
    $now = gmdate('c');
    return [
        'user_id' => (int) $u['id'],
        'kind' => $kind,
        'slug' => (string) $u['slug'],
        'title' => page_title_from_user($u),
        'bio' => (string) ($u['bio'] ?? ''),
        'activity' => (string) ($u['activity'] ?? ''),
        'services' => (string) ($u['services'] ?? ''),
        'git_url' => (string) ($u['git_url'] ?? ''),
        'website_url' => (string) ($u['website_url'] ?? ''),
        'project_links' => (string) ($u['project_links'] ?? '[]'),
        'public_contact' => (string) ($u['public_contact'] ?? ''),
        'social_json' => (string) ($u['social_json'] ?? '{}'),
        'avatar' => (string) ($u['avatar'] ?? ''),
        'status' => in_array((string) ($u['status'] ?? ''), ['approved', 'rejected', 'pending'], true)
            ? (string) $u['status'] : 'pending',
        'reject_reason' => (string) ($u['reject_reason'] ?? ''),
        'is_primary' => $primary ? 1 : 0,
        'show_public_donations' => (int) ($u['show_public_donations'] ?? 0),
        'show_page_views' => array_key_exists('show_page_views', $u) ? (int) $u['show_page_views'] : 1,
        'page_views' => (int) ($u['page_views'] ?? 0),
        'public_donations_limit' => (int) ($u['public_donations_limit'] ?? 10),
        'public_donations_sort' => (string) ($u['public_donations_sort'] ?? 'newest'),
        'show_transparency' => (int) ($u['show_transparency'] ?? 0),
        'transparency_note' => (string) ($u['transparency_note'] ?? ''),
        'created_at' => (string) ($u['created_at'] ?? $now),
        'updated_at' => (string) ($u['updated_at'] ?? $now),
    ];
}

function page_insert_row(array $row): int
{
    $st = db()->prepare(
        'INSERT INTO pages (
            user_id, kind, slug, title, bio, activity, services, git_url, website_url, project_links,
            public_contact, social_json, avatar, status, reject_reason, is_primary,
            show_public_donations, show_page_views, page_views, public_donations_limit,
            public_donations_sort, show_transparency, transparency_note, created_at, updated_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $st->execute([
        (int) $row['user_id'], $row['kind'], $row['slug'], $row['title'], $row['bio'], $row['activity'],
        $row['services'], $row['git_url'], $row['website_url'], $row['project_links'],
        $row['public_contact'], $row['social_json'], $row['avatar'], $row['status'],
        $row['reject_reason'], (int) $row['is_primary'],
        (int) $row['show_public_donations'], (int) $row['show_page_views'], (int) $row['page_views'],
        (int) $row['public_donations_limit'], $row['public_donations_sort'],
        (int) $row['show_transparency'], $row['transparency_note'],
        $row['created_at'], $row['updated_at'],
    ]);
    return (int) db()->lastInsertId();
}

function page_create_primary_from_user(array $u): array
{
    $existing = page_primary((int) $u['id']);
    if ($existing) {
        return $existing;
    }
    $id = page_insert_row(page_row_from_user($u, true));
    $row = page_by_id($id);
    return $row ?: page_row_from_user($u, true);
}

function page_sync_primary_from_user(array $u): void
{
    $p = page_primary((int) $u['id']);
    $row = page_row_from_user($u, true);
    if (!$p) {
        page_insert_row($row);
        return;
    }
    $st = db()->prepare(
        'UPDATE pages SET kind=?, slug=?, title=?, bio=?, activity=?, services=?, git_url=?, website_url=?,
         project_links=?, public_contact=?, social_json=?, avatar=?,
         show_public_donations=?, show_page_views=?, public_donations_limit=?, public_donations_sort=?,
         show_transparency=?, transparency_note=?, updated_at=?
         WHERE id=? AND user_id=? AND is_primary=1'
    );
    $st->execute([
        $row['kind'], $row['slug'], $row['title'], $row['bio'], $row['activity'], $row['services'],
        $row['git_url'], $row['website_url'], $row['project_links'], $row['public_contact'],
        $row['social_json'], $row['avatar'], $row['show_public_donations'], $row['show_page_views'],
        $row['public_donations_limit'], $row['public_donations_sort'], $row['show_transparency'],
        $row['transparency_note'], gmdate('c'), (int) $p['id'], (int) $u['id'],
    ]);
}

/**
 * @param array<string,mixed> $data
 * @return array{ok:bool,message?:string,page?:array<string,mixed>}
 */
function page_create_extra(array $user, array $data): array
{
    $userId = (int) $user['id'];
    if (($user['role'] ?? 'hamyar') === 'supporter' && empty($user['is_admin'])) {
        return ['ok' => false, 'message' => 'ابتدا صفحه حمایت را فعال کنید.'];
    }
    if (pages_active_count($userId) >= PAGES_MAX_ACTIVE) {
        return ['ok' => false, 'message' => 'حداکثر ' . PAGES_MAX_ACTIVE . ' صفحه برای هر حساب مجاز است.'];
    }
    $kind = page_normalize_kind($data['kind'] ?? 'project');
    $title = trim((string) ($data['title'] ?? ''));
    $slugIn = trim((string) ($data['slug'] ?? ''));
    $bio = trim((string) ($data['bio'] ?? ''));
    $activity = trim((string) ($data['activity'] ?? ''));
    $services = trim((string) ($data['services'] ?? ''));
    $gitUrl = normalize_url_field((string) ($data['git_url'] ?? ''));
    $websiteUrl = normalize_url_field((string) ($data['website_url'] ?? ''));
    $projectLinks = (string) ($data['project_links'] ?? '[]');
    $avatar = (string) ($data['avatar'] ?? ($user['avatar'] ?? 'preset:tux'));

    if (mb_strlen($title) < 2 || mb_strlen($title) > 80) {
        return ['ok' => false, 'message' => 'نام صفحه بین ۲ تا ۸۰ کاراکتر باشد.'];
    }
    if (mb_strlen($activity) < 20) {
        return ['ok' => false, 'message' => 'توضیح فعالیت حداقل ۲۰ کاراکتر باشد.'];
    }
    if (mb_strlen($services) < 10) {
        return ['ok' => false, 'message' => 'کارهایی که حمایت می‌پذیرید را بنویسید.'];
    }
    $meta = presence_link_meta(page_kind_to_display_mode($kind));
    $primaryPresence = $meta['store'] === 'git_url' ? $gitUrl : $websiteUrl;
    if ($primaryPresence === '') {
        return ['ok' => false, 'message' => $meta['label'] . ' را وارد کنید.'];
    }
    $now = gmdate('c');
    $slug = unique_page_slug($slugIn !== '' ? $slugIn : $title);
    $id = page_insert_row([
        'user_id' => $userId,
        'kind' => $kind,
        'slug' => $slug,
        'title' => mb_substr($title, 0, 80),
        'bio' => $bio,
        'activity' => $activity,
        'services' => $services,
        'git_url' => $gitUrl,
        'website_url' => $websiteUrl,
        'project_links' => $projectLinks,
        'public_contact' => trim((string) ($data['public_contact'] ?? '')),
        'social_json' => (string) ($data['social_json'] ?? '{}'),
        'avatar' => $avatar,
        'status' => 'pending',
        'reject_reason' => '',
        'is_primary' => 0,
        'show_public_donations' => !empty($data['show_public_donations']) ? 1 : 0,
        'show_page_views' => array_key_exists('show_page_views', $data) ? (int) !empty($data['show_page_views']) : 1,
        'page_views' => 0,
        'public_donations_limit' => 10,
        'public_donations_sort' => 'newest',
        'show_transparency' => 0,
        'transparency_note' => '',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $page = page_by_id($id);
    return $page ? ['ok' => true, 'page' => $page] : ['ok' => false, 'message' => 'ساخت صفحه ناموفق بود.'];
}

function page_update_owned(int $pageId, int $userId, array $fields): bool
{
    $p = page_owned($pageId, $userId);
    if (!$p) {
        return false;
    }
    $allowed = [
        'kind', 'slug', 'title', 'bio', 'activity', 'services', 'git_url', 'website_url', 'project_links',
        'public_contact', 'social_json', 'avatar', 'show_public_donations', 'show_page_views',
        'public_donations_limit', 'public_donations_sort', 'show_transparency', 'transparency_note',
    ];
    $sets = ['updated_at=?'];
    $params = [gmdate('c')];
    foreach ($allowed as $col) {
        if (!array_key_exists($col, $fields)) {
            continue;
        }
        $sets[] = $col . '=?';
        $params[] = $fields[$col];
    }
    $params[] = $pageId;
    $params[] = $userId;
    $sql = 'UPDATE pages SET ' . implode(', ', $sets) . ' WHERE id=? AND user_id=?';
    db()->prepare($sql)->execute($params);
    return true;
}

function page_archive_owned(int $pageId, int $userId): array
{
    $p = page_owned($pageId, $userId);
    if (!$p) {
        return ['ok' => false, 'message' => 'صفحه یافت نشد.'];
    }
    if (!empty($p['is_primary'])) {
        return ['ok' => false, 'message' => 'صفحهٔ اصلی حساب را نمی‌توان آرشیو کرد.'];
    }
    db()->prepare("UPDATE pages SET status='archived', updated_at=? WHERE id=? AND user_id=?")
        ->execute([gmdate('c'), $pageId, $userId]);
    return ['ok' => true];
}

function page_set_status(int $pageId, string $status, string $reason = ''): bool
{
    if (!in_array($status, ['pending', 'approved', 'rejected', 'archived'], true)) {
        return false;
    }
    db()->prepare('UPDATE pages SET status=?, reject_reason=?, updated_at=? WHERE id=?')
        ->execute([$status, $status === 'rejected' ? $reason : '', gmdate('c'), $pageId]);
    return true;
}

function pages_approve_primary_for_user(int $userId, string $userStatus): void
{
    $status = $userStatus === 'approved' ? 'approved' : ($userStatus === 'rejected' ? 'rejected' : 'pending');
    db()->prepare('UPDATE pages SET status=?, updated_at=? WHERE user_id=? AND is_primary=1')
        ->execute([$status, gmdate('c'), $userId]);
}

function pages_pending_extra_count(): int
{
    return (int) db()->query(
        "SELECT COUNT(*) FROM pages p
         JOIN users u ON u.id=p.user_id
         WHERE p.status='pending' AND p.is_primary=0 AND u.is_admin=0"
    )->fetchColumn();
}

/** @return list<array<string,mixed>> */
function pages_admin_list(string $filter = 'pending'): array
{
    $sql = "SELECT p.*, u.display_name AS owner_display_name, u.email AS owner_email,
                   u.phone AS owner_phone, u.slug AS owner_slug, u.status AS owner_status
            FROM pages p
            JOIN users u ON u.id = p.user_id
            WHERE u.is_admin=0 AND p.is_primary=0";
    $params = [];
    if ($filter !== 'all') {
        $sql .= ' AND p.status=?';
        $params[] = $filter;
    }
    $sql .= ' ORDER BY p.id DESC';
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function pages_ensure_backfill(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $users = $pdo->query(
            "SELECT * FROM users WHERE is_admin=0 AND COALESCE(role,'hamyar')='hamyar'"
        )->fetchAll();
        foreach ($users as $u) {
            $st = $pdo->prepare('SELECT id FROM pages WHERE user_id=? AND is_primary=1');
            $st->execute([(int) $u['id']]);
            $pid = $st->fetchColumn();
            if (!$pid) {
                $pid = page_insert_row(page_row_from_user($u, true));
            }
            $pid = (int) $pid;
            if ($pid < 1) {
                continue;
            }
            $pdo->prepare('UPDATE donations SET page_id=? WHERE user_id=? AND (page_id IS NULL OR page_id=0)')
                ->execute([$pid, (int) $u['id']]);
            $pdo->prepare('UPDATE campaigns SET page_id=? WHERE user_id=? AND (page_id IS NULL OR page_id=0)')
                ->execute([$pid, (int) $u['id']]);
            $pdo->prepare('UPDATE spend_entries SET page_id=? WHERE user_id=? AND (page_id IS NULL OR page_id=0)')
                ->execute([$pid, (int) $u['id']]);
        }
    } catch (Throwable $e) {
        error_log('pages_ensure_backfill: ' . $e->getMessage());
    }
}
