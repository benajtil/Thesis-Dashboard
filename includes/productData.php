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

// Fetch Top 10 Best-Selling Products
$query = "SELECT product_name, SUM(quantity) AS total_sold 
          FROM transactions 
          WHERE product_name IS NOT NULL 
          GROUP BY product_name 
          ORDER BY total_sold DESC 
          LIMIT 10";

$result = $conn->query($query);
$products = [];

while ($row = $result->fetch_assoc()) {
    $products[] = ["product_name" => $row["product_name"], "total_sold" => $row["total_sold"]];
}

$conn->close();
echo json_encode($products);
