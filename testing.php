<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Fetch Country List from customer_segments
$countriesQuery = $conn->query("SELECT DISTINCT country FROM customer_segments ORDER BY country ASC");
$countries = [];
while ($row = $countriesQuery->fetch_assoc()) {
    $countries[] = $row["country"];
}

// ✅ Default Country Selection
$selectedCountry = isset($_GET['country']) ? $_GET['country'] : (count($countries) > 0 ? $countries[0] : "");

// ✅ Fetch RFM Data from customer_rfm (JOIN with customer_segments)
$rfmQuery = $conn->query("
    SELECT r.customer_id, r.recency, r.frequency, r.monetary, s.country
    FROM customer_rfm r
    JOIN customer_segments s ON r.customer_id = s.customer_id
    WHERE s.country = '$selectedCountry'
");

// ✅ Fetch LRFMP Data from customer_lrfmp (JOIN with customer_segments)
$lrfmpQuery = $conn->query("
    SELECT l.customer_id, l.length, l.recency, l.frequency, l.monetary, l.periodicity, s.country
    FROM customer_lrfmp l
    JOIN customer_segments s ON l.customer_id = s.customer_id
    WHERE s.country = '$selectedCountry'
");

// ✅ Store Data in Arrays
$rfmData = [];
while ($row = $rfmQuery->fetch_assoc()) {
    $rfmData[] = $row;
}

$lrfmpData = [];
while ($row = $lrfmpQuery->fetch_assoc()) {
    $lrfmpData[] = $row;
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

    <script>
        function filterCountry() {
            var selected = document.getElementById("countrySelect").value;
            window.location.href = "?country=" + selected;
        }

        document.addEventListener("DOMContentLoaded", function() {
            // ✅ RFM Bar Chart
            var ctx1 = document.getElementById("rfmBarChart").getContext("2d");
            new Chart(ctx1, {
                type: "bar",
                data: {
                    labels: ["VIP", "Loyal", "Dormant", "Regular"],
                    datasets: [{
                        label: "Number of Customers",
                        data: [
                            <?= count(array_filter($rfmData, fn($c) => $c["monetary"] >= 20000)) ?>,
                            <?= count(array_filter($rfmData, fn($c) => $c["monetary"] >= 1000 && $c["monetary"] < 20000)) ?>,
                            <?= count(array_filter($rfmData, fn($c) => $c["recency"] > 180)) ?>,
                            <?= count(array_filter($rfmData, fn($c) => $c["recency"] <= 180 && $c["monetary"] < 1000)) ?>
                        ],
                        backgroundColor: ["#6f42c1", "#28a745", "#dc3545", "#007bff"]
                    }]
                }
            });

            // ✅ LRFMP Scatter Plot
            var ctx2 = document.getElementById("lrfmpScatterPlot").getContext("2d");
            var lrfmpData = <?= json_encode($lrfmpData) ?>;
            var clusters = {};
            lrfmpData.forEach(c => {
                var category = c.monetary >= 20000 ? "High-Value" : 
                               (c.monetary >= 1000 ? "Mid-Tier" : "Low-Value");

                if (!clusters[category]) clusters[category] = { x: [], y: [] };
                clusters[category].x.push(c.frequency);
                clusters[category].y.push(c.monetary);
            });

            var datasets = [];
            Object.keys(clusters).forEach(cluster => {
                datasets.push({
                    label: cluster,
                    data: clusters[cluster].x.map((x, i) => ({ x, y: clusters[cluster].y[i] })),
                    backgroundColor: "#" + Math.floor(Math.random() * 16777215).toString(16)
                });
            });

            new Chart(ctx2, {
                type: "scatter",
                data: { datasets }
            });
        });
    </script>

    <style>
        body { background: #f4f6f9; font-family: Arial, sans-serif; }
        .container { max-width: 1100px; margin: auto; padding: 20px; background: white; border-radius: 10px; box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1); }
        .chart-container { margin-top: 20px; }
    </style>
</head>
<body>

<div class="container">
    <h2 class="text-center my-4">Customer Segmentation Dashboard</h2>

    <!-- Dropdown for Country Selection -->
    <div class="mb-3">
        <label class="form-label"><strong>Select Country:</strong></label>
        <select id="countrySelect" class="form-select" onchange="filterCountry()">
            <?php foreach ($countries as $country): ?>
                <option value="<?= $country ?>" <?= ($selectedCountry == $country) ? 'selected' : '' ?>>
                    <?= $country ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- RFM Bar Chart -->
    <div class="chart-container">
        <h5>📊 RFM Segmentation (Bar Chart)</h5>
        <canvas id="rfmBarChart"></canvas>
    </div>

    <!-- LRFMP Scatter Plot -->
    <div class="chart-container">
        <h5>📈 LRFMP Clusters (Scatter Plot)</h5>
        <canvas id="lrfmpScatterPlot"></canvas>
    </div>

    <!-- RFM Data Table -->
    <div class="card p-3">
        <h5>🏆 Top 10 RFM Customers in <?= $selectedCountry ?></h5>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Recency</th>
                    <th>Frequency</th>
                    <th>Monetary ($)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($rfmData, 0, 10) as $row): ?>
                    <tr>
                        <td><?= $row["customer_id"] ?></td>
                        <td><?= $row["recency"] ?> days</td>
                        <td><?= $row["frequency"] ?></td>
                        <td>$<?= number_format($row["monetary"], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- LRFMP Data Table -->
    <div class="card p-3">
        <h5>📊 LRFMP Customer Segments</h5>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Length</th>
                    <th>Recency</th>
                    <th>Frequency</th>
                    <th>Monetary ($)</th>
                    <th>Periodicity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($lrfmpData, 0, 10) as $row): ?>
                    <tr>
                        <td><?= $row["customer_id"] ?></td>
                        <td><?= $row["length"] ?> days</td>
                        <td><?= $row["recency"] ?> days</td>
                        <td><?= $row["frequency"] ?></td>
                        <td>$<?= number_format($row["monetary"], 2) ?></td>
                        <td><?= number_format($row["periodicity"], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="container mt-4">
    <h2 class="text-center">Log-Transformed Distributions</h2>
    
    <!-- Display the saved Python-generated image -->
    <div class="text-center">
        <img src="images/log_distribution.png" class="img-fluid" alt="Log Distribution Chart">
    </div>

</div>

</div>

</body>
</html>
