<?php
/**
 * Minimal IMAP client over a TLS socket. No php-imap extension needed.
 */
class ImapException extends Exception {}

class Imap
{
    private $s;
    private int $tag = 0;
    public array $capabilities = [];

    public function __construct(string $host, int $port, string $security = 'ssl', int $timeout = 20, bool $verifyPeer = true)
    {
        $ctx = stream_context_create(['ssl' => [
            'verify_peer' => $verifyPeer, 'verify_peer_name' => $verifyPeer, 'allow_self_signed' => !$verifyPeer,
        ]]);
        $proto = $security === 'ssl' ? 'ssl://' : 'tcp://';
        $this->s = @stream_socket_client($proto . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->s) throw new ImapException("Cannot connect to mail server ($errstr)");
        stream_set_timeout($this->s, $timeout);
        $greet = $this->line();
        if (strpos($greet, '* OK') !== 0) throw new ImapException('Unexpected server greeting');
        if ($security === 'tls') {
            $this->cmd('STARTTLS');
            if (!stream_socket_enable_crypto($this->s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new ImapException('STARTTLS failed');
        }
    }

    public function __destruct()
    {
        if ($this->s) { @fwrite($this->s, 'Z LOGOUT' . "\r\n"); @fclose($this->s); }
    }

    private function line(): string
    {
        $l = fgets($this->s);
        if ($l === false) throw new ImapException('Connection to mail server lost');
        return $l;
    }

    private function write(string $data): void
    {
        if (fwrite($this->s, $data) === false) throw new ImapException('Write to mail server failed');
    }

    private static function up($x): string
    {
        return is_string($x) ? strtoupper($x) : '';
    }

    public static function quote(string $s): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
    }

    /**
     * Run a command. Returns list of untagged responses; each is an array of tokens (parsed).
     * $literals: strings to send as {n} literals, referenced in $command by %L placeholders.
     */
    public function cmd(string $command, array $literals = []): array
    {
        $tag = 'A' . (++$this->tag);
        $parts = explode('%L', $command);
        $this->write($tag . ' ' . array_shift($parts));
        foreach ($parts as $i => $rest) {
            $lit = $literals[$i];
            $this->write('{' . strlen($lit) . "}\r\n");
            $cont = $this->line();
            if ($cont[0] !== '+') throw new ImapException('Server rejected data: ' . trim($cont));
            $this->write($lit . $rest);
        }
        $this->write("\r\n");

        $untagged = [];
        while (true) {
            $raw = $this->readResponse();
            if (strpos($raw, $tag . ' ') === 0) {
                $status = strtoupper(substr($raw, strlen($tag) + 1, 2));
                if ($status !== 'OK') {
                    $msg = trim(substr($raw, strlen($tag) + 1));
                    throw new ImapException(preg_replace('/^(NO|BAD)\s*(\[[^\]]*\]\s*)?/i', '', $msg) ?: 'Command failed');
                }
                return $untagged;
            }
            if ($raw[0] === '*') $untagged[] = $this->parse(substr($raw, 2));
        }
    }

    /** Read one full response line, inlining literals as a special marker. */
    private function readResponse(): string
    {
        $out = '';
        while (true) {
            $l = $this->line();
            if (preg_match('/\{(\d+)\}\r\n$/', $l, $m)) {
                $out .= substr($l, 0, -strlen($m[0]));
                $n = (int)$m[1];
                $buf = '';
                while (strlen($buf) < $n) {
                    $chunk = fread($this->s, min(65536, $n - strlen($buf)));
                    if ($chunk === false || $chunk === '') throw new ImapException('Connection lost while reading message');
                    $buf .= $chunk;
                }
                $out .= "\x00LIT" . base64_encode($buf) . "\x00";
                continue;
            }
            return $out . rtrim($l, "\r\n");
        }
    }

    /** Parse an IMAP response into nested arrays of tokens. */
    private function parse(string $s): array
    {
        $pos = 0;
        return $this->parseList($s, $pos, true);
    }

    private function parseList(string $s, int &$pos, bool $top = false): array
    {
        $out = [];
        $len = strlen($s);
        while ($pos < $len) {
            $c = $s[$pos];
            if ($c === ' ') { $pos++; continue; }
            if ($c === ')') { $pos++; if (!$top) return $out; continue; }
            if ($c === '(') { $pos++; $out[] = $this->parseList($s, $pos); continue; }
            if ($c === '"') {
                $pos++; $str = '';
                while ($pos < $len && $s[$pos] !== '"') {
                    if ($s[$pos] === '\\') $pos++;
                    $str .= $s[$pos++];
                }
                $pos++; $out[] = $str; continue;
            }
            if ($c === "\x00") {
                $end = strpos($s, "\x00", $pos + 1);
                $out[] = base64_decode(substr($s, $pos + 4, $end - $pos - 4));
                $pos = $end + 1; continue;
            }
            // atom (may contain [] e.g. BODY[HEADER.FIELDS (..)])
            $start = $pos; $depth = 0;
            while ($pos < $len) {
                $ch = $s[$pos];
                if ($ch === '[') $depth++;
                elseif ($ch === ']') $depth--;
                elseif ($depth === 0 && ($ch === ' ' || $ch === '(' || $ch === ')')) break;
                $pos++;
            }
            $atom = substr($s, $start, $pos - $start);
            $out[] = strtoupper($atom) === 'NIL' ? null : $atom;
        }
        return $out;
    }

    public function login(string $user, string $pass): void
    {
        $this->cmd('LOGIN %L %L', [$user, $pass]);
    }

    /** List folders: [ ['name'=>..., 'delim'=>..., 'flags'=>[...]] ] */
    public function folders(): array
    {
        $res = $this->cmd('LIST "" "*"');
        $out = [];
        foreach ($res as $r) {
            if (self::up($r[0] ?? '') !== 'LIST') continue;
            $out[] = ['flags' => array_map('strtolower', (array)$r[1]), 'delim' => $r[2], 'name' => $r[3]];
        }
        return $out;
    }

    public function status(string $folder): array
    {
        $res = $this->cmd('STATUS ' . self::quote(self::encodeFolder($folder)) . ' (MESSAGES UNSEEN)');
        foreach ($res as $r) {
            if (self::up($r[0] ?? '') === 'STATUS') {
                $kv = $r[2]; $o = [];
                for ($i = 0; $i < count($kv); $i += 2) $o[strtolower($kv[$i])] = (int)$kv[$i + 1];
                return $o;
            }
        }
        return ['messages' => 0, 'unseen' => 0];
    }

    public function select(string $folder): int
    {
        $res = $this->cmd('SELECT ' . self::quote(self::encodeFolder($folder)));
        $exists = 0;
        foreach ($res as $r) if (isset($r[1]) && self::up($r[1]) === 'EXISTS') $exists = (int)$r[0];
        return $exists;
    }

    /** UIDs matching criteria, newest first. */
    public function search(string $criteria = 'ALL'): array
    {
        $uids = [];
        foreach ($this->cmd('UID SEARCH ' . $criteria) as $r) {
            if (self::up($r[0] ?? '') === 'SEARCH') $uids = array_merge($uids, array_map('intval', array_slice($r, 1)));
        }
        rsort($uids);
        return $uids;
    }

    /** Fetch summaries for a list of UIDs. */
    public function summaries(array $uids): array
    {
        if (!$uids) return [];
        $res = $this->cmd('UID FETCH ' . implode(',', $uids) . ' (UID FLAGS RFC822.SIZE INTERNALDATE BODY.PEEK[HEADER.FIELDS (FROM TO SUBJECT DATE CONTENT-TYPE)])');
        $out = [];
        foreach ($res as $r) {
            if (self::up($r[1] ?? '') !== 'FETCH') continue;
            $d = $this->kv($r[2]);
            $uid = (int)($d['UID'] ?? 0);
            $hdr = Mime::parseHeaders((string)($d['HEADER'] ?? ''));
            $ct = strtolower($hdr['content-type'] ?? '');
            $out[$uid] = [
                'uid' => $uid,
                'flags' => array_map('strtolower', (array)($d['FLAGS'] ?? [])),
                'size' => (int)($d['RFC822.SIZE'] ?? 0),
                'date' => strtotime($hdr['date'] ?? '') ?: strtotime((string)($d['INTERNALDATE'] ?? '')) ?: time(),
                'from' => Mime::decodeHeader($hdr['from'] ?? ''),
                'to' => Mime::decodeHeader($hdr['to'] ?? ''),
                'subject' => Mime::decodeHeader($hdr['subject'] ?? ''),
                'attach' => strpos($ct, 'multipart/mixed') !== false,
            ];
        }
        $sorted = [];
        foreach ($uids as $u) if (isset($out[$u])) $sorted[] = $out[$u];
        return $sorted;
    }

    private function kv(array $list): array
    {
        $o = [];
        for ($i = 0; $i < count($list); $i += 2) {
            $k = strtoupper((string)$list[$i]);
            if (strpos($k, 'BODY[') === 0) $k = (strpos($k, 'HEADER') !== false) ? 'HEADER' : 'BODY';
            $o[$k] = $list[$i + 1] ?? null;
        }
        return $o;
    }

    /** Full raw message. */
    public function raw(int $uid, bool $markSeen = true): ?string
    {
        $res = $this->cmd('UID FETCH ' . $uid . ' (UID ' . ($markSeen ? 'BODY[]' : 'BODY.PEEK[]') . ')');
        foreach ($res as $r) {
            if (self::up($r[1] ?? '') === 'FETCH') { $d = $this->kv($r[2]); if (isset($d['BODY'])) return $d['BODY']; }
        }
        return null;
    }

    public function flag(array $uids, string $flag, bool $on = true): void
    {
        if ($uids) $this->cmd('UID STORE ' . implode(',', $uids) . ($on ? ' +' : ' -') . 'FLAGS.SILENT (' . $flag . ')');
    }

    public function move(array $uids, string $to): void
    {
        if (!$uids) return;
        $set = implode(',', $uids);
        $dest = self::quote(self::encodeFolder($to));
        if (in_array('MOVE', $this->caps(), true)) { $this->cmd("UID MOVE $set $dest"); return; }
        $this->cmd("UID COPY $set $dest");
        $this->flag($uids, '\\Deleted');
        $this->cmd('EXPUNGE');
    }

    public function delete(array $uids): void
    {
        if (!$uids) return;
        $this->flag($uids, '\\Deleted');
        $this->cmd('EXPUNGE');
    }

    public function append(string $folder, string $raw, string $flags = '\\Seen'): void
    {
        $this->cmd('APPEND ' . self::quote(self::encodeFolder($folder)) . " ($flags) %L", [$raw]);
    }

    public function create(string $folder): void
    {
        $this->cmd('CREATE ' . self::quote(self::encodeFolder($folder)));
    }

    private function caps(): array
    {
        if (!$this->capabilities) {
            foreach ($this->cmd('CAPABILITY') as $r) if (self::up($r[0] ?? '') === 'CAPABILITY') $this->capabilities = array_map('strtoupper', array_slice($r, 1));
        }
        return $this->capabilities;
    }

    /** IMAP modified UTF-7 */
    public static function encodeFolder(string $s): string
    {
        return function_exists('mb_convert_encoding') && preg_match('/[^\x20-\x7e]|&/', $s) ? mb_convert_encoding($s, 'UTF7-IMAP', 'UTF-8') : $s;
    }

    public static function decodeFolder(string $s): string
    {
        return strpos($s, '&') !== false && function_exists('mb_convert_encoding') ? mb_convert_encoding($s, 'UTF-8', 'UTF7-IMAP') : $s;
    }
}
