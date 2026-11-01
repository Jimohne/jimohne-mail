<?php
declare(strict_types=1);
require __DIR__ . '/lib/App.php';
require __DIR__ . '/templates/email.php';
require __DIR__ . '/templates/views.php';

App::boot();
$a = $_GET['a'] ?? ($_POST['a'] ?? '');

try {
    // ---------- public ----------
    if ($a === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        App::checkCsrf();
        try {
            App::login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
            App::redirect();
        } catch (Exception $ex) {
            view_login($ex->getMessage(), (string)($_POST['email'] ?? ''));
        }
        exit;
    }
    if (!App::user() || App::password() === null) { view_login(); exit; }

    if ($a === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') { App::checkCsrf(); App::logout(); App::redirect(); }

    $folders = App::folders();
    $folder = (string)($_GET['f'] ?? $_POST['f'] ?? $folders['roles']['inbox']);
    $valid = array_column($folders['all'], 'name');
    if (!in_array($folder, $valid, true)) $folder = $folders['roles']['inbox'];
    $imap = App::imap();

    switch ($a) {
        case 'read':
            $uid = (int)($_GET['uid'] ?? 0);
            $imap->select($folder);
            $raw = $imap->raw($uid, true);
            if ($raw === null) { App::flash('That message no longer exists.', 'err'); App::redirect(['f' => $folder]); }
            $msg = Mime::parse($raw);
            view_read($folders, $folder, $uid, $msg);
            break;

        case 'frame': // sanitized HTML for the sandboxed iframe
            $uid = (int)($_GET['uid'] ?? 0);
            $imap->select($folder);
            $msg = Mime::parse((string)$imap->raw($uid, false));
            $allowImg = !empty($_GET['img']);
            $cid = fn($c) => App::url(['a' => 'att', 'f' => $folder, 'uid' => $uid, 'cid' => $c, 'inline' => 1]);
            [$html] = $msg['html'] !== '' ? Sanitize::email($msg['html'], $cid) : [Sanitize::textToHtml($msg['text'])];
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:" . ($allowImg ? ' https: http:' : '') . "; font-src 'none'; sandbox allow-popups allow-popups-to-escape-sandbox allow-same-origin");
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><base target="_blank"><style>body{margin:0;padding:4px 2px;font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;color:#1f2933;word-wrap:break-word}img{max-width:100%;height:auto}blockquote{border-left:3px solid #d5dbe3;margin:8px 0;padding-left:12px;color:#5b6772}pre{white-space:pre-wrap}</style></head><body>' . $html . '</body></html>';
            break;

        case 'att':
            $uid = (int)($_GET['uid'] ?? 0);
            $imap->select($folder);
            $msg = Mime::parse((string)$imap->raw($uid, false));
            foreach ($msg['attachments'] as $att) {
                if ((isset($_GET['part']) && $att['id'] === $_GET['part']) || (isset($_GET['cid']) && $att['cid'] !== '' && $att['cid'] === $_GET['cid'])) {
                    $safeInline = !empty($_GET['inline']) && preg_match('#^image/(png|jpe?g|gif|webp)$#', $att['mime']);
                    header('Content-Type: ' . ($safeInline ? $att['mime'] : 'application/octet-stream'));
                    header('Content-Length: ' . strlen($att['data']));
                    header('Content-Disposition: ' . ($safeInline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($att['filename']));
                    header('Cache-Control: private, max-age=3600');
                    echo $att['data'];
                    exit;
                }
            }
            http_response_code(404); echo 'Attachment not found';
            break;

        case 'compose':
            $pre = ['to' => '', 'cc' => '', 'bcc' => '', 'subject' => '', 'body' => '', 'quote' => '', 'inReplyTo' => '', 'references' => '', 'fwd' => [], 'fwdFolder' => '', 'fwdUid' => 0, 'draft' => 0, 'mode' => 'new'];
            if (!empty($_GET['to'])) $pre['to'] = (string)$_GET['to'];
            foreach (['reply', 'all', 'fwd', 'draft'] as $mode) {
                if (empty($_GET[$mode])) continue;
                $uid = (int)$_GET[$mode];
                $imap->select($folder);
                $m = Mime::parse((string)$imap->raw($uid, false));
                $h = $m['headers'];
                $subj = Mime::decodeHeader($h['subject'] ?? '');
                $text = $m['text'] !== '' ? $m['text'] : Mime::htmlToText($m['html']);
                if ($mode === 'draft') {
                    $pre = array_merge($pre, ['to' => Mime::decodeHeader($h['to'] ?? ''), 'cc' => Mime::decodeHeader($h['cc'] ?? ''), 'subject' => $subj, 'body' => $text, 'draft' => $uid, 'mode' => 'draft', 'inReplyTo' => $h['in-reply-to'] ?? '', 'references' => $h['references'] ?? '']);
                    break;
                }
                $from = Mime::decodeHeader($h['from'] ?? '');
                $when = date('D, M j, Y \a\t g:i A', strtotime($h['date'] ?? 'now') ?: time());
                $pre['quote'] = "On $when, $from wrote:\n" . preg_replace('/^/m', '> ', trim($text));
                if ($mode === 'fwd') {
                    $pre['subject'] = preg_match('/^fwd?:/i', $subj) ? $subj : 'Fwd: ' . $subj;
                    $pre['quote'] = "---------- Forwarded message ----------\nFrom: $from\nDate: $when\nSubject: $subj\nTo: " . Mime::decodeHeader($h['to'] ?? '') . "\n\n" . trim($text);
                    $pre['fwd'] = array_map(fn($x) => ['id' => $x['id'], 'filename' => $x['filename'], 'size' => $x['size']], array_values(array_filter($m['attachments'], fn($x) => empty($x['inline']))));
                    $pre['fwdFolder'] = $folder; $pre['fwdUid'] = $uid; $pre['mode'] = 'fwd';
                } else {
                    $replyTo = Mime::decodeHeader($h['reply-to'] ?? '') ?: $from;
                    $pre['to'] = $replyTo;
                    if ($mode === 'all') {
                        $me = App::user();
                        $others = array_filter(array_merge(Mime::addresses(Mime::decodeHeader($h['to'] ?? '')), Mime::addresses(Mime::decodeHeader($h['cc'] ?? ''))), fn($x) => strtolower($x['email']) !== $me && stripos($replyTo, $x['email']) === false);
                        $pre['cc'] = implode(', ', array_map(fn($x) => $x['name'] ? "{$x['name']} <{$x['email']}>" : $x['email'], $others));
                    }
                    $pre['subject'] = preg_match('/^re:/i', $subj) ? $subj : 'Re: ' . $subj;
                    $pre['inReplyTo'] = $h['message-id'] ?? '';
                    $pre['references'] = trim(($h['references'] ?? '') . ' ' . ($h['message-id'] ?? ''));
                    $pre['mode'] = $mode === 'all' ? 'reply all' : 'reply';
                    $pre['replyUid'] = $uid; $pre['replyFolder'] = $folder;
                }
                break;
            }
            view_compose($folders, $folder, $pre);
            break;

        case 'preview':
            App::checkCsrf();
            header('Content-Type: text/html; charset=UTF-8');
            echo build_html((string)($_POST['body'] ?? ''), (string)($_POST['quote'] ?? ''), !empty($_POST['tpl']), !empty($_POST['sig']));
            break;

        case 'send':
            App::checkCsrf();
            handle_send($folders, $imap);
            break;

        case 'act':
            App::checkCsrf();
            $uids = array_map('intval', (array)($_POST['uids'] ?? []));
            $do = (string)($_POST['do'] ?? '');
            if ($do === '' && !empty($_POST['to'])) $do = 'move';
            $imap->select($folder);
            $back = ['f' => $folder];
            if (!$uids) { App::flash('Select at least one message.', 'err'); App::redirect($back); }
            switch ($do) {
                case 'read': $imap->flag($uids, '\\Seen', true); break;
                case 'unread': $imap->flag($uids, '\\Seen', false); break;
                case 'star': $imap->flag($uids, '\\Flagged', true); break;
                case 'unstar': $imap->flag($uids, '\\Flagged', false); break;
                case 'delete':
                    $trash = App::role('trash');
                    if ($folder === $trash || $trash === null && !empty($_POST['permanent'])) { $imap->delete($uids); App::flash(count($uids) . ' deleted permanently.'); }
                    else { $imap->move($uids, App::ensure('trash')); App::flash(count($uids) > 1 ? count($uids) . ' messages moved to Trash.' : 'Moved to Trash.'); }
                    break;
                case 'spam': $imap->move($uids, App::ensure('junk')); App::flash('Marked as spam.'); break;
                case 'notspam': $imap->move($uids, App::role('inbox')); App::flash('Moved to Inbox.'); break;
                case 'archive': $imap->move($uids, App::ensure('archive')); App::flash('Archived.'); break;
                case 'move':
                    $to = (string)($_POST['to'] ?? '');
                    if (in_array($to, $valid, true)) { $imap->move($uids, $to); App::flash('Moved.'); }
                    break;
            }
            if (!empty($_POST['ret']) && $_POST['ret'] === 'read' && in_array($do, ['read', 'unread', 'star', 'unstar'], true)) $back = ['a' => 'read', 'f' => $folder, 'uid' => $uids[0]];
            if ($do === 'unread') $back = ['f' => $folder];
            App::redirect($back);
            break;

        case 'refresh':
            App::folders(true);
            App::redirect(['f' => $folder]);
            break;

        default:
            $page = max(1, (int)($_GET['p'] ?? 1));
            $q = trim((string)($_GET['q'] ?? ''));
            $imap->select($folder);
            if ($q !== '') {
                $uids = [];
                try { $uids = $imap->search('CHARSET UTF-8 TEXT ' . Imap::quote($q)); }
                catch (ImapException $e) { $uids = $imap->search('TEXT ' . Imap::quote(preg_replace('/[^\x20-\x7e]/', '', $q))); }
            } else {
                $uids = $imap->search('ALL');
            }
            $size = (int)App::$cfg['page_size'];
            $total = count($uids);
            $list = $imap->summaries(array_slice($uids, ($page - 1) * $size, $size));
            $unread = $imap->status($folders['roles']['inbox'])['unseen'] ?? 0;
            view_list($folders, $folder, $list, $page, $total, $q, (int)$unread);
    }
} catch (ImapException | SmtpException $ex) {
    view_error($ex->getMessage());
}

// ---------- helpers ----------
function build_html(string $body, string $quote, bool $tpl, bool $sig): string
{
    $html = compose_text_to_html($body);
    if (trim($quote) !== '') {
        $html .= '<div style="margin-top:24px;padding-left:14px;border-left:3px solid #d5dbe3;color:#5b6772;font-size:14px">' . nl2br(htmlspecialchars(preg_replace('/^> ?/m', '', $quote), ENT_QUOTES, 'UTF-8')) . '</div>';
    }
    if ($tpl) return jimohne_email($html, App::$cfg, (string)App::user(), $sig);
    $s = App::$cfg['signature'];
    if ($sig) $html .= '<p style="margin-top:24px;color:#4a5361">--<br><strong>' . e($s['name']) . '</strong><br>' . e($s['title']) . '<br>' . e(App::user()) . ' · ' . e($s['phone']) . '<br>' . e($s['website']) . '</p>';
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1f2933">' . $html . '</body></html>';
}

function parse_recipients(string $v, string $label): array
{
    $out = [];
    foreach (preg_split('/[,;](?=(?:[^"]*"[^"]*")*[^"]*$)/', $v) as $tok) {
        $tok = trim($tok);
        if ($tok !== '' && strpos($tok, '@') === false) throw new Exception("\"$tok\" in $label is not a valid email address.");
    }
    foreach (Mime::addresses(str_replace(';', ',', $v)) as $a) {
        if (!filter_var($a['email'], FILTER_VALIDATE_EMAIL)) throw new Exception("\"{$a['email']}\" in $label is not a valid email address.");
        $out[] = $a;
    }
    return $out;
}

function handle_send(array $folders, Imap $imap): void
{
    $saveDraft = !empty($_POST['save_draft']);
    $body = (string)($_POST['body'] ?? '');
    $quote = (string)($_POST['quote'] ?? '');
    $subject = trim((string)($_POST['subject'] ?? ''));
    try {
        $to = parse_recipients((string)($_POST['to'] ?? ''), 'To');
        $cc = parse_recipients((string)($_POST['cc'] ?? ''), 'Cc');
        $bcc = parse_recipients((string)($_POST['bcc'] ?? ''), 'Bcc');
        if (!$saveDraft && !$to && !$cc && !$bcc) throw new Exception('Add at least one recipient.');

        $atts = [];
        $maxBytes = (int)App::$cfg['max_upload_mb'] * 1048576; $total = 0;
        if (!empty($_FILES['files']['name'][0])) {
            foreach ($_FILES['files']['name'] as $i => $name) {
                if ($_FILES['files']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) throw new Exception("Could not upload \"$name\". It may be too large.");
                $data = (string)file_get_contents($_FILES['files']['tmp_name'][$i]);
                $total += strlen($data);
                $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($data) ?: 'application/octet-stream';
                $atts[] = ['name' => basename($name), 'mime' => $mime, 'data' => $data];
            }
        }
        if (!empty($_POST['fwd_att']) && !empty($_POST['fwd_uid'])) {
            $imap->select((string)$_POST['fwd_folder']);
            $orig = Mime::parse((string)$imap->raw((int)$_POST['fwd_uid'], false));
            foreach ($orig['attachments'] as $x) {
                if (in_array($x['id'], (array)$_POST['fwd_att'], true)) { $atts[] = ['name' => $x['filename'], 'mime' => $x['mime'], 'data' => $x['data']]; $total += $x['size']; }
            }
        }
        if ($total > $maxBytes) throw new Exception('Attachments are larger than ' . App::$cfg['max_upload_mb'] . ' MB.');

        $fmt = fn($list) => array_map(fn($a) => Mime::formatAddress($a['email'], $a['name']), $list);
        $html = $saveDraft ? compose_text_to_html($body) : build_html($body, $quote, !empty($_POST['tpl']), !empty($_POST['sig']));
        $text = str_replace('**', '', trim($body)) . (trim($quote) !== '' ? "\n\n" . $quote : '');
        if (!$saveDraft && !empty($_POST['sig'])) { $s = App::$cfg['signature']; $text .= "\n\n--\n{$s['name']}\n{$s['title']}\n" . App::user() . " · {$s['phone']}\n{$s['website']}"; }
        $built = Mime::build([
            'from' => App::user(), 'fromName' => App::$cfg['signature']['name'],
            'to' => $fmt($to), 'cc' => $fmt($cc), 'subject' => $subject, 'html' => $html, 'text' => $text,
            'attachments' => $atts, 'inReplyTo' => trim((string)($_POST['in_reply_to'] ?? '')), 'references' => trim((string)($_POST['references'] ?? '')),
        ], substr((string)strrchr((string)App::user(), '@'), 1));

        $draftUid = (int)($_POST['draft_uid'] ?? 0);
        $drafts = App::role('drafts');
        if ($saveDraft) {
            $df = App::ensure('drafts');
            $imap->append($df, $built['raw'], '\\Seen \\Draft');
            if ($draftUid && $drafts) { $imap->select($drafts); $imap->delete([$draftUid]); }
            App::flash('Draft saved.');
            App::redirect(['f' => $df]);
        }

        $rcpt = array_unique(array_map(fn($a) => $a['email'], array_merge($to, $cc, $bcc)));
        App::smtp()->mail((string)App::user(), $rcpt, $built['raw']);

        try { $imap->append(App::ensure('sent'), $built['raw'], '\\Seen'); } catch (ImapException $e) { /* sent anyway */ }
        if ($draftUid && $drafts) { try { $imap->select($drafts); $imap->delete([$draftUid]); } catch (ImapException $e) {} }
        if (!empty($_POST['reply_uid']) && !empty($_POST['reply_folder'])) {
            try { $imap->select((string)$_POST['reply_folder']); $imap->flag([(int)$_POST['reply_uid']], '\\Answered'); } catch (ImapException $e) {}
        }
        App::flash('Message sent to ' . implode(', ', array_slice($rcpt, 0, 3)) . (count($rcpt) > 3 ? ' and ' . (count($rcpt) - 3) . ' more' : '') . '.');
        App::redirect(['f' => App::role('inbox')]);
    } catch (SmtpException $ex) {
        $_POST['error'] = 'Not sent: ' . $ex->getMessage();
    } catch (Exception $ex) {
        $_POST['error'] = $ex->getMessage();
    }
    // re-show composer with what was typed
    view_compose($folders, (string)($_POST['f'] ?? 'INBOX'), [
        'to' => $_POST['to'] ?? '', 'cc' => $_POST['cc'] ?? '', 'bcc' => $_POST['bcc'] ?? '', 'subject' => $subject, 'body' => $body, 'quote' => $quote,
        'inReplyTo' => $_POST['in_reply_to'] ?? '', 'references' => $_POST['references'] ?? '', 'fwd' => [], 'fwdFolder' => '', 'fwdUid' => 0,
        'draft' => (int)($_POST['draft_uid'] ?? 0), 'mode' => 'new', 'error' => $_POST['error'],
    ]);
}
