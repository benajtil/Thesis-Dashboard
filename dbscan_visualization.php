<?php
$jsonFile = "dbscan_results.json";

if (!file_exists($jsonFile)) {
    die("<h3 style='color:red; text-align:center;'>DBSCAN results not found! Run dbscan_segmentation.php first.</h3>");
}

$dbscanData = json_decode(file_get_contents($jsonFile), true);

// Default display option
$displayBy = isset($_GET['display_by']) && $_GET['display_by'] === 'country' ? 'country' : 'customer_id';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DBSCAN Segmentation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f4f6f9; font-family: Arial, sans-serif; }
        .dashboard-container { max-width: 1200px; margin: auto; padding: 20px; }
        .card { box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1); padding: 20px; text-align: center; margin-bottom: 15px; }
        .segment-title { font-size: 1.2em; font-weight: bold; margin-bottom: 10px; }
    </style>
</head>
<body>

<div class="container dashboard-container">
    <h2 class="text-center my-4">DBSCAN Segmentation</h2>

    <!-- Dropdown Menu to Select Display Option -->
    <form method="GET" class="text-center mb-3">
        <label for="display_by">View By: </label>
        <select name="display_by" id="display_by" onchange="this.form.submit()">
            <option value="customer_id" <?php if ($displayBy === 'customer_id') echo 'selected'; ?>>Customer ID</option>
            <option value="country" <?php if ($displayBy === 'country') echo 'selected'; ?>>Country</option>
        </select>
    </form>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <h5 class="segment-title">Segmentation Results</h5>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><?php echo ucfirst(str_replace('_', ' ', $displayBy)); ?></th>
                            <th>Length</th>
                            <th>Recency</th>
                            <th>Frequency</th>
                            <th>Monetary</th>
                            <th>Periodicity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 0;
                        foreach ($dbscanData as $key => $values): 
                            if ($i >= 10) break; // Display only top 10
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($key); ?></td>
                                <td><?php echo $values['length']; ?></td>
                                <td><?php echo $values['recency']; ?></td>
                                <td><?php echo $values['frequency']; ?></td>
                                <td><?php echo "$" . number_format($values['monetary'], 2); ?></td>
                                <td><?php echo $values['periodicity']; ?></td>
                            </tr>
                        <?php $i++; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card p-3 mt-4">
        <h5>Customer Segment Distribution</h5>
        <canvas id="dbscanChart"></canvas>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        var dbscanData = <?php echo json_encode(array_values($dbscanData)); ?>;
        var segmentLabels = <?php echo json_encode(array_keys($dbscanData)); ?>;

        var ctx = document.getElementById("dbscanChart").getContext("2d");
        new Chart(ctx, {
            type: "bar",
            data: {
                labels: segmentLabels.slice(0, 10),
                datasets: [{
                    label: "Monetary Value",
                    data: dbscanData.map(data => data.monetary).slice(0, 10),
                    backgroundColor: "#36A2EB"
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });
    });
</script>

</body>
</html>
