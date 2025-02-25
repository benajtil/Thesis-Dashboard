<?php
session_start();
include "includes/db_connection.php";


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


$query = $conn->query("
    SELECT 
        DAYNAME(invoice_date) AS order_day, 
        COUNT(*) AS total_orders, 
        SUM(total_price) AS total_spent
    FROM transactions 
    GROUP BY order_day
    ORDER BY FIELD(order_day, 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday')
");
$days = [];
$orders = [];
$spending = [];

while ($row = $query->fetch_assoc()) {
    $days[] = $row['order_day'];
    $orders[] = $row['total_orders'];
    $spending[] = $row['total_spent'];
}


$salesQuery = $conn->query("SELECT SUM(Monetary) AS total_sales FROM customer_analysis");
$totalSales = $salesQuery->fetch_assoc()['total_sales'];

$productQuery = $conn->query("SELECT COUNT(DISTINCT product_name) AS total_products FROM cleaned_transactions");
$totalProducts = $productQuery->fetch_assoc()['total_products'];

$customersQuery = $conn->query("SELECT COUNT(DISTINCT customerId) AS total_customers FROM customer_analysis WHERE customerId > 0");
$totalCustomers = $customersQuery->fetch_assoc()['total_customers'];

$countryQuery = $conn->query("SELECT COUNT(DISTINCT country) AS total_country FROM cleaned_transactions");
$totalCountry = $countryQuery->fetch_assoc()['total_country'];


$refundedQuery = $conn->query("SELECT COUNT(*) AS refunded_orders, SUM(total_price) AS refunded_amount FROM transactions WHERE quantity < 0");
$refundedData = $refundedQuery->fetch_assoc();
$totalRefundedOrders = $refundedData['refunded_orders'];
$totalRefundedAmount = $refundedData['refunded_amount'];

$conn->close();
?>

<?php include 'includes/navbar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>


    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap/dist/css/jsvectormap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jsvectormap"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsvectormap/dist/maps/world.js"></script>


    <style>

        body {
            background-color: #181B23;
            color: #E2E8F0;
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
        }
        a, a:hover, a:focus, a:active {
            text-decoration: none;
            color: inherit;
        }
        

        .navbar {
            background-color: #1F2029;
            border-bottom: 1px solid #2D2F36;
        }
        .navbar-brand, .nav-link {
            color: #E2E8F0 !important;
        }
        .nav-link:hover {
            color: #63B3ED !important;
        }


        .dashboard-container {
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }


        .card {
            background-color: #1F2029;
            border: none;
            border-radius: 8px;
            margin-bottom: 20px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .card h5 {
            margin-bottom: 15px;
            color: #A0AEC0;
        }


        .info-card {
            border-radius: 8px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            background-color: #2D2F36;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .info-card h3 {
            font-size: 24px;
            margin-bottom: 5px;
            color: #E2E8F0;
        }
        .info-card p {
            color: #A0AEC0;
            margin: 0;
        }


        canvas {
            width: 100% !important;
            max-height: 300px;
        }


        .text-center {
            text-align: center;
        }
        .my-4 {
            margin-top: 1.5rem !important;
            margin-bottom: 1.5rem !important;
        }


        .chart-container {
            position: relative;
            height: 300px;
        }
    </style>
</head>

<body>

    <!-- <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">My Dashboard</a>
            ...
        </div>
    </nav> -->

    <div class="container dashboard-container">
        <h2 class="text-center my-4">Admin Dashboard</h2>


        <div class="row g-3">
            <div class="col-md-3">
                <div class="info-card">
                    <h3>$<?php echo number_format($totalSales, 2); ?></h3>
                    <p>Total Sales</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-card">
                    <h3><?php echo number_format($totalProducts); ?></h3>
                    <p>Total Products</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-card">
                    <h3><?php echo number_format($totalCustomers); ?></h3>
                    <p>Total Customers</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-card">
                    <h3><?php echo number_format($totalCountry); ?></h3>
                    <p>Total Countries</p>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-md-3">
                <div class="info-card">
                    <h3><?php echo number_format($totalRefundedOrders); ?></h3>
                    <p>Refunded ( $<?php echo number_format($totalRefundedAmount, 2); ?> )</p>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <h5>Sales Over Time</h5>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <h5>Weekly Orders & Spending</h5>
                    <div class="chart-container">
                        <canvas id="weeklyOrdersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <h5>Top 10 Best-Selling Products</h5>
                    <div class="chart-container">
                        <canvas id="productChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <h5>Top 10 Countries by Quantity Ordered</h5>
                    <div class="chart-container">
                        <canvas id="topOrdersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Map -->
        <div class="card">
            <h5>Country Overview</h5>
            <div id="customerMap" style="width: 100%; height: 400px;"></div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <h5>Top 10 Countries by Spending</h5>
                    <div class="chart-container">
                        <canvas id="topSpendingChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <h5>Least Ordered Countries</h5>
                    <div class="chart-container">
                        <canvas id="leastOrdersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script>
        Chart.defaults.color = "#E2E8F0";
        Chart.defaults.borderColor = "rgba(255,255,255,0.1)";
        
        document.addEventListener("DOMContentLoaded", async function() {
            async function loadCustomerMap() {
    try {
        const response = await fetch("includes/country.php");
        const data = await response.json();

        const continentColors = {
            "North America": "#36A2EB",
            "South America": "#FF6384",
            "Europe":        "#FFCE56",
            "Africa":        "#4BC0C0",
            "Asia":          "#9966FF",
            "Oceania":       "#FF9F40",
            "Unknown":       "#757575"
        };

        let countryColors = {};
        let countryContinents = {};

        for (const isoCode in data) {
            const continent = data[isoCode].continent || "Unknown";
            const color = continentColors[continent] || "#757575";
            countryColors[isoCode] = color;
            countryContinents[isoCode] = continent;
        }

        new jsVectorMap({
            selector: "#customerMap",
            map: "world",
            backgroundColor: "rgba(255, 255, 255, 0.32)",
            regionStyle: {
                initial: { fill: "#555555" },
                hover: { fill: "#888888" }
            },
            series: {
                regions: [{
                    attribute: "fill",
                    scale: Object.values(continentColors),
                    values: countryColors
                }]
            },
            onRegionTipShow: function(event, label, code) {
                if (data[code]) {
                    const info = data[code];
                    label.html(`
                        <strong>${label.html()}</strong><br>
                        🌍 Continent: ${countryContinents[code]}<br>
                        💰 Total Spent: $${info.total_spent.toLocaleString()}<br>
                        📦 Orders: ${info.total_orders}
                    `);
                }
            }
        });
    } catch (error) {
        console.error("Customer Map Error:", error);
    }
}

loadCustomerMap();
            async function loadSalesChart() {
                try {
                    const response = await fetch("includes/salesMonths.php");
                    const data = await response.json();

                    if (!data || !Array.isArray(data) || data.length === 0) {
                        console.error("No data received for sales chart.");
                        return;
                    }

                    const months = data.map(entry => entry.month);
                    const totalSales = data.map(entry => entry.total_sales);
                    const refundedSales = data.map(entry => entry.refunded_sales);

                    const ctx = document.getElementById("salesChart").getContext("2d");

                    new Chart(ctx, {
                        type: "line",
                        data: {
                            labels: months,
                            datasets: [
                                {
                                    label: "Total Sales ($)",
                                    data: totalSales,
                                    borderColor: "#63B3ED",
                                    borderWidth: 3,
                                    fill: false,
                                    tension: 0.3
                                },
                                {
                                    label: "Refunded Orders ($)",
                                    data: refundedSales,
                                    borderColor: "#F6AD55",
                                    borderWidth: 3,
                                    fill: false,
                                    tension: 0.3
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: "top" },
                                tooltip: { enabled: true }
                            },
                            scales: {
                                x: {
                                    title: { display: true, text: "Months" }
                                },
                                y: {
                                    title: { display: true, text: "Sales ($)" },
                                    beginAtZero: false
                                }
                            }
                        }
                    });
                } catch (error) {
                    console.error("Sales Chart Error:", error);
                }
            }
            loadSalesChart();

            // Load Weekly Orders & Spending
            var ctxWeekly = document.getElementById("weeklyOrdersChart").getContext("2d");
            new Chart(ctxWeekly, {
                type: "line",
                data: {
                    labels: <?php echo json_encode($days); ?>,
                    datasets: [
                        {
                            label: "Total Orders",
                            data: <?php echo json_encode($orders); ?>,
                            borderColor: "#3182CE",
                            backgroundColor: "rgba(49, 130, 206, 0.2)",
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: "Total Spending ($)",
                            data: <?php echo json_encode($spending); ?>,
                            borderColor: "#E53E3E",
                            backgroundColor: "rgba(229, 62, 62, 0.2)",
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: "top" },
                        tooltip: { enabled: true }
                    },
                    scales: {
                        x: {
                            title: { display: true, text: "Weekdays" }
                        },
                        y: {
                            title: { display: true, text: "Orders & Spending" },
                            beginAtZero: true
                        }
                    }
                }
            });

            // Load Top Products Chart
            async function loadTopProductsChart() {
                try {
                    const response = await fetch("includes/productData.php");
                    const data = await response.json();

                    if (!data || data.length === 0) {
                        console.error("No data received for top products chart.");
                        return;
                    }

                    const labels = data.map(item => item.product_name);
                    const values = data.map(item => item.total_sold);

                    const ctx = document.getElementById("productChart").getContext("2d");

                    new Chart(ctx, {
                        type: "doughnut",
                        data: {
                            labels: labels,
                            datasets: [{
                                label: "Top 10 Best-Selling Products",
                                data: values,
                                backgroundColor: [
                                    "#FF6384", "#36A2EB", "#FFCE56", "#4BC0C0",
                                    "#9966FF", "#FF9F40", "#E57373", "#81C784",
                                    "#64B5F6", "#FFD54F"
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: "right",
                                    labels: { color: "#E2E8F0" }
                                }
                            }
                        }
                    });
                } catch (error) {
                    console.error("Top Products Chart Error:", error);
                }
            }
            loadTopProductsChart();

            // Load Country Charts
            async function loadCountryCharts() {
                try {
                    const response = await fetch("includes/topCountries.php");
                    const data = await response.json();

                    // Top 10 Countries by Spending
                    const topSpendingCtx = document.getElementById("topSpendingChart").getContext("2d");
                    new Chart(topSpendingCtx, {
                        type: "bar",
                        data: {
                            labels: data.top_spending.map(item => item.country),
                            datasets: [{
                                label: "Total Spent ($)",
                                data: data.top_spending.map(item => item.total_spent),
                                backgroundColor: data.top_spending.map(() => getRandomGradient()),
                                borderRadius: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { display: false },
                                tooltip: { enabled: true }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { display: false },
                                    ticks: { color: "#E2E8F0" }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { color: "#E2E8F0" }
                                }
                            }
                        }
                    });

                    // Top 10 Countries by Total Orders & Quantity
                    const topOrdersCtx = document.getElementById("topOrdersChart").getContext("2d");
                    new Chart(topOrdersCtx, {
                        type: "bar",
                        data: {
                            labels: data.top_orders.map(item => item.country),
                            datasets: [
                                {
                                    label: "Total Orders",
                                    data: data.top_orders.map(item => item.total_orders),
                                    backgroundColor: "#36A2EB"
                                },
                                {
                                    label: "Total Quantity",
                                    data: data.top_orders.map(item => item.total_quantity),
                                    backgroundColor: "rgba(75, 192, 192, 0.5)"
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { position: "top", labels: { color: "#E2E8F0" } }
                            },
                            scales: {
                                x: {
                                    stacked: true,
                                    grid: { display: false },
                                    ticks: { color: "#E2E8F0" }
                                },
                                y: {
                                    stacked: true,
                                    beginAtZero: true,
                                    ticks: { color: "#E2E8F0" }
                                }
                            }
                        }
                    });


                    const leastOrdersCtx = document.getElementById("leastOrdersChart").getContext("2d");
                    new Chart(leastOrdersCtx, {
                        type: "line",
                        data: {
                            labels: data.least_orders.map(item => item.country),
                            datasets: [
                                {
                                    label: "Total Orders",
                                    data: data.least_orders.map(item => item.total_orders),
                                    borderColor: "#FF9F40",
                                    backgroundColor: "rgba(255, 159, 64, 0.2)",
                                    borderWidth: 2,
                                    pointRadius: 4,
                                    pointStyle: "circle",
                                    borderDash: [5, 5]
                                },
                                {
                                    label: "Total Quantity",
                                    data: data.least_orders.map(item => item.total_quantity),
                                    borderColor: "#9966FF",
                                    backgroundColor: "rgba(153, 102, 255, 0.2)",
                                    borderWidth: 2,
                                    pointRadius: 4,
                                    pointStyle: "rectRounded"
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { position: "bottom", labels: { color: "#E2E8F0" } }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { display: true },
                                    ticks: { color: "#E2E8F0" }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { color: "#E2E8F0" }
                                }
                            }
                        }
                    });

                } catch (error) {
                    console.error("Country Chart Error:", error);
                }
            }
            loadCountryCharts();
        });

        function getRandomGradient() {
            const ctx = document.createElement("canvas").getContext("2d");
            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, getRandomColor());
            gradient.addColorStop(1, getRandomColor());
            return gradient;
        }

        function getRandomColor() {
            const letters = "0123456789ABCDEF";
            let color = "#";
            for (let i = 0; i < 6; i++) {
                color += letters[Math.floor(Math.random() * 16)];
            }
            return color;
        }
    </script>
</body>
</html>
