<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$data = [
    "top_spending" => [],
    "top_orders" => [],
    "least_orders" => []
];


$query = "
    SELECT country, SUM(total_price) AS total_spent
    FROM transactions
    GROUP BY country
    ORDER BY total_spent DESC
    LIMIT 10
";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $data["top_spending"][] = [
        "country" => $row["country"],
        "total_spent" => floatval($row["total_spent"])
    ];
}


$query = "
    SELECT country, 
           COUNT(DISTINCT invoice_no) AS total_orders, 
           SUM(quantity) AS total_quantity
    FROM transactions
    WHERE quantity > 0 
    GROUP BY country
    ORDER BY total_orders DESC
    LIMIT 10
";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $data["top_orders"][] = [
        "country" => $row["country"],
        "total_orders" => intval($row["total_orders"]),
        "total_quantity" => intval($row["total_quantity"])
    ];
}


$query = "
    SELECT country, 
           COUNT(DISTINCT invoice_no) AS total_orders, 
           SUM(quantity) AS total_quantity
    FROM transactions
    WHERE quantity > 0
    GROUP BY country
    ORDER BY total_orders ASC
    LIMIT 10
";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $data["least_orders"][] = [
        "country" => $row["country"],
        "total_orders" => intval($row["total_orders"]),
        "total_quantity" => intval($row["total_quantity"])
    ];
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($data, JSON_PRETTY_PRINT);
?>
