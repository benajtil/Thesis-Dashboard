<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "retail_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Mapping country names to ISO Alpha-2 codes for JSVectorMap
$countryISOMap = [
    "UNITED STATES" => "US",
    "USA" => "US",
    "CANADA" => "CA",
    "UNITED KINGDOM" => "GB",
    "GERMANY" => "DE",
    "FRANCE" => "FR",
    "INDIA" => "IN",
    "AUSTRALIA" => "AU",
    "JAPAN" => "JP",
    "CHINA" => "CN",
    "BRAZIL" => "BR",
    "EIRE" => "IE",
    "IRELAND" => "IE",
    "SINGAPORE" => "SG",
    "HONG KONG" => "HK",
    "SPAIN" => "ES",
    "PORTUGAL" => "PT",
    "NORWAY" => "NO",
    "BELGIUM" => "BE",
    "NETHERLANDS" => "NL",
    "SWITZERLAND" => "CH",
    "CHANNEL ISLANDS" => "JE", // Jersey (Main Channel Island)
    "CYPRUS" => "CY",
    "FINLAND" => "FI",
    "ITALY" => "IT",
    "SWEDEN" => "SE",
    "POLAND" => "PL",
    "AUSTRIA" => "AT",
    "ISRAEL" => "IL",
    "DENMARK" => "DK",
    "GREECE" => "GR",
    "MALTA" => "MT",
    "ICELAND" => "IS",
    "LITHUANIA" => "LT",
    "LEBANON" => "LB",
    "UNITED ARAB EMIRATES" => "AE",
    "BAHRAIN" => "BH",
    "SAUDI ARABIA" => "SA",
    "CZECH REPUBLIC" => "CZ",
    "EUROPEAN COMMUNITY" => "EU", // Placeholder, doesn't have an official country code
];


// Fetch country spending & total orders
$countryData = [];
$result = $conn->query("SELECT 
                        CASE 
                            WHEN UPPER(country) = 'EIRE' THEN 'IRELAND'
                            ELSE UPPER(country)
                        END AS country, 
                        SUM(total_price) AS total_spent,
                        COUNT(DISTINCT invoice_no) AS total_orders
                        FROM transactions 
                        GROUP BY country 
                        ORDER BY total_spent DESC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $countryName = strtoupper(trim($row["country"])); // Ensure uppercase & trim spaces
        $isoCode = $countryISOMap[$countryName] ?? null;

        if ($isoCode) {
            // Determine customer segment
if ($row["total_spent"] > 200000 && $row["total_orders"] > 200) {
    $segment = "VIP Customer"; // 🔥 Best customers
} elseif ($row["total_spent"] > 100000) {
    $segment = "High Spender"; // 💰 Spends a lot, even if they don't buy frequently
} elseif ($row["total_orders"] > 100) {
    $segment = "Loyal"; // 🛒 Buys frequently, regardless of amount
} elseif ($row["total_spent"] > 5000 || $row["total_orders"] > 20) {
    $segment = "Frequent Shopper"; // 🏬 Shops often but not huge spending
} elseif ($row["total_spent"] > 500 || $row["total_orders"] > 5) {
    $segment = "Casual Buyer"; // 🛍️ Buys occasionally
} else {
    $segment = "Dormant"; // ❄️ Rarely purchases
}


            // Store data
            if ($isoCode && $countryName !== "UNSPECIFIED") { 
                // Only process valid countries
                $countryData[$isoCode] = [
                    "total_spent" => floatval($row["total_spent"]),
                    "total_orders" => intval($row["total_orders"]),
                    "segment" => $segment
                ];
            } elseif ($countryName === "UNSPECIFIED") {
                // Don't show warning for "UNSPECIFIED"
            } else {
                echo "⚠️ Warning: No ISO code found for country: $countryName <br>"; // Debugging other missing countries
            }
            
        }
    }
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>
