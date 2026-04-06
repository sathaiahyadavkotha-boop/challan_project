<?php
/**
 * clear_violations.php
 *
 * POST parameters:
 *   vehicle_id  - ID of the vehicle whose violations should be cleared
 *   challan_id  - ID of the challan that was paid
 *
 * Behaviour:
 *   - Verifies the challan belongs to the vehicle and has status 'paid'.
 *   - Deletes all violation records for that vehicle.
 *   - Resets violation_count to 0 on the vehicles row.
 *   - Returns a JSON response indicating success or the reason for failure.
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

$vehicle_id = $_POST['vehicle_id'] ?? null;
$challan_id = $_POST['challan_id'] ?? null;

// Validate required parameters
if (!$vehicle_id || !$challan_id) {
    echo json_encode(["status" => "error", "message" => "vehicle_id and challan_id are required"]);
    $conn->close();
    exit;
}

// Check that the challan exists, belongs to this vehicle, and is paid
$stmt = $conn->prepare("
    SELECT id, status
    FROM challans
    WHERE id = ? AND vehicle_id = ?
");
$stmt->bind_param("ii", $challan_id, $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Challan not found for this vehicle"]);
    $stmt->close();
    $conn->close();
    exit;
}

$challan = $result->fetch_assoc();
$stmt->close();

if ($challan['status'] !== 'paid') {
    echo json_encode([
        "status"  => "error",
        "message" => "Challan is not paid yet. Current status: " . $challan['status']
    ]);
    $conn->close();
    exit;
}

// Delete all violation records for this vehicle
$stmtDel = $conn->prepare("DELETE FROM violations WHERE vehicle_id = ?");
$stmtDel->bind_param("i", $vehicle_id);
$stmtDel->execute();
$deleted_rows = $stmtDel->affected_rows;
$stmtDel->close();

// Reset violation_count on the vehicles table
$stmtReset = $conn->prepare("UPDATE vehicles SET violation_count = 0 WHERE id = ?");
$stmtReset->bind_param("i", $vehicle_id);
$stmtReset->execute();
$stmtReset->close();

$conn->close();

echo json_encode([
    "status"            => "success",
    "message"           => "Violation records cleared and violation_count reset",
    "vehicle_id"        => (int) $vehicle_id,
    "challan_id"        => (int) $challan_id,
    "violations_deleted"=> $deleted_rows
]);
?>
