<?php
/**
 * The branded Jimohne email design used for outgoing mail.
 * Table layout + inline styles so it looks right in Gmail, Outlook and phones.
 */
function jimohne_email(string $bodyHtml, array $cfg, string $fromEmail, bool $signature = true): string
{
    $b = $cfg['brand'];
    $s = $cfg['signature'];
    $accent = $b['accent'] ?? '#1d6cf0';
    $font = "font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;";
    $sig = '';
    if ($signature) {
        $wa = !empty($s['whatsapp']) ? ' &nbsp;·&nbsp; <a href="https://wa.me/' . e($s['whatsapp']) . '" style="color:' . $accent . ';text-decoration:none">WhatsApp</a>' : '';
        $sig = '
        <tr><td style="padding:0 40px 32px 40px">
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #e8ebf0;width:100%">
            <tr><td style="padding-top:20px;' . $font . 'font-size:14px;line-height:1.6;color:#4a5361">
              <div style="font-size:15px;font-weight:700;color:#0f1720">' . e($s['name']) . '</div>
              <div>' . e($s['title']) . '</div>
              <div style="margin-top:6px">
                <a href="mailto:' . e($fromEmail) . '" style="color:' . $accent . ';text-decoration:none">' . e($fromEmail) . '</a>
                &nbsp;·&nbsp; ' . e($s['phone']) . $wa . '
              </div>
              <div><a href="https://' . e($s['website']) . '" style="color:' . $accent . ';text-decoration:none">' . e($s['website']) . '</a></div>
            </td></tr>
          </table>
        </td></tr>';
    }

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"></head>
<body style="margin:0;padding:0;background:#f3f5f8">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f5f8">
  <tr><td align="center" style="padding:28px 12px">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e8ebf0">
      <tr><td style="background:#0f1720;padding:22px 40px">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="' . $font . 'font-size:22px;font-weight:800;color:#ffffff;letter-spacing:-0.3px">' . e($b['name']) . '</td>
          <td style="padding-left:8px"><span style="display:inline-block;width:20px;height:20px;line-height:20px;border-radius:50%;background:' . $accent . ';color:#ffffff;text-align:center;font-size:12px;font-weight:700;' . $font . '">&#10003;</span></td>
        </tr></table>
        <div style="' . $font . 'font-size:12px;color:#93a0ad;margin-top:4px">' . e($b['tagline']) . '</div>
      </td></tr>
      <tr><td style="height:4px;background:' . $accent . ';font-size:0;line-height:0">&nbsp;</td></tr>
      <tr><td style="padding:36px 40px 28px 40px;' . $font . 'font-size:15px;line-height:1.7;color:#1f2933">' . $bodyHtml . '</td></tr>' . $sig . '
    </table>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px">
      <tr><td align="center" style="padding:18px 20px;' . $font . 'font-size:12px;color:#8a94a0">
        ' . e($b['name']) . ' &nbsp;·&nbsp; <a href="' . e($b['website']) . '" style="color:#8a94a0">' . e(preg_replace('#^https?://#', '', $b['website'])) . '</a>
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>';
}

/** Turn the plain text typed in the composer into safe HTML paragraphs. */
function compose_text_to_html(string $text): string
{
    $text = str_replace("\r\n", "\n", trim($text));
    $out = '';
    foreach (preg_split("/\n{2,}/", $text) as $para) {
        $p = htmlspecialchars($para, ENT_QUOTES, 'UTF-8');
        $p = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $p);
        $p = preg_replace('#(https?://[^\s<]+)#', '<a href="$1" style="color:#1d6cf0">$1</a>', $p);
        if (preg_match('/^(- .*(\n|$))+$/', $para)) {
            $items = array_map(fn($l) => '<li style="margin:0 0 6px 0">' . substr($l, 2) . '</li>', explode("\n", $p));
            $out .= '<ul style="margin:0 0 16px 0;padding-left:20px">' . implode('', $items) . '</ul>';
        } else {
            $out .= '<p style="margin:0 0 16px 0">' . nl2br($p) . '</p>';
        }
    }
    return $out;
}
