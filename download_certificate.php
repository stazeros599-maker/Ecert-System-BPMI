<?php
include 'db.php';

if (!isset($_GET['serialNum']) || empty($_GET['serialNum'])) {
    die('Invalid certificate ID.');
}

$serialNum = $_GET['serialNum'];

// Query from certificates table (no JOIN needed - name is already in this table)
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

$cert_file = 'certificates/' . $cert['certificate_file'];

if (file_exists($cert_file)) {
    $ext = strtolower(pathinfo($cert_file, PATHINFO_EXTENSION));
    
    if ($ext == 'pdf') {
        header('Content-Type: application/pdf');
    } elseif (in_array($ext, ['jpg', 'jpeg'])) {
        header('Content-Type: image/jpeg');
    } elseif ($ext == 'png') {
        header('Content-Type: image/png');
    } elseif ($ext == 'gif') {
        header('Content-Type: image/gif');
    } else {
        header('Content-Type: application/octet-stream');
    }
    
    header('Content-Disposition: attachment; filename="' . str_replace(' ', '_', $cert['name']) . '_Certificate.' . $ext . '"');
    header('Content-Length: ' . filesize($cert_file));
    readfile($cert_file);
    exit;
} else {
    // File doesn't exist — redirect to view page
    header('Location: view_certificate.php?serialNum=' . urlencode($serialNum));
    exit;
}
?>