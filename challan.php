<?php
include __DIR__ . '/db_connect.php';

function generateChallan($vehicle_id, $sensor_code) {
    global $conn;

    // Step 1: Fetch violation count before reset
    $stmt = $conn->prepare("SELECT violation_count FROM violations WHERE vehicle_id=?");
    $stmt->bind_param("i", $vehicle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $violation_count = $row['violation_count'] ?? 0;

    // Step 2: Calculate challan amount (₹250 per violation)
    $amount = $violation_count * 250;

    // Step 3: Insert challan record
    $stmt2 = $conn->prepare("
        INSERT INTO challans (vehicle_id, sensor_code, violation_count, amount, status, challan_date)
        VALUES (?, ?, ?, ?, 'unpaid', NOW())
    ");
    $stmt2->bind_param("isid", $vehicle_id, $sensor_code, $violation_count, $amount);

    if (!$stmt2->execute()) {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to create challan: " . $stmt2->error
        ]);
        return;
    }

    // Step 4: Reset violations for this vehicle
    $resetStmt = $conn->prepare("
        UPDATE violations 
        SET violation_count = 0, min_count = 0, violation_date = NOW() 
        WHERE vehicle_id = ?
    ");
    $resetStmt->bind_param("i", $vehicle_id);
    $resetStmt->execute();

    // Step 5: Response
    echo json_encode([
        "status" => "success",
        "message" => "Challan generated",
        "vehicle_id" => $vehicle_id,
        "sensor_code" => $sensor_code,
        "violation_count" => $violation_count,
        "amount" => $amount
    ]);
}
?>
