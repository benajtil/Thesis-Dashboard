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
    if (isset($_FILES["csvFile"]["tmp_name"]) && $_FILES["csvFile"]["size"] > 0) {
        $filePath = $_FILES["csvFile"]["tmp_name"];
        $fileName = $_FILES["csvFile"]["name"];

        // Move uploaded file to a temporary location
        $targetPath = "uploads/" . $fileName;
        move_uploaded_file($filePath, $targetPath);

        // Run Python preprocessing script
        $output = shell_exec("python process_data.py $targetPath 2>&1");

        $message = "<div class='alert alert-success'>✅ File processed successfully! Check the database for results.<br>Output: <pre>$output</pre></div>";
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
    <title>Upload & Process CSV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        body { background: #f4f6f9; font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: auto; padding: 20px; background: white; border-radius: 10px; box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body>

<div class="container mt-5">
    <h2 class="text-center">Upload & Process CSV</h2>

    <?php if (!empty($message)) echo $message; ?>

    <form action="process_data.php" method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Select CSV File:</label>
            <input type="file" name="csvFile" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">Upload & Process</button>
    </form>
</div>

</body>
</html>
