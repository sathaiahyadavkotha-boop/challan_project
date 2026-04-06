<?php
$host = "mysql-ifj.railway.internal";
$user = "root";
$pass = "OqBOdvXusaRyGzBoZbGVQLSrUeQgoRtt";
$db   = "railway";
$port = 3306;

$conn = mysqli_init();
mysqli_real_connect($conn, $host, $user, $pass, $db, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
