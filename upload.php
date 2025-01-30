<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the user confirmed deleting old data
if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] == 'yes') {
    $conn->query("DELETE FROM transactions");
}

if (isset($_FILES["csvFile"]["tmp_name"])) {
    $file = fopen($_FILES["csvFile"]["tmp_name"], "r");

    // Skip header row
    fgetcsv($file);

    while (($row = fgetcsv($file, 10000, ",")) !== FALSE) {
        // Adjust based on your CSV format
        $invoice_no = $row[0];
        $customer_id = $row[1];
        $product_name = $row[2];
        $quantity = $row[3];
        $unit_price = $row[4];
        $total_price = $row[5];
        $invoice_date = $row[6];
        $country = $row[7];  // New column

        // Insert data into MySQL
        $stmt = $conn->prepare("INSERT INTO transactions (invoice_no, customer_id, product_name, quantity, unit_price, total_price, invoice_date, country) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisiddss", $invoice_no, $customer_id, $product_name, $quantity, $unit_price, $total_price, $invoice_date, $country);
        $stmt->execute();
    }

    fclose($file);
    echo "CSV file successfully imported!";
}

$conn->close();
?>
