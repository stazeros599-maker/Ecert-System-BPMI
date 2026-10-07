<?php
// db.php - Database connection file

$host     = getenv('MYSQL_ADDON_HOST')     ?: 'b2alyrh6onix1ljywzb1-mysql.services.clever-cloud.com';
$username = getenv('MYSQL_ADDON_USER')     ?: 'ukelfumksdmwak5e';
$password = getenv('MYSQL_ADDON_PASSWORD') ?: 'bBP1FHwCuwyH8uSMGbdp';
$database = getenv('MYSQL_ADDON_DB')       ?: 'b2alyrh6onix1ljywzb1';
$port     = getenv('MYSQL_ADDON_PORT')     ?: 3306;

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
