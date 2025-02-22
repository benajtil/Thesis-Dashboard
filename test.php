<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Fetch Available Countries for Dropdown
$country_query = "SELECT DISTINCT country FROM cleaned_transactions ORDER BY country ASC";
$country_result = $conn->query($country_query);

// ✅ Handle Country and CustomerID Filtering
$country_filter = "";
$search_query = "";
$selected_country = "";

if (isset($_GET['country']) && !empty($_GET['country'])) {
    $selected_country = $_GET['country'];
    $country_filter = " AND c.country = '$selected_country'";  // ✅ Join `cleaned_transactions`
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_customer = $_GET['search'];
    $search_query = " AND r.CustomerID = '$search_customer'";
}

// ✅ Fetch Top 10 Customers in Selected Country or Search Result
$sql = "
    SELECT r.*, l.LRFMPGroup, l.LRFMPScore, l.L, l.P, l.Cluster, c.country
    FROM customer_rfm_analysis r
    JOIN customer_lrfmp_analysis l ON r.CustomerID = l.CustomerID
    JOIN cleaned_transactions c ON r.CustomerID = c.customer_id  -- ✅ JOIN instead of WHERE
    WHERE 1=1 $country_filter $search_query
    GROUP BY r.CustomerID
    ORDER BY r.RFMScore DESC
    LIMIT 10
";

$result = $conn->query($sql);

// ✅ Fetch Cluster Averages for Interpretation (JOIN Fix)
$cluster_sql = "
    SELECT r.Cluster, 
           ROUND(AVG(r.Recency), 2) AS AvgRecency, 
           ROUND(AVG(r.Frequency), 2) AS AvgFrequency, 
           ROUND(AVG(r.Monetary), 2) AS AvgMonetary
    FROM customer_rfm_analysis r
    JOIN cleaned_transactions c ON r.CustomerID = c.customer_id  -- ✅ JOIN instead of WHERE
    WHERE r.Cluster != -1 $country_filter
    GROUP BY r.Cluster
";

$cluster_result = $conn->query($cluster_sql);
$cluster_data = [];
while ($row = $cluster_result->fetch_assoc()) {
    $cluster_data[$row['Cluster']] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Segmentation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>

<body>

    <div class="container mt-4">
        <h2 class="mb-3">Customer Segmentation</h2>

        <!-- ✅ Country Filter Dropdown -->
        <form method="GET" class="mb-3">
            <label>Select Country:</label>
            <select name="country" class="form-select w-25 d-inline">
                <option value="">All</option>
                <?php while ($row = $country_result->fetch_assoc()) { ?>
                    <option value="<?php echo $row['country']; ?>"
                        <?php if ($selected_country == $row['country']) echo 'selected'; ?>>
                        <?php echo $row['country']; ?>
                    </option>
                <?php } ?>
            </select>

            <!-- ✅ Search Form -->
            <input type="text" name="search" class="form-control w-25 d-inline"
                placeholder="Search by Customer ID" value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>

        <!-- ✅ RFM Table -->
        <h4>RFM Analysis</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>CustomerID</th>
                    <th>Recency</th>
                    <th>Frequency</th>
                    <th>Monetary</th>
                    <th>RFM Group</th>
                    <th>RFM Score</th>
                    <th>Cluster</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo $row['CustomerID']; ?></td>
                        <td><?php echo $row['Recency']; ?></td>
                        <td><?php echo $row['Frequency']; ?></td>
                        <td><?php echo number_format($row['Monetary'], 2); ?></td>
                        <td><?php echo $row['RFMGroup']; ?></td>
                        <td><?php echo $row['RFMScore']; ?></td>
                        <td><?php echo ($row['Cluster'] == -1) ? "Noise" : $row['Cluster']; ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <!-- ✅ LRFMP Table -->
        <h4>LRFMP Analysis</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>CustomerID</th>
                    <th>Length</th>
                    <th>Recency</th>
                    <th>Frequency</th>
                    <th>Monetary</th>
                    <th>Periodicity</th>
                    <th>LRFMP Group</th>
                    <th>LRFMP Score</th>
                    <th>Cluster</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result->data_seek(0); // Reset pointer to reuse the query
                while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo $row['CustomerID']; ?></td>
                        <td><?php echo $row['L']; ?></td>
                        <td><?php echo $row['Recency']; ?></td>
                        <td><?php echo $row['Frequency']; ?></td>
                        <td><?php echo number_format($row['Monetary'], 2); ?></td>
                        <td><?php echo number_format($row['P'], 2); ?></td>
                        <td><?php echo $row['LRFMPGroup']; ?></td>
                        <td><?php echo $row['LRFMPScore']; ?></td>
                        <td><?php echo ($row['Cluster'] == -1) ? "Noise" : $row['Cluster']; ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <!-- ✅ Cluster Interpretations -->
        <h4>RFM Cluster Interpretations</h4>
        <?php foreach ($cluster_data as $cluster => $data) { ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h5>Cluster <?php echo $cluster; ?> (RFM)</h5>
                    <p><strong>Recency (Avg):</strong> <?php echo $data['AvgRecency']; ?> days</p>
                    <p><strong>Frequency (Avg):</strong> <?php echo $data['AvgFrequency']; ?> transactions</p>
                    <p><strong>Monetary (Avg):</strong> $<?php echo number_format($data['AvgMonetary'], 2); ?></p>

                    <p><strong>Interpretation:</strong>
                        <?php
                        if ($data['AvgRecency'] > 150 && $data['AvgFrequency'] < 20 && $data['AvgMonetary'] < 500) {
                            echo "🛑 Customers in this cluster are likely 'At-Risk' or 'Lapsed' customers. They haven’t purchased recently, buy infrequently, and spend little. A re-engagement campaign may help.";
                        } elseif ($data['AvgRecency'] < 30 && $data['AvgFrequency'] > 200 && $data['AvgMonetary'] > 5000) {
                            echo "⭐ This cluster represents your 'Champions' or 'Loyal' customers. They shop frequently, spend a lot, and are highly engaged.";
                        } elseif ($data['AvgRecency'] > 60 && $data['AvgFrequency'] > 50 && $data['AvgMonetary'] > 1000) {
                            echo "🚀 These customers are 'Potential Loyalists'. They buy frequently and spend well, but may need incentives to keep them engaged.";
                        } elseif ($data['AvgRecency'] < 45 && $data['AvgFrequency'] > 30) {
                            echo "💎 These customers are 'Recent Buyers' and could become long-term loyal customers with proper incentives.";
                        } else {
                            echo "🟡 This cluster represents a balanced mix of customer behaviors, requiring tailored engagement strategies.";
                        }
                        ?>
                    </p>
                </div>
            </div>
        <?php } ?>

        <h4>LRFMP Cluster Interpretations</h4>
<?php if (!empty($lrfmp_cluster_data)) { ?>
    <?php foreach ($lrfmp_cluster_data as $cluster => $data) { ?>
        <div class="card mb-3">
            <div class="card-body">
                <h5>Cluster <?php echo $cluster; ?> (LRFMP)</h5>
                <p><strong>Length (Avg):</strong> <?php echo $data['AvgLength']; ?> days</p>
                <p><strong>Recency (Avg):</strong> <?php echo $data['AvgRecency']; ?> days</p>
                <p><strong>Frequency (Avg):</strong> <?php echo $data['AvgFrequency']; ?> transactions</p>
                <p><strong>Monetary (Avg):</strong> $<?php echo number_format($data['AvgMonetary'], 2); ?></p>
                <p><strong>Periodicity (Avg):</strong> <?php echo $data['AvgPeriodicity']; ?> days</p>

                <p><strong>Interpretation:</strong> 
                    <?php
                    if ($data['AvgLength'] > 365 && $data['AvgRecency'] > 150 && $data['AvgFrequency'] < 10) {
                        echo "⚠️ This group represents 'Long-Time Dormant Customers'. They have been inactive for a long time and need strong incentives to return.";
                    } elseif ($data['AvgLength'] < 100 && $data['AvgRecency'] < 30 && $data['AvgFrequency'] > 50) {
                        echo "🎯 These are 'New Engaged Customers' who have started buying recently and frequently.";
                    } elseif ($data['AvgPeriodicity'] < 10 && $data['AvgMonetary'] > 5000) {
                        echo "💰 These are 'High-Spending Frequent Buyers'. They purchase often and spend significantly.";
                    } elseif ($data['AvgRecency'] < 60 && $data['AvgFrequency'] > 40) {
                        echo "🔥 These customers are 'Active Repeat Buyers' and should be given loyalty rewards.";
                    } else {
                        echo "🟠 These customers exhibit mixed behavior and require further segmentation.";
                    }
                    ?>
                </p>
            </div>
        </div>
    <?php } ?>
<?php } else { ?>
    <p>No LRFMP cluster data found.</p>
<?php } ?>


    </div>

</body>

</html>

<?php $conn->close(); ?>