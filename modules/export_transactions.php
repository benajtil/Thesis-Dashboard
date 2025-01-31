<?php
include '../includes/db_connection.php';

// Query to get transactions
$query = "SELECT customer_id, invoice_no, invoice_date, SUM(total_price) AS total_amt 
          FROM transactions GROUP BY customer_id, invoice_no, invoice_date";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    $file = fopen("../data/transactions.csv", "w");
    fputcsv($file, ["CustomerID", "InvoiceNo", "InvoiceDate", "total_amt"]);

    while ($row = $result->fetch_assoc()) {
        fputcsv($file, $row);
    }

    fclose($file);
    echo "✅ Transactions exported successfully!";
} else {
    echo "⚠️ No transaction data found!";
}

$conn->close();
?>
