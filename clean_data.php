<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch data from transactions table
$result = $conn->query("SELECT * FROM transactions");

$cleanedData = [];
while ($row = $result->fetch_assoc()) {
    // Remove rows with NULL values
    if (in_array(null, $row, true) || in_array('', $row, true)) {
        continue;
    }
    
    // Remove negative values
    if ($row['quantity'] <= 0 || $row['unit_price'] <= 0 || $row['total_price'] <= 0) {
        continue;
    }
    
    $cleanedData[] = $row;
}

$conn->close();

// Generate CSV file for cleaned data
$filename = "cleaned_transactions.csv";
$file = fopen($filename, "w");

// Add headers
fputcsv($file, array_keys($cleanedData[0]));

// Add data
foreach ($cleanedData as $data) {
    fputcsv($file, $data);
}

fclose($file);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleaned Data Download</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5 text-center">
        <h2>Data Cleaning Completed</h2>
        <p>The dataset has been cleaned by removing NULL values, negative values, and any errors.</p>
        <a href="<?php echo $filename; ?>" class="btn btn-success">Download Cleaned Data</a>
    </div>
</body>
</html>