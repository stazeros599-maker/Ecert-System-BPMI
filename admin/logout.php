<?php
session_start();

require_once '../db.php';
require_once 'logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
	log_activity($conn, 'auth', 'logout', 'success', 'Admin logged out',
		$_SESSION['admin_username'] ?? null,
		['role' => $_SESSION['admin_role'] ?? 'admin']);
}

// Clear navigation stack
$_SESSION['nav_stack'] = [];

session_destroy();
header('Location: ../index.php');
exit;
?>