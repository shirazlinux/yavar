<?php
declare(strict_types=1);

function member_public_name(array $u): string
{
    $mode = (string) ($u['display_mode'] ?? 'personal');
    $platform = trim((string) ($u['platform_name'] ?? ''));
    if (($mode === 'platform' || $mode === 'community') && $platform !== '') {
        return $platform;
    }
    return (string) ($u['display_name'] ?? '');
}

/** برچسب نوع صفحه در فهرست */
function member_type_badge(array $u): string
{
    return match ((string) ($u['display_mode'] ?? 'personal')) {
        'platform' => 'پروژه',
        'community' => 'جامعه',
        default => 'فعال',
    };
}

function normalize_display_mode(?string $mode): string
{
    $mode = (string) $mode;
    return in_array($mode, ['personal', 'platform', 'community'], true) ? $mode : 'personal';
}

/** نرمال‌سازی URL عمومی (گیت / وب / محل فعالیت) */
function normalize_url_field(string $u): string
{
    $u = trim($u);
    if ($u === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $u)) {
        $u = 'https://' . $u;
    }
    return filter_var($u, FILTER_VALIDATE_URL) ? mb_substr($u, 0, 300) : '';
}

/**
 * فیلد لینک اجباری ثبت‌نام بر اساس نوع صفحه.
 * @return array{store:string,label:string,hint:string,placeholder:string}
 */
/** توضیح کوتاه نوع صفحه برای ثبت‌نام و ساخت صفحه */
function yavar_type_help_html(?string $selected = null): void
{
    $selected = $selected === 'platform' ? 'project' : (string) $selected;
    $items = [
        'personal' => [
            'title' => 'فعال',
            'who' => 'یک نفر',
            'text' => 'صفحه با نام خودت دیده می‌شود. برای کسی که شخصاً روی نرم‌افزار آزاد کار می‌کند: توسعه، مستند، ترجمه، نگهداری، آموزش، …',
        ],
        'project' => [
            'title' => 'پروژه',
            'who' => 'یک کار مشخص',
            'text' => 'صفحه با نام پروژه دیده می‌شود، نه نام شخصی. برای یک مخزن، ابزار یا محصول آزاد مشخص که حمایت برای همان کار است.',
        ],
        'community' => [
            'title' => 'جامعه',
            'who' => 'یک جمع',
            'text' => 'صفحه با نام جامعه دیده می‌شود. برای گروه کاربری، انجمن، LUG، رویداد یا ویکی جمعی نرم‌افزار آزاد.',
        ],
    ];
    echo '<div class="type-help" id="type-help">';
    echo '<p class="type-help__lead">این سه تا چه فرقی دارند؟ بعداً هم می‌توانی با همین حساب صفحهٔ دیگری بسازی.</p>';
    echo '<ul class="type-help__list">';
    foreach ($items as $key => $it) {
        $cls = 'type-help__item' . ($selected === $key ? ' is-current' : '');
        echo '<li class="' . e($cls) . '" data-type="' . e($key) . '">';
        echo '<strong>' . e($it['title']) . '</strong>';
        echo '<span class="type-help__who">' . e($it['who']) . '</span>';
        echo '<span>' . e($it['text']) . '</span>';
        echo '</li>';
    }
    echo '</ul>';
    echo '<p class="hint" style="margin:.65rem 0 0">در حالت پروژه یا جامعه، در صفحهٔ عمومی فقط همان نام دیده می‌شود.</p>';
    echo '</div>';
}

function presence_link_meta(?string $mode): array
{
    $mode = normalize_display_mode($mode);
    return match ($mode) {
        'platform' => [
            'store' => 'git_url',
            'label' => 'لینک مخزن پروژه',
            'hint' => 'آدرس ریپوی پروژه (گیت‌هاب، کدبرگ، گیت‌لب، …) تا حامیان قبل از حمایت بررسی کنند.',
            'placeholder' => 'https://github.com/org/project',
        ],
        'community' => [
            'store' => 'website_url',
            'label' => 'لینک صفحه جامعه',
            'hint' => 'وب‌سایت، ویکی، گروه یا صفحهٔ عمومی جامعه تا حامیان بتوانند آن را بررسی کنند.',
            'placeholder' => 'https://example.org/community',
        ],
        default => [
            'store' => 'website_url',
            'label' => 'لینک محل فعالیت',
            'hint' => 'جایی که به‌عنوان فعال نرم‌افزار آزاد شناخته می‌شوید (پروفایل گیت، وبلاگ، صفحه مشارکت‌ها، …).',
            'placeholder' => 'https://github.com/username',
        ],
    };
}

const PROJECT_LINKS_MAX = 10;

/** لینک‌های اضافی پروژه / فعالیت (غیر از git_url و website_url) */
function project_links_from_user(array $u): array
{
    $raw = (string) ($u['project_links'] ?? '[]');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }
    $out = [];
    $seen = [];
    foreach ($data as $item) {
        $url = is_array($item) ? (string) ($item['url'] ?? '') : (string) $item;
        $url = normalize_url_field($url);
        if ($url === '') {
            continue;
        }
        $key = mb_strtolower($url);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $url;
        if (count($out) >= PROJECT_LINKS_MAX) {
            break;
        }
    }
    return $out;
}

function extra_project_link_icon(string $url): string
{
    if (preg_match('#(github\.com|codeberg\.org|gitlab\.[^/]+|gitea\.|git\.sr\.ht|bitbucket\.org)#i', $url)) {
        return 'git';
    }
    return 'web';
}

function extra_project_link_label(?string $mode, int $index): string
{
    $n = $index + 2;
    $fa = strtr((string) $n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    return match (normalize_display_mode($mode)) {
        'platform' => 'پروژه ' . $fa,
        'community' => 'صفحه ' . $fa,
        default => 'لینک ' . $fa,
    };
}

function project_links_validate_post(array $post): ?string
{
    $raw = $post['project_links'] ?? [];
    if (!is_array($raw)) {
        return 'لینک‌های اضافی نامعتبر است.';
    }
    $n = 0;
    foreach ($raw as $item) {
        $t = trim((string) $item);
        if ($t === '') {
            continue;
        }
        if (normalize_url_field($t) === '') {
            return 'یکی از لینک‌های اضافی پروژه معتبر نیست.';
        }
        $n++;
        if ($n > PROJECT_LINKS_MAX) {
            return 'حداکثر ۱۰ لینک اضافی می‌توانید اضافه کنید.';
        }
    }
    return null;
}

/** @return list<string> مقادیر خام (برای نمایش مجدد فرم) */
function project_links_posted(array $post): array
{
    $raw = $post['project_links'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $item) {
        $t = trim((string) $item);
        if ($t !== '') {
            $out[] = mb_substr($t, 0, 300);
        }
        if (count($out) >= PROJECT_LINKS_MAX) {
            break;
        }
    }
    return $out;
}

function project_links_normalize_from_post(array $post, string $primaryGit = '', string $primaryWeb = ''): string
{
    $raw = $post['project_links'] ?? [];
    if (!is_array($raw)) {
        $raw = [];
    }
    $primaries = [];
    foreach ([$primaryGit, $primaryWeb] as $p) {
        $p = mb_strtolower(rtrim(trim($p), '/'));
        if ($p !== '') {
            $primaries[$p] = true;
        }
    }
    $out = [];
    $seen = [];
    foreach ($raw as $item) {
        $url = normalize_url_field((string) $item);
        if ($url === '') {
            continue;
        }
        $key = mb_strtolower(rtrim($url, '/'));
        if (isset($seen[$key]) || isset($primaries[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $url;
        if (count($out) >= PROJECT_LINKS_MAX) {
            break;
        }
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** لینک اصلی برای بررسی قبل از حمایت */
function member_primary_presence_link(array $u): ?array
{
    $mode = normalize_display_mode($u['display_mode'] ?? 'personal');
    $meta = presence_link_meta($mode);
    $git = trim((string) ($u['git_url'] ?? ''));
    $web = trim((string) ($u['website_url'] ?? ''));
    $url = '';
    $label = $meta['label'];
    if ($mode === 'platform') {
        $url = $git !== '' ? $git : $web;
        $label = 'مخزن پروژه';
    } elseif ($mode === 'community') {
        $url = $web !== '' ? $web : $git;
        $label = 'صفحه جامعه';
    } else {
        $url = $web !== '' ? $web : $git;
        $label = 'محل فعالیت';
    }
    if ($url === '') {
        return null;
    }
    return [
        'label' => $label,
        'url' => $url,
        'text' => preg_replace('#^https?://#i', '', $url) ?? $url,
    ];
}


/** نسخهٔ فایل برای شکستن کش مرورگر روی آواتارهای استاتیک */
function avatar_asset_url(string $relPath): string
{
    $relPath = '/' . ltrim($relPath, '/');
    $abs = dirname(__DIR__) . $relPath;
    $v = is_file($abs) ? (string) filemtime($abs) : '1';
    return $relPath . '?v=' . $v;
}

function member_avatar_url(array $u): string
{
    $a = trim((string) ($u['avatar'] ?? ''));
    if ($a === '') {
        return is_file(dirname(__DIR__) . '/assets/avatars/tux.png') ? avatar_asset_url('/assets/avatars/tux.png') : avatar_asset_url('/assets/avatars/tux.svg');
    }
    if (str_starts_with($a, 'preset:')) {
        $key = preg_replace('/[^a-z0-9_\-]/', '', substr($a, 7)) ?: 'tux';
        $dir = dirname(__DIR__) . '/assets/avatars';
        // PNG اول (نسخهٔ استاندارد کاربر)، بعد SVG
        if (is_file($dir . '/' . $key . '.png')) {
            return avatar_asset_url('/assets/avatars/' . $key . '.png');
        }
        if (is_file($dir . '/' . $key . '.svg')) {
            return avatar_asset_url('/assets/avatars/' . $key . '.svg');
        }
        return is_file(dirname(__DIR__) . '/assets/avatars/tux.png') ? avatar_asset_url('/assets/avatars/tux.png') : avatar_asset_url('/assets/avatars/tux.svg');
    }
    if (str_starts_with($a, 'upload:')) {
        $file = basename(substr($a, 7));
        return '/assets/uploads/' . $file;
    }
    return is_file(dirname(__DIR__) . '/assets/avatars/tux.png') ? avatar_asset_url('/assets/avatars/tux.png') : avatar_asset_url('/assets/avatars/tux.svg');
}

/** @return list<array{id:string,label:string,url:string}> */
function avatar_presets(): array
{
    return [
        ['id' => 'tux', 'label' => 'لینوکس تاکس', 'url' => avatar_asset_url('/assets/avatars/tux.png')],
        ['id' => 'gnu-linux', 'label' => 'گنو/لینوکس', 'url' => avatar_asset_url('/assets/avatars/gnu-linux.png')],
        ['id' => 'freedo', 'label' => 'لیبره گنو/لینوکس (Freedo)', 'url' => avatar_asset_url('/assets/avatars/freedo.png')],
        ['id' => 'fsf', 'label' => 'بنیاد نرم‌افزار آزاد', 'url' => avatar_asset_url('/assets/avatars/fsf.png')],
        ['id' => 'persepolis', 'label' => 'پرسپولیس — دانلود منیجر', 'url' => avatar_asset_url('/assets/avatars/persepolis.png')],
        ['id' => 'arch', 'label' => 'آرچ لینوکس', 'url' => avatar_asset_url('/assets/avatars/arch.png')],
    ];
}

/** شبکه‌های اجتماعی / پیوندهای عمومی آزاد */
function social_network_defs(): array
{
    return [
        'matrix'   => ['label' => 'ماتریکس', 'placeholder' => 'https://matrix.to/#/@user:matrix.org', 'icon' => 'matrix'],
        'mastodon' => ['label' => 'ماستودون', 'placeholder' => 'https://mas.to/@user', 'icon' => 'mastodon'],
        'telegram' => ['label' => 'تلگرام', 'placeholder' => 'https://t.me/username', 'icon' => 'telegram'],
        'x'        => ['label' => 'اکس (توییتر)', 'placeholder' => 'https://x.com/username', 'icon' => 'x'],
        'codeberg' => ['label' => 'کدبرگ', 'placeholder' => 'https://codeberg.org/user', 'icon' => 'codeberg'],
        'github'   => ['label' => 'گیت‌هاب', 'placeholder' => 'https://github.com/user', 'icon' => 'github'],
        'peertube' => ['label' => 'پیرتیوب', 'placeholder' => 'https://tubedu.org/c/channel', 'icon' => 'peertube'],
        'pixelfed' => ['label' => 'پیکسل‌فد', 'placeholder' => 'https://pixelfed.social/user', 'icon' => 'pixelfed'],
    ];
}

/** آیکون SVG ساده برای شبکه‌ها (بدون وابستگی خارجی) */
function social_icon_svg(string $key): string
{
    $common = 'width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"';
    return match ($key) {
        'matrix' => '<svg ' . $common . '><path d="M3 3v18h3v-2H5V5h1V3H3zm13 0v2h1v14h-1v2h3V3h-3zM8 7v10h2.5c1.7 0 3-1.1 3-3.1 0-1.2-.7-2.2-1.8-2.6.7-.4 1.2-1.2 1.2-2.2C13 7.9 11.8 7 10.3 7H8zm2 1.6h.9c.7 0 1.2.4 1.2 1.1S11.6 11 10.9 11H10V8.6zm0 3.9h1.1c.9 0 1.5.5 1.5 1.4s-.6 1.5-1.5 1.5H10v-2.9z"/></svg>',
        'mastodon' => '<svg ' . $common . '><path d="M21.3 13.9c-.3 1.5-2.6 3.1-5.3 3.4-1.4.2-2.7.3-4.1.1h0c-1.4.1-2.7 0-4.1-.1-2.7-.3-5-1.9-5.3-3.4-.1-.7-.2-1.5-.2-2.2V8.5c0-3.4 2.2-4.4 2.2-4.4C6 3.4 8 3.2 10.1 3.1h.1c2.1.1 4.1.3 5.6 1 .8.4 1.4.8 1.4.8s2.2 1 2.2 4.4v3.2c0 .7-.1 1.5-.2 2.2zM17 8.7c0-1.3-.3-2.4-1.2-3-.8-.5-1.8-.6-2.7-.6-1.2 0-2.2.3-2.8 1l-.2.3-.2-.3c-.5-.7-1.6-1-2.8-1-.9 0-1.9.1-2.7.6C3.6 6.3 3.3 7.4 3.3 8.7v5c.2 1.1 1.6 2 3.7 2.2 1 .1 1.9.1 2.9 0V13c-1.1.2-2.2.1-2.6-.1-.4-.2-.5-.5-.5-.8V8.9c0-.7.4-1.1 1.1-1.1.8 0 1.1.5 1.1 1.5v2.3h2.1V9.3c0-1 .4-1.5 1.1-1.5.8 0 1.1.4 1.1 1.1v5.2c0 .4-.1.7-.5.8-.4.2-1.5.3-2.6.1v2.9c1 .1 1.9.1 2.9 0 2.1-.2 3.5-1.1 3.7-2.2v-5z"/></svg>',
        'telegram' => '<svg ' . $common . '><path d="M9.8 15.4 9.5 19c.4 0 .6-.2.9-.4l2.1-2 4.4 3.2c.8.4 1.4.2 1.6-.8L21.8 5c.3-1.2-.4-1.7-1.2-1.4L3.4 9.7c-1.1.4-1.1 1.1-.2 1.4l4.4 1.4 10.2-6.4c.5-.3.9-.1.6.2L9.8 15.4z"/></svg>',
        'x' => '<svg ' . $common . '><path d="M18.2 3H21l-6.6 7.5L22 21h-5.5l-4.3-5.6L7 21H4.2l7-8L2 3h5.6l3.9 5.1L18.2 3zm-1 16.2h1.5L7.9 4.7H6.3l10.9 14.5z"/></svg>',
        'codeberg' => '<svg ' . $common . '><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm0 2.5c4.1 0 7.5 3.4 7.5 7.5 0 2.3-1 4.3-2.7 5.7L12 10.2 7.2 17.7A7.45 7.45 0 0 1 4.5 12c0-4.1 3.4-7.5 7.5-7.5zm0 9.2 3.3 5.1c-1 .5-2.1.7-3.3.7s-2.3-.2-3.3-.7L12 13.7z"/></svg>',
        'github' => '<svg ' . $common . '><path d="M12 2C6.5 2 2 6.6 2 12.2c0 4.5 2.9 8.3 6.9 9.6.5.1.7-.2.7-.5v-1.9c-2.8.6-3.4-1.2-3.4-1.2-.4-1.1-1.1-1.4-1.1-1.4-.9-.6.1-.6.1-.6 1 .1 1.5 1 1.5 1 .9 1.5 2.3 1.1 2.9.8.1-.7.4-1.1.6-1.4-2.2-.3-4.6-1.1-4.6-5 0-1.1.4-2 1-2.7-.1-.3-.4-1.3.1-2.7 0 0 .8-.3 2.8 1a9.3 9.3 0 0 1 5 0c2-1.3 2.8-1 2.8-1 .5 1.4.2 2.4.1 2.7.7.7 1 1.6 1 2.7 0 3.9-2.3 4.7-4.6 5 .4.3.7.9.7 1.9v2.8c0 .3.2.6.7.5 4-1.3 6.9-5.1 6.9-9.6C22 6.6 17.5 2 12 2z"/></svg>',
        'peertube' => '<svg ' . $common . '><path d="M12 3 3 8v8l9 5 9-5V8l-9-5zm0 2.3 6.5 3.6v7.2L12 19.7l-6.5-3.6V8.9L12 5.3zm-1.5 3.7v6l5-3-5-3z"/></svg>',
        'pixelfed' => '<svg ' . $common . '><path d="M12 2a10 10 0 1 0 .01 20.01A10 10 0 0 0 12 2zm0 2c4.4 0 8 3.6 8 8s-3.6 8-8 8-8-3.6-8-8 3.6-8 8-8zm0 3a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6z"/></svg>',
        'git' => '<svg ' . $common . '><path d="M21.5 11.1 12.9 2.5a1.7 1.7 0 0 0-2.4 0L8.4 4.6l2.3 2.3a1.5 1.5 0 0 1 1.9 1.9l2.2 2.2a1.5 1.5 0 1 1-.9.9l-2.1-2.1v5.5a1.5 1.5 0 1 1-1.2.1V9.8a1.5 1.5 0 0 1-.8-2L7.5 5.5 2.5 10.5a1.7 1.7 0 0 0 0 2.4l8.6 8.6a1.7 1.7 0 0 0 2.4 0l8-8a1.7 1.7 0 0 0 0-2.4z"/></svg>',
        'web' => '<svg ' . $common . '><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm7.9 9h-3.2a15 15 0 0 0-1.3-5 8 8 0 0 1 4.5 5zM12 4c.9 0 2.3 1.8 3 5H9c.7-3.2 2.1-5 3-5zM4.1 13h3.2c.2 1.8.6 3.5 1.3 5A8 8 0 0 1 4.1 13zm3.2-2H4.1a8 8 0 0 1 4.5-5 15 15 0 0 0-1.3 5zM12 20c-.9 0-2.3-1.8-3-5h6c-.7 3.2-2.1 5-3 5zm2.7-2c.7-1.5 1.1-3.2 1.3-5H8c.2 1.8.6 3.5 1.3 5h5.4z"/></svg>',
        'contact' => '<svg ' . $common . '><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5L4 8V6l8 5 8-5v2z"/></svg>',
        default => '<svg ' . $common . '><circle cx="12" cy="12" r="8"/></svg>',
    };
}

/** آیتم‌های قابل‌نمایش پیوندها برای صفحه عمومی */
function member_link_items(array $u): array
{
    $items = [];
    $git = trim((string) ($u['git_url'] ?? ''));
    $web = trim((string) ($u['website_url'] ?? ''));
    $contact = trim((string) ($u['public_contact'] ?? ''));
    $mode = normalize_display_mode($u['display_mode'] ?? 'personal');
    if ($git !== '') {
        $gitLabel = $mode === 'platform' ? 'مخزن پروژه' : 'مخزن';
        $items[] = ['key' => 'git', 'label' => $gitLabel, 'url' => $git, 'text' => preg_replace('#^https?://#', '', $git)];
    }
    if ($web !== '') {
        $webLabel = match ($mode) {
            'community' => 'صفحه جامعه',
            'personal' => 'محل فعالیت',
            default => 'وب',
        };
        $items[] = ['key' => 'web', 'label' => $webLabel, 'url' => $web, 'text' => preg_replace('#^https?://#', '', $web)];
    }
    $primaryKeys = [];
    if ($git !== '') {
        $primaryKeys[mb_strtolower(rtrim($git, '/'))] = true;
    }
    if ($web !== '') {
        $primaryKeys[mb_strtolower(rtrim($web, '/'))] = true;
    }
    $extraI = 0;
    foreach (project_links_from_user($u) as $extraUrl) {
        $ek = mb_strtolower(rtrim($extraUrl, '/'));
        if (isset($primaryKeys[$ek])) {
            continue;
        }
        $items[] = [
            'key' => extra_project_link_icon($extraUrl),
            'label' => extra_project_link_label($mode, $extraI),
            'url' => $extraUrl,
            'text' => preg_replace('#^https?://#', '', $extraUrl),
        ];
        $extraI++;
    }
    $defs = social_network_defs();
    foreach (social_links_from_user($u) as $k => $url) {
        $items[] = [
            'key' => $k,
            'label' => $defs[$k]['label'] ?? $k,
            'url' => $url,
            'text' => preg_replace('#^https?://#', '', $url),
        ];
    }
    if ($contact !== '') {
        $items[] = ['key' => 'contact', 'label' => 'تماس', 'url' => '', 'text' => $contact];
    }
    return $items;
}

function social_links_from_user(array $u): array
{
    $raw = (string) ($u['social_json'] ?? '{}');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = [];
    }
    $defs = social_network_defs();
    $out = [];
    foreach ($defs as $key => $meta) {
        $v = trim((string) ($data[$key] ?? ''));
        if ($v !== '') {
            $out[$key] = $v;
        }
    }
    return $out;
}

function donor_public_label(array $d): string
{
    if (!empty($d['is_anonymous'])) {
        return 'حامی ناشناس';
    }
    $n = trim((string) ($d['donor_name'] ?? ''));
    return $n !== '' ? $n : 'حامی';
}

function social_links_normalize(array $post): string
{
    $defs = social_network_defs();
    $out = [];
    foreach ($defs as $key => $meta) {
        $v = trim((string) ($post['social_' . $key] ?? ''));
        if ($v === '') {
            continue;
        }
        // handle bare @user for telegram/matrix loosely
        if ($key === 'telegram' && preg_match('/^@?[A-Za-z0-9_]{4,}$/', $v)) {
            $v = 'https://t.me/' . ltrim($v, '@');
        }
        if (!preg_match('#^https?://#i', $v)) {
            $v = 'https://' . $v;
        }
        if (filter_var($v, FILTER_VALIDATE_URL)) {
            $out[$key] = mb_substr($v, 0, 300);
        }
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
