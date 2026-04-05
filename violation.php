<?php
$conn = new mysqli(
    $_ENV["MYSQLHOST"],
    $_ENV["MYSQLUSER"],
    $_ENV["MYSQLPASSWORD"],
    $_ENV["MYSQLDATABASE"],
    $_ENV["MYSQLPORT"]
);

if ($conn->connect_error) {
    die(json_encode(["status"=>"error","message"=>"Database connection failed"]));
}

$sensor_code     = $_POST['sensor_code'] ?? null;
$pollution_value = $_POST['pollution_value'] ?? null;

if (!$sensor_code || !$pollution_value) {
    echo json_encode(["status"=>"error","message"=>"Missing sensor_code or pollution_value"]);
    exit;
}

// Find vehicle by sensor_code
$stmt = $conn->prepare("SELECT id, vehicle_number FROM vehicles WHERE sensor_code=?");
$stmt->bind_param("s", $sensor_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $vehicle_id = $row['id'];

    // Insert violation
    $stmt2 = $conn->prepare("
        INSERT INTO violations (vehicle_id, sensor_code, pollution_value, violation_date)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt2->bind_param("iss", $vehicle_id, $sensor_code, $pollution_value);
    $stmt2->execute();

    // Count violations in last 1 minute
    $stmt3 = $conn->prepare("
        SELECT COUNT(*) AS total 
        FROM violations 
        WHERE vehicle_id=? 
          AND violation_date >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");
    $stmt3->bind_param("i", $vehicle_id);
    $stmt3->execute();
    $countResult = $stmt3->get_result();
    $countRow = $countResult->fetch_assoc();
    $violations_last_minute = $countRow['total'];

    $response = [
        "status" => "success",
        "message" => "Violation recorded",
        "vehicle_id" => $vehicle_id,
        "vehicle_number" => $row['vehicle_number'],
        "violations_last_minute" => $violations_last_minute
    ];

    // 🚨 Auto-create challan if violations > 5 in last minute
    if ($violations_last_minute > 5) {
        $stmt4 = $conn->prepare("
            INSERT INTO challans (vehicle_id, challan_date, amount, status)
            VALUES (?, NOW(), 500, 'unpaid')
        ");
        $stmt4->bind_param("i", $vehicle_id);
        $stmt4->execute();

        $response["message"] = "Violation recorded, challan issued";
        $response["challan_amount"] = 500;
    }

    echo json_encode($response);

} else {
    echo json_encode(["status"=>"error","message"=>"Sensor code not found"]);
}

$conn->close();
?>
