<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch total sales
$query = "
    SELECT 
        DATE_FORMAT(invoice_date, '%Y-%m') AS month, 
        SUM(total_price) AS total_sales,
        SUM(CASE WHEN invoice_no LIKE 'C%' THEN total_price ELSE 0 END) AS cancelled_sales,
        SUM(CASE WHEN total_price < 0 THEN total_price ELSE 0 END) AS refunded_sales
    FROM transactions
    GROUP BY month
    ORDER BY month ASC
";

$result = $conn->query($query);
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        "month" => $row["month"],
        "total_sales" => floatval($row["total_sales"]),
        "cancelled_sales" => floatval($row["cancelled_sales"]),
        "refunded_sales" => abs(floatval($row["refunded_sales"])) // Convert negative refund values to positive
    ];
}

$conn->close();

header('Content-Type: application/json');
echo json_encode($data, JSON_PRETTY_PRINT);
?>
