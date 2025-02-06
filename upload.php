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

// Ensure tables exist
$conn->query("
    CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_no VARCHAR(255),
        customer_id VARCHAR(255),
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
        customer_id VARCHAR(255),
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
        customer_id VARCHAR(255) PRIMARY KEY,
        recency INT NOT NULL,
        frequency INT NOT NULL,
        monetary FLOAT NOT NULL,
        cluster INT NOT NULL
    )
");

// Handle Deletion Without Upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["only_delete"])) {
    if (isset($_POST['delete_transactions']) && $_POST['delete_transactions'] == 'yes') {
        $conn->query("DELETE FROM transactions");
    }
    if (isset($_POST['delete_cleaned']) && $_POST['delete_cleaned'] == 'yes') {
        $conn->query("DELETE FROM clean_transactions");
    }
    if (isset($_POST['delete_segments']) && $_POST['delete_segments'] == 'yes') {
        $conn->query("DELETE FROM customer_segments");
    }
    $message = "<div class='alert alert-success'>✅ Selected tables have been cleared.</div>";
}

// Handle File Upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["csvFile"])) {
    if ($_FILES["csvFile"]["size"] == 0) {
        $message = "<div class='alert alert-danger'>⚠️ No file uploaded or invalid file.</div>";
    } else {
        // Save the uploaded file
        $uploadDir = "uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $targetPath = $uploadDir . "latest.csv";
        if (!move_uploaded_file($_FILES["csvFile"]["tmp_name"], $targetPath)) {
            die("<div class='alert alert-danger'>❌ Error moving uploaded file.</div>");
        }

        // Delete existing data if confirmed
        if (isset($_POST['delete_transactions']) && $_POST['delete_transactions'] == 'yes') {
            $conn->query("DELETE FROM transactions");
        }
        if (isset($_POST['delete_cleaned']) && $_POST['delete_cleaned'] == 'yes') {
            $conn->query("DELETE FROM clean_transactions");
        }
        if (isset($_POST['delete_segments']) && $_POST['delete_segments'] == 'yes') {
            $conn->query("DELETE FROM customer_segments");
        }

        // Get Row Limit
        $rowLimit = $_POST['row_limit'];

        // Insert raw data into transactions table
        $file = fopen($targetPath, "r");
        fgetcsv($file); // Skip header row

        $insertedRows = 0;
        while (($row = fgetcsv($file, 10000, ",")) !== FALSE) {
            if (count($row) < 8) continue;
            if ($insertedRows >= $rowLimit && $rowLimit != "all") break;

            $stmt = $conn->prepare("INSERT INTO transactions 
                (invoice_no, customer_id, product_name, quantity, unit_price, total_price, invoice_date, country) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$invoice_no = $row[0];
$customer_id = $row[1];
$product_name = $row[2];
$quantity = intval($row[3]);
$unit_price = floatval($row[4]);
$total_price = floatval($row[5]);
$invoice_date = $row[6];
$country = $row[7];

$stmt->bind_param("sssddsss", $invoice_no, $customer_id, $product_name, $quantity, $unit_price, $total_price, $invoice_date, $country);
            $stmt->execute();

            $insertedRows++;
        }
        fclose($file);
        
        // Process Data Using Python if Cleaned Data is Selected
        if (isset($_POST['process_cleaned']) && $_POST['process_cleaned'] == 'yes') {
            $pythonPath = trim(shell_exec("where python"));
            if (!$pythonPath) {
                die("<div class='alert alert-danger'>❌ Python not found! Install Python first.</div>");
            }

            $processScript = "C:\\xampp\\htdocs\\Dashboard\\process_data.py";
            $output = shell_exec("$pythonPath $processScript $targetPath 2>&1");

            $message .= "<div class='alert alert-info'><b>Python Processing Output:</b><br><pre>$output</pre></div>";
        }

        $message = "<div class='alert alert-success'>✅ CSV uploaded successfully! Rows inserted: $insertedRows</div>";
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

        <div class="mb-3">
            <label class="form-label">Select Row Limit:</label>
            <select name="row_limit" class="form-control">
                <option value="1000">1,000 Rows</option>
                <option value="10000">10,000 Rows</option>
                <option value="50000">50,000 Rows</option>
                <option value="all">All Rows</option>
            </select>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="process_cleaned" value="yes" id="processCleaned">
            <label class="form-check-label" for="processCleaned">
                Process Cleaned Data (Store in `clean_transactions`)
            </label>
        </div>

        <h5>Delete Old Data?</h5>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="delete_transactions" value="yes">
            <label class="form-check-label">Delete `transactions`</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="delete_cleaned" value="yes">
            <label class="form-check-label">Delete `clean_transactions`</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="delete_segments" value="yes">
            <label class="form-check-label">Delete `customer_segments`</label>
        </div>

        <button type="submit" class="btn btn-primary w-100 mt-3">Upload & Process</button>
    </form>

    <form action="upload.php" method="post">
        <input type="hidden" name="only_delete" value="1">
        <button type="submit" class="btn btn-danger w-100 mt-3">Delete Selected Data Without Upload</button>
    </form>
</div>

</body>
</html>
