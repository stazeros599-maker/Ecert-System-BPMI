<?php
// db.php - Database connection file

if (!extension_loaded('mysqli')) {
    die('Database connection unavailable: enable the PHP mysqli extension in the web server PHP configuration and restart the web server.');
}

$host     = getenv('MYSQL_ADDON_HOST')     ?: 'mysql2.hostuno.com';
$username = getenv('MYSQL_ADDON_USER')     ?: 'm3549_faye';
$password = getenv('MYSQL_ADDON_PASSWORD') ?: 'pwfOtbZMTNmN06';
$database = getenv('MYSQL_ADDON_DB')       ?: 'm3549_ecertPortalDB';
$port     = getenv('MYSQL_ADDON_PORT')     ?: 3306;

mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
mysqli_real_connect($conn, $host, $username, $password, $database, $port);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/csrf.php';
csrf_validate_post();

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