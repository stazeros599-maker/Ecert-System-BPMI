<?php
/**
 * logger.php — Central activity logging helper
 * 
 * Usage:
 *   log_activity($conn, 'auth', 'login', 'success', 'User logged in', 'admin');
 *   log_activity($conn, 'certificate', 'add', 'failure', 'Duplicate serial', 'PN-0074');
 */

if (!function_exists('log_activity')) {

    function log_activity($conn, $module, $action, $status = 'success', $message = null, $target_id = null, $details = null) {
        
        // Guard: connection must be alive
        if (!isset($conn) || !($conn instanceof mysqli)) {
            return false;
        }
        
        // Skip logging if activity_logs table doesn't exist yet
        static $table_checked = false;
        static $table_exists = true;
        if (!$table_checked) {
            $check = @$conn->query("SHOW TABLES LIKE 'activity_logs'");
            $table_exists = ($check && $check->num_rows > 0);
            $table_checked = true;
        }
        if (!$table_exists) {
            return false;
        }
        
        // ============================================
        // WHO
        // ============================================
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            $actor_type = ($_SESSION['admin_role'] ?? 'admin') === 'developer' ? 'developer' : 'admin';
            $actor_id   = $_SESSION['admin_username'] ?? 'unknown';
        } else {
            $actor_type = 'public';
            $actor_id   = $_GET['nokp'] ?? $_POST['nokp'] ?? null;
        }
        
        $actor_ip   = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
        $page_url   = isset($_SERVER['REQUEST_URI']) ? substr($_SERVER['REQUEST_URI'], 0, 255) : null;
        
        // ============================================
        // WHAT
        // ============================================
        $details_json = !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
        
        if ($message) {
            $message = substr($message, 0, 500);
        }
        if ($target_id) {
            $target_id = substr($target_id, 0, 50);
        }
        
        // ============================================
        // INSERT
        // ============================================
        $sql = "INSERT INTO activity_logs 
                (actor_type, actor_id, actor_ip, user_agent, module, action, target_id, status, message, details, page_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = @$conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param(
            "sssssssssss",
            $actor_type, $actor_id, $actor_ip, $user_agent,
            $module, $action, $target_id, $status, $message, $details_json, $page_url
        );
        
        $result = @$stmt->execute();
        $stmt->close();
        
        return $result;
    }
}
?>