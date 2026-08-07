<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/profile.php';
require_once __DIR__ . '/campaigns.php';

/**
 * دادهٔ عمومی برای بج / ویجت embed (فقط کاربران approved).
 */
final class Embed
{
    public static function loadActivist(string $slug): ?array
    {
        $slug = trim($slug);
        $slug = preg_replace('/[^A-Za-z0-9\-_]/', '', $slug) ?? '';
        if ($slug === '' || mb_strlen($slug) > 40) {
            return null;
        }
        $st = db()->prepare(
            "SELECT * FROM users WHERE lower(slug)=lower(?) AND status='approved' AND is_admin=0 AND COALESCE(role,'hamyar')='hamyar' LIMIT 1"
        );
        $st->execute([$slug]);
        $u = $st->fetch();
        return $u ?: null;
    }

    /** @return array{user:array,name:string,url:string,sum:int,count:int,avatar:string,type:string,bio:string} */
    public static function publicCard(array $u): array
    {
        $cfg = app_config();
        $base = rtrim((string) ($cfg['site_url'] ?? 'https://donate.sudoshz.ir'), '/');
        $tot = user_total_paid((int) $u['id']);
        $bio = trim((string) ($u['bio'] ?? ''));
        if ($bio === '') {
            $bio = trim((string) ($u['activity'] ?? ''));
        }
        $bio = mb_substr($bio, 0, 160);
        return [
            'user' => $u,
            'name' => member_public_name($u),
            'slug' => (string) $u['slug'],
            'url' => $base . '/u/' . rawurlencode((string) $u['slug']),
            'sum' => (int) $tot['sum'],
            'count' => (int) $tot['count'],
            'avatar' => $base . member_avatar_url($u),
            'type' => member_type_badge($u),
            'bio' => $bio,
            'site' => $base,
        ];
    }

    public static function escapeSvg(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** عرض تقریبی متن برای SVG */
    public static function textWidth(string $s, float $charW = 7.2): int
    {
        $len = max(1, mb_strlen($s));
        return (int) ceil($len * $charW);
    }
}
