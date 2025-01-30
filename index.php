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

// Get top 10 sales months for line chart
$salesData = [];
$result = $conn->query("SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS month, SUM(total_price) AS total 
                        FROM transactions GROUP BY month ORDER BY total DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $salesData[$row["month"]] = $row["total"];
}

// Get top 10 products for pie chart
$productData = [];
$result = $conn->query("SELECT product_name, COUNT(*) AS count FROM transactions 
                        GROUP BY product_name ORDER BY count DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $productData[$row["product_name"]] = $row["count"];
}

// Get customer segmentation and top 10 customers in each category
$customerSegments = [
    "Dormant" => [],
    "High Spender" => [],
    "Loyal" => []
];

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

// Limit to top 10
foreach ($customerSegments as $key => $customers) {
    $customerSegments[$key] = array_slice($customers, 0, 10);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">

   <!-- JSVectorMap CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap/dist/css/jsvectormap.min.css">
<!-- JSVectorMap JS -->
<script src="https://cdn.jsdelivr.net/npm/jsvectormap"></script>
<script src="https://cdn.jsdelivr.net/npm/jsvectormap/dist/maps/world.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
        }
        .dashboard-container {
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }
        .card {
            box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1);
        }
        #world-map {
            height: 500px;
        }
    </style>
</head>
<body>

<div class="container dashboard-container">
    <h2 class="text-center my-4">Admin Dashboard</h2>

    <div class="row">
        <!-- Pie Chart -->
        <div class="col-md-6">
            <div class="card p-3">
                <h5>Top 10 Product Sales</h5>
                <canvas id="pieChart"></canvas>
            </div>
        </div>

        <!-- Line Chart -->
        <div class="col-md-6">
            <div class="card p-3">
                <h5>Top 10 Sales Months</h5>
                <canvas id="lineChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Customer Segmentation -->
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

    <!-- Vector Map -->
    <div class="card p-3 my-4">
        <h5>Customer Spending by Country</h5>
        <div id="world-map"></div>
    </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function() {
    var countryData = <?php echo json_encode($countryData, JSON_FORCE_OBJECT); ?>;
    console.log("Country Data from PHP:", countryData); // Debugging

    if (!countryData || Object.keys(countryData).length === 0) {
        console.error("No country data available. Check PHP output.");
        return;
    }

    var formattedData = {};
    for (var key in countryData) {
        formattedData[key.toUpperCase()] = parseFloat(countryData[key]["total_spent"]); // Use total spent for colors
    }

    console.log("Formatted Country Data:", formattedData); // Debugging

    var map = new jsVectorMap({
        selector: "#world-map",
        map: "world",
        backgroundColor: "transparent",
        regionStyle: {
            initial: {
                fill: "#D3D3D3"
            }
        },
        series: {
            regions: [{
                scale: ["#FFD700", "#FF8C00", "#FF0000"],
                normalizeFunction: "polynomial",
                attribute: "fill",
                values: formattedData,
                min: Math.min(...Object.values(formattedData)),
                max: Math.max(...Object.values(formattedData))
            }]
        },
        onRegionTooltipShow: function(event, tooltip, code) {
            if (countryData[code]) {
                let data = countryData[code];
                tooltip.text(`${tooltip.text()}
                    \nTotal Spending: $${data.total_spent.toFixed(2)}
                    \nTotal Orders: ${data.total_orders}
                    \nSegment: ${data.segment}
                `);
            }
        }
    });
});

    // Pie Chart
    var ctx1 = document.getElementById("pieChart").getContext("2d");
    new Chart(ctx1, {
        type: "pie",
        data: {
            labels: <?php echo json_encode(array_keys($productData)); ?>,
            datasets: [{
                data: <?php echo json_encode(array_values($productData)); ?>,
                backgroundColor: ["#FF6384", "#36A2EB", "#FFCE56", "#4CAF50", "#FF9800", "#8E44AD", "#E67E22", "#2ECC71", "#3498DB", "#F1C40F"]
            }]
        }
    });

    // Line Chart
    var ctx2 = document.getElementById("lineChart").getContext("2d");
    new Chart(ctx2, {
        type: "line",
        data: {
            labels: <?php echo json_encode(array_keys($salesData)); ?>,
            datasets: [{
                label: "Total Sales",
                data: <?php echo json_encode(array_values($salesData)); ?>,
                borderColor: "#36A2EB",
                fill: false
            }]
        }
    });
</script>

</body>
</html>
