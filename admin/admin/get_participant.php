<?php
// Set JSON header FIRST (before any output)
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../db.php';
require 'check_role.php';
require_role('admin');   // ← Only admins can access

$type = isset($_GET['type']) ? $_GET['type'] : '';
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query)) {
    echo json_encode(['found' => false]);
    exit;
}

if ($type === 'ic') {
    $stmt = $conn->prepare("SELECT icNum, fullName, insider FROM participant WHERE icNum = ?");
    $stmt->bind_param("s", $query);
} elseif ($type === 'name') {
    $stmt = $conn->prepare("SELECT icNum, fullName, insider FROM participant WHERE fullName LIKE ? LIMIT 5");
    $search = '%' . $query . '%';
    $stmt->bind_param("s", $search);
} else {
    echo json_encode(['found' => false]);
    exit;
}

$stmt->execute();
$result = $stmt->get_result();

if ($type === 'ic') {
    $row = $result->fetch_assoc();
    if ($row) {
        echo json_encode([
            'found' => true,
            'icNum' => $row['icNum'],
            'fullName' => $row['fullName'],
            'insider' => intval($row['insider'])
        ]);
    } else {
        echo json_encode(['found' => false]);
    }
} else {
    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = [
            'icNum' => $row['icNum'],
            'fullName' => $row['fullName'],
            'insider' => intval($row['insider'])
        ];
    }
    echo json_encode(['found' => count($suggestions) > 0, 'suggestions' => $suggestions]);
}

$stmt->close();
$conn->close();
?>