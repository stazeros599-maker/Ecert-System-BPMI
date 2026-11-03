<?php

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') .
        '">';
}

function csrf_validate_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    $submittedToken = $_POST['_csrf_token'] ?? null;
    $sessionToken = $_SESSION['_csrf_token'] ?? null;

    if (
        !is_string($submittedToken) ||
        !is_string($sessionToken) ||
        !hash_equals($sessionToken, $submittedToken)
    ) {
        http_response_code(403);
        if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Request could not be verified.']);
            exit;
        }
        exit('Request could not be verified. Reload the page and try again.');
    }
}
