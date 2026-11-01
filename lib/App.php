<?php
require_once __DIR__ . '/Imap.php';
require_once __DIR__ . '/Mime.php';
require_once __DIR__ . '/Smtp.php';
require_once __DIR__ . '/Sanitize.php';

class App
{
    public static array $cfg = [];
    private static ?Imap $imap = null;

    public static function boot(): void
    {
        $file = dirname(__DIR__) . '/config.php';
        if (!is_file($file)) $file = dirname(__DIR__) . '/config.sample.php';
        self::$cfg = require $file;
        date_default_timezone_set(self::$cfg['timezone'] ?? 'Africa/Lagos');
        mb_internal_encoding('UTF-8');

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_name('jm_sess');
        session_set_cookie_params(['lifetime' => 0, 'path' => self::basePath(), 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
        $dir = self::dataDir() . '/sessions';
        if (is_dir($dir) || @mkdir($dir, 0700, true)) session_save_path($dir);
        ini_set('session.gc_maxlifetime', (string)(self::$cfg['session_hours'] * 3600));
        session_start();

        if (!empty($_SESSION['user']) && (time() - ($_SESSION['seen'] ?? 0)) > self::$cfg['session_hours'] * 3600) self::logout();
        $_SESSION['seen'] = time();

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; frame-src 'self'; form-action 'self'; base-uri 'none'");
    }

    public static function dataDir(): string
    {
        $d = self::$cfg['data_dir'] ?? (dirname(__DIR__) . '/data');
        if (!is_dir($d)) @mkdir($d, 0700, true);
        return $d;
    }

    public static function basePath(): string
    {
        $p = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        return $p === '' ? '/' : $p . '/';
    }

    public static function url(array $q = []): string
    {
        return self::basePath() . ($q ? '?' . http_build_query($q) : '');
    }

    public static function redirect(array $q = []): void
    {
        header('Location: ' . self::url($q));
        exit;
    }

    public static function flash(?string $msg = null, string $type = 'ok')
    {
        if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
        $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
    }

    // ---------- CSRF ----------
    public static function csrf(): string
    {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf'];
    }

    public static function checkCsrf(): void
    {
        if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            exit('Your session expired. Go back and try again.');
        }
    }

    // ---------- Auth ----------
    public static function user(): ?string
    {
        return $_SESSION['user'] ?? null;
    }

    private static function cryptoKey(bool $create = false): ?string
    {
        if (!empty($_COOKIE['jm_k'])) return base64_decode($_COOKIE['jm_k']);
        if (!$create) return null;
        $k = random_bytes(32);
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('jm_k', base64_encode($k), ['expires' => 0, 'path' => self::basePath(), 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
        $_COOKIE['jm_k'] = base64_encode($k);
        return $k;
    }

    public static function password(): ?string
    {
        $k = self::cryptoKey();
        if (!$k || empty($_SESSION['pw'])) return null;
        [$iv, $tag, $ct] = array_map('base64_decode', explode('.', $_SESSION['pw']));
        $p = openssl_decrypt($ct, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag);
        return $p === false ? null : $p;
    }

    public static function login(string $email, string $pass): void
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Enter a valid email address.');
        $allowed = self::$cfg['allowed_domains'] ?? [];
        if ($allowed && !in_array(substr(strrchr($email, '@'), 1), $allowed, true)) throw new Exception('This webmail is only for ' . implode(', ', $allowed) . ' addresses.');
        self::throttle($email);

        $imap = self::connect();
        try { $imap->login($email, $pass); }
        catch (ImapException $e) { self::throttle($email, true); throw new Exception('Wrong email or password.'); }

        session_regenerate_id(true);
        $k = self::cryptoKey(true);
        $iv = random_bytes(12);
        $ct = openssl_encrypt($pass, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag);
        $_SESSION['user'] = $email;
        $_SESSION['pw'] = base64_encode($iv) . '.' . base64_encode($tag) . '.' . base64_encode($ct);
        $_SESSION['seen'] = time();
        unset($_SESSION['csrf']);
        self::$imap = $imap;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        setcookie('jm_k', '', ['expires' => time() - 3600, 'path' => self::basePath()]);
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }

    /** Simple brute-force protection: 8 failed logins per 15 min per IP+email. */
    private static function throttle(string $email, bool $fail = false): void
    {
        $f = self::dataDir() . '/throttle.json';
        $all = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
        $key = sha1(($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $email);
        $now = time();
        foreach ($all as $k => $v) if ($v['t'] < $now - 900) unset($all[$k]);
        if ($fail) {
            $all[$key] = ['n' => ($all[$key]['n'] ?? 0) + 1, 't' => $now];
            file_put_contents($f, json_encode($all), LOCK_EX);
            return;
        }
        if (($all[$key]['n'] ?? 0) >= 8) throw new Exception('Too many failed attempts. Wait 15 minutes and try again.');
    }

    // ---------- Mail connections ----------
    public static function connect(): Imap
    {
        $c = self::$cfg;
        return new Imap($c['imap_host'], (int)$c['imap_port'], $c['imap_security'], 20, (bool)$c['verify_ssl']);
    }

    public static function imap(): Imap
    {
        if (self::$imap) return self::$imap;
        $pw = self::password();
        if (!self::user() || $pw === null) { self::logout(); self::redirect(); }
        $imap = self::connect();
        $imap->login(self::user(), $pw);
        return self::$imap = $imap;
    }

    public static function smtp(): Smtp
    {
        $c = self::$cfg;
        $s = new Smtp($c['smtp_host'], (int)$c['smtp_port'], $c['smtp_security'], 30, (bool)$c['verify_ssl']);
        $s->auth(self::user(), (string)self::password());
        return $s;
    }

    /** Folders with roles, cached in the session for 5 minutes. */
    public static function folders(bool $refresh = false): array
    {
        if (!$refresh && !empty($_SESSION['folders']) && $_SESSION['folders_t'] > time() - 300) return $_SESSION['folders'];
        $list = self::imap()->folders();
        $roles = ['inbox' => null, 'sent' => null, 'drafts' => null, 'junk' => null, 'trash' => null, 'archive' => null];
        $names = ['sent' => ['sent', 'sent items', 'sent messages', 'inbox.sent'], 'drafts' => ['drafts', 'inbox.drafts'], 'junk' => ['junk', 'spam', 'inbox.junk', 'inbox.spam'], 'trash' => ['trash', 'deleted items', 'inbox.trash'], 'archive' => ['archive', 'inbox.archive']];
        foreach ($list as $f) {
            if (in_array('\\noselect', $f['flags'], true)) continue;
            $n = $f['name']; $ln = strtolower($n);
            if ($ln === 'inbox') { $roles['inbox'] = $n; continue; }
            foreach (['sent', 'drafts', 'junk', 'trash', 'archive'] as $r) {
                if ($roles[$r] === null && (in_array('\\' . $r, $f['flags'], true) || in_array($ln, $names[$r], true))) $roles[$r] = $n;
            }
        }
        $roles['inbox'] = $roles['inbox'] ?? 'INBOX';
        $out = ['roles' => $roles, 'all' => []];
        foreach ($list as $f) {
            if (in_array('\\noselect', $f['flags'], true)) continue;
            $role = array_search($f['name'], $roles, true) ?: '';
            $label = $role ? ($role === 'junk' ? 'Spam' : ucfirst($role)) : Imap::decodeFolder(preg_replace('/^INBOX[.\/]/i', '', $f['name']));
            $out['all'][] = ['name' => $f['name'], 'label' => $label, 'role' => $role];
        }
        $order = ['inbox' => 0, 'drafts' => 1, 'sent' => 2, 'archive' => 3, 'junk' => 4, 'trash' => 5, '' => 9];
        usort($out['all'], fn($a, $b) => [$order[$a['role']], $a['label']] <=> [$order[$b['role']], $b['label']]);
        $_SESSION['folders'] = $out; $_SESSION['folders_t'] = time();
        return $out;
    }

    public static function role(string $role): ?string
    {
        return self::folders()['roles'][$role] ?? null;
    }

    /** Ensure a role folder exists (creates Sent/Drafts/Trash if missing). */
    public static function ensure(string $role): string
    {
        $f = self::role($role);
        if ($f) return $f;
        $sep = '.';
        foreach (self::imap()->folders() as $x) { $sep = $x['delim'] ?: '.'; break; }
        $name = ($sep === '.' ? 'INBOX.' : '') . ucfirst($role);
        try { self::imap()->create($name); } catch (ImapException $e) { /* may already exist */ }
        self::folders(true);
        return $name;
    }
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function human_size(int $b): string
{
    if ($b < 1024) return $b . ' B';
    if ($b < 1048576) return round($b / 1024) . ' KB';
    return round($b / 1048576, 1) . ' MB';
}

function short_date(int $t): string
{
    if (date('Y-m-d', $t) === date('Y-m-d')) return date('g:i A', $t);
    if (date('Y', $t) === date('Y')) return date('M j', $t);
    return date('M j, Y', $t);
}
