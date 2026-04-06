<?php
include 'db_connect.php';

// Fetch all challans with vehicle and violation details
$query = "SELECT c.id, c.vehicle_id, v.violation_name, c.count, c.status, c.created_at, c.updated_at
          FROM challan c
          JOIN violations v ON c.violation_id = v.id
          ORDER BY c.created_at DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Challan Records</title>
    <style>
        table {
            border-collapse: collapse;
            width: 80%;
            margin: 20px auto;
        }
        th, td {
            border: 1px solid #333;
            padding: 8px;
            text-align: center;
        }
        th {
            background: #eee;
        }
    </style>
</head>
<body>
    <h2 style="text-align:center;">Challan Records</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Vehicle ID</th>
            <th>Violation</th>
            <th>Count</th>
            <th>Status</th>
            <th>Created At</th>
            <th>Updated At</th>
        </tr>
        <?php while($row = mysqli_fetch_assoc($result)) { ?>
        <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo $row['vehicle_id']; ?></td>
            <td><?php echo $row['violation_name']; ?></td>
            <td><?php echo $row['count']; ?></td>
            <td><?php echo ucfirst($row['status']); ?></td>
            <td><?php echo $row['created_at']; ?></td>
            <td><?php echo $row['updated_at']; ?></td>
        </tr>
        <?php } ?>
    </table>
</body>
</html>
