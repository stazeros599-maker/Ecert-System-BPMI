<?php
$config = require __DIR__ . '/mail_config.php';
require_once __DIR__ . '/mailer.php';

if (
    empty($config['password'])
    || empty($config['admin'])
    || empty($config['from'])
) {
    exit("SMTP test cannot run: password, admin, and from must be configured.\n");
}

echo 'Host: ' . $config['host'] . PHP_EOL;
echo 'Port: ' . $config['port'] . PHP_EOL;
echo 'From: ' . $config['from'] . PHP_EOL;
echo 'To: ' . $config['admin'] . PHP_EOL;
echo 'Site URL: ' . $config['site_url'] . PHP_EOL;

$body = 'This is a test message from the eCert BPMI SMTP configuration.';
$sent = send_mail(
    $config['admin'],
    'eCert BPMI Admin',
    'eCert BPMI - SMTP test',
    '<p>' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</p>',
    $body
);

echo $sent ? "OK\n" : "FAILED\n";
echo "Check the PHP error_log for failure details.\n";
