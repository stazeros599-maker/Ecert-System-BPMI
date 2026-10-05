<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include '../db.php';
require 'check_role.php';
require_role('admin');

$admin_id = $_SESSION['admin_id'];
include 'nav_stack.php';
push_nav_stack();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_submit'])) {
    $course_name = trim($_POST['course_name']);
    $course_date = $_POST['course_date'];
    $cert_type = in_array($_POST['cert_type'] ?? 'e-cert', ['e-cert', 'physical']) 
        ? $_POST['cert_type'] : 'e-cert';
    
    $participants = isset($_POST['participants']) ? $_POST['participants'] : [];
    $uploaded_files = isset($_FILES['cert_files']) ? $_FILES['cert_files'] : null;
    
    $errors = [];
    $success_count = 0;
    $error_rows = [];
    
    if (empty($course_name)) $errors[] = 'Course name is required.';
    if (empty($course_date)) $errors[] = 'Course date is required.';
    if (empty($participants)) $errors[] = 'At least one participant is required.';
    
    if (empty($errors)) {
        if (!is_dir('../certificates')) {
            mkdir('../certificates', 0777, true);
        }
        
        foreach ($participants as $i => $p) {
            $serialNum = trim($p['serialNum'] ?? '');
            $nokp = trim($p['nokp'] ?? '');
            $name = trim($p['name'] ?? '');
            $insider = isset($p['insider']) ? intval($p['insider']) : 0;
            
            $row_errors = [];
            if (empty($serialNum)) $row_errors[] = 'Serial required';
            if (strlen($serialNum) > 10) $row_errors[] = 'Serial too long';
            if (empty($nokp) || strlen($nokp) != 12 || !ctype_digit($nokp)) $row_errors[] = 'IC invalid';
            if (empty($name)) $row_errors[] = 'Name required';
            
            if (!empty($serialNum)) {
                $check_sn = $conn->prepare("SELECT serialNum FROM certificates WHERE serialNum = ?");
                $check_sn->bind_param("s", $serialNum);
                $check_sn->execute();
                if ($check_sn->get_result()->num_rows > 0) {
                    $row_errors[] = 'Serial already exists';
                }
                $check_sn->close();
            }
            
            $cert_file = '';
            if ($uploaded_files && isset($uploaded_files['name'][$i]) 
                && $uploaded_files['error'][$i] == 0 && !empty($uploaded_files['name'][$i])) {
                
                $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
                $ext = strtolower(pathinfo($uploaded_files['name'][$i], PATHINFO_EXTENSION));
                $max_size = 5 * 1024 * 1024;
                
                if ($uploaded_files['size'][$i] > $max_size) {
                    $row_errors[] = 'File too large';
                } elseif (in_array($ext, $allowed)) {
                    $cert_file = 'cert_' . time() . '_' . $i . '_' . rand(1000, 9999) . '.' . $ext;
                    $upload_path = '../certificates/' . $cert_file;
                    if (!move_uploaded_file($uploaded_files['tmp_name'][$i], $upload_path)) {
                        $row_errors[] = 'Upload failed';
                        $cert_file = '';
                    }
                } else {
                    $row_errors[] = 'File type invalid';
                }
            } else {
                $row_errors[] = 'Certificate file required';
            }
            
            if (!empty($row_errors)) {
                $error_rows[] = 'Row ' . ($i + 1) . ' (' . $serialNum . '): ' . implode(', ', $row_errors);
                continue;
            }
            
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
            
            $stmt = $conn->prepare("INSERT INTO certificates (serialNum, admin_id, nokp, name, course_name, course_date, certificate_file, cert_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sissssss", $serialNum, $admin_id, $nokp, $name, $course_name, $course_date, $cert_file, $cert_type);
            
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $error_rows[] = 'Row ' . ($i + 1) . ': ' . $stmt->error;
                if ($cert_file && file_exists('../certificates/' . $cert_file)) {
                    @unlink('../certificates/' . $cert_file);
                }
            }
            $stmt->close();
        }
    }
    
    if (!empty($errors)) {
        $message = 'Errors: ' . implode('<br>', $errors);
        $message_type = 'danger';
    } elseif ($success_count > 0 && empty($error_rows)) {
        $_SESSION['admin_message'] = "Successfully added {$success_count} certificates!";
        $_SESSION['admin_message_type'] = 'success';
        header('Location: index.php');
        exit;
    } elseif ($success_count > 0 && !empty($error_rows)) {
        $message = "Added {$success_count} certificates. Some rows had errors:<br>" . implode('<br>', $error_rows);
        $message_type = 'warning';
    } else {
        $message = 'No certificates were added.<br>' . implode('<br>', $error_rows);
        $message_type = 'danger';
    }
}

$page_title = 'Bulk Add Certificates - eCert BPMI';
include 'header.php';
include 'nav.php';
?>

<style>
    .bulk-card {
        background: var(--bg-card, white);
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 20px;
    }
    .bulk-card .card-header {
        background: #1a3c5e;
        color: white;
        padding: 15px 20px;
        font-size: 16px;
        font-weight: 600;
    }
    .bulk-card .card-body {
        padding: 25px 30px;
    }
    .participant-row {
        background: #f8f9fa;
        border: 1px solid #e1e5eb;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 12px;
        transition: all 0.2s;
    }
    .participant-row:hover {
        border-color: #1a3c5e;
        box-shadow: 0 2px 8px rgba(26,60,94,0.1);
    }
    .participant-row.row-flagged {
        border-left: 4px solid #ffc107;
        background: #fffdf5;
    }
    .participant-row.row-duplicate {
        border-left: 4px solid #dc3545;
        background: #fff5f5;
    }
    .participant-row .row-number {
        display: inline-block;
        width: 28px;
        height: 28px;
        background: #1a3c5e;
        color: white;
        border-radius: 50%;
        text-align: center;
        line-height: 28px;
        font-weight: 700;
        font-size: 13px;
        margin-right: 8px;
    }
    .remove-row-btn {
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 6px;
        padding: 6px 12px;
        cursor: pointer;
        font-size: 13px;
    }
    .remove-row-btn:hover {
        background: #c82333;
    }
    .bulk-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: center;
        align-items: center;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid #e1e5eb;
    }
    .btn-add-row {
        background: #28a745;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .btn-add-row:hover { background: #218838; }
    .btn-scan-bulk {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .btn-scan-bulk:hover { opacity: 0.92; }
    .btn-scan-bulk:disabled { opacity: 0.55; cursor: not-allowed; }
    .btn-submit-bulk {
        background: #1a3c5e;
        color: white;
        border: none;
        padding: 14px 40px;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        font-size: 16px;
        min-height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .btn-submit-bulk:hover { background: #0f2a42; }
    .bulk-submit-actions {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .bulk-submit-actions a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 48px;
        padding: 12px 30px;
        margin-left: 0 !important;
    }
    .inline-field {
        display: flex;
        flex-direction: column;
    }
    .inline-field label {
        font-size: 11px;
        font-weight: 600;
        color: #666;
        text-transform: uppercase;
        margin-bottom: 3px;
        letter-spacing: 0.3px;
    }
    .inline-field input, .inline-field select {
        height: 38px;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 0 10px;
        font-size: 13px;
    }
    .inline-field input:focus {
        border-color: #1a3c5e;
        outline: none;
        box-shadow: 0 0 0 2px rgba(26,60,94,0.1);
    }
    .insider-radio-group {
        display: flex;
        gap: 10px;
        align-items: center;
        height: 38px;
        padding: 0 10px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 12px;
    }
    .insider-radio-group label {
        display: flex;
        align-items: center;
        gap: 4px;
        cursor: pointer;
        text-transform: none;
        color: #333;
        font-weight: 500;
        margin: 0;
        font-size: 12px;
    }
    .spin {
        animation: spin 1s linear infinite;
        display: inline-block;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    /* ===== Bulk Scan / Drag-Drop styles ===== */
    .drop-zone-active {
        outline: 3px dashed #667eea;
        outline-offset: -10px;
        background: rgba(102,126,234,0.06);
        border-radius: 8px;
        transition: background 0.15s ease;
    }
    .bulk-scan-panel {
        margin-top: 18px;
        border: 1px solid #dfe3ee;
        border-radius: 8px;
        background: #fbfcff;
        overflow: hidden;
        display: none;
    }
    .bulk-scan-panel.visible { display: block; }
    .bulk-scan-panel .panel-head {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
    }
    .bulk-scan-panel .panel-head .cancel-btn {
        background: rgba(255,255,255,0.18);
        color: white;
        border: none;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        cursor: pointer;
    }
    .bulk-scan-panel .panel-head .cancel-btn:hover { background: rgba(255,255,255,0.3); }
    .bulk-scan-progress {
        height: 6px;
        background: #e6e9f2;
        overflow: hidden;
    }
    .bulk-scan-progress > div {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, #667eea, #764ba2);
        transition: width 0.25s ease;
    }
    .bulk-scan-list {
        max-height: 260px;
        overflow-y: auto;
        padding: 8px 14px;
        font-size: 12.5px;
    }
    .bulk-scan-list .scan-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        padding: 5px 0;
        border-bottom: 1px dashed #eceff5;
        color: #444;
    }
    .bulk-scan-list .scan-item:last-child { border-bottom: none; }
    .bulk-scan-list .scan-item .fname {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .bulk-scan-list .scan-item .status { font-weight: 600; flex-shrink: 0; }
    .scan-ok    { color: #155724; }
    .scan-warn  { color: #856404; }
    .scan-err   { color: #b02a37; }
    .scan-pend  { color: #6c757d; }

    /* ===== Badges ===== */
    .row-flag-badge {
        display: inline-block;
        background: #ffc107;
        color: #4a3600;
        font-size: 10.5px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 10px;
        margin-left: 8px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .row-flag-badge.badge-dup {
        background: #dc3545;
        color: white;
    }
    .dup-counter {
        display: none;
        background: #dc3545;
        color: white;
        font-size: 11.5px;
        font-weight: 700;
        padding: 2px 10px;
        border-radius: 20px;
        margin-left: 8px;
        vertical-align: middle;
    }
    .dup-counter.visible { display: inline-block; }

    /* ===== Insider hint tag ===== */
    .insider-hint {
        margin-top: 4px;
        font-size: 11px;
        font-weight: 600;
        display: none;
        padding: 2px 8px;
        border-radius: 10px;
    }
    .insider-hint.visible { display: inline-block; }
    .insider-hint.from-db {
        background: #d1ecf1;
        color: #0c5460;
    }
    .insider-hint.from-serial {
        background: #e2e3ff;
        color: #383d8f;
    }
    /* No-IC warning badge */
    .row-flag-badge.badge-noic {
        background: #fd7e14;
        color: white;
    }
    /* Low-confidence warning badge */
    .row-flag-badge.badge-guess {
        background: #6f42c1;
        color: white;
    }
    /* Confidence pills next to each field */
    .conf-pill {
        display: inline-block;
        font-size: 10px;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 8px;
        margin-left: 6px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        vertical-align: middle;
    }
    .conf-pill.conf-high   { background: #d4edda; color: #155724; }
    .conf-pill.conf-medium { background: #fff3cd; color: #856404; }
    .conf-pill.conf-low    { background: #f8d7da; color: #721c24; }
    .conf-pill.conf-none   { display: none; }

    /* Highlight the IC input when a row needs it */
    .participant-row.row-noic .bulk-ic-input {
        border: 2px solid #fd7e14;
        background: #fff7ef;
    }
    .participant-row.row-noic .bulk-ic-input:focus {
        border-color: #fd7e14;
        box-shadow: 0 0 0 2px rgba(253,126,20,0.15);
    }

    /* Raw text debug panel */
    .raw-text-toggle {
        background: none;
        border: 1px solid #ccc;
        color: #666;
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 4px;
        cursor: pointer;
        margin-top: 6px;
    }
    .raw-text-toggle:hover {
        background: #f0f0f0;
    }
    .raw-text-box {
        display: none;
        margin-top: 6px;
        padding: 8px 10px;
        background: #f8f9fa;
        border: 1px solid #e1e5eb;
        border-radius: 4px;
        font-family: monospace;
        font-size: 11px;
        color: #333;
        max-height: 180px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .raw-text-box.visible { display: block; }

    /* ===== Dark mode fixes ===== */
    body.dark-mode .bulk-card .card-body,
    body.dark-mode .participant-row,
    body.dark-mode .participant-row *,
    body.dark-mode .inline-field label,
    body.dark-mode .insider-radio-group,
    body.dark-mode .insider-radio-group label,
    body.dark-mode .bulk-actions,
    body.dark-mode #dropZone,
    body.dark-mode #dropZone p,
    body.dark-mode .card-body > p {
        color: #ffffff !important;
    }
    body.dark-mode .inline-field input,
    body.dark-mode .inline-field select {
        background: #ffffff !important;
        color: #000000 !important;
    }
    [data-theme="dark"] .inline-field input,
    [data-theme="dark"] .inline-field select {
        background: #ffffff !important;
        color: #000000 !important;
        -webkit-text-fill-color: #000000 !important;
        caret-color: #000000 !important;
    }
    [data-theme="dark"] .inline-field input::placeholder {
        color: #555555 !important;
        opacity: 1;
    }
    [data-theme="dark"] .inline-field input:-webkit-autofill,
    [data-theme="dark"] .inline-field input:-webkit-autofill:hover,
    [data-theme="dark"] .inline-field input:-webkit-autofill:focus {
        -webkit-text-fill-color: #000000 !important;
        -webkit-box-shadow: 0 0 0 1000px #ffffff inset !important;
    }
    body.dark-mode .participant-row {
        background: #1e2a3a !important;
        border-color: #2c3e50 !important;
    }
    body.dark-mode .participant-row:hover {
        border-color: #667eea !important;
    }
    body.dark-mode .bulk-card .card-body {
        background: #16212e !important;
    }
    body.dark-mode .insider-radio-group {
        background: #2a3645 !important;
        border-color: #3b4a5a !important;
    }
    body.dark-mode .participant-row.row-flagged {
        background: #2b2419 !important;
    }
    body.dark-mode .participant-row.row-duplicate {
        background: #2a1a1c !important;
    }
    body.dark-mode .participant-row.row-noic .bulk-ic-input {
        background: #2b2419 !important;
        color: #ffffff !important;
    }
    body.dark-mode .participant-row.row-noic .bulk-ic-input:focus {
        background: #2b2419 !important;
    }
    body.dark-mode .raw-text-toggle {
        color: #ddd;
        border-color: #445;
    }
    body.dark-mode .raw-text-toggle:hover {
        background: #2a3645;
    }
    body.dark-mode .raw-text-box {
        background: #0f1720;
        border-color: #2c3e50;
        color: #d0d8e0;
    }
</style>

<div class="container" style="padding-top: 20px; max-width: 1200px;">

    <div class="row">
        <div class="col-md-12">
            <h2 style="font-size: 24px; font-weight: 700; color: #1a3c5e; margin: 0 0 10px 0;">
                <span class="glyphicon glyphicon-duplicate"></span> Bulk Add Certificates
            </h2>
            <hr style="border-top: 2px solid #e1e5eb; margin: 10px 0 25px 0;">
            <p style="color: #666; margin-bottom: 20px;">
                Add certificates for <strong>multiple participants</strong> attending the <strong>same course</strong>.
                Fill in the course details once, then add each participant below — or
                <strong>drop a folder of PDF certificates</strong> onto the participants area to auto-scan them all.
            </p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>" style="border-radius: 8px;">
            <strong>Result:</strong><br><?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data" id="bulkForm">
        <input type="hidden" name="bulk_submit" value="1">
        
        <div class="bulk-card">
            <div class="card-header">
                <span class="glyphicon glyphicon-book"></span> Course Details (applies to all participants)
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group">
                            <label style="font-weight: 600; font-size: 14px;">Course Name <span style="color: #d9534f;">*</span></label>
                            <input type="text" name="course_name" class="form-control" required 
                                   value="<?php echo htmlspecialchars($_POST['course_name'] ?? ''); ?>"
                                   placeholder="e.g., Kursus Asas Penyelenggaraan Enjin Sangkut Khas"
                                   style="height: 45px;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label style="font-weight: 600; font-size: 14px;">Course Date <span style="color: #d9534f;">*</span></label>
                            <input type="date" name="course_date" class="form-control" required 
                                   value="<?php echo htmlspecialchars($_POST['course_date'] ?? date('Y-m-d')); ?>"
                                   style="height: 45px;">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label style="font-weight: 600; font-size: 14px;">Certificate Type <span style="color: #d9534f;">*</span></label>
                            <select name="cert_type" class="form-control" required style="height: 45px;">
                                <option value="e-cert" <?php echo (($_POST['cert_type'] ?? '') == 'e-cert') ? 'selected' : ''; ?>>E-Certificate</option>
                                <option value="physical" <?php echo (($_POST['cert_type'] ?? '') == 'physical') ? 'selected' : ''; ?>>Physical Certificate</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bulk-card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <span class="glyphicon glyphicon-user"></span> Participants
                    <span id="participantCount" style="background: rgba(255,255,255,0.2); padding: 2px 10px; border-radius: 20px; font-size: 13px; margin-left: 8px;">1</span>
                    <span id="dupCounter" class="dup-counter">⚠ 0 duplicates</span>
                </div>
                <button type="button" class="btn-add-row" onclick="addRow()" style="background: rgba(40,167,69,1);">
                    <span class="glyphicon glyphicon-plus"></span> Add Row
                </button>
            </div>
            <div class="card-body" id="dropZone">
                
                <div id="participantsContainer">
                    <!-- Rows inserted here by JS -->
                </div>

                <div class="bulk-scan-panel" id="scanPanel">
                    <div class="panel-head">
                        <span><span class="glyphicon glyphicon-search"></span> <span id="scanPanelTitle">Scanning PDFs…</span></span>
                        <button type="button" class="cancel-btn" id="scanCancelBtn" onclick="cancelBulkScan()">Cancel</button>
                    </div>
                    <div class="bulk-scan-progress"><div id="scanProgressBar"></div></div>
                    <div class="bulk-scan-list" id="scanList"></div>
                </div>
                
                <div class="bulk-actions">
                    <button type="button" class="btn-add-row" onclick="addRow()">
                        <span class="glyphicon glyphicon-plus"></span> Add Another Participant
                    </button>

                    <button type="button" class="btn-scan-bulk" id="bulkScanBtn" onclick="document.getElementById('bulkPdfInput').click()">
                        <span class="glyphicon glyphicon-search"></span> Bulk Scan PDFs
                    </button>
                    <input type="file" id="bulkPdfInput" accept=".pdf,application/pdf" multiple
                           style="display:none;" onchange="handleBulkFiles(this.files); this.value='';">
                </div>

                <p style="margin: 12px 0 0 0; font-size: 12.5px; color: #7a7f8a;">
                    <span class="glyphicon glyphicon-info-sign"></span>
                    Tip: You can also <strong>drag &amp; drop a batch of PDF certificates</strong> anywhere onto this panel.
                    One PDF = one participant row. Scanned files are attached to their rows automatically.
                </p>
                
            </div>
        </div>

        <div class="bulk-submit-actions" style="margin-top: 30px; margin-bottom: 40px;">
            <button type="submit" class="btn-submit-bulk">
                <span class="glyphicon glyphicon-save"></span> Save All Certificates
            </button>
            <a href="index.php" style="background: #6c757d; color: white; padding: 14px 30px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-left: 10px;">
                <span class="glyphicon glyphicon-remove"></span> Cancel
            </a>
        </div>
        
    </form>
</div>

<script>
let rowCounter = 0;
let bulkScanAbort = false;
const MAX_BULK_FILES = 100;

const duplicateFlags = new WeakMap();

const PREFIX_MAP = {
    'JPS': 1,
    'PN':  0
};

document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('participantsContainer').children.length === 0) {
        addRow();
    }
    setupDropZone();
    refreshDuplicateState();
    attachFormSubmitGuard();
});

/* ============================================================
   ROW MANAGEMENT
   ============================================================ */
function addRow() {
    rowCounter++;
    const container = document.getElementById('participantsContainer');
    
    const row = document.createElement('div');
    row.className = 'participant-row';
    row.dataset.rowId = rowCounter;
    row.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <div>
                <span class="row-number">${rowCounter}</span>
                <strong style="color: #1a3c5e;">Participant #${rowCounter}</strong>
                <span class="row-flag-badge" style="display:none;">Needs Review</span>
                <span class="row-dup-badge row-flag-badge badge-dup" style="display:none;">Duplicate</span>
            </div>
            <button type="button" class="remove-row-btn" onclick="removeRow(this)">
                <span class="glyphicon glyphicon-trash"></span> Remove
            </button>
        </div>
        <div class="row" style="margin: 0;">
            <div class="col-md-2" style="padding: 0 6px;">
                <div class="inline-field">
                    <label>Serial No. *</label>
                    <input type="text" name="participants[${rowCounter}][serialNum]" 
                           class="bulk-serial-input"
                           placeholder="PN-00XX" maxlength="10" required>
                    <span class="insider-hint"></span>
                </div>
            </div>
            <div class="col-md-2" style="padding: 0 6px;">
                <div class="inline-field">
                    <label>IC Number *</label>
                    <input type="text" name="participants[${rowCounter}][nokp]" 
                           class="bulk-ic-input" placeholder="123456789012" maxlength="12" 
                           pattern="[0-9]{12}" required>
                </div>
            </div>
            <div class="col-md-3" style="padding: 0 6px;">
                <div class="inline-field">
                    <label>Full Name *</label>
                    <input type="text" name="participants[${rowCounter}][name]" 
                           class="bulk-name-input" placeholder="Full name" required>
                </div>
            </div>
            <div class="col-md-2" style="padding: 0 6px;">
                <div class="inline-field">
                    <label>Type *</label>
                    <div class="insider-radio-group">
                        <label><input type="radio" name="participants[${rowCounter}][insider]" value="0" checked> Public</label>
                        <label><input type="radio" name="participants[${rowCounter}][insider]" value="1"> Insider</label>
                    </div>
                </div>
            </div>
            <div class="col-md-3" style="padding: 0 6px;">
                <div class="inline-field">
                    <label>Certificate File *</label>
                    <input type="file" name="cert_files[${rowCounter}]" class="bulk-file-input" 
                           accept=".pdf,.jpg,.jpeg,.png,.gif" required
                           style="height: 38px; padding: 6px 10px;">
                </div>
                <div class="bulk-scan-area" style="margin-top: 6px; display: none;">
                    <button type="button" class="scan-row-btn" 
                            style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer;">
                        <span class="glyphicon glyphicon-search"></span> Auto-fill
                    </button>
                    <span class="scan-row-status" style="margin-left: 8px; font-size: 11px; color: #666;"></span>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(row);
    attachRowListeners(row);
    updateCount();
    refreshDuplicateState();
}

function removeRow(btn) {
    const row = btn.closest('.participant-row');
    row.remove();
    updateCount();
    renumberRows();
    refreshDuplicateState();
}

function updateCount() {
    const count = document.querySelectorAll('.participant-row').length;
    document.getElementById('participantCount').textContent = count;
}

function renumberRows() {
    const rows = document.querySelectorAll('.participant-row');
    rows.forEach((row, i) => {
        row.querySelector('.row-number').textContent = i + 1;
        const strongEl = row.querySelector('strong');
        if (strongEl) strongEl.textContent = 'Participant #' + (i + 1);
    });
}

function attachRowListeners(row) {
    const serialInput   = row.querySelector('.bulk-serial-input');
    const icInput       = row.querySelector('.bulk-ic-input');
    const nameInput     = row.querySelector('.bulk-name-input');
    const insiderRadios = row.querySelectorAll('input[name*="[insider]"]');
    const fileInput     = row.querySelector('.bulk-file-input');
    const scanArea      = row.querySelector('.bulk-scan-area');
    const scanBtn       = row.querySelector('.scan-row-btn');
    const scanStatus    = row.querySelector('.scan-row-status');

    if (serialInput) {
        serialInput.addEventListener('input', function() {
            refreshDuplicateState();

            const val = this.value.trim().toUpperCase();
            if (!val) {
                clearInsiderHint(row);
                return;
            }

            let prefix = '';
            if (val.includes('-')) {
                prefix = val.split('-')[0];
            } else {
                const m = val.match(/^([A-Z]+)/);
                prefix = m ? m[1] : '';
            }

            if (prefix && PREFIX_MAP.hasOwnProperty(prefix)) {
                applyInsiderToRow(row, PREFIX_MAP[prefix], 'serial');
            } else {
                clearInsiderHint(row);
            }
        });
        serialInput.addEventListener('change', refreshDuplicateState);
    }
    
    if (icInput) {
        icInput.addEventListener('input', function() {
            if (this.value.trim().length === 12) {
                clearNoIcMark(row);
            }
        });

        icInput.addEventListener('blur', function() {
            const ic = this.value.trim();
            if (ic.length !== 12) return;
            
            fetch('get_participant.php?type=ic&q=' + encodeURIComponent(ic))
                .then(r => r.json())
                .then(data => {
                    if (data.found) {
                        if (nameInput && !nameInput.value.trim()) {
                            nameInput.value = data.fullName;
                            highlightField(nameInput);
                        }
                        applyInsiderToRow(row, parseInt(data.insider), 'db');
                    }
                })
                .catch(err => console.error('Autofill error:', err));
        });
        
        icInput.addEventListener('paste', function() {
            setTimeout(() => this.dispatchEvent(new Event('blur')), 50);
        });
    }
    
    if (nameInput) {
        let debounceTimer;
        nameInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const name = this.value.trim();
            if (name.length < 3) return;
            
            debounceTimer = setTimeout(() => {
                fetch('get_participant.php?type=name&q=' + encodeURIComponent(name))
                    .then(r => r.json())
                    .then(data => {
                        if (data.found && data.suggestions.length > 0) {
                            showSuggestions(nameInput, data.suggestions, icInput, insiderRadios, row);
                        }
                    })
                    .catch(err => console.error('Suggest error:', err));
            }, 300);
        });
    }
    
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) {
                scanArea.style.display = 'none';
                return;
            }
            const ext = file.name.split('.').pop().toLowerCase();
            scanArea.style.display = (ext === 'pdf') ? 'block' : 'none';
        });
    }
    
    if (scanBtn) {
        scanBtn.addEventListener('click', function() {
            const file = fileInput.files[0];
            if (!file) {
                scanStatus.innerHTML = '<span style="color: #dc3545;">No file selected</span>';
                return;
            }
            
            scanStatus.innerHTML = '<span class="glyphicon glyphicon-refresh spin"></span> Scanning...';
            scanBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('certificate_file', file);
            
            fetch('scan_certificate.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                scanBtn.disabled = false;
                
                if (!data.success) {
                    scanStatus.innerHTML = '<span style="color: #dc3545;">✗ ' + data.error + '</span>';
                    return;
                }
                
                const ext = data.extracted;
                applyScanResultToRow(row, ext);
                scanStatus.innerHTML = '<span style="color: #155724; font-weight: 600;">✓ Done</span>';
            })
            .catch(err => {
                scanBtn.disabled = false;
                scanStatus.innerHTML = '<span style="color: #dc3545;">✗ ' + err.message + '</span>';
            });
        });
    }
}

/* ============================================================
   APPLY SCAN RESULT TO A ROW
   ============================================================ */
function applyScanResultToRow(row, ext) {
    const serialInput   = row.querySelector('.bulk-serial-input');
    const icInput       = row.querySelector('.bulk-ic-input');
    const nameInput     = row.querySelector('.bulk-name-input');

    if (ext.serialNum && serialInput && !serialInput.value.trim()) {
        serialInput.value = ext.serialNum;
        highlightField(serialInput);
    }
    if (ext.nokp && icInput && !icInput.value.trim()) {
        icInput.value = ext.nokp;
        highlightField(icInput);
    }
    if (ext.name && nameInput && !nameInput.value.trim()) {
        nameInput.value = ext.name;
        highlightField(nameInput);
    }

    // No-IC handling
    const hasIc = !!(icInput && icInput.value.trim());
    if (!hasIc) {
        markRowAsNoIc(row);

        if (ext.name && nameInput) {
            fetch('get_participant.php?type=name&q=' + encodeURIComponent(ext.name))
                .then(r => r.json())
                .then(data => {
                    if (data.found && data.suggestions.length > 0) {
                        const radios = row.querySelectorAll('input[name*="[insider]"]');
                        showSuggestions(nameInput, data.suggestions, icInput, radios, row);
                    }
                })
                .catch(() => {});
        }
    } else {
        clearNoIcMark(row);
    }

    // DB duplicate flag
    if (ext.serial_exists_in_db) {
        setDuplicateFlag(row, 'db', true);
    } else {
        setDuplicateFlag(row, 'db', false);
    }

    // A — Confidence pills
    if (ext.confidence) {
        setConfidence(row, '.bulk-serial-input', ext.confidence.serialNum);
        setConfidence(row, '.bulk-ic-input',     ext.confidence.nokp);
        setConfidence(row, '.bulk-name-input',   ext.confidence.name);
    }

    // D — Guessed flag
    const guessFields = [];
    if (ext.confidence && ext.confidence.nokp === 'low') guessFields.push('IC');
    if (ext.confidence && ext.confidence.name === 'low') guessFields.push('name');
    if (guessFields.length > 0) {
        markRowAsGuessed(row);
    }

    // C — Raw text panel
    if (ext.raw_text) {
        attachRawTextPanel(row, ext.raw_text);
    }

    // Insider
    if (ext.insider !== null && ext.insider !== undefined) {
        applyInsiderToRow(row, parseInt(ext.insider), ext.insider_source || 'serial');
    }

    if (ext.nokp) {
        fetch('get_participant.php?type=ic&q=' + encodeURIComponent(ext.nokp))
            .then(r => r.json())
            .then(pdata => {
                if (pdata.found) {
                    applyInsiderToRow(row, parseInt(pdata.insider), 'db');
                }
            })
            .catch(() => {});
    }

    refreshDuplicateState();
}

/* ============================================================
   INSIDER HELPERS
   ============================================================ */
function applyInsiderToRow(row, insiderValue, source) {
    const radios = row.querySelectorAll('input[name*="[insider]"]');
    radios.forEach(radio => {
        if (parseInt(radio.value) === insiderValue) radio.checked = true;
    });

    const hint = row.querySelector('.insider-hint');
    if (!hint) return;

    hint.classList.remove('from-db', 'from-serial', 'visible');

    if (source === 'db') {
        hint.textContent = '👤 From DB';
        hint.classList.add('from-db', 'visible');
    } else if (source === 'serial') {
        hint.textContent = '📄 From serial';
        hint.classList.add('from-serial', 'visible');
    } else {
        hint.textContent = '';
    }
}

function clearInsiderHint(row) {
    const hint = row.querySelector('.insider-hint');
    if (hint) {
        hint.textContent = '';
        hint.classList.remove('from-db', 'from-serial', 'visible');
    }
}

/* ============================================================
   NO-IC HELPERS
   ============================================================ */
function markRowAsNoIc(row) {
    row.classList.add('row-noic');

    const badge = row.querySelector('.row-flag-badge:not(.row-dup-badge)');
    if (badge) {
        badge.classList.remove('badge-guess');
        badge.textContent = 'No IC — fill manually';
        badge.classList.add('badge-noic');
        badge.style.display = 'inline-block';
    }

    const icInput = row.querySelector('.bulk-ic-input');
    if (icInput && !icInput.value.trim()) {
        const active = document.activeElement;
        if (!active || !active.closest('.participant-row')) {
            setTimeout(() => icInput.focus(), 250);
        }
    }
}

function clearNoIcMark(row) {
    row.classList.remove('row-noic');

    const badge = row.querySelector('.row-flag-badge:not(.row-dup-badge)');
    if (badge) {
        badge.classList.remove('badge-noic');

        if (badge.classList.contains('badge-guess')) {
            badge.textContent = '⚠ Some fields guessed';
            badge.style.display = 'inline-block';
        } else if (!row.classList.contains('row-flagged')) {
            badge.style.display = 'none';
        }
    }
}

/* ============================================================
   CONFIDENCE PILLS (A) + RAW TEXT PANEL (C) + GUESSED (D)
   ============================================================ */
function setConfidence(row, fieldClass, level) {
    const input = row.querySelector(fieldClass);
    if (!input) return;

    const existing = input.parentElement.querySelector('.conf-pill');
    if (existing) existing.remove();

    if (!level || level === 'none') return;

    const pill = document.createElement('span');
    pill.className = 'conf-pill conf-' + level;
    const labels = { high: 'high', medium: 'med', low: '⚠ verify' };
    pill.textContent = labels[level] || level;

    input.parentElement.appendChild(pill);
}

function markRowAsGuessed(row) {
    const badge = row.querySelector('.row-flag-badge:not(.row-dup-badge)');
    if (!badge) return;
    if (!badge.classList.contains('badge-noic')) {
        badge.textContent = '⚠ Some fields guessed';
        badge.classList.add('badge-guess');
        badge.style.display = 'inline-block';
    }
}

function attachRawTextPanel(row, rawText) {
    if (!rawText) return;

    const existing = row.querySelector('.raw-text-toggle');
    if (existing) existing.remove();
    const existingBox = row.querySelector('.raw-text-box');
    if (existingBox) existingBox.remove();

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'raw-text-toggle';
    toggle.innerHTML = '🔍 Show raw OCR text';

    const box = document.createElement('div');
    box.className = 'raw-text-box';
    box.textContent = rawText;

    toggle.onclick = function() {
        const isVisible = box.classList.toggle('visible');
        toggle.innerHTML = isVisible ? '✕ Hide raw OCR text' : '🔍 Show raw OCR text';
    };

    const footer = document.createElement('div');
    footer.style.cssText = 'margin-top:10px;';
    footer.appendChild(toggle);
    footer.appendChild(box);
    row.appendChild(footer);
}

function highlightField(el) {
    el.style.transition = 'background 0.6s';
    el.style.background = '#d4edda';
    setTimeout(() => el.style.background = '', 1500);
}

/* ============================================================
   NAME SUGGESTIONS
   ============================================================ */
function showSuggestions(input, suggestions, icInput, insiderRadios, row) {
    document.querySelectorAll('.autofill-suggestions').forEach(el => el.remove());
    
    const wrapper = document.createElement('div');
    wrapper.className = 'autofill-suggestions';
    wrapper.style.cssText = 'position:absolute;background:white;border:1px solid #ddd;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);max-height:200px;overflow-y:auto;z-index:1000;min-width:250px;margin-top:2px;';
    
    suggestions.forEach(s => {
        const item = document.createElement('div');
        item.style.cssText = 'padding:8px 12px;cursor:pointer;border-bottom:1px solid #eee;font-size:13px;';
        item.innerHTML = '<strong>' + escapeHtml(s.fullName) + '</strong> <span style="color:#888;font-size:11px;">(' + escapeHtml(s.icNum) + ')</span>';
        
        item.onmouseenter = () => item.style.background = '#f0f7ff';
        item.onmouseleave = () => item.style.background = 'white';
        item.onclick = () => {
            if (icInput) icInput.value = s.icNum;
            input.value = s.fullName;
            insiderRadios.forEach(radio => {
                if (parseInt(radio.value) === s.insider) radio.checked = true;
            });
            applyInsiderToRow(row, parseInt(s.insider), 'db');
            document.querySelectorAll('.autofill-suggestions').forEach(el => el.remove());
        };
        wrapper.appendChild(item);
    });
    
    input.parentElement.style.position = 'relative';
    input.parentElement.appendChild(wrapper);
    
    setTimeout(() => {
        document.addEventListener('click', function closeSuggestions(e) {
            if (!e.target.closest('.autofill-suggestions') && e.target !== input) {
                document.querySelectorAll('.autofill-suggestions').forEach(el => el.remove());
                document.removeEventListener('click', closeSuggestions);
            }
        });
    }, 100);
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/* ============================================================
   DUPLICATE DETECTION
   ============================================================ */
function setDuplicateFlag(row, kind, value) {
    const current = duplicateFlags.get(row) || { db: false, batch: false };
    current[kind] = value;
    duplicateFlags.set(row, current);
}

function refreshDuplicateState() {
    const rows = Array.from(document.querySelectorAll('.participant-row'));

    const serialCounts = new Map();
    rows.forEach(row => {
        const s = (row.querySelector('.bulk-serial-input')?.value || '').trim().toUpperCase();
        if (!s) return;
        serialCounts.set(s, (serialCounts.get(s) || 0) + 1);
    });

    let dupCount = 0;

    rows.forEach(row => {
        const s = (row.querySelector('.bulk-serial-input')?.value || '').trim().toUpperCase();
        const isBatchDup = s && serialCounts.get(s) > 1;
        setDuplicateFlag(row, 'batch', !!isBatchDup);

        const flags = duplicateFlags.get(row) || { db: false, batch: false };
        const isDup = flags.db || flags.batch;

        const badge = row.querySelector('.row-dup-badge');
        if (badge) {
            if (isDup) {
                let label = 'Duplicate';
                if (flags.db && flags.batch) label = 'Duplicate (DB + batch)';
                else if (flags.db)           label = 'Already in DB';
                else                         label = 'Duplicate in batch';
                badge.textContent = label;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }

        row.classList.toggle('row-duplicate', isDup);
        if (isDup) dupCount++;
    });

    const counter = document.getElementById('dupCounter');
    if (counter) {
        if (dupCount > 0) {
            counter.textContent = '⚠ ' + dupCount + ' duplicate' + (dupCount > 1 ? 's' : '');
            counter.classList.add('visible');
        } else {
            counter.classList.remove('visible');
        }
    }
}

/* ============================================================
   SUBMIT GUARD
   ============================================================ */
function attachFormSubmitGuard() {
    const form = document.getElementById('bulkForm');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        const rows = Array.from(document.querySelectorAll('.participant-row'));
        const dupRows  = rows.filter(r => r.classList.contains('row-duplicate'));
        const noIcRows = rows.filter(r => r.classList.contains('row-noic'));

        let block = false;

        if (noIcRows.length > 0) {
            const proceed = confirm(
                'You have ' + noIcRows.length + ' row(s) with no IC number.\n\n' +
                'Every certificate must be linked to a valid 12-digit IC so public users can find it.\n' +
                'These rows will FAIL on submit.\n\n' +
                'Do you want to submit anyway?'
            );
            if (!proceed) {
                e.preventDefault();
                noIcRows[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                const icInput = noIcRows[0].querySelector('.bulk-ic-input');
                if (icInput) setTimeout(() => icInput.focus(), 300);
                block = true;
            }
        }

        if (!block && dupRows.length > 0) {
            const proceed = confirm(
                'You have ' + dupRows.length + ' row(s) flagged as duplicate serial numbers.\n\n' +
                'These will likely FAIL on submit ("Serial already exists").\n\n' +
                'Do you want to submit anyway?'
            );
            if (!proceed) {
                e.preventDefault();
                dupRows[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
}

/* ============================================================
   BULK SCAN — sequential with progress panel
   ============================================================ */
function setupDropZone() {
    const zone = document.getElementById('dropZone');
    if (!zone) return;

    let dragDepth = 0;

    ['dragenter', 'dragover'].forEach(evt => {
        zone.addEventListener(evt, e => {
            if (!e.dataTransfer) return;
            if (Array.from(e.dataTransfer.types || []).indexOf('Files') === -1) return;
            e.preventDefault();
            e.stopPropagation();
            if (evt === 'dragenter') dragDepth++;
            zone.classList.add('drop-zone-active');
        });
    });

    ['dragleave', 'drop'].forEach(evt => {
        zone.addEventListener(evt, e => {
            e.preventDefault();
            e.stopPropagation();
            if (evt === 'dragleave') {
                dragDepth = Math.max(0, dragDepth - 1);
                if (dragDepth === 0) zone.classList.remove('drop-zone-active');
            } else {
                dragDepth = 0;
                zone.classList.remove('drop-zone-active');
                const files = e.dataTransfer && e.dataTransfer.files;
                if (files && files.length) handleBulkFiles(files);
            }
        });
    });
}

async function handleBulkFiles(fileList) {
    const files = Array.from(fileList).filter(f => /\.pdf$/i.test(f.name));

    if (files.length === 0) {
        alert('Please select PDF files only.');
        return;
    }
    if (files.length > MAX_BULK_FILES) {
        alert('Too many files. Maximum is ' + MAX_BULK_FILES + ' PDFs per batch.\nYou selected ' + files.length + '.');
        return;
    }

    const proceed = confirm(
        'Ready to scan ' + files.length + ' PDF' + (files.length > 1 ? 's' : '') + '.\n\n' +
        'Each PDF will be scanned sequentially and a participant row will be created for it.\n' +
        'This may take a while depending on file size and count.\n\nProceed?'
    );
    if (!proceed) return;

    bulkScanAbort = false;
    document.getElementById('bulkScanBtn').disabled = true;

    const panel   = document.getElementById('scanPanel');
    const listEl  = document.getElementById('scanList');
    const barEl   = document.getElementById('scanProgressBar');
    const titleEl = document.getElementById('scanPanelTitle');
    const cancelBtn = document.getElementById('scanCancelBtn');

    panel.classList.add('visible');
    listEl.innerHTML = '';
    barEl.style.width = '0%';
    cancelBtn.style.display = 'inline-block';

    const itemEls = files.map((f, idx) => {
        const div = document.createElement('div');
        div.className = 'scan-item';
        div.innerHTML =
            '<span class="fname">' + (idx + 1) + '. ' + escapeHtml(f.name) + '</span>' +
            '<span class="status scan-pend">Waiting…</span>';
        listEl.appendChild(div);
        return div.querySelector('.status');
    });

    let okCount = 0, warnCount = 0, errCount = 0, dupCount = 0;

    for (let i = 0; i < files.length; i++) {
        if (bulkScanAbort) {
            for (let j = i; j < files.length; j++) {
                itemEls[j].textContent = 'Cancelled';
                itemEls[j].className = 'status scan-pend';
            }
            break;
        }

        const file = files[i];
        titleEl.textContent = 'Scanning ' + (i + 1) + ' / ' + files.length + ' — ' + file.name;
        itemEls[i].innerHTML = '<span class="glyphicon glyphicon-refresh spin"></span> Scanning…';
        itemEls[i].className = 'status scan-pend';

        const result = await scanSinglePdf(file);

        if (result.success) {
            const ext = result.extracted || {};
            const filledCount = (result.filled || []).length;
            const isDbDup = !!ext.serial_exists_in_db;

            if (isDbDup) {
                itemEls[i].textContent = '⚠ Serial already in DB: ' + ext.serialNum;
                itemEls[i].className = 'status scan-err';
                dupCount++;
            } else if (filledCount === 0) {
                itemEls[i].textContent = '⚠ No fields detected';
                itemEls[i].className = 'status scan-warn';
                warnCount++;
            } else {
                itemEls[i].textContent = '✓ ' + result.filled.join(', ');
                itemEls[i].className = 'status scan-ok';
                okCount++;
            }

            createRowFromScan(file, ext, filledCount === 0, isDbDup);
        } else {
            itemEls[i].textContent = '✗ ' + (result.error || 'Scan failed');
            itemEls[i].className = 'status scan-err';
            errCount++;
            createRowFromScan(file, {}, true, false);
        }

        barEl.style.width = Math.round(((i + 1) / files.length) * 100) + '%';
        await new Promise(r => setTimeout(r, 30));
    }

    titleEl.textContent = 'Done — ' + okCount + ' filled, ' + warnCount + ' partial, ' +
                          errCount + ' failed, ' + dupCount + ' duplicate serial(s)';
    document.getElementById('bulkScanBtn').disabled = false;
    cancelBtn.style.display = 'none';

    refreshDuplicateState();

    if (errCount === 0 && warnCount === 0 && dupCount === 0 && !bulkScanAbort) {
        setTimeout(() => { panel.classList.remove('visible'); }, 2500);
    }
}

async function scanSinglePdf(file) {
    const formData = new FormData();
    formData.append('certificate_file', file);

    try {
        const res = await fetch('scan_certificate.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (!data.success) {
            return { success: false, error: data.error || 'OCR failed' };
        }

        const ext = data.extracted || {};
        const filled = [];
        if (ext.serialNum) filled.push('Serial');
        if (ext.nokp)      filled.push('IC');
        if (ext.name)      filled.push('Name');

        return { success: true, extracted: ext, filled: filled };
    } catch (err) {
        return { success: false, error: err.message || 'Network error' };
    }
}

function createRowFromScan(file, extracted, flagged, isDbDup) {
    addRow();
    const rows = document.querySelectorAll('.participant-row');
    const row = rows[rows.length - 1];

    applyScanResultToRow(row, extracted || {});

    if (isDbDup) setDuplicateFlag(row, 'db', true);

    const fileInput = row.querySelector('.bulk-file-input');
    try {
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (e) {
        console.warn('Could not attach file programmatically:', e);
    }

    if (flagged) {
        row.classList.add('row-flagged');
        const badge = row.querySelector('.row-flag-badge:not(.row-dup-badge)');
        if (badge && !badge.classList.contains('badge-noic') && !badge.classList.contains('badge-guess')) {
            badge.style.display = 'inline-block';
        }
    }

    refreshDuplicateState();
}

function cancelBulkScan() {
    bulkScanAbort = true;
    const titleEl = document.getElementById('scanPanelTitle');
    if (titleEl) titleEl.textContent = 'Cancelling…';
}
</script>

<?php include 'footer.php'; ?>