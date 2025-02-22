<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

// ✅ If DELETE old data is checked, remove existing records
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] == 'yes') {
        $conn->query("DELETE FROM cleaned_transactions");
    }

    if (isset($_FILES["csvFile"]["tmp_name"]) && $_FILES["csvFile"]["size"] > 0) {
        $file = fopen($_FILES["csvFile"]["tmp_name"], "r");
        fgetcsv($file); // Skip header row

        $insertedRows = 0;
        $batchSize = 1000; // ✅ Batch insert for performance
        $values = [];

        while (($row = fgetcsv($file, 10000, ",")) !== FALSE) {
            if (count($row) < 8) {
                continue; // Skip invalid rows
            }

            // ✅ Match CSV columns correctly
            $invoice_no = $conn->real_escape_string($row[0]);
            $customer_id = intval($row[1]);  
            $country = $conn->real_escape_string($row[2]);
            $invoice_date = trim($row[3]);  
            $product_name = trim($conn->real_escape_string($row[4]));
            $quantity = intval($row[5]);
            $unit_price = floatval($row[6]);
            $total_price = floatval($row[7]);

            // ✅ Convert MM/DD/YYYY to YYYY-MM-DD
            $dateObj = DateTime::createFromFormat("m/d/Y", $invoice_date);
            $invoice_date = $dateObj ? $dateObj->format("Y-m-d") : "1970-01-01";

            // ✅ Apply Data Cleaning
            if (isset($_POST['clean_data']) && $_POST['clean_data'] == 'yes') {
                if (empty($product_name) || $quantity <= 0 || $unit_price <= 0 || strpos($invoice_no, "C") === 0) {
                    continue; // 🛑 Skip duplicates, negative values, zero prices & cancelled orders
                }
            }

            $values[] = "('$invoice_no', $customer_id, '$country', '$invoice_date', '$product_name', $quantity, $unit_price, $total_price)";

            // ✅ Insert in batches of 1000 for large datasets
            if (count($values) >= $batchSize) {
                $sql = "INSERT INTO cleaned_transactions (invoice_no, customer_id, country, invoice_date, product_name, quantity, unit_price, total_price) 
                        VALUES " . implode(",", $values) . " 
                        ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), unit_price=VALUES(unit_price), total_price=VALUES(total_price)";

                if ($conn->query($sql)) {
                    $insertedRows += count($values);
                }
                $values = []; // Reset batch
            }
        }

        // ✅ Insert remaining rows
        if (!empty($values)) {
            $sql = "INSERT INTO cleaned_transactions (invoice_no, customer_id, country, invoice_date, product_name, quantity, unit_price, total_price) 
                    VALUES " . implode(",", $values) . " 
                    ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), unit_price=VALUES(unit_price), total_price=VALUES(total_price)";

            if ($conn->query($sql)) {
                $insertedRows += count($values);
            }
        }

        fclose($file);
        $message = "<div class='alert alert-success'>✅ CSV file imported successfully! Rows Inserted: $insertedRows</div>";
    } else {
        $message = "<div class='alert alert-danger'>⚠️ No file uploaded or invalid file.</div>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Cleaned Transactions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        body { background: #f4f6f9; font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: auto; padding: 20px; background: white; border-radius: 10px; box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body>

<div class="container mt-5">
    <h2 class="text-center">📂 Upload Cleaned Transactions (CSV)</h2>

    <?php if (!empty($message)) echo $message; ?>

    <form action="cleaned_upload.php" method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Select CSV File:</label>
            <input type="file" name="csvFile" class="form-control" required>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="confirm_delete" value="yes" id="deleteData">
            <label class="form-check-label" for="deleteData">
                Delete old transactions before upload
            </label>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="clean_data" value="yes" id="cleanData">
            <label class="form-check-label" for="cleanData">
                Clean Data (Remove duplicates, negative values, and cancelled orders)
            </label>
        </div>

        <button type="submit" class="btn btn-primary w-100">Upload & Clean Data</button>
    </form>
</div>

</body>
</html>
