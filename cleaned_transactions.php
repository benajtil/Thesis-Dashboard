<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1️⃣ Create the cleaned_transactions table
$createTable = "
    CREATE TABLE IF NOT EXISTS cleaned_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_no VARCHAR(20),
        invoice_date DATETIME,
        customer_id INT,
        country VARCHAR(50),
        product_name VARCHAR(255),
        quantity INT CHECK (quantity > 0),
        unit_price DECIMAL(10,2) CHECK (unit_price > 0),
        total_price DECIMAL(10,2),
        UNIQUE(invoice_no, product_name)
    );
";
$conn->query($createTable);

// 2️⃣ Clean the data and insert into cleaned_transactions
$cleanDataQuery = "
    INSERT INTO cleaned_transactions (invoice_no, invoice_date, customer_id, country, product_name, quantity, unit_price, total_price)
    SELECT DISTINCT invoice_no, invoice_date, customer_id, country, product_name, quantity, unit_price, total_price
    FROM transactions
    WHERE quantity > 0 
    AND unit_price > 0
    AND invoice_no NOT LIKE 'C%'
    AND invoice_no IS NOT NULL
    AND product_name IS NOT NULL;
";

$conn->query("DELETE FROM cleaned_transactions"); // Clear old data before inserting new cleaned data
$conn->query($cleanDataQuery);

echo "✅ Data cleaning completed successfully! Cleaned data is now available in `cleaned_transactions`.";

$conn->close();
?>
