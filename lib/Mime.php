<?php
/**
 * MIME parsing (reading) and building (sending).
 */
class Mime
{
    public static function parseHeaders(string $block): array
    {
        $block = preg_replace("/\r?\n[ \t]+/", ' ', $block); // unfold
        $h = [];
        foreach (preg_split("/\r?\n/", $block) as $line) {
            if (($p = strpos($line, ':')) === false) continue;
            $k = strtolower(trim(substr($line, 0, $p)));
            $v = trim(substr($line, $p + 1));
            $h[$k] = isset($h[$k]) && $k === 'received' ? $h[$k] . "\n" . $v : ($h[$k] ?? $v);
        }
        return $h;
    }

    public static function decodeHeader(string $v): string
    {
        if ($v === '') return '';
        if (strpos($v, '=?') !== false) {
            // join adjacent encoded words
            $v = preg_replace('/(\?=)\s+(=\?)/', '$1$2', $v);
            $out = preg_replace_callback('/=\?([^?]+)\?([QqBb])\?([^?]*)\?=/', function ($m) {
                $txt = strtoupper($m[2]) === 'B' ? base64_decode($m[3]) : quoted_printable_decode(str_replace('_', ' ', $m[3]));
                return self::toUtf8($txt, $m[1]);
            }, $v);
            return $out;
        }
        return self::toUtf8($v, 'UTF-8');
    }

    public static function toUtf8(string $s, ?string $charset): string
    {
        $charset = strtoupper(trim((string)$charset, " \"'")) ?: 'UTF-8';
        if (in_array($charset, ['UTF-8', 'US-ASCII', 'ASCII'], true)) {
            return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }
        $aliases = ['KS_C_5601-1987' => 'CP949', 'GB2312' => 'GBK', 'WINDOWS-1252' => 'CP1252'];
        $charset = $aliases[$charset] ?? $charset;
        $r = @iconv($charset, 'UTF-8//TRANSLIT//IGNORE', $s);
        if ($r === false) $r = @mb_convert_encoding($s, 'UTF-8', $charset);
        return $r === false || $r === null ? mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1') : $r;
    }

    /** "Name <a@b>, c@d" -> [['name'=>..,'email'=>..], ...] */
    public static function addresses(string $v): array
    {
        $out = [];
        $parts = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $v);
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;
            if (preg_match('/^(.*)<([^>]+)>$/', $p, $m)) $out[] = ['name' => trim($m[1], " \"'"), 'email' => trim($m[2])];
            elseif (strpos($p, '@') !== false) $out[] = ['name' => '', 'email' => $p];
        }
        return $out;
    }

    public static function displayName(string $v): string
    {
        $a = self::addresses($v);
        if (!$a) return $v;
        return $a[0]['name'] !== '' ? $a[0]['name'] : $a[0]['email'];
    }

    private static function params(string $v): array
    {
        $out = ['value' => strtolower(trim(strtok($v, ';')))];
        // RFC2231 continuations and plain params
        preg_match_all('/;\s*([\w\-*]+)\s*=\s*("([^"]*)"|[^;]*)/', $v, $m, PREG_SET_ORDER);
        $cont = [];
        foreach ($m as $x) {
            $k = strtolower($x[1]); $val = (isset($x[3]) && $x[3] !== '') ? $x[3] : trim($x[2], ' "');
            if (preg_match('/^(\w+)\*(\d+)?\*?$/', $k, $mm)) { $cont[$mm[1]][(int)($mm[2] ?? 0)] = [$val, substr($k, -1) === '*']; continue; }
            $out[$k] = $val;
        }
        foreach ($cont as $k => $pieces) {
            ksort($pieces); $s = ''; $cs = 'UTF-8';
            foreach ($pieces as $i => [$val, $enc]) {
                if ($enc) {
                    if ($i === 0 && preg_match("/^([^']*)'[^']*'(.*)$/", $val, $q)) { $cs = $q[1] ?: 'UTF-8'; $val = $q[2]; }
                    $val = rawurldecode($val);
                }
                $s .= $val;
            }
            $out[$k] = self::toUtf8($s, $cs);
        }
        return $out;
    }

    /** Parse full message. */
    public static function parse(string $raw): array
    {
        $msg = ['headers' => [], 'text' => '', 'html' => '', 'attachments' => []];
        [$head, $body] = self::split($raw);
        $msg['headers'] = self::parseHeaders($head);
        self::walk($msg['headers'], $body, $msg, '1');
        return $msg;
    }

    private static function split(string $raw): array
    {
        $p = strpos($raw, "\r\n\r\n"); $l = 4;
        $q = strpos($raw, "\n\n");
        if ($p === false || ($q !== false && $q < $p)) { $p = $q; $l = 2; }
        if ($p === false) return [$raw, ''];
        return [substr($raw, 0, $p), substr($raw, $p + $l)];
    }

    private static function walk(array $h, string $body, array &$msg, string $partId): void
    {
        $ct = self::params($h['content-type'] ?? 'text/plain; charset=us-ascii');
        $type = $ct['value'] ?: 'text/plain';
        if (strpos($type, 'multipart/') === 0 && !empty($ct['boundary'])) {
            $b = '--' . $ct['boundary'];
            $chunks = explode($b, $body);
            array_shift($chunks); // preamble
            $i = 0;
            foreach ($chunks as $chunk) {
                if (strpos($chunk, '--') === 0) break; // closing boundary
                $chunk = preg_replace('/^\r?\n/', '', $chunk);
                $chunk = preg_replace('/\r?\n$/', '', $chunk);
                [$ph, $pb] = self::split($chunk);
                self::walk(self::parseHeaders($ph), $pb, $msg, $partId . '.' . (++$i));
            }
            return;
        }
        if ($type === 'message/rfc822') {
            $msg['attachments'][] = ['id' => $partId, 'filename' => 'message.eml', 'mime' => $type, 'size' => strlen($body), 'cid' => '', 'data' => $body];
            return;
        }
        $data = self::decodeBody($body, strtolower(trim($h['content-transfer-encoding'] ?? '7bit')));
        $disp = self::params($h['content-disposition'] ?? '');
        $filename = $disp['filename'] ?? ($ct['name'] ?? '');
        if ($filename !== '') $filename = self::decodeHeader($filename);
        $cid = trim($h['content-id'] ?? '', '<> ');
        $isAttach = $disp['value'] === 'attachment' || ($filename !== '' && !in_array($type, ['text/plain', 'text/html'], true)) || ($filename !== '' && $disp['value'] === 'attachment');

        if (!$isAttach && $type === 'text/html' && $msg['html'] === '') { $msg['html'] = self::toUtf8($data, $ct['charset'] ?? 'UTF-8'); return; }
        if (!$isAttach && $type === 'text/plain' && $msg['text'] === '' && $filename === '') { $msg['text'] = self::toUtf8($data, $ct['charset'] ?? 'UTF-8'); return; }
        if (!$isAttach && $filename === '' && strpos($type, 'text/') === 0) return;
        $msg['attachments'][] = [
            'id' => $partId,
            'filename' => $filename !== '' ? $filename : ('attachment-' . $partId . self::ext($type)),
            'mime' => $type, 'size' => strlen($data), 'cid' => $cid,
            'inline' => $disp['value'] === 'inline' && $cid !== '',
            'data' => $data,
        ];
    }

    private static function ext(string $mime): string
    {
        $map = ['image/png' => '.png', 'image/jpeg' => '.jpg', 'image/gif' => '.gif', 'application/pdf' => '.pdf', 'text/calendar' => '.ics'];
        return $map[$mime] ?? '';
    }

    private static function decodeBody(string $b, string $enc): string
    {
        if ($enc === 'base64') return base64_decode(preg_replace('/\s+/', '', $b)) ?: '';
        if ($enc === 'quoted-printable') return quoted_printable_decode($b);
        return $b;
    }

    // ---------- building ----------

    public static function encodeHeader(string $s): string
    {
        if (!preg_match('/[^\x20-\x7e]/', $s)) return $s;
        return mb_encode_mimeheader($s, 'UTF-8', 'B', "\r\n", 0);
    }

    public static function formatAddress(string $email, string $name = ''): string
    {
        if ($name === '') return $email;
        $n = preg_match('/[^\x20-\x7e]/', $name) ? self::encodeHeader($name) : '"' . addcslashes($name, '"\\') . '"';
        return $n . ' <' . $email . '>';
    }

    /**
     * Build a complete RFC 5322 message.
     * $m: from, fromName, to[], cc[], bcc[], subject, html, text, attachments[[name,mime,data]], inReplyTo, references
     */
    public static function build(array $m, string $domain): array
    {
        $msgId = '<' . bin2hex(random_bytes(12)) . '@' . $domain . '>';
        $h = [];
        $h[] = 'Date: ' . date('r');
        $h[] = 'From: ' . self::formatAddress($m['from'], $m['fromName'] ?? '');
        if (!empty($m['to'])) $h[] = 'To: ' . implode(', ', $m['to']);
        if (!empty($m['cc'])) $h[] = 'Cc: ' . implode(', ', $m['cc']);
        $h[] = 'Subject: ' . self::encodeHeader($m['subject'] ?? '');
        $h[] = 'Message-ID: ' . $msgId;
        if (!empty($m['inReplyTo'])) $h[] = 'In-Reply-To: ' . $m['inReplyTo'];
        if (!empty($m['references'])) $h[] = 'References: ' . $m['references'];
        $h[] = 'MIME-Version: 1.0';
        $h[] = 'X-Mailer: Jimohne Mail';

        $alt = 'alt_' . bin2hex(random_bytes(8));
        $altBody = "--$alt\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode(str_replace("\n", "\r\n", str_replace("\r\n", "\n", $m['text'] ?? ''))) . "\r\n"
            . "--$alt\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($m['html'] ?? '') . "\r\n--$alt--\r\n";

        if (empty($m['attachments'])) {
            $h[] = "Content-Type: multipart/alternative; boundary=\"$alt\"";
            $body = $altBody;
        } else {
            $mix = 'mix_' . bin2hex(random_bytes(8));
            $h[] = "Content-Type: multipart/mixed; boundary=\"$mix\"";
            $body = "--$mix\r\nContent-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n" . $altBody;
            foreach ($m['attachments'] as $a) {
                $fn = str_replace(['"', "\r", "\n"], '', $a['name']);
                $fnHeader = preg_match('/[^\x20-\x7e]/', $fn) ? "filename*=UTF-8''" . rawurlencode($fn) : 'filename="' . $fn . '"';
                $body .= "--$mix\r\nContent-Type: " . $a['mime'] . "; name=\"" . self::encodeHeader($fn) . "\"\r\n"
                    . "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; $fnHeader\r\n\r\n"
                    . chunk_split(base64_encode($a['data']), 76, "\r\n");
            }
            $body .= "--$mix--\r\n";
        }
        return ['raw' => implode("\r\n", $h) . "\r\n\r\n" . $body, 'id' => $msgId];
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(br|/p|/div|/tr|/h\d|/li)[^>]*>#i', "\n", $html);
        $t = html_entity_decode(strip_tags(preg_replace('#<(style|script|head)[^>]*>.*?</\1>#is', '', $html)), ENT_QUOTES, 'UTF-8');
        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $t)));
    }
}
