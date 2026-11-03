<?php
// Values are supplied via environment variables (cPanel or .htaccess SetEnv).
return [
    'host' => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
    'port' => (int) (getenv('MAIL_PORT') ?: 587),
    'secure' => getenv('MAIL_SECURE') ?: 'tls',
    'username' => getenv('MAIL_USERNAME') ?: 'stazeros599@gmail.com',
    'password' => getenv('MAIL_PASSWORD') ?: 'nfkellshjbvlojhe',
    'from' => getenv('MAIL_FROM') ?: 'stazeros599@gmail.com',
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'eCert BPMI',
    'admin' => getenv('MAIL_ADMIN') ?: 'stazeros599@gmail.com',
    'site_url' => rtrim(getenv('MAIL_SITE_URL') ?: 'https://faezah.useruno.com', '/'),
];
