<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Fetch Country List
$countriesQuery = $conn->query("SELECT DISTINCT country FROM customer_segments ORDER BY country ASC");
$countries = [];
while ($row = $countriesQuery->fetch_assoc()) {
    $countries[] = $row["country"];
}

// ✅ Default Country Selection
$selectedCountry = isset($_GET['country']) ? $_GET['country'] : (count($countries) > 0 ? $countries[0] : "");

// ✅ Fetch Country-Based Stats with Extended Segmentation
$countryStatsQuery = $conn->query("
    SELECT 
        COUNT(DISTINCT customer_id) AS total_customers,
        COUNT(CASE WHEN total_spent >= 20000 THEN 1 END) AS vip_customers,
        COUNT(CASE WHEN total_spent >= 1000 AND total_spent < 20000 THEN 1 END) AS loyal_customers,
        COUNT(CASE WHEN recency > 180 THEN 1 END) AS dormant_customers,
        COUNT(CASE WHEN recency <= 30 THEN 1 END) AS new_customers,
        COUNT(CASE WHEN total_orders >= 20 THEN 1 END) AS frequent_buyers,
        COUNT(CASE WHEN monetary >= 5000 THEN 1 END) AS big_spenders,
        COUNT(CASE WHEN monetary < 100 THEN 1 END) AS low_value_customers
    FROM customer_segments
    WHERE country = '$selectedCountry'
");
$countryStats = $countryStatsQuery->fetch_assoc();

// ✅ Fetch Top 10 Customers from Selected Country
$topCustomersQuery = $conn->query("
    SELECT customer_id, total_spent, total_orders, recency, monetary,
        CASE 
            WHEN total_spent >= 20000 THEN 'VIP'
            WHEN total_spent >= 1000 THEN 'Loyal'
            WHEN recency > 180 THEN 'Dormant'
            WHEN recency <= 30 THEN 'New'
            WHEN total_orders >= 20 THEN 'Frequent Buyer'
            WHEN monetary >= 5000 THEN 'Big Spender'
            WHEN monetary < 100 THEN 'Low Value'
            ELSE 'Regular'
        END AS segment
    FROM customer_segments
    WHERE country = '$selectedCountry'
    ORDER BY total_spent DESC
    LIMIT 10
");

$topGlobalCustomersQuery = $conn->query("
    SELECT customer_id, country, total_spent, total_orders, segment,
    CASE 
            WHEN total_spent >= 20000 THEN 'VIP'
            WHEN total_spent >= 1000 THEN 'Loyal'
            WHEN recency > 180 THEN 'Dormant'
            WHEN recency <= 30 THEN 'New'
            WHEN total_orders >= 20 THEN 'Frequent Buyer'
            WHEN monetary >= 5000 THEN 'Big Spender'
            WHEN monetary < 100 THEN 'Low Value'
            ELSE 'Regular'
        END AS segment
    
    FROM customer_segments
    ORDER BY total_spent DESC
    LIMIT 10
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Segmentation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <script>
        function filterCountry() {
            var selected = document.getElementById("countrySelect").value;
            window.location.href = "?country=" + selected;
        }
    </script>
    <style>
        body { background: #f4f6f9; font-family: Arial, sans-serif; }
        .container { max-width: 1100px; margin: auto; padding: 20px; background: white; border-radius: 10px; box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1); }
        .info-card { padding: 15px; border-radius: 10px; text-align: center; color: white; margin-bottom: 15px; }
        .info-card h3 { font-size: 24px; margin-bottom: 5px; }
        .blue { background-color: #007bff; }
        .green { background-color: #28a745; }
        .red { background-color: #dc3545; }
        .purple { background-color: #6f42c1; }
        .orange { background-color: #fd7e14; }
        .pink { background-color: #e83e8c; }
        .cyan { background-color: #17a2b8; }
        .gray { background-color: #6c757d; }
    </style>
</head>
<body>

<div class="container">
    <h2 class="text-center my-4">Customer Segmentation Dashboard</h2>

    <!-- Country Selection -->
    <div class="mb-3">
        <label class="form-label"><strong>Filter by Country:</strong></label>
        <select id="countrySelect" class="form-select" onchange="filterCountry()">
            <?php foreach ($countries as $country): ?>
                <option value="<?= $country ?>" <?= ($selectedCountry == $country) ? 'selected' : '' ?>>
                    <?= $country ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Country Stats -->
    <div class="row">
        <div class="col-md-3">
            <div class="info-card blue">
                <h3><?= number_format($countryStats["total_customers"]); ?></h3>
                <p>Total Customers</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-card green">
                <h3><?= number_format($countryStats["loyal_customers"]); ?></h3>
                <p>Loyal Customers</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-card red">
                <h3><?= number_format($countryStats["dormant_customers"]); ?></h3>
                <p>Dormant Customers</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-card purple">
                <h3><?= number_format($countryStats["vip_customers"]); ?></h3>
                <p>VIP Customers</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-card orange">
                <h3><?= number_format($countryStats["new_customers"]); ?></h3>
                <p>New Customers</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-card pink">
                <h3><?= number_format($countryStats["frequent_buyers"]); ?></h3>
                <p>Frequent Buyers</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-card cyan">
                <h3><?= number_format($countryStats["big_spenders"]); ?></h3>
                <p>Big Spenders</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-card gray">
                <h3><?= number_format($countryStats["low_value_customers"]); ?></h3>
                <p>Low-Value Customers</p>
            </div>
        </div>
    </div>

    <div class="card p-3">
        <h5>🏆 Top 10 Customers in <?= $selectedCountry ?></h5>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Total Spent ($)</th>
                    <th>Total Orders</th>
                    <th>Recency (Days)</th>
                    <th>Segment</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $topCustomersQuery->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row["customer_id"] ?></td>
                        <td>$<?= number_format($row["total_spent"], 2) ?></td>
                        <td><?= $row["total_orders"] ?></td>
                        <td><?= $row["recency"] ?></td>
                        <td><span class="badge bg-primary"><?= $row["segment"] ?></span></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <div class="card p-3">
        <h5>🌍 Top 10 Customers Across All Countries</h5>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Country</th>
                    <th>Total Spent ($)</th>
                    <th>Total Orders</th>
                    <th>Segment</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $topGlobalCustomersQuery->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row["customer_id"] ?></td>
                        <td><?= $row["country"] ?></td>
                        <td>$<?= number_format($row["total_spent"], 2) ?></td>
                        <td><?= $row["total_orders"] ?></td>
                        <td><span class="badge bg-primary"><?= $row["segment"] ?></span></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
