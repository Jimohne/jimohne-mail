<?php
/**
 * Cleans incoming HTML email. The result is also shown inside a sandboxed iframe
 * with a strict Content-Security-Policy, so scripts can never run.
 */
class Sanitize
{
    private const DROP = ['script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button',
        'select', 'textarea', 'meta', 'link', 'base', 'svg', 'math', 'noscript', 'template', 'audio', 'video', 'source'];

    /** @return array [html, remoteImageCount] */
    public static function email(string $html, callable $cidUrl): array
    {
        if (trim($html) === '') return ['', 0];
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        foreach (self::DROP as $tag) {
            $nodes = $doc->getElementsByTagName($tag);
            for ($i = $nodes->length - 1; $i >= 0; $i--) $nodes->item($i)->parentNode->removeChild($nodes->item($i));
        }
        $remote = 0;
        $xp = new DOMXPath($doc);
        foreach ($xp->query('//*') as $el) {
            /** @var DOMElement $el */
            $remove = [];
            foreach ($el->attributes as $a) {
                $n = strtolower($a->name); $v = trim($a->value);
                if (strpos($n, 'on') === 0 || $n === 'formaction' || $n === 'srcset') { $remove[] = $a->name; continue; }
                if (in_array($n, ['href', 'src', 'background', 'action', 'xlink:href'], true)) {
                    if (preg_match('/^\s*(javascript|vbscript|data:text)/i', $v)) { $remove[] = $a->name; continue; }
                }
                if ($n === 'style' && preg_match('/expression\s*\(|javascript:|behavior\s*:/i', $v)) $remove[] = $a->name;
            }
            foreach ($remove as $r) $el->removeAttribute($r);

            $tag = strtolower($el->tagName);
            if ($tag === 'a') { $el->setAttribute('target', '_blank'); $el->setAttribute('rel', 'noopener noreferrer'); }
            if ($tag === 'img') {
                $src = $el->getAttribute('src');
                if (stripos($src, 'cid:') === 0) $el->setAttribute('src', $cidUrl(substr($src, 4)));
                elseif (preg_match('#^https?://#i', $src)) $remote++;
            }
        }
        $body = $doc->getElementsByTagName('body')->item(0);
        $headStyles = '';
        foreach ($doc->getElementsByTagName('style') as $st) {
            if ($body && !self::isInside($st, $body)) $headStyles .= $doc->saveHTML($st);
        }
        $out = '';
        if ($body) foreach ($body->childNodes as $c) $out .= $doc->saveHTML($c);
        else $out = $doc->saveHTML();
        return [$headStyles . $out, $remote];
    }

    private static function isInside(DOMNode $n, DOMNode $ancestor): bool
    {
        for ($p = $n->parentNode; $p; $p = $p->parentNode) if ($p === $ancestor) return true;
        return false;
    }

    public static function textToHtml(string $t): string
    {
        $e = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
        $e = preg_replace('#(https?://[^\s<]+)#', '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', $e);
        return '<div style="white-space:pre-wrap;font-family:inherit">' . $e . '</div>';
    }
}
