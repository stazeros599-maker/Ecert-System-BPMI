<?php
// db.php - Database connection file

// Replace these 4 values with your actual InfinityFree MySQL details
$host     = 'sql107.infinityfree.com'; // Your InfinityFree MySQL Hostname
$username = 'if0_43222027';            // Your InfinityFree MySQL Username
$password = 'ecertSystemBpmi';  // Your InfinityFree vPanel/Account Password
$database = 'if0_43222027_ecertPortal_db';    // Your InfinityFree Database Name
$port     = 3306;

// Initialize mysqli without SSL requirement
$conn = mysqli_connect($host, $username, $password, $database, $port);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$conn->set_charset("utf8mb4");

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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
