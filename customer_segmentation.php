<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$country_query = "SELECT DISTINCT country FROM transactions ORDER BY country ASC";
$country_result = $conn->query($country_query);

$segmentation = isset($_GET['segmentation']) ? $_GET['segmentation'] : 'rfm';
$combination  = isset($_GET['combination']) ? $_GET['combination'] : 'full';
$search       = isset($_GET['search']) ? $_GET['search'] : "";

$rfmOptions = [
    "full" => ["clusterCol" => "KMeans_RFM_Cluster", "flagCol" => "DBSCAN_RFM_Flag"],
    "rf"   => ["clusterCol" => "KMeans_RF_Cluster",  "flagCol" => "DBSCAN_RF_Flag"],
    "rm"   => ["clusterCol" => "KMeans_RM_Cluster",  "flagCol" => "DBSCAN_RM_Flag"],
    "fm"   => ["clusterCol" => "KMeans_FM_Cluster",  "flagCol" => "DBSCAN_FM_Flag"]
];

$lrfmpOptions = [
    "full" => ["clusterCol" => "KMeans_LRFMP_Cluster", "flagCol" => "DBSCAN_LRFMP_Flag"],
    "lr"   => ["clusterCol" => "KMeans_LR_Cluster",    "flagCol" => "DBSCAN_LR_Flag"],
    "lf"   => ["clusterCol" => "KMeans_LF_Cluster",    "flagCol" => "DBSCAN_LF_Flag"],
    "lm"   => ["clusterCol" => "KMeans_LM_Cluster",    "flagCol" => "DBSCAN_LM_Flag"],
    "fp"   => ["clusterCol" => "KMeans_FP_Cluster",    "flagCol" => "DBSCAN_FP_Flag"],
    "mp"   => ["clusterCol" => "KMeans_MP_Cluster",    "flagCol" => "DBSCAN_MP_Flag"]
];

if ($segmentation == 'rfm') {
    $table = "customer_rfm_kmeans_dbscan";
    $options = $rfmOptions;
} else {
    $table = "customer_lrfmp_kmeans_dbscan";
    $options = $lrfmpOptions;
}

if (!isset($options[$combination])) {
    $combination = 'full';
}

$clusterCol = $options[$combination]["clusterCol"];
$flagCol    = $options[$combination]["flagCol"];


$selected_country = "";
if (!empty($_GET['country'])) {
    $selected_country = $conn->real_escape_string($_GET['country']);
    $country_filter = " AND Country = '$selected_country'";
} else {
    $country_filter = "";
}


$search_filter = "";
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $search_filter = " AND CustomerID = '$search'";
}


$sort_column = isset($_GET['sort']) ? $_GET['sort'] : "CustomerID";
$sort_order  = (isset($_GET['order']) && $_GET['order'] == "asc") ? "ASC" : "DESC";
$limit = 10;


$sql = "SELECT * FROM $table WHERE 1=1 $country_filter $search_filter ORDER BY $sort_column $sort_order LIMIT $limit";
$result = $conn->query($sql);
?>
<?php include 'includes/navbar.php' ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Customer Segmentation Dashboard</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.0/dist/darkly/bootstrap.min.css">
    <style>
        body {
            background-color: #181B23;
            color: #E2E8F0;
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
        }

        a,
        a:hover,
        a:focus,
        a:active {
            text-decoration: none;
            color: inherit;
        }

        .navbar {
            background-color: #1F2029;
            border-bottom: 1px solid #2D2F36;
        }

        .navbar-brand,
        .nav-link {
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
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .table-dark th,
        .table-dark td {
            border-color: #2D2F36;
        }

        .table-hover tbody tr:hover {
            background-color: #2D2F36;
        }

        .modal-content {
            background-color: #1F2029;
            color: #E2E8F0;
            border: none;
        }

        .modal-header,
        .modal-footer {
            border: none;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"></a>
            <form method="GET" class="d-flex">
                <label for="segmentation" class="text-light me-2">Segmentation:</label>
                <select name="segmentation" id="segmentation" class="form-select me-2" onchange="reloadPage()">
                    <option value="rfm" <?php if ($segmentation == 'rfm') echo 'selected'; ?>>RFM</option>
                    <option value="lrfmp" <?php if ($segmentation == 'lrfmp') echo 'selected'; ?>>LRFMP</option>
                </select>
                <label for="combination" class="text-light me-2">Combination:</label>
                <select name="combination" id="combination" class="form-select me-2" onchange="reloadPage()">
                    <?php
                    if ($segmentation == 'rfm') {
                        $labels = [
                            "full" => "Full RFM",
                            "rf"   => "Recency - Frequency",
                            "rm"   => "Recency - Monetary",
                            "fm"   => "Frequency - Monetary"
                        ];
                    } else {
                        $labels = [
                            "full" => "Full LRFMP",
                            "lr"   => "Length - Recency",
                            "lf"   => "Length - Frequency",
                            "lm"   => "Length - Monetary",
                            "fp"   => "Frequency - Periodicity",
                            "mp"   => "Monetary - Periodicity"
                        ];
                    }
                    foreach ($labels as $key => $label) {
                        echo "<option value=\"$key\"";
                        if ($combination == $key) echo " selected";
                        echo ">$label</option>";
                    }
                    ?>
                </select>
                <label for="country" class="text-light me-2">Country:</label>
                <select name="country" id="country" class="form-select me-2" onchange="reloadPage()">
                    <option value="">All Countries</option>
                    <?php
                    $country_result->data_seek(0);
                    while ($row = $country_result->fetch_assoc()) { ?>
                        <option value="<?php echo $row['country']; ?>" <?php if ($selected_country == $row['country']) echo 'selected'; ?>>
                            <?php echo $row['country']; ?>
                        </option>
                    <?php } ?>
                </select>
                <input type="text" name="search" id="search" class="form-control me-2" placeholder="Search Customer ID"
                    value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
                <button type="submit" class="btn btn-outline-light">Search</button>
            </form>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-3">Customer Segmentation (<?php echo strtoupper($segmentation); ?>)</h2>
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead class="table-dark">
                    <tr>
                        <th><a href="javascript:sortTable('CustomerID')" class="text-white">CustomerID</a></th>
                        <?php if ($segmentation == 'rfm') { ?>
                            <th><a href="javascript:sortTable('Recency')" class="text-white">Recency (days)</a></th>
                            <th><a href="javascript:sortTable('Frequency')" class="text-white">Frequency</a></th>
                            <th><a href="javascript:sortTable('Monetary')" class="text-white">Monetary ($)</a></th>
                        <?php } else { ?>
                            <th><a href="javascript:sortTable('Length')" class="text-white">Length (days)</a></th>
                            <th><a href="javascript:sortTable('Recency')" class="text-white">Recency (days)</a></th>
                            <th><a href="javascript:sortTable('Frequency')" class="text-white">Frequency</a></th>
                            <th><a href="javascript:sortTable('Monetary')" class="text-white">Monetary ($)</a></th>
                            <th><a href="javascript:sortTable('Periodicity')" class="text-white">Periodicity (days)</a></th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($row = $result->fetch_assoc()) {
                        $jsonDetails = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr onclick="showCustomerDetails(this)" data-details='<?php echo $jsonDetails; ?>'>
                            <td><?php echo $row['CustomerID']; ?></td>
                            <?php if ($segmentation == 'rfm') { ?>
                                <td><?php echo $row['Recency']; ?></td>
                                <td><?php echo $row['Frequency']; ?></td>
                                <td>$<?php echo number_format($row['Monetary'], 2); ?></td>
                            <?php } else { ?>
                                <td><?php echo $row['Length']; ?></td>
                                <td><?php echo $row['Recency']; ?></td>
                                <td><?php echo $row['Frequency']; ?></td>
                                <td>$<?php echo number_format($row['Monetary'], 2); ?></td>
                                <td><?php echo number_format($row['Periodicity'], 2); ?></td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="detailsModalLabel">Customer Details &amp; Insights</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-bordered">
                            <tr>
                                <th>CustomerID</th>
                                <td id="modalCustomerID"></td>
                            </tr>
                            <?php if ($segmentation == 'rfm') { ?>
                                <tr>
                                    <th>Recency</th>
                                    <td id="modalMetric1"></td>
                                </tr>
                                <tr>
                                    <th>Frequency</th>
                                    <td id="modalMetric2"></td>
                                </tr>
                                <tr>
                                    <th>Monetary</th>
                                    <td id="modalMetric3"></td>
                                </tr>
                            <?php } else { ?>
                                <tr>
                                    <th>Length</th>
                                    <td id="modalMetric1"></td>
                                </tr>
                                <tr>
                                    <th>Recency</th>
                                    <td id="modalMetric2"></td>
                                </tr>
                                <tr>
                                    <th>Frequency</th>
                                    <td id="modalMetric3"></td>
                                </tr>
                                <tr>
                                    <th>Monetary</th>
                                    <td id="modalMetric4"></td>
                                </tr>
                                <tr>
                                    <th>Periodicity</th>
                                    <td id="modalMetric5"></td>
                                </tr>
                                <tr>
                                    <th>Country</th>
                                    <td id="modalCountry"></td>
                                </tr>
                            <?php } ?>
                            <tr>
                                <th>Cluster Label</th>
                                <td id="modalStatus"></td>
                            </tr>
                            <tr>
                                <th>DBSCAN Status</th>
                                <td id="modalDBSCANStatus"></td>
                            </tr>
                            <tr>
                                <th>DBSCAN Insights</th>
                                <td id="modalDBSCANInsights"></td>
                            </tr>
                            <tr>
                                <th>DBSCAN Suggestions</th>
                                <td id="modalDBSCANSuggestions"></td>
                            </tr>
                            <tr>
                                <th>Insights</th>
                                <td id="modalInsights"></td>
                            </tr>
                            <tr>
                                <th>Suggestions</th>
                                <td id="modalSuggestions"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="includes/js/insights.js"></script>
    <script src="includes/js/dbscan.js"></script>

    <script>
        function reloadPage() {
            let seg = document.getElementById('segmentation').value;
            let comb = document.getElementById('combination').value;
            let country = document.getElementById('country').value;
            let search = document.getElementById('search').value;
            window.location.href = "?segmentation=" + seg + "&combination=" + comb + "&country=" + country + "&search=" + search;
        }

        function showCustomerDetails(rowElem) {
            let details = JSON.parse(rowElem.getAttribute("data-details"));


            let clusterVal = details["<?php echo $clusterCol; ?>"];
            let statusText = (clusterVal === "0") ? "Dormant" : (clusterVal === "1") ? "Active" : "Loyal";
            document.getElementById("modalStatus").innerText = statusText;


            document.getElementById("modalCustomerID").innerText = details.CustomerID;
            let seg = "<?php echo $segmentation; ?>";


            if (seg === "rfm") {
                document.getElementById("modalMetric1").innerText = "Recency: " + details.Recency + " days";
                document.getElementById("modalMetric2").innerText = "Frequency: " + details.Frequency;
                document.getElementById("modalMetric3").innerText = "Monetary: $" + parseFloat(details.Monetary).toFixed(2);
            } else {
                document.getElementById("modalMetric1").innerText = "Length: " + details.Length + " days";
                document.getElementById("modalMetric2").innerText = "Recency: " + details.Recency + " days";
                document.getElementById("modalMetric3").innerText = "Frequency: " + details.Frequency;
                document.getElementById("modalMetric4").innerText = "Monetary: $" + parseFloat(details.Monetary).toFixed(2);
                document.getElementById("modalMetric5").innerText = "Periodicity: " + parseFloat(details.Periodicity).toFixed(2) + " days";
                if (details.Country) {
                    document.getElementById("modalCountry").innerText = details.Country;
                }
            }


            var kmeansResult = generateKMeansInsights(details, seg);
            document.getElementById("modalInsights").innerText = kmeansResult.insights;
            document.getElementById("modalSuggestions").innerText = kmeansResult.suggestions;

            var dbscanResult = generateDBSCANInsights(details, seg, "<?php echo $flagCol; ?>");
            document.getElementById("modalDBSCANStatus").innerText = dbscanResult.status;
            document.getElementById("modalDBSCANInsights").innerText = dbscanResult.insights;
            document.getElementById("modalDBSCANSuggestions").innerText = dbscanResult.suggestions;

            let myModal = new bootstrap.Modal(document.getElementById('detailsModal'));
            myModal.show();
        }

        function sortTable(column) {
            let currentUrl = new URL(window.location.href);
            let currentOrder = currentUrl.searchParams.get("order");
            let newOrder = (currentOrder === "asc") ? "desc" : "asc";
            currentUrl.searchParams.set("sort", column);
            currentUrl.searchParams.set("order", newOrder);
            window.location.href = currentUrl.href;
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php
$conn->close();
?>