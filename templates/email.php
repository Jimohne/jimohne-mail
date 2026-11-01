<?php
/**
 * The branded Jimohne email design used for outgoing mail.
 * Table layout + inline styles so it looks right in Gmail, Outlook and phones.
 */
function jimohne_email(string $bodyHtml, array $cfg, string $fromEmail, bool $signature = true): string
{
    $b = $cfg['brand'];
    $s = $cfg['signature'];
    // Luxury palette: ink, ivory and champagne gold
    $ink = '#0e0e10'; $ivory = '#faf7f1'; $paper = '#ffffff'; $gold = '#b8955a'; $goldSoft = '#e7dcc7'; $text = '#2a2a2e'; $muted = '#8a8478';
    $serif = "font-family:'Playfair Display',Didot,'Bodoni MT',Georgia,'Times New Roman',serif;";
    $sans = "font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;";
    $site = preg_replace('#^https?://#', '', $b['website']);
    $initial = mb_strtoupper(mb_substr($b['name'], 0, 1));
    $brandBase = rtrim($b['website'], '/') . '/mail/assets/brand/';
    $logoUrl = $brandBase . 'jimohne-logo-white.png';
    $iconUrl = $brandBase . 'jimohne-icon-512.png';

    $sig = '';
    if ($signature) {
        $wa = !empty($s['whatsapp']) ? '<a href="https://wa.me/' . e($s['whatsapp']) . '" style="color:' . $gold . ';text-decoration:none">WhatsApp</a>&nbsp;&nbsp;<span style="color:' . $goldSoft . '">|</span>&nbsp;&nbsp;' : '';
        $sig = '
          <tr><td class="jm-pad" style="padding:8px 56px 48px 56px">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr><td style="border-top:1px solid ' . $goldSoft . ';font-size:0;line-height:0;height:1px">&nbsp;</td></tr>
              <tr><td style="padding-top:28px">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                  <td valign="top" style="padding-right:18px">
                    <img src="' . e($iconUrl) . '" width="48" height="50" alt="" style="display:block;width:48px;height:auto;border:0">
                  </td>
                  <td valign="top" style="' . $sans . 'font-size:13px;line-height:1.7;color:' . $muted . '">
                    <div style="' . $serif . 'font-size:19px;color:' . $ink . ';letter-spacing:.2px;line-height:1.3">' . e($s['name']) . '</div>
                    <div style="font-size:10.5px;letter-spacing:2.6px;text-transform:uppercase;color:' . $gold . ';margin:3px 0 10px 0">' . e($s['title']) . '</div>
                    <div><a href="mailto:' . e($fromEmail) . '" style="color:' . $text . ';text-decoration:none">' . e($fromEmail) . '</a></div>
                    <div>' . e($s['phone']) . '</div>
                    <div style="margin-top:6px">' . $wa . '<a href="https://' . e($s['website']) . '" style="color:' . $gold . ';text-decoration:none">' . e($s['website']) . '</a></div>
                  </td>
                </tr></table>
              </td></tr>
            </table>
          </td></tr>';
    }

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">
<style>a{color:' . $gold . '}@media (max-width:620px){.jm-pad{padding-left:26px!important;padding-right:26px!important}.jm-h{padding-left:26px!important;padding-right:26px!important}}</style></head>
<body style="margin:0;padding:0;background:' . $ivory . ';-webkit-text-size-adjust:100%">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $ivory . '">
  <tr><td align="center" style="padding:40px 14px">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px;background:' . $paper . ';border:1px solid #ece4d6">
      <!-- header -->
      <tr><td class="jm-h" align="center" style="background:' . $ink . ';padding:40px 56px 34px 56px">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td align="center">
          <img src="' . e($logoUrl) . '" width="230" height="74" alt="' . e($b['name']) . '" style="display:block;width:230px;height:auto;border:0;margin:0 auto">
        </td></tr>
        <tr><td align="center" style="padding-top:12px">
          <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
            <td style="width:34px;border-top:1px solid ' . $gold . ';font-size:0;line-height:0">&nbsp;</td>
            <td style="padding:0 12px;' . $sans . 'font-size:10px;letter-spacing:3px;text-transform:uppercase;color:#c9bfae;white-space:nowrap">' . e($b['tagline']) . '</td>
            <td style="width:34px;border-top:1px solid ' . $gold . ';font-size:0;line-height:0">&nbsp;</td>
          </tr></table>
        </td></tr></table>
      </td></tr>
      <tr><td style="height:3px;background:' . $gold . ';font-size:0;line-height:0">&nbsp;</td></tr>
      <!-- body -->
      <tr><td class="jm-pad" style="padding:48px 56px 22px 56px;' . $sans . 'font-size:15.5px;line-height:1.8;color:' . $text . '">' . $bodyHtml . '</td></tr>' . $sig . '
    </table>
    <!-- footer -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px">
      <tr><td align="center" style="padding:26px 20px 6px 20px;' . $serif . 'font-size:15px;color:' . $ink . ';letter-spacing:3px;text-transform:uppercase">' . e($b['name']) . '</td></tr>
      <tr><td align="center" style="padding:0 20px 8px 20px;' . $sans . 'font-size:11px;letter-spacing:1.5px;color:' . $muted . '">
        <a href="' . e($b['website']) . '" style="color:' . $muted . ';text-decoration:none">' . e($site) . '</a>
      </td></tr>
      <tr><td align="center" style="padding:0 20px;' . $sans . 'font-size:10.5px;color:#b3ab9d">&copy; ' . date('Y') . ' ' . e($b['name']) . '. Crafted with care.</td></tr>
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
        $p = preg_replace('/\*\*(.+?)\*\*/s', '<strong style="color:#0e0e10;font-weight:600">$1</strong>', $p);
        $p = preg_replace('#(https?://[^\s<]+)#', '<a href="$1" style="color:#b8955a;text-decoration:underline">$1</a>', $p);
        if (preg_match('/^(- .*(\n|$))+$/', $para)) {
            $items = array_map(fn($l) => '<li style="margin:0 0 8px 0;padding-left:4px">' . substr($l, 2) . '</li>', explode("\n", $p));
            $out .= '<ul style="margin:0 0 20px 0;padding-left:22px;color:#2a2a2e">' . implode('', $items) . '</ul>';
        } else {
            $out .= '<p style="margin:0 0 20px 0">' . nl2br($p) . '</p>';
        }
    }
    return $out;
}
