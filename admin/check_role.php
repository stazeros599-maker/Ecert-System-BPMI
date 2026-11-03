<?php
/**
 * check_role.php — Role-based access control
 * 
 * Usage:
 *   require 'check_role.php';
 *   require_role('admin');      // Only admins
 *   require_role('developer');  // Only developers
 */

if (!function_exists('require_role')) {

    function require_role($required_role) {
        // Check if logged in
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            // Redirect to login — adjust path based on context
            $login_path = 'login.php';
            if (strpos($_SERVER['REQUEST_URI'], '/developer/') !== false) {
                $login_path = '../login.php';
            }
            header('Location: ' . $login_path);
            exit;
        }
        
        $current_role = $_SESSION['admin_role'] ?? 'admin';
        
        if ($current_role !== $required_role) {
            // Redirect to their appropriate home
            if ($current_role === 'developer') {
                // Developer trying to access admin pages
                $redirect = 'developer/index.php';
                if (strpos($_SERVER['REQUEST_URI'], '/developer/') !== false) {
                    $redirect = 'index.php';
                }
                header('Location: ' . $redirect);
            } else {
                // Admin trying to access developer pages
                $redirect = 'index.php';
                if (strpos($_SERVER['REQUEST_URI'], '/developer/') !== false) {
                    $redirect = '../index.php';
                }
                header('Location: ' . $redirect);
            }
            exit;
        }
    }
}
?>