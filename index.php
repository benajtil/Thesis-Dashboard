<?php
include 'includes/country.php';

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 🔹 Fetch DBSCAN Clustering Results
$clusters = [];
$result = $conn->query("SELECT cluster, COUNT(*) AS count FROM customer_segments GROUP BY cluster ORDER BY count DESC");
while ($row = $result->fetch_assoc()) {
    $clusters[$row["cluster"]] = $row["count"];
}

// 🔹 Fetch Top 10 Sales Months for Line Chart
$salesData = [];
$result = $conn->query("SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS month, SUM(total_price) AS total 
                        FROM transactions GROUP BY month ORDER BY total DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $salesData[$row["month"]] = $row["total"];
}

// 🔹 Fetch Top 10 Products for Pie Chart
$productData = [];
$result = $conn->query("SELECT product_name, COUNT(*) AS count FROM transactions 
                        GROUP BY product_name ORDER BY count DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $productData[$row["product_name"]] = $row["count"];
}

// 🔹 Fetch Customer Segmentation (Dormant, High Spender, Loyal)
$customerSegments = ["Dormant" => [], "High Spender" => [], "Loyal" => []];

$result = $conn->query("SELECT country, SUM(total_price) AS total_spent, COUNT(DISTINCT invoice_no) AS order_count 
                        FROM transactions GROUP BY country ORDER BY total_spent DESC");
while ($row = $result->fetch_assoc()) {
    if ($row["order_count"] == 1) {
        $customerSegments["Dormant"][] = $row;
    } elseif ($row["total_spent"] > 100000) {
        $customerSegments["High Spender"][] = $row;
    } elseif ($row["order_count"] > 100) {
        $customerSegments["Loyal"][] = $row;
    }
}

// 🔹 Pass Country Data to JavaScript
$countryData = [];
$result = $conn->query("SELECT country, SUM(total_price) AS total_spent, COUNT(DISTINCT invoice_no) AS total_orders FROM transactions GROUP BY country");
while ($row = $result->fetch_assoc()) {
    $countryData[$row['country']] = [
        "total_spent" => $row['total_spent'],
        "total_orders" => $row['total_orders'],
        "segment" => ($row['total_spent'] > 100000 ? "High Spender" : ($row['total_orders'] > 100 ? "Loyal" : "Dormant"))
    ];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap/dist/css/jsvectormap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jsvectormap"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsvectormap/dist/maps/world.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { background: #f4f6f9; font-family: Arial, sans-serif; }
        .dashboard-container { max-width: 1200px; margin: auto; padding: 20px; }
        .card { box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1); }
        #world-map { height: 500px; }
    </style>
</head>
<body>

<div class="container dashboard-container">
    <h2 class="text-center my-4">Admin Dashboard</h2>

    <div class="row">
        <div class="col-md-6">
            <div class="card p-3">
                <h5>Top 10 Product Sales</h5>
                <canvas id="pieChart"></canvas>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card p-3">
                <h5>Top 10 Sales Months</h5>
                <canvas id="lineChart"></canvas>
            </div>
        </div>
    </div>

    <div class="row my-4">
        <h3 class="text-center">Customer Segments</h3>
        <?php foreach ($customerSegments as $segment => $customers): ?>
            <div class="col-md-4">
                <div class="card p-3">
                    <h5><?php echo $segment; ?> Customers</h5>
                    <ul>
                        <?php foreach ($customers as $customer): ?>
                            <li> <?php echo $customer["country"]; ?> - $<?php echo number_format($customer["total_spent"], 2); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card p-3 my-4">
        <h5>Customer Spending by Country</h5>
        <div id="world-map"></div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var countryData = <?php echo json_encode($countryData, JSON_FORCE_OBJECT); ?>;
    
    var formattedData = {};
    for (var key in countryData) {
        formattedData[key.toUpperCase()] = parseFloat(countryData[key]["total_spent"]);
    }

    var map = new jsVectorMap({
        selector: "#world-map",
        map: "world",
        backgroundColor: "transparent",
        series: { regions: [{ scale: ["#FFD700", "#FF8C00", "#FF0000"], normalizeFunction: "polynomial", values: formattedData }] }
    });
});

var ctx1 = document.getElementById("pieChart").getContext("2d");
new Chart(ctx1, {
    type: "pie",
    data: { labels: <?php echo json_encode(array_keys($productData)); ?>, datasets: [{ data: <?php echo json_encode(array_values($productData)); ?>, backgroundColor: ["#FF6384", "#36A2EB", "#FFCE56"] }] }
});

var ctx2 = document.getElementById("lineChart").getContext("2d");
new Chart(ctx2, {
    type: "line",
    data: { labels: <?php echo json_encode(array_keys($salesData)); ?>, datasets: [{ label: "Total Sales", data: <?php echo json_encode(array_values($salesData)); ?>, borderColor: "#36A2EB", fill: false }] }
});
</script>

</body>
</html>
