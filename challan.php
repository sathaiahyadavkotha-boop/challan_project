<?php
session_start();

// Connect to DB
$conn = new mysqli(
    $_ENV["MYSQLHOST"],
    $_ENV["MYSQLUSER"],
    $_ENV["MYSQLPASSWORD"],
    $_ENV["MYSQLDATABASE"],
    $_ENV["MYSQLPORT"]
);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$vehicle_number = $_POST['vehicle_number'] ?? null;
$message = null;
$row = null;
$violations_after = null;
$challan_amount = 0;

if ($vehicle_number) {
    // Fetch vehicle details
    $stmt = $conn->prepare("SELECT * FROM vehicles WHERE vehicle_number=?");
    $stmt->bind_param("s", $vehicle_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Count violations after last challan
        $stmt2 = $conn->prepare("
            SELECT COUNT(*) AS violations_after_challan
            FROM violations
            WHERE vehicle_id = ?
              AND violation_date > (
                SELECT IFNULL(MAX(challan_date), '1970-01-01')
                FROM challans
                WHERE vehicle_id = ?
            )
        ");
        $stmt2->bind_param("ii", $row['id'], $row['id']);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $violations_after = $result2->fetch_assoc()['violations_after_challan'];

        // Get last challan info
        $stmt3 = $conn->prepare("SELECT amount, status FROM challans WHERE vehicle_id=? ORDER BY challan_date DESC LIMIT 1");
        $stmt3->bind_param("i", $row['id']);
        $stmt3->execute();
        $result3 = $stmt3->get_result();

        if ($result3->num_rows > 0) {
            $lastChallan = $result3->fetch_assoc();
            if ($lastChallan['status'] === 'unpaid') {
                // Add 20 extra if not paid
                $challan_amount = $lastChallan['amount'] + 20;
            } else {
                $challan_amount = $lastChallan['amount'];
            }
        } else {
            // First challan default amount
            $challan_amount = 500;
        }
    } else {
        $message = "<div class='message error'>❌ Vehicle not found. Please check the number and try again.</div>";
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Challan Status</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <h2>💳 Challan Status</h2>

  <?php if ($message) echo $message; ?>

  <?php if ($row): ?>
    <div class="message success">
      Owner: <b><?php echo htmlspecialchars($row['owner_name']); ?></b><br>
      Vehicle: <b><?php echo htmlspecialchars($row['vehicle_number']); ?></b><br>
      Total Violations: <b><?php echo htmlspecialchars($row['violation_count']); ?></b><br>
      Violations after last challan: <b><?php echo htmlspecialchars($violations_after); ?></b>
    </div>

    <?php if ($row['violation_count'] > 5): ?>
      <div class="message warning">⚠️ Challan Issued! Please pay.</div>
      <p>Amount Due: <b>₹<?php echo $challan_amount; ?></b></p>
      <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=upi://pay?pa=gov@upi&pn=PollutionDept&am=<?php echo $challan_amount; ?>&cu=INR" alt="Pay QR">
    <?php else: ?>
      <div class="message success">✅ No challan issued yet.</div>
    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
