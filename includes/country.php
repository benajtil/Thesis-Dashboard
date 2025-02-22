<?php


$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$countryISOMap = [
    "UNITED STATES" => "US", "USA" => "US", "CANADA" => "CA", "UNITED KINGDOM" => "GB",
    "GERMANY" => "DE", "FRANCE" => "FR", "INDIA" => "IN", "AUSTRALIA" => "AU",
    "JAPAN" => "JP", "CHINA" => "CN", "BRAZIL" => "BR", "EIRE" => "IE",
    "IRELAND" => "IE", "SINGAPORE" => "SG", "HONG KONG" => "HK", "SPAIN" => "ES",
    "PORTUGAL" => "PT", "NORWAY" => "NO", "BELGIUM" => "BE", "NETHERLANDS" => "NL",
    "SWITZERLAND" => "CH", "CHANNEL ISLANDS" => "JE", "CYPRUS" => "CY",
    "FINLAND" => "FI", "ITALY" => "IT", "SWEDEN" => "SE", "POLAND" => "PL",
    "AUSTRIA" => "AT", "ISRAEL" => "IL", "DENMARK" => "DK", "GREECE" => "GR",
    "MALTA" => "MT", "ICELAND" => "IS", "LITHUANIA" => "LT", "LEBANON" => "LB",
    "UNITED ARAB EMIRATES" => "AE", "BAHRAIN" => "BH", "SAUDI ARABIA" => "SA",
    "CZECH REPUBLIC" => "CZ", "EUROPEAN COMMUNITY" => "EU"
];

$countryData = [];
$query = "
    SELECT UPPER(country) AS country, 
           SUM(total_price) AS total_spent, 
           COUNT(DISTINCT invoice_no) AS total_orders
    FROM transactions
    GROUP BY country
    ORDER BY total_spent DESC
";
$result = $conn->query($query);
if (!$result) {
    die("Query Error: " . $conn->error);
}

while ($row = $result->fetch_assoc()) {
    $countryName = strtoupper(trim($row['country']));
    $isoCode = $countryISOMap[$countryName] ?? null;
    if ($isoCode) {

        $spent  = floatval($row['total_spent']);
        $orders = intval($row['total_orders']);
        $segment = ($spent > 200000) ? "VIP Customer" : (($orders > 100) ? "Loyal" : "Casual Buyer");
        $countryData[$isoCode] = [
            "total_spent"  => $spent,
            "total_orders" => $orders,
            "segment"      => $segment
        ];
    }
}
$conn->close();
header('Content-Type: application/json');
echo json_encode($countryData, JSON_PRETTY_PRINT);
?>
