<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$query = "
    SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS month, COUNT(DISTINCT invoice_no) AS total_orders
    FROM transactions
    GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
    ORDER BY month ASC;
";

$result = $conn->query($query);
$ordersData = [];

while ($row = $result->fetch_assoc()) {
    $ordersData[] = $row;
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($ordersData);
?>
