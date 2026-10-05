<?php
// admin/nav_stack.php - Navigation history stack for admin pages

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Push the current admin page URL onto the navigation stack.
 * Skips pushing if:
 *   - It's the same as the last pushed URL (avoids duplicates on refresh)
 *   - It's a POST request (form submission — no need to track)
 */
function push_nav_stack() {
    // Don't track form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return;
    }
    
    if (!isset($_SESSION['nav_stack'])) {
        $_SESSION['nav_stack'] = [];
    }
    
    $current = $_SERVER['REQUEST_URI'];
    
    // Don't push if it's the same as the last one
    $last = end($_SESSION['nav_stack']);
    if ($last === $current) {
        return;
    }
    
    // Cap the stack at 15 entries
    if (count($_SESSION['nav_stack']) >= 15) {
        array_shift($_SESSION['nav_stack']);
    }
    
    $_SESSION['nav_stack'][] = $current;
}

/**
 * Pop the current page off and return the previous page URL.
 * If the stack is empty, returns the fallback.
 */
function pop_nav_stack($fallback = 'index.php') {
    if (empty($_SESSION['nav_stack']) || count($_SESSION['nav_stack']) < 2) {
        return $fallback;
    }
    
    // Remove current page
    array_pop($_SESSION['nav_stack']);
    
    // Previous page
    $previous = end($_SESSION['nav_stack']);
    
    return !empty($previous) ? $previous : $fallback;
}

/**
 * Peek at the previous page without removing it
 */
function peek_nav_stack($fallback = 'index.php') {
    if (empty($_SESSION['nav_stack']) || count($_SESSION['nav_stack']) < 2) {
        return $fallback;
    }
    
    $stack = $_SESSION['nav_stack'];
    array_pop($stack);
    
    $previous = end($stack);
    return !empty($previous) ? $previous : $fallback;
}

/**
 * Clear the entire stack (useful on logout or after login)
 */
function clear_nav_stack() {
    $_SESSION['nav_stack'] = [];
}
?>