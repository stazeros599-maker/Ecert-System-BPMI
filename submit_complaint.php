<?php
session_start();
include 'db.php';
require_once 'admin/logger.php';
require_once __DIR__ . '/mailer.php';
$cfg = require __DIR__ . '/mail_config.php';

// If form submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    $errors = [];
    $database_failed = false;
    
    // Validation
    if (empty($name)) $errors[] = 'Name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (empty($subject)) $errors[] = 'Subject is required.';
    if (empty($message)) $errors[] = 'Complaint message is required.';
    
    if (empty($errors)) {
        
        // ============================================
        // STEP 1: Save to database
        // ============================================
        $stmt = $conn->prepare("INSERT INTO complaints (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $email, $phone, $subject, $message);
        
        if ($stmt->execute()) {
            $complaint_id = $stmt->insert_id;

            log_activity($conn, 'complaint', 'submit', 'success', 
                "Complaint #{$complaint_id} submitted", (string)$complaint_id, 
                ['subject' => $subject, 'email' => $email]);

            $stmt->close();
            
            // ============================================
            // STEP 2: Send email notification to admin
            // ============================================
            $email_subject = "New Complaint #{$complaint_id}: {$subject}";
            
            $email_body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #1a3c5e; color: white; padding: 15px; border-radius: 8px 8px 0 0; }
                    .body { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
                    .field { margin-bottom: 15px; }
                    .field-label { font-weight: bold; color: #1a3c5e; font-size: 12px; text-transform: uppercase; }
                    .field-value { margin-top: 3px; padding: 8px 12px; background: white; border-radius: 4px; border-left: 3px solid #c9a959; }
                    .message { background: white; padding: 15px; border-radius: 4px; border-left: 3px solid #1a3c5e; white-space: pre-wrap; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2 style='margin: 0;'>📢 New Public Complaint</h2>
                        <p style='margin: 5px 0 0; opacity: 0.8;'>Complaint ID: #{$complaint_id}</p>
                    </div>
                    <div class='body'>
                        <div class='field'>
                            <div class='field-label'>From</div>
                            <div class='field-value'>" . htmlspecialchars($name) . "</div>
                        </div>
                        <div class='field'>
                            <div class='field-label'>Email</div>
                            <div class='field-value'>" . htmlspecialchars($email) . "</div>
                        </div>
                        <div class='field'>
                            <div class='field-label'>Phone</div>
                            <div class='field-value'>" . ($phone ? htmlspecialchars($phone) : 'Not provided') . "</div>
                        </div>
                        <div class='field'>
                            <div class='field-label'>Subject</div>
                            <div class='field-value'>" . htmlspecialchars($subject) . "</div>
                        </div>
                        <div class='field'>
                            <div class='field-label'>Message</div>
                            <div class='message'>" . htmlspecialchars($message) . "</div>
                        </div>
                        <p style='margin-top: 20px; font-size: 12px; color: #888;'>
                            Submitted on " . date('d F Y, H:i') . "
                        </p>
                        <p style='margin-top: 15px; padding: 10px; background: #e8f0fe; border-radius: 4px; font-size: 13px;'>
                            <strong>💡 To manage this complaint, log in to:</strong><br>
                            <a href='{$cfg['site_url']}/admin/complaints.php'>Admin Panel → Complaints</a>
                        </p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            if (!send_mail(
                $cfg['admin'],
                '',
                $email_subject,
                $email_body,
                "New complaint from {$name} ({$email}). Subject: {$subject}. Message: {$message}",
                $email,
                $name
            )) {
                log_activity($conn, 'complaint', 'notification', 'failure',
                    'Complaint was saved but the notification email failed', (string) $complaint_id);
            }

            $_SESSION['complaint_success'] = "Thank you! Your complaint has been submitted.";
            header('Location: index.php?complaint=success');
            exit;
            
        } else {
            $database_failed = true;
            log_activity($conn, 'complaint', 'submit', 'failure',
                'Complaint could not be saved');
            $errors[] = 'Database error: ' . $stmt->error;
            $stmt->close();
        }
    }
    
    if (!empty($errors)) {
        if (!$database_failed) {
            log_activity($conn, 'complaint', 'submit', 'warning',
                'Complaint submission rejected by validation', null,
                ['validation_errors' => $errors]);
        }
        $_SESSION['complaint_errors'] = $errors;
        header('Location: index.php?complaint=error');
        exit;
    }
}

$conn->close();
header('Location: index.php');
exit;
?>