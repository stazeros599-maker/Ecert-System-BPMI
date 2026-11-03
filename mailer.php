<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

function send_mail(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody,
    ?string $replyToEmail = null,
    ?string $replyToName = null,
    ?string $ccEmail = null
): bool {
    $cfg = require __DIR__ . '/mail_config.php';
    if ($cfg['password'] === '') {
        error_log('Email notification was not sent because MAIL_PASSWORD is not configured.');
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $cfg['username'];
        $mail->Password = $cfg['password'];
        $mail->SMTPSecure = $cfg['secure'] === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $cfg['port'];
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 10;

        $mail->setFrom($cfg['from'], $cfg['from_name']);
        $mail->addAddress($toEmail, $toName);

        if (
            filter_var($ccEmail, FILTER_VALIDATE_EMAIL)
            && strcasecmp($ccEmail, $toEmail) !== 0
        ) {
            $mail->addCC($ccEmail);
        }

        if (filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
            $replyToName = str_replace(["\r", "\n"], '', $replyToName ?? '');
            $mail->addReplyTo($replyToEmail, $replyToName);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Email to ' . $toEmail . ' failed: ' . $mail->ErrorInfo);
        return false;
    }
}
