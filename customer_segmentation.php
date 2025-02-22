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

// ✅ Fetch RFM Data
$rfmQuery = $conn->query("
    SELECT customer_id, recency, frequency, monetary,
        CASE 
            WHEN monetary >= 20000 THEN 'VIP'
            WHEN monetary >= 1000 THEN 'Loyal'
            WHEN recency > 180 THEN 'Dormant'
            ELSE 'Regular'
        END AS rfm_segment
    FROM customer_rfm
    WHERE customer_id IN (SELECT customer_id FROM customer_segments WHERE country = '$selectedCountry')
");

// ✅ Fetch LRFMP data from `customer_lrfmp` and get dbscan_cluster from `customer_segments`
$lrfmpQuery = $conn->query("
    SELECT l.customer_id, l.length, l.recency, l.frequency, l.monetary, l.periodicity, s.dbscan_cluster,
        CASE 
            WHEN dbscan_cluster = -1 THEN 'Noise'
            WHEN dbscan_cluster = 0 THEN 'Low-Value'
            WHEN dbscan_cluster = 1 THEN 'Mid-Tier'
            WHEN dbscan_cluster = 2 THEN 'High-Value'
            ELSE 'Unknown'
        END AS lrfmp_segment
    FROM customer_lrfmp l
    JOIN customer_segments s ON l.customer_id = s.customer_id
    WHERE s.country = '$selectedCountry'
");

// ✅ Store Data
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
    <title>Customer Segmentation</title>
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
                            <?= count(array_filter($rfmData, fn($c) => $c["rfm_segment"] == "VIP")) ?>,
                            <?= count(array_filter($rfmData, fn($c) => $c["rfm_segment"] == "Loyal")) ?>,
                            <?= count(array_filter($rfmData, fn($c) => $c["rfm_segment"] == "Dormant")) ?>,
                            <?= count(array_filter($rfmData, fn($c) => $c["rfm_segment"] == "Regular")) ?>
                        ],
                        backgroundColor: ["#6f42c1", "#28a745", "#dc3545", "#007bff"]
                    }]
                }
            });

            // ✅ LRFMP Cluster Scatter Plot
            var ctx2 = document.getElementById("lrfmpScatterPlot").getContext("2d");
            var lrfmpData = <?= json_encode($lrfmpData) ?>;
            var clusters = {};
            lrfmpData.forEach(c => {
                if (!clusters[c.dbscan_cluster]) clusters[c.dbscan_cluster] = { x: [], y: [] };
                clusters[c.dbscan_cluster].x.push(c.frequency);
                clusters[c.dbscan_cluster].y.push(c.monetary);
            });

            var datasets = [];
            Object.keys(clusters).forEach(cluster => {
                datasets.push({
                    label: "Cluster " + cluster,
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
                    <th>RFM Segment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($rfmData, 0, 10) as $row): ?>
                    <tr>
                        <td><?= $row["customer_id"] ?></td>
                        <td><?= $row["recency"] ?> days</td>
                        <td><?= $row["frequency"] ?></td>
                        <td>$<?= number_format($row["monetary"], 2) ?></td>
                        <td><span class="badge bg-primary"><?= $row["rfm_segment"] ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
