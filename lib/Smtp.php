<?php
/**
 * Minimal authenticated SMTP sender (SSL on 465 or STARTTLS on 587).
 */
class SmtpException extends Exception {}

class Smtp
{
    private $s;

    public function __construct(string $host, int $port, string $security = 'ssl', int $timeout = 30, bool $verifyPeer = true)
    {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => $verifyPeer, 'verify_peer_name' => $verifyPeer, 'allow_self_signed' => !$verifyPeer]]);
        $proto = $security === 'ssl' ? 'ssl://' : 'tcp://';
        $this->s = @stream_socket_client($proto . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->s) throw new SmtpException("Cannot connect to outgoing mail server ($errstr)");
        stream_set_timeout($this->s, $timeout);
        $this->expect(220);
        $this->ehlo();
        if ($security === 'tls') {
            $this->send('STARTTLS', 220);
            if (!stream_socket_enable_crypto($this->s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new SmtpException('STARTTLS failed');
            $this->ehlo();
        }
    }

    public function __destruct()
    {
        if ($this->s) { @fwrite($this->s, "QUIT\r\n"); @fclose($this->s); }
    }

    private function ehlo(): void
    {
        $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $this->send('EHLO ' . preg_replace('/[^a-z0-9.\-]/i', '', $host), 250);
    }

    private function read(): array
    {
        $text = '';
        while (true) {
            $l = fgets($this->s, 4096);
            if ($l === false) throw new SmtpException('Outgoing mail server closed the connection');
            $text .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') return [(int)substr($l, 0, 3), $text];
        }
    }

    private function expect($codes): string
    {
        [$code, $text] = $this->read();
        if (!in_array($code, (array)$codes, true)) {
            throw new SmtpException(trim(preg_replace('/^\d{3}[ -]/m', '', $text)) ?: "SMTP error $code");
        }
        return $text;
    }

    private function send(string $line, $codes): string
    {
        fwrite($this->s, $line . "\r\n");
        return $this->expect($codes);
    }

    public function auth(string $user, string $pass): void
    {
        try {
            $this->send('AUTH LOGIN', 334);
            $this->send(base64_encode($user), 334);
            $this->send(base64_encode($pass), 235);
        } catch (SmtpException $e) {
            $this->send('AUTH PLAIN ' . base64_encode("\0$user\0$pass"), 235);
        }
    }

    /** @param string[] $recipients bare email addresses */
    public function mail(string $from, array $recipients, string $raw): void
    {
        $this->send('MAIL FROM:<' . $from . '>', 250);
        foreach ($recipients as $r) $this->send('RCPT TO:<' . $r . '>', [250, 251]);
        $this->send('DATA', 354);
        $raw = str_replace("\r\n.", "\r\n..", $raw); // dot-stuffing
        if (strpos($raw, '.') === 0) $raw = '.' . $raw;
        fwrite($this->s, $raw . (substr($raw, -2) === "\r\n" ? '' : "\r\n") . ".\r\n");
        $this->expect(250);
    }
}
