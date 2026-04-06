<?php
/**
 * challan_check.php
 *
 * Called every 3 minutes via cron (or a Railway cron job).
 * Finds every vehicle that has accumulated at least 1 violation and
 * creates an 'unpaid' challan for it, recording the current violation_count.
 *
 * Cron example (every 3 minutes):
 *   * /3 * * * * curl -s https://<your-domain>/challan_check.php
 */

$conn = new mysqli(
    $_ENV["MYSQLHOST"],
    $_ENV["MYSQLUSER"],
    $_ENV["MYSQLPASSWORD"],
    $_ENV["MYSQLDATABASE"],
    $_ENV["MYSQLPORT"]
);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}

// Fetch all vehicles that have at least one recorded violation
$stmt = $conn->prepare("
    SELECT vehicle_id, violation_count
    FROM violations
    WHERE violation_count > 0
");
$stmt->execute();
$result = $stmt->get_result();

$challans_created = 0;
$skipped          = 0;

while ($row = $result->fetch_assoc()) {
    $vehicle_id      = $row['vehicle_id'];
    $violation_count = $row['violation_count'];

    // Create a challan capturing the current violation_count
    $stmtIns = $conn->prepare("
        INSERT INTO challans (vehicle_id, challan_date, amount, status, violation_count)
        VALUES (?, NOW(), 500, 'unpaid', ?)
    ");
    $stmtIns->bind_param("ii", $vehicle_id, $violation_count);

    if ($stmtIns->execute()) {
        $challans_created++;
    } else {
        $skipped++;
    }
    $stmtIns->close();
}

$stmt->close();
$conn->close();

echo json_encode([
    "status"           => "success",
    "challans_created" => $challans_created,
    "skipped"          => $skipped
]);
?>
