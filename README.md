# Jimohne Mail

A lightweight, branded webmail for **ejim@jimohne.com**, hosted at **https://jimohne.com/mail**.

- Sign in with any `@jimohne.com` mailbox (cPanel email account)
- Inbox, Sent, Drafts, Spam, Trash and custom folders
- Read mail safely: HTML emails are cleaned and shown in a sandbox; web images are blocked until you click **Show images**
- Compose, reply, reply all, forward (with original attachments), save drafts
- Branded **Jimohne email template** and signature on outgoing mail, with live preview
- Quick templates (proposal, follow-up, invoice, thank you, meeting)
- Attachments up to 20 MB, search, bulk actions, mobile layout

No database and no PHP extensions beyond the standard ones (OpenSSL, mbstring, iconv, DOM, fileinfo). IMAP and SMTP are spoken directly over TLS sockets.

## Install

1. Upload this folder to `public_html/mail`.
2. Copy `config.sample.php` to `config.php` and check the mail server settings
   (cPanel → Email Accounts → Connect Devices). Set `data_dir` to a folder outside `public_html`.
3. Open https://jimohne.com/mail and sign in.

## Customise

- Brand, colours and signature: `config.php`
- Outgoing email design: `templates/email.php`
- Quick templates: `templates/snippets.php`
- Webmail look: `assets/style.css`

## Security

- Passwords are kept only in the session, encrypted with a key that lives in a separate cookie
- CSRF tokens on every action, login throttling (8 failures / 15 min), strict Content-Security-Policy
- `lib/`, `templates/`, `data/` and `config.php` are blocked from the web by `.htaccess`
