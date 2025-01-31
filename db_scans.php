<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch cleaned data
$result = $conn->query("SELECT customer_id, invoice_date, total_price FROM transactions ORDER BY invoice_date ASC");

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$conn->close();

// Apply DBSCAN - This should be done in Python (Google Colab) and results can be imported
$clusters = []; // Replace with actual DBSCAN logic

// Compute RFM (Recency, Frequency, Monetary) Metrics
$rfmData = [];
foreach ($transactions as $transaction) {
    $customerId = $transaction['customer_id'];
    if (!isset($rfmData[$customerId])) {
        $rfmData[$customerId] = [
            'recency' => strtotime("now") - strtotime($transaction['invoice_date']),
            'frequency' => 0,
            'monetary' => 0,
            'periodicity' => 0
        ];
    }
    $rfmData[$customerId]['frequency'] += 1;
    $rfmData[$customerId]['monetary'] += $transaction['total_price'];
    $rfmData[$customerId]['periodicity'] = $rfmData[$customerId]['frequency'] / ($rfmData[$customerId]['recency'] + 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DBSCAN Segmentation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center">Customer Segmentation using DBSCAN</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Recency</th>
                    <th>Frequency</th>
                    <th>Monetary</th>
                    <th>Periodicity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rfmData as $customerId => $data): ?>
                    <tr>
                        <td><?php echo $customerId; ?></td>
                        <td><?php echo round($data['recency'] / (60 * 60 * 24), 2); ?> days</td>
                        <td><?php echo $data['frequency']; ?></td>
                        <td>$<?php echo number_format($data['monetary'], 2); ?></td>
                        <td><?php echo round($data['periodicity'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
