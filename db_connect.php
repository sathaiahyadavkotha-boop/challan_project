<?php
$host = $_ENV['mysql-ifj.railway.internal'];
$user = $_ENV['root'];
$pass = $_ENV['OqBOdvXusaRyGzBoZbGVQLSrUeQgoRtt'];
$db   = $_ENV['railway'];
$port = $_ENV['3306'];

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
