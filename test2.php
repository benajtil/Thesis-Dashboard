<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 📊 Fetch main statistics
$salesQuery = $conn->query("SELECT SUM(total_price) AS total_sales FROM cleaned_transactions");
$totalSales = $salesQuery->fetch_assoc()['total_sales'];

$productQuery = $conn->query("SELECT COUNT(DISTINCT product_name) AS total_products FROM cleaned_transactions");
$totalProducts = $productQuery->fetch_assoc()['total_products'];

$customersQuery = $conn->query("SELECT COUNT(DISTINCT customer_id) AS total_customers FROM cleaned_transactions");
$totalCustomers = $customersQuery->fetch_assoc()['total_customers'];

// ✅ Total Cancelled Orders
$cancelledQuery = $conn->query("SELECT COUNT(*) AS cancelled_orders, SUM(total_price) AS cancelled_amount FROM transactions WHERE invoice_no LIKE 'C%'");
$cancelledData = $cancelledQuery->fetch_assoc();
$totalCancelledOrders = $cancelledData['cancelled_orders'] ?? 0;
$totalCancelledAmount = $cancelledData['cancelled_amount'] ?? 0;

// ✅ Total Refunded Orders
$refundedQuery = $conn->query("SELECT COUNT(*) AS refunded_orders, SUM(total_price) AS refunded_amount FROM transactions WHERE quantity < 0");
$refundedData = $refundedQuery->fetch_assoc();
$totalRefundedOrders = $refundedData['refunded_orders'] ?? 0;
$totalRefundedAmount = $refundedData['refunded_amount'] ?? 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsvectormap"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsvectormap/dist/maps/world.js"></script>

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
            margin-bottom: 20px;
            padding: 15px;
        }
        .info-card {
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            color: white;
        }
        .blue { background-color: #007bff; }
        .green { background-color: #28a745; }
        .yellow { background-color: #ffc107; }
        .red { background-color: #dc3545; }
        .orange { background-color: #ff5733; }
        .purple { background-color: #6f42c1; }
    </style>
</head>

<body>

    <div class="container dashboard-container">
        <h2 class="text-center my-4">Admin Dashboard</h2>

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-3"><div class="info-card blue"><h3>$<?php echo number_format($totalSales, 2); ?></h3><p>Total Sales</p></div></div>
            <div class="col-md-3"><div class="info-card green"><h3><?php echo number_format($totalProducts); ?></h3><p>Total Products</p></div></div>
            <div class="col-md-3"><div class="info-card yellow"><h3><?php echo number_format($totalCustomers); ?></h3><p>Total Customers</p></div></div>
            <div class="col-md-3"><div class="info-card red"><h3><?php echo number_format($totalCancelledOrders); ?></h3><p>Cancelled Orders ($<?php echo number_format($totalCancelledAmount, 2); ?>)</p></div></div>
            <div class="col-md-3"><div class="info-card orange"><h3><?php echo number_format($totalRefundedOrders); ?></h3><p>Refunded Orders ($<?php echo number_format($totalRefundedAmount, 2); ?>)</p></div></div>
        </div>

        <!-- Sales Chart -->
        <div class="card">
            <h5>📈 Sales Over Time</h5>
            <canvas id="salesChart"></canvas>
        </div>

        <div class="row">
            <div class="col-md-6"><div class="card"><h5>🏆 Top 10 Best-Selling Products</h5><canvas id="productChart"></canvas></div></div>
            <div class="col-md-6"><div class="card"><h5>📦 Top 10 Countries by Quantity Ordered</h5><canvas id="topOrdersChart"></canvas></div></div>
        </div>

        <div class="row">
            <div class="col-md-6"><div class="card"><h5>🌍 Top 10 Countries by Spending</h5><canvas id="topSpendingChart"></canvas></div></div>
            <div class="col-md-6"><div class="card"><h5>📉 Least Ordered Countries</h5><canvas id="leastOrdersChart"></canvas></div></div>
        </div>
    </div>

    <script>
        async function loadSalesChart() {
            try {
                const response = await fetch("includes/salesMonths.php");
                const data = await response.json();

                if (!data || !Array.isArray(data) || data.length === 0) {
                    console.error("No data received for stock chart.");
                    return;
                }

                const months = data.map(entry => entry.month);
                const totalSales = data.map(entry => entry.total_sales);
                const cancelledSales = data.map(entry => entry.cancelled_sales);
                const refundedSales = data.map(entry => entry.refunded_sales);

                new Chart(document.getElementById("salesChart").getContext("2d"), {
                    type: "line",
                    data: {
                        labels: months,
                        datasets: [
                            { label: "Total Sales ($)", data: totalSales, borderColor: "#007bff", fill: false, tension: 0.3 },
                            { label: "Cancelled Orders ($)", data: cancelledSales, borderColor: "#FF6384", fill: false, tension: 0.3 },
                            { label: "Refunded Orders ($)", data: refundedSales, borderColor: "#FFCE56", fill: false, tension: 0.3 }
                        ]
                    },
                    options: { responsive: true, plugins: { legend: { position: "top" } } }
                });

            } catch (error) {
                console.error("Sales Chart Error:", error);
            }
        }

        async function loadTopOrdersChart() {
            try {
                const response = await fetch("includes/topCountries.php");
                const data = await response.json();

                const labels = data.map(item => item.country);
                const totalOrders = data.map(item => item.total_orders);
                const totalQuantity = data.map(item => item.total_quantity);
                const cancelledQuantity = data.map(item => item.cancelled_quantity);
                const refundedQuantity = data.map(item => item.refunded_quantity);

                new Chart(document.getElementById("topOrdersChart").getContext("2d"), {
                    type: "bar",
                    data: {
                        labels: labels,
                        datasets: [
                            { label: "Total Orders", data: totalOrders, backgroundColor: "#36A2EB" },
                            { label: "Total Quantity Ordered", data: totalQuantity, backgroundColor: "rgba(75, 192, 192, 0.5)" },
                            { label: "Cancelled Orders", data: cancelledQuantity, backgroundColor: "#FF6384" },
                            { label: "Refunded Orders", data: refundedQuantity, backgroundColor: "#FFCE56" }
                        ]
                    },
                    options: { responsive: true, plugins: { legend: { position: "top" } } }
                });

            } catch (error) {
                console.error("Top Orders Chart Error:", error);
            }
        }

        loadSalesChart();
        loadTopOrdersChart();
    </script>

</body>
</html>
