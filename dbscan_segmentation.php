<?php
exec("python apply_dbscan.py", $output, $return_var);

// Check if Python script executed successfully
if ($return_var !== 0) {
    die("<h3 style='color: red;'>⚠️ Error running DBSCAN script!</h3>");
}

$dbscanFile = "dbscan_results.json";

// Check if file exists
if (!file_exists($dbscanFile)) {
    die("<h3 style='color: red;'>⚠️ DBSCAN results not found!</h3>");
}

// Read DBSCAN results
$data = json_decode(file_get_contents($dbscanFile), true);

if (!$data || empty($data)) {
    die("<h3 style='color: red;'>⚠️ DBSCAN results file is empty!</h3>");
}
// Load the cleaned dataset (assumed CSV from preprocessing)
$data = array_map('str_getcsv', file("cleaned_transactions.csv"));
array_shift($data); // Remove header row

// Initialize arrays for clustering
$customers = [];
$LFM_Segments = [];
$DBSCAN_Segments = [];

// Group transactions by customer
foreach ($data as $row) {
    $customer_id = $row[0];
    $total_spent = floatval($row[1]);
    $total_orders = intval($row[2]);
    $last_order_date = $row[3];
    $order_periodicity = $row[4]; // How often the customer orders

    if (!isset($customers[$customer_id])) {
        $customers[$customer_id] = [
            "total_spent" => 0,
            "total_orders" => 0,
            "last_order" => $last_order_date,
            "periodicity" => $order_periodicity
        ];
    }

    // Aggregate customer spending and order count
    $customers[$customer_id]["total_spent"] += $total_spent;
    $customers[$customer_id]["total_orders"] += $total_orders;
}

// Apply LFM Segmentation (Length, Frequency, Monetary)
foreach ($customers as $customer_id => $data) {
    $length = count($customers);
    $frequency = $data["total_orders"];
    $monetary = $data["total_spent"];

    // Store in LFM Segments
    $LFM_Segments[$customer_id] = [
        "length" => $length,
        "frequency" => $frequency,
        "monetary" => $monetary
    ];
}

// Apply LRFMP Model (Length, Recency, Frequency, Monetary, Periodicity)
foreach ($customers as $customer_id => $data) {
    $recency = strtotime("today") - strtotime($data["last_order"]);
    $periodicity = $data["periodicity"];

    // Store in DBSCAN segmentation array
    $DBSCAN_Segments[$customer_id] = [
        "length" => $length,
        "recency" => $recency,
        "frequency" => $data["total_orders"],
        "monetary" => $data["total_spent"],
        "periodicity" => $periodicity
    ];
}

// Convert the final DBSCAN results into JSON
file_put_contents("dbscan_results.json", json_encode($DBSCAN_Segments));

// Display success message
echo "DBSCAN clustering and segmentation successfully applied!";
?>
