<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed"]));
}

$range = $_GET['range'] ?? 'all_time';

$query = "SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS month, SUM(total_price) AS total FROM transactions GROUP BY month ORDER BY month ASC";
$result = $conn->query($query);

$salesData = [];
while ($row = $result->fetch_assoc()) {
    $salesData[$row["month"]] = floatval($row["total"]);
}

echo json_encode($salesData);
$conn->close();
?>
