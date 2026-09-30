<?php
session_start();

// Clear navigation stack
$_SESSION['nav_stack'] = [];

session_destroy();
header('Location: ../index.php');
exit;
?>