<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
include 'db_connection.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container">
        <h1>Welcome, <?php echo $_SESSION['user']; ?>!</h1>

        <!-- Upload CSV Form -->
        <form action="upload.php" method="post" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required>
            <button type="submit" name="upload">Upload CSV</button>
        </form>

        <h2>Transaction Records</h2>
        <table border="1">
            <tr>
                <th>ID</th>
                <th>Invoice No</th>
                <th>Customer ID</th>
                <th>Product Name</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total Price</th>
                <th>Invoice Date</th>
            </tr>
            <?php
            $result = $conn->query("SELECT * FROM transactions ORDER BY id DESC");
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                        <td>{$row['id']}</td>
                        <td>{$row['invoice_no']}</td>
                        <td>{$row['customer_id']}</td>
                        <td>{$row['product_name']}</td>
                        <td>{$row['quantity']}</td>
                        <td>\${$row['unit_price']}</td>
                        <td>\${$row['total_price']}</td>
                        <td>{$row['invoice_date']}</td>
                    </tr>";
            }
            ?>
        </table>
    </div>
    <form action="upload.php" method="post" enctype="multipart/form-data" onsubmit="return confirmDelete()">
    <label for="csv_file">Upload CSV:</label>
    <input type="file" name="csv_file" id="csv_file" required>
    <input type="hidden" name="confirm_delete" id="confirm_delete" value="0">
    <button type="submit" name="upload">Upload CSV</button>
</form>

<script>
function confirmDelete() {
    let confirmAction = confirm("Uploading a new file will delete old data. Do you want to proceed?");
    if (confirmAction) {
        document.getElementById("confirm_delete").value = "1"; // Mark as confirmed
        return true; // Proceed with form submission
    } else {
        return false; // Cancel upload
    }
}
</script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
