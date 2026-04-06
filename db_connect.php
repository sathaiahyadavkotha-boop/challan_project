<?php
$servername = "localhost";
$username   = "root";       // your DB username
$password   = "";           // your DB password
$dbname     = "trafficdb";  // your database name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
