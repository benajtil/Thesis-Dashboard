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

// ✅ Ensure necessary tables exist
$conn->query("
    CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_no VARCHAR(255),
        customer_id INT,
        product_name VARCHAR(255),
        quantity INT,
        unit_price FLOAT,
        total_price FLOAT,
        invoice_date DATETIME,
        country VARCHAR(255)
    )
");

$conn->query("
    CREATE TABLE IF NOT EXISTS clean_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_no VARCHAR(255),
        customer_id INT,
        product_name VARCHAR(255),
        quantity INT,
        unit_price FLOAT,
        total_price FLOAT,
        invoice_date DATETIME,
        country VARCHAR(255)
    )
");

$conn->query("
    CREATE TABLE IF NOT EXISTS customer_segments (
        customer_id INT PRIMARY KEY,
        recency INT NOT NULL,
        frequency INT NOT NULL,
        monetary FLOAT NOT NULL,
        cluster INT NOT NULL
    )
");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_FILES["csvFile"]) || $_FILES["csvFile"]["size"] == 0) {
        $message = "<div class='alert alert-danger'>⚠️ No file uploaded or invalid file.</div>";
    } else {
        // ✅ Save uploaded file
        $uploadDir = "uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $targetPath = $uploadDir . "latest.csv";
        if (!move_uploaded_file($_FILES["csvFile"]["tmp_name"], $targetPath)) {
            die("<div class='alert alert-danger'>❌ Error moving uploaded file.</div>");
        }

        // ✅ Delete existing data if confirmed
        if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] == 'yes') {
            $conn->query("DELETE FROM transactions");
            $conn->query("DELETE FROM clean_transactions");
            $conn->query("DELETE FROM customer_segments");
        }

        // ✅ Insert raw data into `transactions`
        $file = fopen($targetPath, "r");
        fgetcsv($file); // Skip header row

        $stmt = $conn->prepare("INSERT INTO transactions 
            (invoice_no, customer_id, product_name, quantity, unit_price, total_price, invoice_date, country) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        while (($row = fgetcsv($file, 10000, ",")) !== FALSE) {
            if (count($row) < 8) continue;

            // ✅ Store values in variables before binding
            $invoice_no = $row[0];
            $customer_id = $row[1];
            $product_name = $row[2];
            $quantity = intval($row[3]);
            $unit_price = floatval($row[4]);
            $total_price = floatval($row[5]);
            $invoice_date = $row[6];
            $country = $row[7];

            $stmt->bind_param("sisddsss", $invoice_no, $customer_id, $product_name, $quantity, $unit_price, $total_price, $invoice_date, $country);
            $stmt->execute();
        }
        fclose($file);
        $stmt->close();

        // ✅ Automatically detect Python path
        $pythonPath = trim(shell_exec("where python"));
        if (!$pythonPath) {
            die("<div class='alert alert-danger'>❌ Python not found! Install Python first.</div>");
        }

        // ✅ Run Python script for cleaning and clustering
        $processScript = "C:\\xampp\\htdocs\\Dashboard\\process_data.py";
        $output = shell_exec("$pythonPath $processScript $targetPath 2>&1");

        $message = "<div class='alert alert-success'>✅ CSV uploaded and processed successfully!</div>";
        $message .= "<div class='alert alert-info'><b>Python Output:</b><br><pre>$output</pre></div>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload & Process Data</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        body { background: #f4f6f9; font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: auto; padding: 20px; background: white; border-radius: 10px; box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body>

<div class="container mt-5">
    <h2 class="text-center">Upload & Process Data</h2>

    <?php if (!empty($message)) echo $message; ?>

    <form action="upload.php" method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Select CSV File:</label>
            <input type="file" name="csvFile" class="form-control" required accept=".csv">
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="confirm_delete" value="yes" id="deleteData">
            <label class="form-check-label" for="deleteData">
                Delete old data before upload
            </label>
        </div>

        <button type="submit" class="btn btn-primary w-100">Upload & Process</button>
    </form>
</div>

</body>
</html>
