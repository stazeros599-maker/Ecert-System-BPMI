<?php
// Start session if not already started (needed for admin detection)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';

if (!isset($_GET['serialNum']) || empty($_GET['serialNum'])) {
    die('Invalid certificate ID.');
}

$serialNum = $_GET['serialNum'];

// Get certificate from certificates table
$stmt = $conn->prepare("SELECT * FROM certificates WHERE serialNum = ?");
$stmt->bind_param("s", $serialNum);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die('Certificate not found.');
}

$cert = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Check if actual PDF exists
$cert_file = 'certificates/' . $cert['certificate_file'];
$has_pdf = file_exists($cert_file) && strtolower(pathinfo($cert_file, PATHINFO_EXTENSION)) == 'pdf';

// Use serial number from database
$cert_number = $cert['serialNum'];

// Detect if viewer is admin
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate - <?php echo htmlspecialchars($cert['name']); ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Great+Vibes&family=Open+Sans:wght@300;400;600&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Open Sans', sans-serif; background: linear-gradient(135deg, #0f2027, #203a43, #2c5364); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 40px 20px; }
        .certificate-wrapper { max-width: 1000px; width: 100%; background: white; border-radius: 20px; box-shadow: 0 30px 80px rgba(0,0,0,0.6); overflow: hidden; position: relative; }
        .certificate-wrapper::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: radial-gradient(ellipse at 20% 50%, rgba(201, 169, 89, 0.05) 0%, transparent 70%), radial-gradient(ellipse at 80% 50%, rgba(201, 169, 89, 0.05) 0%, transparent 70%); pointer-events: none; z-index: 1; }
        .cert-toolbar { background: #1a3c5e; padding: 12px 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-bottom: 3px solid #c9a959; }
        .cert-toolbar .brand { color: white; font-weight: 600; font-size: 16px; }
        .cert-toolbar .brand span { color: #c9a959; }
        .cert-toolbar .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .cert-toolbar .actions .btn { border-radius: 6px; padding: 6px 16px; font-size: 13px; font-weight: 600; transition: all 0.3s; cursor: pointer; border: none; text-decoration: none; }
        .cert-toolbar .actions .btn-download { background: #17a2b8; color: white; }
        .cert-toolbar .actions .btn-download:hover { background: #138496; transform: scale(1.02); color: white; }
        .cert-toolbar .actions .btn-request { background: #ffc107; color: #856404; }
        .cert-toolbar .actions .btn-request { display: inline-flex; align-items: center; justify-content: center; gap: 4px; width: 120px; max-width: 100%; min-width: 0; white-space: normal; line-height: 1.25; text-align: center; }
        .cert-toolbar .actions .btn-request span:last-child { min-width: 0; }
        .cert-toolbar .actions .btn-request:hover { background: #e0a800; transform: scale(1.02); color: #856404; }
        .cert-toolbar .actions .btn-back { background: #6c757d; color: white; }
        .cert-toolbar .actions .btn-back:hover { background: #5a6268; transform: scale(1.02); color: white; }
        .admin-badge { background: #ffd700; color: #1a3c5e; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; margin-left: 8px; }
        .certificate-content { padding: 50px 60px 40px; position: relative; z-index: 2; background: white; }
        .certificate-border { border: 3px solid #c9a959; padding: 35px 40px 30px; position: relative; background: white; }
        .certificate-border::before { content: ''; position: absolute; top: 8px; left: 8px; right: 8px; bottom: 8px; border: 1px solid rgba(201, 169, 89, 0.3); pointer-events: none; }
        .corner { position: absolute; width: 30px; height: 30px; border-color: #c9a959; border-style: solid; border-width: 0; }
        .corner-tl { top: 10px; left: 10px; border-top-width: 3px; border-left-width: 3px; }
        .corner-tr { top: 10px; right: 10px; border-top-width: 3px; border-right-width: 3px; }
        .corner-bl { bottom: 10px; left: 10px; border-bottom-width: 3px; border-left-width: 3px; }
        .corner-br { bottom: 10px; right: 10px; border-bottom-width: 3px; border-right-width: 3px; }
        .cert-header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #f0e8d8; padding-bottom: 20px; }
        .cert-header .logo-icon { font-size: 40px; color: #c9a959; display: block; margin-bottom: 5px; }
        .cert-header h1 { font-family: 'Playfair Display', serif; font-size: 32px; font-weight: 700; color: #1a3c5e; letter-spacing: 4px; margin: 0; }
        .cert-header .subtitle { font-family: 'Playfair Display', serif; font-size: 16px; color: #c9a959; letter-spacing: 6px; text-transform: uppercase; margin-top: 2px; }
        .cert-header .cert-number { font-size: 12px; color: #aaa; margin-top: 8px; letter-spacing: 1px; }
        .cert-body { text-align: center; padding: 10px 0 20px; }
        .cert-body .awarded-text { font-family: 'Open Sans', sans-serif; font-size: 16px; color: #666; font-weight: 300; text-transform: uppercase; letter-spacing: 3px; }
        .cert-body .recipient { font-family: 'Great Vibes', cursive; font-size: 48px; color: #1a3c5e; margin: 10px 0 5px; padding: 5px 30px; display: inline-block; position: relative; }
        .cert-body .recipient::before, .cert-body .recipient::after { content: '✦'; color: #c9a959; font-size: 14px; position: absolute; top: 50%; transform: translateY(-50%); }
        .cert-body .recipient::before { left: 0; }
        .cert-body .recipient::after { right: 0; }
        .cert-body .for-text { font-family: 'Open Sans', sans-serif; font-size: 14px; color: #888; font-weight: 300; text-transform: uppercase; letter-spacing: 2px; }
        .cert-body .course-name { font-family: 'Playfair Display', serif; font-size: 28px; color: #c9a959; font-weight: 700; margin: 8px 0 15px; padding: 0 20px; }
        .cert-body .date-text { font-family: 'Open Sans', sans-serif; font-size: 15px; color: #666; font-weight: 300; }
        .cert-body .date-text strong { color: #1a3c5e; font-weight: 600; }
        .signature-area { margin-top: 35px; padding-top: 25px; border-top: 1px solid #f0e8d8; display: flex; justify-content: center; gap: 80px; flex-wrap: wrap; }
        .signature-item { text-align: center; min-width: 150px; }
        .signature-item .sign-line { width: 180px; border-bottom: 1.5px solid #1a3c5e; margin: 0 auto 8px; height: 30px; }
        .signature-item .sign-label { font-size: 12px; color: #999; letter-spacing: 1px; text-transform: uppercase; }
        .signature-item .sign-name { font-family: 'Great Vibes', cursive; font-size: 20px; color: #1a3c5e; margin-top: 2px; }
        .cert-footer { margin-top: 25px; text-align: center; border-top: 1px solid #f0e8d8; padding-top: 15px; }
        .cert-footer p { font-size: 11px; color: #bbb; letter-spacing: 0.5px; margin: 0; }
        .cert-footer .verify-link { color: #c9a959; text-decoration: none; font-weight: 600; }
        .cert-footer .verify-link:hover { text-decoration: underline; }
        .pdf-mode .certificate-content { padding: 20px; }
        .pdf-viewer { width: 100%; height: 600px; border: none; border-radius: 8px; }
        @media (max-width: 768px) {
            body { padding: 20px 10px; }
            .certificate-content { padding: 25px 20px 20px; }
            .certificate-border { padding: 20px 15px 15px; }
            .cert-header h1 { font-size: 24px; letter-spacing: 2px; }
            .cert-header .subtitle { font-size: 13px; letter-spacing: 4px; }
            .cert-body .recipient { font-size: 32px; padding: 5px 20px; }
            .cert-body .recipient::before, .cert-body .recipient::after { display: none; }
            .cert-body .course-name { font-size: 22px; }
            .signature-area { gap: 30px; }
            .signature-item .sign-line { width: 120px; }
            .cert-toolbar { padding: 10px 15px; flex-direction: column; align-items: stretch; }
            .cert-toolbar .brand { text-align: center; font-size: 14px; }
            .cert-toolbar .actions { justify-content: center; }
            .cert-toolbar .actions .btn { font-size: 12px; padding: 5px 12px; }
            .corner { width: 20px; height: 20px; }
            .pdf-viewer { height: 400px; }
        }
        @media (max-width: 480px) {
            .certificate-content { padding: 15px 10px 10px; }
            .certificate-border { padding: 15px 10px 10px; }
            .cert-header h1 { font-size: 20px; }
            .cert-body .recipient { font-size: 26px; }
            .cert-body .course-name { font-size: 18px; }
            .signature-area { gap: 20px; flex-direction: column; align-items: center; }
            .signature-item .sign-line { width: 150px; }
            .cert-toolbar .actions .btn { font-size: 11px; padding: 4px 10px; }
            .pdf-viewer { height: 300px; }
        }
        @media print {
            body { background: white !important; padding: 0 !important; margin: 0 !important; display: block !important; }
            .cert-toolbar { display: none !important; }
            .certificate-wrapper { box-shadow: none !important; border-radius: 0 !important; max-width: 100% !important; margin: 0 !important; }
            .certificate-content { padding: 30px 40px !important; }
            .certificate-border { border-color: #000 !important; border-width: 3px !important; }
            .certificate-border::before { display: none; }
            .corner { border-color: #000 !important; }
            .cert-header { border-bottom-color: #ccc !important; }
            .cert-header h1 { color: #000 !important; }
            .cert-header .subtitle { color: #000 !important; }
            .cert-body .recipient { color: #000 !important; }
            .cert-body .recipient::before, .cert-body .recipient::after { color: #000 !important; }
            .cert-body .course-name { color: #000 !important; }
            .cert-body .date-text strong { color: #000 !important; }
            .signature-area { border-top-color: #ccc !important; }
            .signature-item .sign-line { border-bottom-color: #000 !important; }
            .cert-footer { border-top-color: #ccc !important; }
            .cert-footer p { color: #666 !important; }
            .pdf-viewer { height: 700px !important; }
        }
    </style>
</head>
<body>

<div class="certificate-wrapper <?php echo $has_pdf ? 'pdf-mode' : ''; ?>">
    
    <!-- Toolbar -->
    <div class="cert-toolbar">
        <div class="brand">
            <span class="glyphicon glyphicon-certificate"></span> eCert <span>BPMI</span>
            <?php if ($is_admin): ?>
                <span class="admin-badge">ADMIN VIEW</span>
            <?php endif; ?>
        </div>
        <div class="actions">
            <!-- DOWNLOAD — Available for both admin and public -->
            <a href="download_certificate.php?serialNum=<?php echo urlencode($cert['serialNum']); ?>" 
               class="btn btn-download">
                <span class="glyphicon glyphicon-download-alt"></span> Download
            </a>
            
            <!-- REQUEST — Only for physical certificates AND only for public users -->
            <?php if (!$is_admin && isset($cert['cert_type']) && $cert['cert_type'] == 'physical'): ?>
                <a href="request_physical.php?serialNum=<?php echo urlencode($cert['serialNum']); ?>" 
                   class="btn btn-request">
                    <span class="glyphicon glyphicon-send"></span><span>Request Physical</span>
                </a>
            <?php endif; ?>
            
            <!-- BACK — Uses explicit back param, referer, or fallback -->
            <?php 
            if ($is_admin):
                $back_url = 'admin/index.php';
                $back_label = 'Back to Dashboard';
                
                // ============================================
                // METHOD 1: Use the explicit `back` param (BEST)
                // ============================================
                if (isset($_GET['back']) && !empty($_GET['back'])) {
                    $back_param = $_GET['back'];
                    
                    // Security: only allow relative paths (no http:// or //)
                    if (strpos($back_param, 'http') !== 0 && strpos($back_param, '//') !== 0) {
                        $back_url = 'admin/' . $back_param;
                        $back_label = 'Back';
                    }
                }
                // ============================================
                // METHOD 2: Fall back to referer
                // ============================================
                elseif (!empty($_SERVER['HTTP_REFERER'])) {
                    $referer = $_SERVER['HTTP_REFERER'];
                    $ref_host = parse_url($referer, PHP_URL_HOST);
                    $cur_host = $_SERVER['HTTP_HOST'];
                    
                    if ($ref_host === $cur_host && !str_contains($referer, 'view_certificate.php')) {
                        $back_url = $referer;
                        $back_label = 'Back';
                    }
                }
                ?>
                
                <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn btn-back">
                    <span class="glyphicon glyphicon-arrow-left"></span> <?php echo $back_label; ?>
                </a>
                
            <?php else: ?>
                
                <a href="index.php" class="btn btn-back">
                    <span class="glyphicon glyphicon-arrow-left"></span> Back
                </a>
                
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($has_pdf): ?>
        <div class="certificate-content">
            <embed src="<?php echo $cert_file; ?>" type="application/pdf" class="pdf-viewer">
        </div>
    <?php else: ?>
        <div class="certificate-content" id="certificateContent">
            <div class="certificate-border">
                <span class="corner corner-tl"></span>
                <span class="corner corner-tr"></span>
                <span class="corner corner-bl"></span>
                <span class="corner corner-br"></span>
                
                <div class="cert-header">
                    <span class="logo-icon">🏛️</span>
                    <h1>CERTIFICATE OF COMPLETION</h1>
                    <div class="subtitle">BPMI Perikanan Sabah</div>
                    <div class="cert-number">Certificate No: <?php echo htmlspecialchars($cert_number); ?></div>
                </div>
                
                <div class="cert-body">
                    <div class="awarded-text">This certificate is proudly awarded to</div>
                    <div class="recipient"><?php echo strtoupper(htmlspecialchars($cert['name'])); ?></div>
                    <div class="for-text">in recognition of successfully completing</div>
                    <div class="course-name"><?php echo htmlspecialchars($cert['course_name']); ?></div>
                    <div class="date-text">Held on <strong><?php echo date('d F Y', strtotime($cert['course_date'])); ?></strong></div>
                    
                    <div class="signature-area">
                        <div class="signature-item">
                            <div class="sign-line"></div>
                            <div class="sign-label">Date</div>
                        </div>
                        <div class="signature-item">
                            <div class="sign-line"></div>
                            <div class="sign-label">Signature</div>
                            <div class="sign-name">Officer</div>
                        </div>
                        <div class="signature-item">
                            <div class="sign-line"></div>
                            <div class="sign-label">Verified By</div>
                        </div>
                    </div>
                </div>
                
                <div class="cert-footer">
                    <p>This is a computer-generated certificate. Verify at <a href="#" class="verify-link">e-cert.bpmi.gov.my</a></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
