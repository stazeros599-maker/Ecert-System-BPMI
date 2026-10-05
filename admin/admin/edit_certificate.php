<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include '../db.php';
require 'check_role.php';
require_role('admin');   // ← Only admins can access
include 'nav_stack.php';
push_nav_stack();

if (!isset($_GET['serialNum']) || empty($_GET['serialNum'])) {
    header('Location: index.php');
    exit;
}

$serialNum = $_GET['serialNum'];

// Get certificate data
$stmt = $conn->prepare("SELECT * FROM certificates WHERE serialNum = ?");
$stmt->bind_param("s", $serialNum);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: index.php');
    exit;
}

$cert = $result->fetch_assoc();
$stmt->close();

// Get current insider status
$insider_current = 0;
$stmt_p = $conn->prepare("SELECT insider FROM participant WHERE icNum = ?");
$stmt_p->bind_param("s", $cert['nokp']);
$stmt_p->execute();
$res_p = $stmt_p->get_result();
if ($row_p = $res_p->fetch_assoc()) {
    $insider_current = $row_p['insider'];
}
$stmt_p->close();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nokp = trim($_POST['nokp']);
    $name = trim($_POST['name']);
    $course_name = trim($_POST['course_name']);
    $course_date = $_POST['course_date'];
    $insider = isset($_POST['insider']) ? intval($_POST['insider']) : 0;
    $cert_type = isset($_POST['cert_type']) && in_array($_POST['cert_type'], ['e-cert', 'physical']) 
        ? $_POST['cert_type'] : $cert['cert_type'];
    
    $errors = [];
    if (empty($nokp)) $errors[] = 'IC number is required.';
    if (strlen($nokp) != 12 || !ctype_digit($nokp)) $errors[] = 'IC number must be 12 digits.';
    if (empty($name)) $errors[] = 'Name is required.';
    if (empty($course_name)) $errors[] = 'Course name is required.';
    if (empty($course_date)) $errors[] = 'Course date is required.';
    
    $cert_file = $cert['certificate_file'];
    
    // Handle file upload
    if (isset($_FILES['certificate_file']) && $_FILES['certificate_file']['error'] == 0) {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['certificate_file']['name'], PATHINFO_EXTENSION));
        $max_size = 5 * 1024 * 1024;
        
        if ($_FILES['certificate_file']['size'] > $max_size) {
            $errors[] = 'File size exceeds 5MB limit.';
        } elseif (in_array($ext, $allowed)) {
            $old_file = '../certificates/' . $cert['certificate_file'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
            
            $cert_file = 'cert_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $upload_path = '../certificates/' . $cert_file;
            if (!move_uploaded_file($_FILES['certificate_file']['tmp_name'], $upload_path)) {
                $errors[] = 'Failed to upload file.';
                $cert_file = $cert['certificate_file'];
            }
        } else {
            $errors[] = 'File must be PDF, JPG, JPEG, PNG, or GIF.';
        }
    }
    
    if (empty($errors)) {
        // Update participant
        $check = $conn->prepare("SELECT icNum FROM participant WHERE icNum = ?");
        $check->bind_param("s", $nokp);
        $check->execute();
        $check_result = $check->get_result();
        
        if ($check_result->num_rows == 0) {
            $stmt_p = $conn->prepare("INSERT INTO participant (icNum, fullName, insider) VALUES (?, ?, ?)");
            $stmt_p->bind_param("ssi", $nokp, $name, $insider);
            $stmt_p->execute();
            $stmt_p->close();
        } else {
            $stmt_p = $conn->prepare("UPDATE participant SET fullName = ?, insider = ? WHERE icNum = ?");
            $stmt_p->bind_param("sis", $name, $insider, $nokp);
            $stmt_p->execute();
            $stmt_p->close();
        }
        $check->close();
        
        // Sync certificate names for this IC
        $sync = $conn->prepare("UPDATE certificates SET name = ? WHERE nokp = ?");
        $sync->bind_param("ss", $name, $nokp);
        $sync->execute();
        $sync->close();
        
        // Update the certificate (with cert_type)
        $stmt = $conn->prepare("UPDATE certificates SET nokp = ?, name = ?, course_name = ?, course_date = ?, certificate_file = ?, cert_type = ? WHERE serialNum = ?");
        $stmt->bind_param("sssssss", $nokp, $name, $course_name, $course_date, $cert_file, $cert_type, $serialNum);
        
        if ($stmt->execute()) {
            $_SESSION['admin_message'] = 'Certificate updated successfully!';
            $_SESSION['admin_message_type'] = 'success';
            header('Location: index.php');
            exit;
        } else {
            $message = 'Database error: ' . $stmt->error;
            $message_type = 'danger';
        }
        $stmt->close();
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Certificate - eCert BPMI</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <style>
        * { box-sizing: border-box; }
        body { background: #f0f2f5; padding-top: 70px; font-family: 'Segoe UI', Arial, sans-serif; overflow-x: hidden; }
        .navbar { background: #1a3c5e; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.2); min-height: 60px; }
        .navbar-brand { color: white !important; font-weight: 600; font-size: 18px; padding: 18px 15px; }
        .navbar-nav > li > a { color: white !important; padding: 18px 15px; }
        .navbar-nav > li > a:hover { background: rgba(255,255,255,0.1) !important; }
        .navbar-toggle { border-color: rgba(255,255,255,0.3); margin-top: 12px; }
        .navbar-toggle .icon-bar { background-color: white; }
        .container { max-width: 700px; padding: 0 15px; }
        .panel { border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: none; overflow: hidden; }
        .panel-heading { background: #1a3c5e !important; color: white !important; border-radius: 10px 10px 0 0 !important; padding: 15px 20px; font-size: 16px; font-weight: 600; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        .panel-heading .badge { background: rgba(255,255,255,0.2); font-size: 13px; padding: 4px 12px; }
        .panel-body { padding: 25px 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { font-weight: 600; color: #555; display: block; margin-bottom: 5px; font-size: 14px; }
        .required:after { content: " *"; color: #d9534f; }
        .form-control { border-radius: 6px; border: 2px solid #e1e5eb; height: 45px; font-size: 14px; padding: 0 15px; width: 100%; transition: all 0.2s; }
        .form-control:focus { border-color: #1a3c5e; box-shadow: 0 0 0 3px rgba(26, 60, 94, 0.1); outline: none; }
        .form-control[type="file"] { height: auto; padding: 10px; border: 2px dashed #e1e5eb; background: #fafafa; cursor: pointer; }
        .text-muted { color: #888; font-size: 12px; margin-top: 5px; display: block; }
        .current-file { background: #f8f9fa; padding: 12px 15px; border-radius: 6px; border: 1px solid #e1e5eb; margin-bottom: 10px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .current-file .glyphicon { color: #1a3c5e; font-size: 18px; }
        .current-file .file-name-display { font-weight: 600; color: #333; word-break: break-all; }
        .current-file .file-size { color: #888; font-size: 12px; margin-left: auto; }
        .btn-submit { background: #1a3c5e; border: none; padding: 12px 30px; font-weight: 600; border-radius: 6px; color: white; transition: all 0.3s; font-size: 15px; }
        .btn-submit:hover { background: #0f2a42; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(26, 60, 94, 0.3); color: white; }
        .btn-cancel { background: #6c757d; border: none; padding: 12px 30px; font-weight: 600; border-radius: 6px; color: white; transition: all 0.3s; font-size: 15px; text-decoration: none; }
        .btn-cancel:hover { background: #5a6268; color: white; }
        .alert { border-radius: 10px; padding: 12px 20px; margin-bottom: 20px; }
        .alert .close { font-size: 20px; }
        .form-actions { margin-top: 25px; display: flex; gap: 10px; flex-wrap: wrap; }
        @media (max-width: 768px) {
            body { padding-top: 60px; }
            .navbar-brand { font-size: 16px; padding: 15px 10px; }
            .navbar-nav > li > a { padding: 10px 15px; }
            .container { padding: 0 10px; }
            .panel-body { padding: 20px 15px; }
            .panel-heading { font-size: 15px; padding: 12px 15px; flex-direction: column; align-items: stretch; text-align: center; }
            .form-control { height: 40px; font-size: 13px; }
            .btn-submit, .btn-cancel { padding: 10px 20px; font-size: 14px; width: 100%; text-align: center; }
            .form-actions { flex-direction: column; }
            .current-file { flex-direction: column; text-align: center; }
            .current-file .file-size { margin-left: 0; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-fixed-top">
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#nav">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="index.php" style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                    <img src="../images/logo-perikanan.png" alt="Logo" style="height: 45px; width: auto;">
                    <span>eCert BPMI</span>
                </a>
            </div>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="nav navbar-nav">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="add_certificate.php">Add Certificate</a></li>
                    <li><a href="complaints.php">Complaints</a></li>
                </ul>
                <ul class="nav navbar-nav navbar-right">
                    <li><a href="logout.php"><span class="glyphicon glyphicon-log-out"></span> Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="panel panel-default">
            <div class="panel-heading">
                <div><span class="glyphicon glyphicon-edit"></span> Edit Certificate</div>
                <span class="badge">Serial: <?php echo htmlspecialchars($cert['serialNum']); ?></span>
            </div>
            <div class="panel-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade in">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Serial Number</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($cert['serialNum']); ?>" disabled style="background: #f5f5f5;">
                        <small class="text-muted">Serial number cannot be changed.</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">IC Number</label>
                        <input type="text" name="nokp" class="form-control" maxlength="12" 
                               pattern="[0-9]{12}" title="Must be 12 digits"
                               value="<?php echo htmlspecialchars($cert['nokp']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Full Name</label>
                        <input type="text" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($cert['name']); ?>" required>
                    </div>
                    
                    <!-- PARTICIPANT TYPE -->
                    <div class="form-group" style="padding: 15px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e1e5eb;">
                        <label class="required" style="display: block; margin-bottom: 10px;">
                            Participant Type
                        </label>
                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500; color: #333; margin: 0;">
                                <input type="radio" name="insider" value="1" 
                                       <?php echo $insider_current == 1 ? 'checked' : ''; ?>
                                       style="width: 18px; height: 18px; cursor: pointer; margin: 0;">
                                <span style="font-size: 14px;">
                                    <span class="glyphicon glyphicon-user" style="color: #28a745;"></span>
                                    Perikanan Staff (Insider)
                                </span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500; color: #333; margin: 0;">
                                <input type="radio" name="insider" value="0" 
                                       <?php echo $insider_current == 0 ? 'checked' : ''; ?>
                                       style="width: 18px; height: 18px; cursor: pointer; margin: 0;">
                                <span style="font-size: 14px;">
                                    <span class="glyphicon glyphicon-user" style="color: #6c757d;"></span>
                                    Non-Insider (Public)
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- CERTIFICATE TYPE -->
                    <div class="form-group" style="padding: 15px; background: #f0f7ff; border-radius: 6px; border: 1px solid #b8d4f0;">
                        <label class="required" style="display: block; margin-bottom: 10px;">
                            Certificate Type
                        </label>
                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500; color: #333; margin: 0;">
                                <input type="radio" name="cert_type" value="e-cert" 
                                       <?php echo $cert['cert_type'] == 'e-cert' ? 'checked' : ''; ?>
                                       style="width: 18px; height: 18px; cursor: pointer; margin: 0;">
                                <span style="font-size: 14px;">
                                    <span class="glyphicon glyphicon-cloud" style="color: #17a2b8;"></span>
                                    E-Certificate
                                </span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500; color: #333; margin: 0;">
                                <input type="radio" name="cert_type" value="physical"
                                       <?php echo $cert['cert_type'] == 'physical' ? 'checked' : ''; ?>
                                       style="width: 18px; height: 18px; cursor: pointer; margin: 0;">
                                <span style="font-size: 14px;">
                                    <span class="glyphicon glyphicon-file" style="color: #ffc107;"></span>
                                    Physical Certificate
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Course Name</label>
                        <input type="text" name="course_name" class="form-control" 
                               value="<?php echo htmlspecialchars($cert['course_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Course Date</label>
                        <input type="date" name="course_date" class="form-control" 
                               value="<?php echo htmlspecialchars($cert['course_date']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Certificate File</label>
                        <div class="current-file">
                            <span class="glyphicon glyphicon-file"></span>
                            <span class="file-name-display"><?php echo htmlspecialchars($cert['certificate_file']); ?></span>
                            <span class="file-size">
                                <?php
                                $file_path = '../certificates/' . $cert['certificate_file'];
                                if (file_exists($file_path)) {
                                    echo number_format(filesize($file_path) / 1024, 2) . ' KB';
                                } else {
                                    echo 'File not found';
                                }
                                ?>
                            </span>
                        </div>
                        <input type="file" name="certificate_file" class="form-control" 
                               accept=".pdf,.jpg,.jpeg,.png,.gif">
                        <small class="text-muted">Leave empty to keep current file. Max 5MB.</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-submit">
                            <span class="glyphicon glyphicon-save"></span> Update Certificate
                        </button>
                        <a href="index.php" class="btn-cancel">
                            <span class="glyphicon glyphicon-remove"></span> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>
<?php 
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
</html>