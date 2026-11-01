<?php
/** HTML views for Jimohne Mail. All output is escaped with e(). */

function logo_html(): string
{
    return '<span class="logo"><img class="logo-icon" src="' . App::basePath() . 'assets/brand/jimohne-icon.svg?v=3" alt="" width="30" height="30"><span class="logo-word">' . e(mb_strtolower(App::$cfg['brand']['name'])) . '</span><span class="logo-sub">Mail</span></span>';
}

function icon(string $n): string
{
    $p = [
        'inbox' => 'M3 13h5l1.5 3h5L16 13h5M5 5h14l2 8v6H3v-6z',
        'sent' => 'M3 11l18-8-8 18-2-8z',
        'drafts' => 'M4 20h4L19 9l-4-4L4 16zM14 6l4 4',
        'junk' => 'M12 3l9 16H3zM12 10v4M12 17h.01',
        'trash' => 'M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13',
        'archive' => 'M3 4h18v4H3zM5 8v12h14V8M10 12h4',
        'folder' => 'M3 6h6l2 2h10v11H3z',
        'pen' => 'M4 20h4L19 9l-4-4L4 16z',
        'search' => 'M11 18a7 7 0 100-14 7 7 0 000 14zM21 21l-5-5',
        'clip' => 'M21 11l-9 9a5 5 0 01-7-7l9-9a3.5 3.5 0 015 5l-9 9a2 2 0 01-3-3l8-8',
        'reply' => 'M10 9V5l-7 7 7 7v-4c5 0 8 1.5 11 5-1-6-4-11-11-11z',
        'replyall' => 'M7 8V5l-6 6 6 6v-3M13 9V5l-7 6 7 6v-4c4 0 7 1.5 9 5-1-6-4-10-9-10z',
        'forward' => 'M14 9V5l7 7-7 7v-4C9 15 6 16.5 3 20c1-6 4-11 11-11z',
        'back' => 'M15 18l-6-6 6-6',
        'star' => 'M12 3l2.8 5.8 6.2.9-4.5 4.4 1 6.2L12 17.4 6.5 20.3l1-6.2L3 9.7l6.2-.9z',
        'refresh' => 'M20 11a8 8 0 10-2.3 6.3M20 4v7h-7',
        'logout' => 'M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'mailopen' => 'M3 9l9-6 9 6v11H3zM3 9l9 6 9-6',
        'download' => 'M12 4v11M7 10l5 5 5-5M4 20h16',
        'eye' => 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 15a3 3 0 100-6 3 3 0 000 6z',
    ];
    return '<svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="' . ($p[$n] ?? $p['folder']) . '"/></svg>';
}

function head(string $title): void
{
    $accent = App::$cfg['brand']['accent'] ?? '#1d6cf0';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . ' · ' . e(App::$cfg['brand']['name']) . ' Mail</title>'
        . '<meta name="robots" content="noindex,nofollow">'
        . '<link rel="icon" href="' . App::basePath() . 'assets/brand/jimohne-icon.svg?v=3" type="image/svg+xml"><link rel="icon" href="' . App::basePath() . 'assets/brand/favicon-64.png?v=3" sizes="64x64" type="image/png"><link rel="apple-touch-icon" href="' . App::basePath() . 'assets/brand/apple-touch-icon.png?v=3">'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fredoka:wght@600&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="' . App::basePath() . 'assets/style.css?v=5">'
        . '<style>:root{--accent:' . e($accent) . '}</style></head><body>';
}

function foot(): void
{
    echo '<script src="' . App::basePath() . 'assets/app.js?v=3"></script></body></html>';
}

function flash_html(): string
{
    $f = App::flash();
    return $f ? '<div class="toast ' . e($f[1]) . '" role="status">' . e($f[0]) . '</div>' : '';
}

function view_login(string $error = '', string $email = ''): void
{
    head('Sign in');
    $domain = App::$cfg['allowed_domains'][0] ?? '';
    echo '<main class="login-wrap"><form class="login-card" method="post" action="' . App::url() . '">'
        . '<div class="login-logo">' . logo_html() . '</div>'
        . '<h1>Sign in to your mailbox</h1>'
        . '<p class="muted">Use your ' . e($domain ? "@$domain " : '') . 'email address and password.</p>'
        . ($error ? '<div class="alert">' . e($error) . '</div>' : '')
        . '<input type="hidden" name="a" value="login"><input type="hidden" name="csrf" value="' . App::csrf() . '">'
        . '<label>Email<input type="email" name="email" value="' . e($email ?: ($domain ? '' : '')) . '" placeholder="you@' . e($domain ?: 'example.com') . '" autocomplete="username" required autofocus></label>'
        . '<label>Password<input type="password" name="password" autocomplete="current-password" required></label>'
        . '<button class="btn primary full" type="submit">Sign in</button>'
        . '<p class="fine">Secure connection to your ' . e(App::$cfg['brand']['name']) . ' mail server. Your password is never stored on disk.</p>'
        . '</form></main>';
    foot();
}

function sidebar(array $folders, string $current, int $unread = -1): string
{
    $h = '<aside class="side" id="side"><div class="side-top">' . logo_html() . '</div>'
        . '<a class="btn primary compose-btn" href="' . App::url(['a' => 'compose', 'f' => $current]) . '">' . icon('pen') . 'Compose</a><nav class="folders">';
    foreach ($folders['all'] as $f) {
        $active = $f['name'] === $current ? ' active' : '';
        $count = ($f['role'] === 'inbox' && $unread > 0) ? '<span class="count">' . $unread . '</span>' : '';
        $h .= '<a class="folder' . $active . '" href="' . App::url(['f' => $f['name']]) . '">' . icon($f['role'] ?: 'folder') . '<span>' . e($f['label']) . '</span>' . $count . '</a>';
    }
    $h .= '</nav><div class="side-foot"><div class="me" title="' . e(App::user()) . '">' . e(App::user()) . '</div>'
        . '<form method="post" action="' . App::url() . '"><input type="hidden" name="a" value="logout"><input type="hidden" name="csrf" value="' . App::csrf() . '"><button class="link-btn" type="submit">' . icon('logout') . 'Sign out</button></form></div></aside>'
        . '<div class="scrim" data-close-side></div>';
    return $h;
}

function topbar(string $title, string $extra = ''): string
{
    return '<header class="top"><button class="icon-btn only-mobile" data-open-side aria-label="Menu">' . icon('menu') . '</button><h2>' . e($title) . '</h2>' . $extra . '</header>';
}

function folder_label(array $folders, string $name): string
{
    foreach ($folders['all'] as $f) if ($f['name'] === $name) return $f['label'];
    return $name;
}

function initials(string $from): string
{
    $n = trim(preg_replace('/[^\p{L}\p{N} ]/u', '', Mime::displayName($from)));
    $parts = preg_split('/\s+/', $n ?: '?');
    return mb_strtoupper(mb_substr($parts[0], 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
}

function avatar_color(string $s): string
{
    $c = ['#1d6cf0', '#0e9f6e', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#4f46e5', '#65a30d'];
    return $c[abs(crc32(strtolower($s))) % count($c)];
}

function view_list(array $folders, string $folder, array $list, int $page, int $total, string $q, int $unread): void
{
    head(folder_label($folders, $folder));
    $role = array_search($folder, $folders['roles'], true) ?: '';
    $isSentLike = in_array($role, ['sent', 'drafts'], true);
    $size = (int)App::$cfg['page_size'];
    $pages = max(1, (int)ceil($total / $size));

    $search = '<form class="search" method="get" action="' . App::url() . '"><input type="hidden" name="f" value="' . e($folder) . '">' . icon('search') . '<input name="q" value="' . e($q) . '" placeholder="Search mail" aria-label="Search mail"></form>';
    echo '<div class="app">' . sidebar($folders, $folder, $unread) . '<main class="main">' . topbar(folder_label($folders, $folder), $search);

    echo '<form method="post" action="' . App::url() . '" class="list-form" id="listForm"><input type="hidden" name="a" value="act"><input type="hidden" name="f" value="' . e($folder) . '"><input type="hidden" name="csrf" value="' . App::csrf() . '">';
    echo '<div class="toolbar"><label class="check"><input type="checkbox" data-check-all aria-label="Select all"></label>'
        . '<div class="bulk"><button name="do" value="read" class="tb">' . icon('mailopen') . '<span>Read</span></button>'
        . '<button name="do" value="unread" class="tb">' . icon('inbox') . '<span>Unread</span></button>'
        . '<button name="do" value="archive" class="tb">' . icon('archive') . '<span>Archive</span></button>'
        . ($role === 'junk' ? '<button name="do" value="notspam" class="tb">' . icon('inbox') . '<span>Not spam</span></button>' : '<button name="do" value="spam" class="tb">' . icon('junk') . '<span>Spam</span></button>')
        . '<button name="do" value="delete" class="tb danger" ' . ($role === 'trash' ? 'data-confirm="Delete forever?"' : '') . '>' . icon('trash') . '<span>' . ($role === 'trash' ? 'Delete forever' : 'Delete') . '</span></button>'
        . '<select name="to" class="tb-select" data-move aria-label="Move to"><option value="">Move to…</option>';
    foreach ($folders['all'] as $f) if ($f['name'] !== $folder) echo '<option value="' . e($f['name']) . '">' . e($f['label']) . '</option>';
    echo '</select></div>'
        . '<div class="pager"><a class="icon-btn" href="' . App::url(['a' => 'refresh', 'f' => $folder]) . '" title="Refresh">' . icon('refresh') . '</a>'
        . '<span class="muted">' . ($total ? (($page - 1) * $size + 1) . '–' . min($total, $page * $size) . ' of ' . $total : '0') . '</span>'
        . ($page > 1 ? '<a class="icon-btn" href="' . App::url(['f' => $folder, 'p' => $page - 1, 'q' => $q ?: null]) . '">‹</a>' : '<span class="icon-btn off">‹</span>')
        . ($page < $pages ? '<a class="icon-btn" href="' . App::url(['f' => $folder, 'p' => $page + 1, 'q' => $q ?: null]) . '">›</a>' : '<span class="icon-btn off">›</span>')
        . '</div></div>';

    if (!$list) {
        echo '<div class="empty">' . icon($role ?: 'folder') . '<h3>' . ($q ? 'No messages match “' . e($q) . '”' : 'Nothing here yet') . '</h3><p class="muted">' . ($q ? 'Try a different word or name.' : 'Messages in ' . e(folder_label($folders, $folder)) . ' will show up here.') . '</p></div>';
    } else {
        echo '<ul class="msgs">';
        foreach ($list as $m) {
            $seen = in_array('\\seen', $m['flags'], true);
            $star = in_array('\\flagged', $m['flags'], true);
            $who = $isSentLike ? 'To: ' . Mime::displayName($m['to']) : Mime::displayName($m['from']);
            $href = $role === 'drafts' ? App::url(['a' => 'compose', 'f' => $folder, 'draft' => $m['uid']]) : App::url(['a' => 'read', 'f' => $folder, 'uid' => $m['uid']]);
            $col = avatar_color($isSentLike ? $m['to'] : $m['from']);
            echo '<li class="msg' . ($seen ? '' : ' unread') . '"><label class="check"><input type="checkbox" name="uids[]" value="' . $m['uid'] . '"></label>'
                . '<a class="row" href="' . $href . '"><span class="av" style="background:' . $col . '">' . e(initials($isSentLike ? $m['to'] : $m['from'])) . '</span>'
                . '<span class="who">' . e($who ?: '(unknown)') . '</span>'
                . '<span class="subj">' . ($star ? '<span class="star on">★</span>' : '') . e($m['subject'] !== '' ? $m['subject'] : '(no subject)') . '</span>'
                . '<span class="meta">' . ($m['attach'] ? icon('clip') : '') . '<time>' . e(short_date($m['date'])) . '</time></span></a></li>';
        }
        echo '</ul>';
    }
    echo '</form><a class="btn primary fab" href="' . App::url(['a' => 'compose', 'f' => $folder]) . '">' . icon('pen') . 'Compose</a></main></div>' . flash_html();
    foot();
}

function view_read(array $folders, string $folder, int $uid, array $msg): void
{
    $h = $msg['headers'];
    $subject = Mime::decodeHeader($h['subject'] ?? '') ?: '(no subject)';
    head($subject);
    $from = Mime::decodeHeader($h['from'] ?? '');
    $fromA = Mime::addresses($from)[0] ?? ['name' => $from, 'email' => ''];
    $date = strtotime($h['date'] ?? '') ?: time();
    $role = array_search($folder, $folders['roles'], true) ?: '';
    $hasRemote = false;
    if ($msg['html'] !== '') { [, $n] = Sanitize::email($msg['html'], fn($c) => ''); $hasRemote = $n > 0; }
    $frame = App::url(['a' => 'frame', 'f' => $folder, 'uid' => $uid]);

    $back = '<a class="icon-btn" href="' . App::url(['f' => $folder]) . '" title="Back">' . icon('back') . '</a>';
    echo '<div class="app">' . sidebar($folders, $folder) . '<main class="main">' . topbar(folder_label($folders, $folder));
    echo '<form method="post" action="' . App::url() . '" class="toolbar read-tools"><input type="hidden" name="a" value="act"><input type="hidden" name="f" value="' . e($folder) . '"><input type="hidden" name="csrf" value="' . App::csrf() . '"><input type="hidden" name="uids[]" value="' . $uid . '"><input type="hidden" name="ret" value="read">'
        . $back . '<div class="bulk"><button name="do" value="archive" class="tb">' . icon('archive') . '<span>Archive</span></button>'
        . ($role === 'junk' ? '<button name="do" value="notspam" class="tb">' . icon('inbox') . '<span>Not spam</span></button>' : '<button name="do" value="spam" class="tb">' . icon('junk') . '<span>Spam</span></button>')
        . '<button name="do" value="delete" class="tb danger">' . icon('trash') . '<span>Delete</span></button>'
        . '<button name="do" value="unread" class="tb">' . icon('mailopen') . '<span>Mark unread</span></button>'
        . '<button name="do" value="star" class="tb">' . icon('star') . '<span>Star</span></button></div></form>';

    echo '<article class="reader"><h1 class="subject">' . e($subject) . '</h1>'
        . '<div class="sender"><span class="av lg" style="background:' . avatar_color($from) . '">' . e(initials($from)) . '</span><div class="sender-info">'
        . '<div><strong>' . e($fromA['name'] ?: $fromA['email']) . '</strong> ' . ($fromA['name'] ? '<span class="muted">&lt;' . e($fromA['email']) . '&gt;</span>' : '') . '</div>'
        . '<div class="muted small">to ' . e(Mime::decodeHeader($h['to'] ?? '')) . (!empty($h['cc']) ? ' · cc ' . e(Mime::decodeHeader($h['cc'])) : '') . '</div></div>'
        . '<time class="muted small">' . e(date('D, M j, Y · g:i A', $date)) . '</time></div>';
    if ($hasRemote) echo '<div class="notice">' . icon('eye') . '<span>Images from the web are hidden to protect your privacy.</span><button type="button" class="link-btn" data-show-images="' . e($frame . '&img=1') . '">Show images</button></div>';
    echo '<iframe class="body-frame" src="' . e($frame) . '" sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin" title="Message body" data-autoheight></iframe>';

    $files = array_values(array_filter($msg['attachments'], fn($a) => empty($a['inline'])));
    if ($files) {
        echo '<div class="atts"><div class="atts-h">' . icon('clip') . count($files) . ' attachment' . (count($files) > 1 ? 's' : '') . '</div><div class="att-grid">';
        foreach ($files as $a) {
            $ext = strtoupper(pathinfo($a['filename'], PATHINFO_EXTENSION) ?: 'FILE');
            echo '<a class="att" href="' . App::url(['a' => 'att', 'f' => $folder, 'uid' => $uid, 'part' => $a['id']]) . '"><span class="ext">' . e(substr($ext, 0, 4)) . '</span><span class="att-name">' . e($a['filename']) . '<small>' . human_size($a['size']) . '</small></span>' . icon('download') . '</a>';
        }
        echo '</div></div>';
    }
    echo '<div class="reply-bar"><a class="btn" href="' . App::url(['a' => 'compose', 'f' => $folder, 'reply' => $uid]) . '">' . icon('reply') . 'Reply</a>'
        . '<a class="btn" href="' . App::url(['a' => 'compose', 'f' => $folder, 'all' => $uid]) . '">' . icon('replyall') . 'Reply all</a>'
        . '<a class="btn" href="' . App::url(['a' => 'compose', 'f' => $folder, 'fwd' => $uid]) . '">' . icon('forward') . 'Forward</a></div>';
    echo '</article></main></div>' . flash_html();
    foot();
}

function view_compose(array $folders, string $folder, array $p): void
{
    head('New message');
    $snips = require __DIR__ . '/snippets.php';
    $title = ['new' => 'New message', 'reply' => 'Reply', 'reply all' => 'Reply all', 'fwd' => 'Forward', 'draft' => 'Edit draft'][$p['mode']] ?? 'New message';
    echo '<div class="app">' . sidebar($folders, $folder) . '<main class="main">' . topbar($title);
    echo '<form class="compose" method="post" action="' . App::url() . '" enctype="multipart/form-data" id="composeForm">'
        . '<input type="hidden" name="a" value="send"><input type="hidden" name="f" value="' . e($folder) . '"><input type="hidden" name="csrf" value="' . App::csrf() . '">'
        . '<input type="hidden" name="in_reply_to" value="' . e($p['inReplyTo']) . '"><input type="hidden" name="references" value="' . e($p['references']) . '">'
        . '<input type="hidden" name="draft_uid" value="' . (int)$p['draft'] . '">'
        . '<input type="hidden" name="reply_uid" value="' . (int)($p['replyUid'] ?? 0) . '"><input type="hidden" name="reply_folder" value="' . e($p['replyFolder'] ?? '') . '">'
        . '<input type="hidden" name="fwd_uid" value="' . (int)$p['fwdUid'] . '"><input type="hidden" name="fwd_folder" value="' . e($p['fwdFolder']) . '">';
    if (!empty($p['error'])) echo '<div class="alert">' . e($p['error']) . '</div>';
    echo '<div class="compose-grid"><div class="compose-main">'
        . '<div class="field"><label for="to">To</label><input id="to" name="to" value="' . e($p['to']) . '" placeholder="name@example.com, another@example.com" autocomplete="email" ' . ($p['mode'] === 'fwd' ? 'autofocus' : '') . '><button type="button" class="link-btn small" data-toggle="#ccRow">Cc / Bcc</button></div>'
        . '<div id="ccRow" class="' . ($p['cc'] || $p['bcc'] ? '' : 'hidden') . '"><div class="field"><label for="cc">Cc</label><input id="cc" name="cc" value="' . e($p['cc']) . '"></div><div class="field"><label for="bcc">Bcc</label><input id="bcc" name="bcc" value="' . e($p['bcc']) . '"></div></div>'
        . '<div class="field"><label for="subject">Subject</label><input id="subject" name="subject" value="' . e($p['subject']) . '"></div>'
        . '<textarea name="body" id="body" placeholder="Write your message…' . "\n\nTip: **bold text**, and lines starting with “- ” become a list." . '" ' . ($p['mode'] === 'reply' || $p['mode'] === 'reply all' ? 'autofocus' : '') . '>' . e($p['body']) . '</textarea>';
    if (trim($p['quote']) !== '') echo '<details class="quote"><summary>' . ($p['mode'] === 'fwd' ? 'Forwarded message' : 'Original message') . ' (included)</summary><textarea name="quote" rows="8">' . e($p['quote']) . '</textarea></details>';
    else echo '<input type="hidden" name="quote" value="">';
    if ($p['fwd']) {
        echo '<div class="fwd-atts"><div class="muted small">Forward attachments</div>';
        foreach ($p['fwd'] as $a) echo '<label class="chip"><input type="checkbox" name="fwd_att[]" value="' . e($a['id']) . '" checked>' . icon('clip') . e($a['filename']) . ' <small>' . human_size($a['size']) . '</small></label>';
        echo '</div>';
    }
    echo '<div class="attach-row"><label class="btn ghost">' . icon('clip') . 'Attach files<input type="file" name="files[]" multiple hidden data-files></label><div class="file-list" data-file-list></div></div>'
        . '<div class="send-row"><button class="btn primary" type="submit">Send</button><button class="btn" type="submit" name="save_draft" value="1" formnovalidate>Save draft</button>'
        . '<a class="btn ghost" href="' . App::url(['f' => $folder]) . '">Discard</a></div>'
        . '</div><aside class="compose-side">'
        . '<div class="panel"><h4>Design</h4>'
        . '<label class="switch"><input type="checkbox" name="tpl" value="1" checked data-preview-input><span></span>Jimohne email template</label>'
        . '<label class="switch"><input type="checkbox" name="sig" value="1" checked data-preview-input><span></span>Signature</label>'
        . '<button type="button" class="btn full" data-preview>' . icon('eye') . 'Preview email</button></div>'
        . '<div class="panel"><h4>Quick templates</h4><div class="snips">';
    foreach ($snips as $k => $s) echo '<button type="button" class="snip" data-snip-subject="' . e($s['subject']) . '" data-snip-body="' . e($s['body']) . '">' . e($s['label']) . '</button>';
    echo '</div><p class="fine">Replace the [placeholders] before sending.</p></div></aside></div></form>'
        . '<div class="modal hidden" id="previewModal"><div class="modal-card"><div class="modal-head"><strong>Email preview</strong><button type="button" class="icon-btn" data-close-modal aria-label="Close">✕</button></div><iframe id="previewFrame" sandbox="" title="Email preview"></iframe></div></div>'
        . '</main></div>' . flash_html();
    echo '<input type="hidden" id="previewUrl" value="' . App::url(['a' => 'preview']) . '">';
    foot();
}

function view_error(string $msg): void
{
    head('Problem');
    echo '<main class="login-wrap"><div class="login-card"><div class="login-logo">' . logo_html() . '</div><h1>Something went wrong</h1><div class="alert">' . e($msg) . '</div>'
        . '<p class="muted">This is usually a temporary problem with the mail server connection.</p><a class="btn primary full" href="' . App::url() . '">Try again</a></div></main>';
    foot();
}
