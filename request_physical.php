<?php
session_start();
include 'db.php';
require_once 'admin/logger.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

$admin_email = 'stazeros599@gmail.com';

$serialNum = isset($_GET['serialNum']) ? trim($_GET['serialNum']) : '';

if (empty($serialNum)) {
    header('Location: index.php');
    exit;
}

// Get certificate
$stmt = $conn->prepare("SELECT * FROM certificates WHERE serialNum = ?");
$stmt->bind_param("s", $serialNum);
$stmt->execute();
$cert = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cert) {
    header('Location: index.php');
    exit;
}

// Only e-certificates can't be requested (they're already digital)
// Physical certificates CAN be requested
if (!isset($cert['cert_type']) || $cert['cert_type'] != 'physical') {
    header('Location: index.php');
    exit;
}

// Check existing request
$stmt = $conn->prepare("SELECT * FROM physical_requests WHERE serialNum = ? AND status IN ('pending', 'approved')");
$stmt->bind_param("s", $serialNum);
$stmt->execute();
$existing_request = $stmt->get_result()->fetch_assoc();
$stmt->close();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$existing_request) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $reason = trim($_POST['reason']);
    
    $errors = [];
    if (empty($name)) $errors[] = 'Full name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (empty($phone)) $errors[] = 'Phone number is required.';
    if (empty($address)) $errors[] = 'Mailing address is required.';
    
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO physical_requests (serialNum, nokp, name, email, phone, address, reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $serialNum, $cert['nokp'], $name, $email, $phone, $address, $reason);
        
        if ($stmt->execute()) {
            $request_id = $stmt->insert_id;

            log_activity($conn, 'physical_request', 'submit', 'success', 
                "Physical request for " . $cert['serialNum'], $cert['serialNum'], 
                ['nokp' => $cert['nokp'], 'address_given' => !empty($address), 'request_id' => $request_id]);

            $stmt->close();

            $email_subject = "New Physical Certificate Request #{$request_id}: {$cert['course_name']}";
            $email_body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { max-width: 650px; margin: 0 auto; padding: 20px; }
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
                        <h2 style='margin: 0;'>📬 New Physical Certificate Request</h2>
                        <p style='margin: 5px 0 0; opacity: 0.8;'>Request ID: #{$request_id}</p>
                    </div>
                    <div class='body'>
                        <div class='field'>
                            <div class='field-label'>Applicant Name</div>
                            <div class='field-value'>" . htmlspecialchars($name) . "</div>
                        </div>
                        <div class='field'>
                            <div class='field-label'>Email</div>
                            <div class='field-value'>" . htmlspecialchars($email) . "</div>
                        </div>
                        <div class='field'>
                            <div class='field-label'>Phone</div>
                            <div class='field-value'>" . htmlspecialchars($phone) . "</div>
                        </div>
                        <div class='field'>
                            <div class='field-label'>IC Number</div>
                            <div class='field-value'>" . htmlspecialchars($cert['nokp']) . "</div>
                        </div>
                        <div class='field'>
                            <div class='field-label'>Certificate Serial Number</div>
                            <div class='field-value'>" . htmlspecialchars($cert['serialNum']) . "</div>
                        </div>
                        <div class='field'>
                            <div class='field-label'>Course</div>
                            <div class='field-value'>" . htmlspecialchars($cert['course_name']) . "</div>
                        </div>
                        <div class='field'>
                            <div class='field-label'>Mailing Address</div>
                            <div class='field-value'>" . htmlspecialchars($address) . "</div>
                        </div>
                        <div class='field'>
                            <div class='field-label'>Reason</div>
                            <div class='message'>" . (!empty($reason) ? htmlspecialchars($reason) : 'Not provided') . "</div>
                        </div>
                        <p style='margin-top: 20px; font-size: 12px; color: #888;'>
                            Submitted on " . date('d F Y, H:i') . "
                        </p>
                        <p style='margin-top: 15px; padding: 10px; background: #e8f0fe; border-radius: 4px; font-size: 13px;'>
                            <strong>💡 To review this request, log in to:</strong><br>
                            <a href='http://10.73.31.89:8000/admin/physical_requests.php'>Admin Panel → Physical Requests</a>
                        </p>
                    </div>
                </div>
            </body>
            </html>
            ";

            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'stazeros599@gmail.com';
                $mail->Password   = 'khrkmjfxtflldfni';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom('stazeros599@gmail.com', 'eCert BPMI');
                $mail->addAddress($admin_email);
                $mail->addReplyTo($email, $name);

                $mail->isHTML(true);
                $mail->Subject = $email_subject;
                $mail->Body    = $email_body;
                $mail->AltBody = "New physical certificate request from {$name} ({$email}) for certificate {$cert['serialNum']}. Mailing address: {$address}";

                $mail->send();
            } catch (Exception $e) {
                error_log('Physical certificate request email failed: ' . $mail->ErrorInfo);
            }

            header('Location: request_physical.php?serialNum=' . urlencode($serialNum) . '&success=1');
            exit;
        } else {
            $message = 'Database error: ' . $stmt->error;
            $message_type = 'danger';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'danger';
    }
}

if (isset($_GET['success'])) {
    $message = "Thank you! Your request has been submitted successfully.";
    $message_type = 'success';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Physical Certificate - eCert BPMI</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #1a3c5e 0%, #2a5f7a 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Arial, sans-serif;
            padding: 40px 20px;
        }
        .main-container { max-width: 800px; margin: 0 auto; }
        .page-header {
            background: white;
            border-radius: 10px 10px 0 0;
            padding: 25px 35px;
            border-bottom: 4px solid #ffd700;
        }
        .page-header h1 { margin: 0 0 5px; font-size: 24px; font-weight: 700; color: #1a3c5e; }
        .page-header p { margin: 0; color: #888; font-size: 14px; }
        .main-card {
            background: white;
            border-radius: 0 0 10px 10px;
            padding: 30px 35px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .cert-info {
            background: #f0f7ff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #1a3c5e;
        }
        .cert-info h4 { margin: 0 0 10px; font-size: 14px; color: #1a3c5e; text-transform: uppercase; letter-spacing: 1px; }
        .cert-info .row-info { display: flex; padding: 6px 0; border-bottom: 1px dashed #d1e2f0; }
        .cert-info .row-info:last-child { border-bottom: none; }
        .cert-info .row-info strong { width: 130px; color: #666; font-size: 13px; }
        .cert-info .row-info span { color: #1a3c5e; font-weight: 600; font-size: 14px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { font-weight: 600; color: #555; font-size: 14px; margin-bottom: 5px; }
        .form-control {
            height: 45px;
            border: 2px solid #e1e5eb;
            border-radius: 6px;
            font-size: 14px;
            padding: 0 15px;
        }
        textarea.form-control { height: auto; padding: 12px 15px; }
        .form-control:focus { border-color: #1a3c5e; box-shadow: 0 0 0 3px rgba(26,60,94,0.1); }
        .btn-submit {
            background: #1a3c5e;
            color: white;
            border: none;
            padding: 14px 35px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-submit:hover { background: #0f2a42; transform: translateY(-2px); }
        .btn-back {
            background: #6c757d;
            color: white;
            padding: 14px 25px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-back:hover { background: #5a6268; color: white; text-decoration: none; }
        .notice-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px 20px;
            margin-bottom: 25px;
            color: #856404;
            font-size: 14px;
            line-height: 1.6;
        }
        .status-box {
            background: #d4edda;
            border: 1px solid #28a745;
            border-radius: 6px;
            padding: 18px 22px;
            color: #155724;
            font-size: 14px;
            line-height: 1.6;
        }
        @media (max-width: 600px) {
            .page-header, .main-card { padding: 20px; }
        }
    </style>
</head>
<body>

<div class="main-container">
    <div class="page-header">
        <h1><span class="glyphicon glyphicon-send"></span> Request Physical Certificate</h1>
        <p>Fill in the form below to request a printed copy of your certificate</p>
    </div>
    
    <div class="main-card">
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>" style="border-radius: 8px;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="cert-info">
            <h4><span class="glyphicon glyphicon-certificate"></span> Certificate Details</h4>
            <div class="row-info"><strong>Serial No:</strong><span><?php echo htmlspecialchars($cert['serialNum']); ?></span></div>
            <div class="row-info"><strong>Name:</strong><span><?php echo htmlspecialchars($cert['name']); ?></span></div>
            <div class="row-info"><strong>Course:</strong><span><?php echo htmlspecialchars($cert['course_name']); ?></span></div>
            <div class="row-info"><strong>Course Date:</strong><span><?php echo date('d F Y', strtotime($cert['course_date'])); ?></span></div>
        </div>
        
        <?php if ($existing_request): ?>
            <div class="status-box">
                <span class="glyphicon glyphicon-info-sign"></span>
                <strong>You have already requested a physical copy for this certificate.</strong>
                <p style="margin: 10px 0 0;">
                    Request ID: <strong>#<?php echo $existing_request['request_id']; ?></strong><br>
                    Status: <strong><?php echo ucfirst($existing_request['status']); ?></strong><br>
                    Submitted: <?php echo date('d F Y, H:i', strtotime($existing_request['requested_at'])); ?>
                </p>
            </div>
            <div style="margin-top: 25px;">
                <a href="index.php" class="btn-back"><span class="glyphicon glyphicon-arrow-left"></span> Back to Search</a>
            </div>
        <?php else: ?>
            <div class="notice-box">
                <strong><span class="glyphicon glyphicon-info-sign"></span> Please note:</strong>
                Physical certificates are printed and mailed by our admin team. Make sure your contact and mailing details are correct.
                If you want to retrieve your certificate at our office, please write so in the mailing address field.
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Full Name <span style="color: #dc3545;">*</span></label>
                    <input type="text" name="name" class="form-control" 
                           value="<?php echo htmlspecialchars($cert['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email Address <span style="color: #dc3545;">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
                </div>
                
                <div class="form-group">
                    <label>Phone Number <span style="color: #dc3545;">*</span></label>
                    <input type="text" name="phone" class="form-control" placeholder="e.g. 012-3456789" required>
                </div>
                
                <div class="form-group">
                    <label>Complete Mailing Address <span style="color: #dc3545;">*</span></label>
                    <textarea name="address" class="form-control" rows="4" 
                              placeholder="House/Unit No., Street, Postcode, City, State" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Reason for Request (Optional)</label>
                    <textarea name="reason" class="form-control" rows="3" 
                              placeholder="e.g. Needed for job application, personal records, etc."></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 25px;">
                    <button type="submit" class="btn-submit">
                        <span class="glyphicon glyphicon-send"></span> Submit Request
                    </button>
                    <a href="index.php" class="btn-back">
                        <span class="glyphicon glyphicon-arrow-left"></span> Cancel
                    </a>
                </div>
            </form>
        <?php endif; ?>
        
    </div>
</div>

</body>
</html>
<?php 
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
