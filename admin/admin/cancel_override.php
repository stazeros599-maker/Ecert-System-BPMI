<?php
session_start();
include 'nav_stack.php';
push_nav_stack();

// Delete the uploaded file if it exists
if (isset($_SESSION['pending_certificate']['cert_file'])) {
    $file = '../certificates/' . $_SESSION['pending_certificate']['cert_file'];
    if (file_exists($file)) {
        unlink($file);
    }
}

// Clear the pending data
unset($_SESSION['pending_certificate']);

// Redirect back to add form
header('Location: add_certificate.php');
exit;
?>