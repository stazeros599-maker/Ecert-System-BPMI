<?php

function is_admin_login_rate_limited(mysqli $conn, string $ipHash, string $usernameHash): bool
{
    $cleanup = $conn->query(
        "DELETE FROM admin_login_attempts
         WHERE attempted_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)"
    );
    if ($cleanup === false) {
        throw new RuntimeException('Unable to clean up admin login attempts.');
    }

    $stmt = $conn->prepare(
        "SELECT
            SUM(ip_hash = ?) AS ip_attempts,
            SUM(username_hash = ?) AS username_attempts
         FROM admin_login_attempts
         WHERE ip_hash = ?
           AND attempted_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)"
    );
    if ($stmt === false) {
        throw new RuntimeException('Unable to check admin login attempt limits.');
    }

    $stmt->bind_param('sss', $ipHash, $usernameHash, $ipHash);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to check admin login attempt limits.');
    }

    $result = $stmt->get_result();
    $attempts = $result->fetch_assoc();
    $stmt->close();

    return (int) ($attempts['ip_attempts'] ?? 0) >= 20
        || (int) ($attempts['username_attempts'] ?? 0) >= 5;
}

function record_admin_login_failure(mysqli $conn, string $ipHash, string $usernameHash): void
{
    $stmt = $conn->prepare(
        "INSERT INTO admin_login_attempts (ip_hash, username_hash, attempted_at)
         VALUES (?, ?, UTC_TIMESTAMP())"
    );
    if ($stmt === false) {
        throw new RuntimeException('Unable to record admin login attempt.');
    }

    $stmt->bind_param('ss', $ipHash, $usernameHash);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to record admin login attempt.');
    }

    $stmt->close();
}
