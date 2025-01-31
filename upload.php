<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = ""; // Store success/error messages

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] == 'yes') {
        $conn->query("DELETE FROM transactions");
    }

    if (isset($_FILES["csvFile"]["tmp_name"]) && $_FILES["csvFile"]["size"] > 0) {
        $file = fopen($_FILES["csvFile"]["tmp_name"], "r");

        // Skip header row
        fgetcsv($file);

        $insertedRows = 0;
        while (($row = fgetcsv($file, 10000, ",")) !== FALSE) {
            if (count($row) < 8) {
                continue; // Skip invalid rows
            }

            // Extract CSV data
            $invoice_no = $row[0];
            $customer_id = $row[1];
            $product_name = $row[2];
            $quantity = intval($row[3]);
            $unit_price = floatval($row[4]);
            $total_price = floatval($row[5]);
            $invoice_date = $row[6];
            $country = $row[7];

            // Insert data into MySQL
            $stmt = $conn->prepare("INSERT INTO transactions 
                (invoice_no, customer_id, product_name, quantity, unit_price, total_price, invoice_date, country) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisddsss", $invoice_no, $customer_id, $product_name, $quantity, $unit_price, $total_price, $invoice_date, $country);

            if ($stmt->execute()) {
                $insertedRows++;
            }
        }

        fclose($file);
        $message = "<div class='alert alert-success'>✅ CSV file successfully imported! Rows Inserted: $insertedRows</div>";
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
    <title>Upload CSV File</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        body { background: #f4f6f9; font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: auto; padding: 20px; background: white; border-radius: 10px; box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body>

<div class="container mt-5">
    <h2 class="text-center">Upload CSV File</h2>

    <!-- Display messages -->
    <?php if (!empty($message)) echo $message; ?>

    <form action="upload.php" method="post" enctype="multipart/form-data">
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

        <button type="submit" class="btn btn-primary w-100">Upload</button>
    </form>
</div>

</body>
</html>
