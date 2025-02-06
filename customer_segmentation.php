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

$customerSegments = [
    "Dormant Customers" => [],
    "High Spender Customers" => [],
    "Loyal Customers" => [],
    "Frequent Shoppers" => [],
    "Churn Risk Customers" => [],
    "New Customers" => [],
    "Bulk Buyers" => [],
    "One-Time High Spenders" => [],
    "Seasonal Buyers" => [],
    "Low-Value Repeat Customers" => []
];

$result = $conn->query("
    SELECT country, SUM(total_price) AS total_spent, COUNT(DISTINCT invoice_no) AS total_orders, 
           MAX(invoice_date) AS last_order_date, AVG(quantity) AS avg_order_quantity,
           DATE_FORMAT(invoice_date, '%M') AS order_month 
    FROM transactions GROUP BY country ORDER BY total_spent DESC LIMIT 10");

$today = date("Y-m-d");
$sixMonthsAgo = date("Y-m-d", strtotime("-6 months"));
$oneMonthAgo = date("Y-m-d", strtotime("-1 month"));

while ($row = $result->fetch_assoc()) {
    $country = $row["country"];
    $totalSpent = $row["total_spent"];
    $totalOrders = $row["total_orders"];
    $lastOrderDate = $row["last_order_date"];
    $avgOrderQuantity = round($row["avg_order_quantity"], 2);
    $orderMonth = $row["order_month"];
    $lastOrderFormatted = ($lastOrderDate == "0000-00-00" || empty($lastOrderDate)) ? null : date("F Y", strtotime($lastOrderDate));

    if ($totalOrders == 1) {
        $customerSegments["Dormant Customers"][$country] = $totalSpent;
    }
    if ($totalSpent > 100000) {
        $customerSegments["High Spender Customers"][$country] = $totalSpent;
    }
    if ($totalOrders > 100) {
        $customerSegments["Loyal Customers"][$country] = $totalOrders;
    }
    if ($lastOrderDate < $sixMonthsAgo && $totalOrders > 0) {
        if ($lastOrderFormatted !== null) {
            $customerSegments["Churn Risk Customers"][$country] = ["total_orders" => $totalOrders, "last_order" => $lastOrderFormatted];
        }
    }
    if ($lastOrderDate > $oneMonthAgo && $totalOrders == 1) {
        $customerSegments["New Customers"][$country] = $totalSpent;
    }
    if ($avgOrderQuantity > 50) {
        $customerSegments["Bulk Buyers"][$country] = $avgOrderQuantity;
    }
    if ($totalSpent > 200 && $totalOrders == 1) {
        $customerSegments["One-Time High Spenders"][$country] = $totalSpent;
    }
    if (!empty($orderMonth)) {
        $customerSegments["Seasonal Buyers"][$country] = $orderMonth;
    }
    if ($totalOrders > 10 && $totalSpent < 500) {
        $customerSegments["Low-Value Repeat Customers"][$country] = $totalSpent;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Segmentation Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container mt-4">
    <h2 class="text-center">Customer Segments</h2>
    <div class="row">
        <?php foreach ($customerSegments as $segment => $countries): ?>
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title"> <?php echo $segment; ?> </h5>
                        <ul class="list-unstyled">
                            <?php foreach ($countries as $country => $value): ?>
                                <li><?php echo $country; ?> - <b>
                                    <?php if ($segment == "Bulk Buyers") {
                                        echo $value . " units per order";
                                    } elseif ($segment == "Seasonal Buyers") {
                                        echo "Mostly orders in " . $value;
                                    } elseif ($segment == "Churn Risk Customers") {
                                        echo " (Last Order: " . $value["last_order"] . ")";
                                    } elseif ($segment == "Loyal Customers") {
                                        echo number_format($value) . " Total Orders";
                                    } else {
                                        echo "$" . number_format($value, 2);
                                    } ?>
                                </b></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="card p-3 mt-4">
        <h5>Customer Segment Distribution</h5>
        <canvas id="customerSegmentChart"></canvas>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        var segmentData = <?php echo json_encode(array_map("count", $customerSegments)); ?>;
        var segmentLabels = <?php echo json_encode(array_keys($customerSegments)); ?>;
        var ctx = document.getElementById("customerSegmentChart").getContext("2d");
        new Chart(ctx, {
            type: "bar",
            data: {
                labels: segmentLabels,
                datasets: [{
                    data: Object.values(segmentData),
                    backgroundColor: "#36A2EB"
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });
    });
</script>
</body>
</html>
