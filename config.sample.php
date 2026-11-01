<?php
// Copy this file to config.php and adjust. config.php is never committed to Git.
return [
    // Mail server (Namecheap hosting / cPanel: see Email Accounts > Connect Devices)
    'imap_host' => 'jimohne.com',
    'imap_port' => 993,
    'imap_security' => 'ssl',      // ssl | tls | none
    'smtp_host' => 'jimohne.com',
    'smtp_port' => 465,
    'smtp_security' => 'ssl',      // ssl | tls | none
    'verify_ssl' => true,

    // Only these domains may log in. Empty array = any.
    'allowed_domains' => ['jimohne.com'],

    'timezone' => 'Africa/Lagos',
    'session_hours' => 12,
    'page_size' => 30,
    'max_upload_mb' => 20,

    // Where sessions and login-throttle data are stored. Keep outside public_html if possible.
    'data_dir' => __DIR__ . '/data',

    // Branding and the signature used in the Jimohne email template
    'brand' => [
        'name' => 'Jimohne',
        'tagline' => 'Websites · Apps · Bots · AI Automation',
        'website' => 'https://jimohne.com',
        'accent' => '#1d6cf0',
    ],
    'signature' => [
        'name' => 'Ejim John Ogbele',
        'title' => 'Founder, Jimohne',
        'phone' => '+234 705 674 5426',
        'whatsapp' => '2347056745426',
        'website' => 'jimohne.com',
    ],
];
