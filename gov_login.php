<?php
session_start();

// Connect to DB using environment variables
$conn = new mysqli(
    getenv("MYSQLHOST"),
    getenv("MYSQLUSER"),
    getenv("MYSQLPASSWORD"),
    getenv("MYSQLDATABASE"),
    getenv("MYSQLPORT")
);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trim to avoid accidental spaces
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Query the stored hash
    $stmt = $conn->prepare("SELECT password_hash FROM gov_users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Verify entered password against bcrypt hash
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['gov_user'] = true;
            header("Location: register.php");
            exit();
        } else {
            header("Location: error.php?type=password");
            exit();
        }
    } else {
        header("Location: error.php?type=password");
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Government Login</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <h2>🔒 Government Login</h2>
  <form method="POST">
    <label>Username:</label>
    <input type="text" name="username" required>
    <label>Password:</label>
    <input type="password" name="password" required>
    <button type="submit">Login</button>
  </form>
</div>
</body>
</html>
