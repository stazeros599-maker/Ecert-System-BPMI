<?php
// db.php - Database connection file

$host = "localhost";
$username = "root";
$password = "";
$database = "ecert_system_bpmi";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to get or create participant
function getOrCreateParticipant($conn, $icNum, $fullName) {
    $stmt = $conn->prepare("SELECT icNum FROM participant WHERE icNum = ?");
    $stmt->bind_param("s", $icNum);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        return $icNum;
    } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO participant (icNum, fullName) VALUES (?, ?)");
        $stmt->bind_param("ss", $icNum, $fullName);
        $stmt->execute();
        $stmt->close();
        return $icNum;
    }
}
?>