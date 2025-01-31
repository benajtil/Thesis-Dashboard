<?php
include '../includes/navbar.php';
include '../includes/db_connection.php';

// Load DBSCAN results
$dbscan_file = '../data/dbscan_results.json';
if (!file_exists($dbscan_file)) {
    die("⚠️ DBSCAN results not found!");
}
$clusters = json_decode(file_get_contents($dbscan_file), true);

// Initialize Segments
$customerSegments = [
    "High Value Customers" => [],
    "Loyal Customers" => [],
    "Frequent Shoppers" => [],
    "Churn Risk Customers" => [],
    "Seasonal Buyers" => [],
    "Low-Value Customers" => []
];

// Assign Clusters to Segments
foreach ($clusters as $row) {
    $customer_id = $row["CustomerID"];
    $monetary = number_format($row["Monetary"], 2);
    $total_orders = number_format($row["Frequency"]);
    $last_purchase = $row["Recency"];
    
    if ($row["Cluster"] == 0) {
        $customerSegments["High Value Customers"][] = "$customer_id - $$monetary";
    } elseif ($row["Cluster"] == 1) {
        $customerSegments["Loyal Customers"][] = "$customer_id - $total_orders Orders";
    } elseif ($row["Cluster"] == 2) {
        $customerSegments["Frequent Shoppers"][] = "$customer_id - $total_orders Orders";
    } elseif ($row["Cluster"] == -1) {
        $customerSegments["Churn Risk Customers"][] = "$customer_id - Last Order: $last_purchase days ago";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DBSCAN Segmentation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container">
    <h2 class="text-center my-4">DBSCAN Customer Segmentation</h2>
    <div class="row">
        <?php foreach ($customerSegments as $segment => $customers): ?>
            <div class="col-md-4">
                <div class="card p-3">
                    <h5><?php echo $segment; ?></h5>
                    <ul>
                        <?php foreach (array_slice($customers, 0, 10) as $customer): ?>
                            <li><?php echo $customer; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Visualization Chart -->
    <div class="card p-3 mt-4">
        <h5>Customer Segments Distribution</h5>
        <canvas id="dbscanChart"></canvas>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var segmentData = <?php echo json_encode(array_map("count", $customerSegments)); ?>;
    var segmentLabels = <?php echo json_encode(array_keys($customerSegments)); ?>;

    var ctx = document.getElementById("dbscanChart").getContext("2d");
    new Chart(ctx, {
        type: "pie",
        data: {
            labels: segmentLabels,
            datasets: [{
                data: Object.values(segmentData),
                backgroundColor: ["#FF6384", "#36A2EB", "#FFCE56", "#4CAF50", "#FF9800"]
            }]
        }
    });
});
</script>

</body>
</html>
