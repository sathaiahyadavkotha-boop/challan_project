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

// Step 1: Safely read POST data
$sensor_code     = $_POST['sensor_code'] ?? null;
$pollution_value = $_POST['pollution_value'] ?? null;

// Step 2: Check vehicle exists
$stmt = $conn->prepare("SELECT id, vehicle_number FROM vehicles WHERE sensor_code=?");
$stmt->bind_param("s", $sensor_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status"=>"error","message"=>"Sensor code not found in vehicles table"]);
    $conn->close();
    exit;
}

// Step 3: Vehicle found → upsert violation row, incrementing violation_count
$row = $result->fetch_assoc();
$vehicle_id = $row['id'];

// Keep one row per vehicle; increment violation_count on each new reading
$stmt2 = $conn->prepare("
    INSERT INTO violations (vehicle_id, sensor_code, pollution_value, violation_count, violation_date)
    VALUES (?, ?, ?, 1, NOW())
    ON DUPLICATE KEY UPDATE
        violation_count = violation_count + 1,
        pollution_value = VALUES(pollution_value),
        violation_date  = NOW()
");
$stmt2->bind_param("iss", $vehicle_id, $sensor_code, $pollution_value);
$stmt2->execute();

// Step 4: Read current accumulated violation_count for this vehicle
$stmt3 = $conn->prepare("
    SELECT violation_count
    FROM violations
    WHERE vehicle_id = ?
");
$stmt3->bind_param("i", $vehicle_id);
$stmt3->execute();
$countResult = $stmt3->get_result();
$countRow    = $countResult->fetch_assoc();
$violation_count = $countRow['violation_count'] ?? 0;

$response = [
    "status"          => "success",
    "message"         => "Violation recorded",
    "vehicle_id"      => $vehicle_id,
    "vehicle_number"  => $row['vehicle_number'],
    "violation_count" => $violation_count
];

echo json_encode($response);

$conn->close();
?>
