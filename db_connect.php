<?php
$conn = new mysqli(
    getenv("MYSQLHOST"),      // Use Railway's MySQL host
    getenv("MYSQLUSER"),      // Use Railway's MySQL user
    getenv("MYSQLPASSWORD"),  // Use Railway's MySQL password
    getenv("MYSQLDATABASE"),  // Use Railway's MySQL database
    getenv("MYSQLPORT")       // Use Railway's MySQL port
);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
