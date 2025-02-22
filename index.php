<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$salesQuery = $conn->query("SELECT SUM(total_price) AS total_sales FROM cleaned_transactions");
$totalSales = $salesQuery->fetch_assoc()['total_sales'];

$productQuery = $conn->query("SELECT COUNT(DISTINCT product_name) AS total_products FROM cleaned_transactions");
$totalProducts = $productQuery->fetch_assoc()['total_products'];

$customersQuery = $conn->query("SELECT COUNT(DISTINCT customer_id) AS total_customers FROM cleaned_transactions");
$totalCustomers = $customersQuery->fetch_assoc()['total_customers'];

// Total Cancelled Orders
$cancelledQuery = $conn->query("SELECT COUNT(*) AS cancelled_orders, SUM(total_price) AS cancelled_amount FROM transactions WHERE invoice_no LIKE 'C%'");
$cancelledData = $cancelledQuery->fetch_assoc();
$totalCancelledOrders = $cancelledData['cancelled_orders'];
$totalCancelledAmount = $cancelledData['cancelled_amount'];

// Total Refunded Orders
$refundedQuery = $conn->query("SELECT COUNT(*) AS refunded_orders, SUM(total_price) AS refunded_amount FROM transactions WHERE quantity < 0");
$refundedData = $refundedQuery->fetch_assoc();
$totalRefundedOrders = $refundedData['refunded_orders'];
$totalRefundedAmount = $refundedData['refunded_amount'];

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap/dist/css/jsvectormap.min.css">
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

        .info-card h3 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .blue {
            background-color: #007bff;
        }

        .green {
            background-color: #28a745;
        }

        .yellow {
            background-color: #ffc107;
        }

        .red {
            background-color: #dc3545;
        }

        .orange {
            background-color: #ff5733;
        }

        .purple {
            background-color: #6f42c1;
        }

        canvas {
            max-height: 250px;
        }
    </style>
</head>

<body>

    <div class="container dashboard-container">
        <h2 class="text-center my-4">Admin Dashboard</h2>

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="info-card blue">
                    <h3>$<?php echo number_format($totalSales, 2); ?></h3>
                    <p>Total Sales</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-card green">
                    <h3><?php echo number_format($totalProducts); ?></h3>
                    <p>Total Products</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-card yellow">
                    <h3><?php echo number_format($totalCustomers); ?></h3>
                    <p>Total Customers</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-card red">
                    <h3><?php echo number_format($totalCancelledOrders); ?></h3>
                    <p>Cancelled Orders (Amount: $<?php echo number_format($totalCancelledAmount, 2); ?>)</p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="info-card orange">
                        <h3><?php echo number_format($totalRefundedOrders); ?></h3>
                        <p>Refunded Orders (Amount: $<?php echo number_format($totalRefundedAmount, 2); ?>)</p>
                    </div>
                </div>
                
            </div>

        </div>


        <div class="card">
            <h5>📈 Sales Over Time</h5>
            <canvas id="salesChart"></canvas>
        </div>

        <div class="row">

            <div class="col-md-6">
                <div class="card">
                    <h5>🏆 Top 10 Best-Selling Products</h5>
                    <canvas id="productChart"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <h5>📦 Top 10 Countries by Quantity Ordered</h5>
                    <canvas id="topOrdersChart"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <h5>🌍 Country</h5>
            <div id="customerMap" style="width: 100%; height: 500px;"></div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <h5>🌍 Top 10 Countries by Spending</h5>
                    <canvas id="topSpendingChart"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <h5>📉 Least Ordered Countries</h5>
                    <canvas id="leastOrdersChart"></canvas>
                </div>
            </div>
        </div>
    </div>


    <script>
        document.addEventListener("DOMContentLoaded", async function() {
            async function loadCustomerMap() {
                try {
                    const response = await fetch("includes/country.php"); // Fetch country data
                    const data = await response.json();

                    let countryColors = {};
                    let markers = [];

                    for (const country in data) {
                        const orders = data[country].total_orders;
                        const spent = data[country].total_spent;

                        let color = "#9e9e9e"; // Default (Casual Buyer)
                        if (spent > 200000) color = "#FF5733"; // VIP Customers (Red)
                        else if (orders > 100) color = "#36A2EB"; // Loyal Customers (Blue)

                        countryColors[country] = color;
                    }

                    // Initialize jsVectorMap
                    new jsVectorMap({
                        selector: "#customerMap",
                        map: "world",
                        backgroundColor: "#f4f6f9",
                        regionStyle: {
                            initial: {
                                fill: "#d1d1d1"
                            },
                            hover: {
                                fill: "#f4a261"
                            }
                        },
                        series: {
                            regions: [{
                                attribute: "fill",
                                scale: ["#9e9e9e", "#36A2EB", "#FF5733"],
                                values: countryColors
                            }]
                        },
                        onRegionTipShow: function(event, label, code) {
                            if (data[code]) {
                                let details = `
                        <strong>${label.html()}</strong><br>
                        💰 Total Spent: $${data[code].total_spent.toLocaleString()}<br>
                        📦 Orders: ${data[code].total_orders} <br>
                        🔥 Segment: ${data[code].segment}
                    `;
                                label.html(details);
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
        const response = await fetch("includes/salesMonths.php"); // Fetch cleaned data
        const data = await response.json();

        if (!data || !Array.isArray(data) || data.length === 0) {
            console.error("No data received for stock chart.");
            return;
        }

        const months = data.map(entry => entry.month);
        const totalSales = data.map(entry => entry.total_sales);
        const cancelledSales = data.map(entry => entry.cancelled_sales);
        const refundedSales = data.map(entry => entry.refunded_sales);

        const ctx = document.getElementById("salesChart").getContext("2d");

        const gradient1 = ctx.createLinearGradient(0, 0, 0, 400);
        gradient1.addColorStop(0, "rgba(0, 123, 255, 0.6)");


        const gradient2 = ctx.createLinearGradient(0, 0, 0, 400);
        gradient2.addColorStop(0, "rgba(255, 99, 132, 0.6)");


        const gradient3 = ctx.createLinearGradient(0, 0, 0, 400);
        gradient3.addColorStop(0, "rgba(255, 205, 86, 0.6)");


        new Chart(ctx, {
            type: "line",
            data: {
                labels: months,
                datasets: [
                    {
                        label: "Total Sales ($)",
                        data: totalSales,
                        borderColor: "#007bff",
                        borderWidth: 3,
                        fill: false,
                        tension: 0.3
                    },
                    {
                        label: "Cancelled Orders ($)",
                        data: cancelledSales,
                        borderColor: "#FF6384",
                        borderWidth: 3,
                        fill: false,
                        tension: 0.3
                    },
                    {
                        label: "Refunded Orders ($)",
                        data: refundedSales,
                        borderColor: "#FFCE56",

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
                    x: { title: { display: true, text: "Months" } },
                    y: { title: { display: true, text: "Sales ($)" }, beginAtZero: false }
                }
            }
        });

    } catch (error) {
        console.error("Sales Chart Error:", error);
    }
}

loadSalesChart();


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

                    // ✅ Destroy existing chart instance if it exists
                    if (window.topProductsChart instanceof Chart) {
                        window.topProductsChart.destroy();
                    }

                    window.topProductsChart = new Chart(ctx, {
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
                                    position: "right"
                                }
                            }
                        }
                    });

                } catch (error) {
                    console.error("Top Products Chart Error:", error);
                }
            }

            loadTopProductsChart();
        });

        async function loadCountryCharts() {
            try {
                const response = await fetch("includes/topCountries.php");
                const data = await response.json();

                // 🌍 **Top 10 Countries by Spending (Gradient Bar)**
                const topSpendingCtx = document.getElementById("topSpendingChart").getContext("2d");
                new Chart(topSpendingCtx, {
                    type: "bar",
                    data: {
                        labels: data.top_spending.map(item => item.country),
                        datasets: [{
                            label: "Total Spent ($)",
                            data: data.top_spending.map(item => item.total_spent),
                            backgroundColor: data.top_spending.map(() => getRandomGradient()), // Unique gradient per bar
                            borderRadius: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                enabled: true
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    display: false
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });

                // 📦 **Top 10 Countries by Total Orders & Quantity (Stacked Bar)**
                const topOrdersCtx = document.getElementById("topOrdersChart").getContext("2d");
                new Chart(topOrdersCtx, {
                    type: "bar",
                    data: {
                        labels: data.top_orders.map(item => item.country),
                        datasets: [{
                            label: "Total Orders",
                            data: data.top_orders.map(item => item.total_orders),
                            backgroundColor: "#36A2EB"
                        }, {
                            label: "Total Quantity Ordered",
                            data: data.top_orders.map(item => item.total_quantity),
                            backgroundColor: "rgba(75, 192, 192, 0.5)"
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: "top"
                            }
                        },
                        scales: {
                            x: {
                                stacked: true,
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true
                            }
                        }
                    }
                });

                // 📉 **Bottom 10 Least Ordered Countries (Dotted Line Chart)**
                const leastOrdersCtx = document.getElementById("leastOrdersChart").getContext("2d");
                new Chart(leastOrdersCtx, {
                    type: "line",
                    data: {
                        labels: data.least_orders.map(item => item.country),
                        datasets: [{
                            label: "Total Orders",
                            data: data.least_orders.map(item => item.total_orders),
                            borderColor: "#FF9F40",
                            backgroundColor: "rgba(255, 159, 64, 0.2)",
                            borderWidth: 2,
                            pointRadius: 5,
                            pointStyle: "circle",
                            borderDash: [5, 5] // Creates a dotted line effect
                        }, {
                            label: "Total Quantity Ordered",
                            data: data.least_orders.map(item => item.total_quantity),
                            borderColor: "#9966FF",
                            backgroundColor: "rgba(153, 102, 255, 0.2)",
                            borderWidth: 2,
                            pointRadius: 5,
                            pointStyle: "rectRounded"
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: "bottom"
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    display: true
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });

            } catch (error) {
                console.error("Country Chart Error:", error);
            }
        }

        loadCountryCharts();

        // Function to generate random gradient colors
        function getRandomGradient() {
            const ctx = document.createElement("canvas").getContext("2d");
            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, getRandomColor());
            gradient.addColorStop(1, getRandomColor());
            return gradient;
        }

        // Function to generate random colors
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