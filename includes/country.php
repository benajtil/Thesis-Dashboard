<?php
// ---------------------------------------
// country.php - Full Code
// ---------------------------------------

// 1. Database Connection Parameters
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "retail_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. Country-to-ISO Map (uppercase country names to ISO Alpha-2 codes)
$countryISOMap = [
    // 🌎 North America
    "UNITED STATES" => "US",
    "USA"           => "US",
    "CANADA"        => "CA",
    "MEXICO"        => "MX",

    // 🌎 South America
    "BRAZIL"        => "BR",
    "ARGENTINA"     => "AR",
    "COLOMBIA"      => "CO",
    "CHILE"         => "CL",
    "PERU"          => "PE",
    "VENEZUELA"     => "VE",
    "ECUADOR"       => "EC",
    "BOLIVIA"       => "BO",
    "URUGUAY"       => "UY",
    "PARAGUAY"      => "PY",

    // 🌍 Europe
    "UNITED KINGDOM" => "GB",
    "GERMANY"        => "DE",
    "FRANCE"         => "FR",
    "ITALY"          => "IT",
    "SPAIN"          => "ES",
    "NETHERLANDS"    => "NL",
    "BELGIUM"        => "BE",
    "SWITZERLAND"    => "CH",
    "SWEDEN"         => "SE",
    "NORWAY"         => "NO",
    "DENMARK"        => "DK",
    "FINLAND"        => "FI",
    "AUSTRIA"        => "AT",
    "IRELAND"        => "IE",
    "PORTUGAL"       => "PT",
    "POLAND"         => "PL",
    "CZECH REPUBLIC" => "CZ",
    "HUNGARY"        => "HU",
    "GREECE"         => "GR",
    "SLOVAKIA"       => "SK",
    "LITHUANIA"      => "LT",
    "LATVIA"         => "LV",
    "ESTONIA"        => "EE",
    "SLOVENIA"       => "SI",
    "CROATIA"        => "HR",
    "BULGARIA"       => "BG",
    "ROMANIA"        => "RO",
    "ICELAND"        => "IS",
    "MALTA"          => "MT",
    "LUXEMBOURG"     => "LU",
    "CYPRUS"         => "CY",

    // 🌍 Africa
    "SOUTH AFRICA"   => "ZA",
    "EGYPT"          => "EG",
    "NIGERIA"       => "NG",
    "KENYA"          => "KE",
    "GHANA"          => "GH",
    "ETHIOPIA"       => "ET",
    "TANZANIA"       => "TZ",
    "UGANDA"         => "UG",
    "ALGERIA"        => "DZ",
    "MOROCCO"        => "MA",
    "ANGOLA"         => "AO",
    "SUDAN"          => "SD",
    "ZAMBIA"         => "ZM",
    "SENEGAL"        => "SN",
    "TUNISIA"        => "TN",
    "IVORY COAST"    => "CI",

    // 🌏 Asia
    "INDIA"          => "IN",
    "CHINA"          => "CN",
    "JAPAN"          => "JP",
    "SOUTH KOREA"    => "KR",
    "INDONESIA"      => "ID",
    "PHILIPPINES"    => "PH",
    "THAILAND"       => "TH",
    "VIETNAM"        => "VN",
    "MALAYSIA"       => "MY",
    "SINGAPORE"      => "SG",
    "PAKISTAN"       => "PK",
    "BANGLADESH"     => "BD",
    "SRI LANKA"      => "LK",
    "NEPAL"          => "NP",
    "KAZAKHSTAN"     => "KZ",
    "IRAN"           => "IR",
    "SAUDI ARABIA"   => "SA",
    "UNITED ARAB EMIRATES" => "AE",
    "ISRAEL"         => "IL",
    "LEBANON"        => "LB",
    "QATAR"          => "QA",
    "KUWAIT"         => "KW",
    "JORDAN"         => "JO",
    "BAHRAIN"        => "BH",
    "OMAN"           => "OM",

    // 🌏 Oceania
    "AUSTRALIA"      => "AU",
    "NEW ZEALAND"    => "NZ",
    "FIJI"           => "FJ",
    "PAPUA NEW GUINEA" => "PG"
];

// 3. ISO-to-Continent Mapping
$continentMap = [
    // North America
    "US" => "North America",
    "CA" => "North America",
    "MX" => "North America",
    // South America
    "BR" => "South America",
    "AR" => "South America",
    "CO" => "South America",
    "CL" => "South America",
    "PE" => "South America",
    "VE" => "South America",
    "EC" => "South America",
    "BO" => "South America",
    "UY" => "South America",
    "PY" => "South America",
    // Europe
    "GB" => "Europe",
    "DE" => "Europe",
    "FR" => "Europe",
    "IT" => "Europe",
    "ES" => "Europe",
    "NL" => "Europe",
    "BE" => "Europe",
    "CH" => "Europe",
    "SE" => "Europe",
    "NO" => "Europe",
    "DK" => "Europe",
    "FI" => "Europe",
    "AT" => "Europe",
    "IE" => "Europe",
    "PT" => "Europe",
    "PL" => "Europe",
    "CZ" => "Europe",
    "HU" => "Europe",
    "GR" => "Europe",
    "SK" => "Europe",
    "LT" => "Europe",
    "LV" => "Europe",
    "EE" => "Europe",
    "SI" => "Europe",
    "HR" => "Europe",
    "BG" => "Europe",
    "RO" => "Europe",
    "IS" => "Europe",
    "MT" => "Europe",
    "LU" => "Europe",
    "CY" => "Europe",
    // Africa
    "ZA" => "Africa",
    "EG" => "Africa",
    "NG" => "Africa",
    "KE" => "Africa",
    "GH" => "Africa",
    "ET" => "Africa",
    "TZ" => "Africa",
    "UG" => "Africa",
    "DZ" => "Africa",
    "MA" => "Africa",
    "AO" => "Africa",
    "SD" => "Africa",
    "ZM" => "Africa",
    "SN" => "Africa",
    "TN" => "Africa",
    "CI" => "Africa",
    // Asia
    "IN" => "Asia",
    "CN" => "Asia",
    "JP" => "Asia",
    "KR" => "Asia",
    "ID" => "Asia",
    "PH" => "Asia",
    "TH" => "Asia",
    "VN" => "Asia",
    "MY" => "Asia",
    "SG" => "Asia",
    "PK" => "Asia",
    "BD" => "Asia",
    "LK" => "Asia",
    "NP" => "Asia",
    "KZ" => "Asia",
    "IR" => "Asia",
    "SA" => "Asia",
    "AE" => "Asia",
    "IL" => "Asia",
    "LB" => "Asia",
    "QA" => "Asia",
    "KW" => "Asia",
    "JO" => "Asia",
    "BH" => "Asia",
    "OM" => "Asia",
    // Oceania
    "AU" => "Oceania",
    "NZ" => "Oceania",
    "FJ" => "Oceania",
    "PG" => "Oceania"
];

// 4. Query the transactions table for total spent and total orders per country
$countryData = [];
$query = "
    SELECT 
        UPPER(country) AS country,
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
    // Look up the ISO code from the country name
    $isoCode = $countryISOMap[$countryName] ?? null;

    if ($isoCode) {
        // Determine continent from ISO code
        $continent = $continentMap[$isoCode] ?? "Unknown";
        $spent     = floatval($row['total_spent']);
        $orders    = intval($row['total_orders']);

        // Build the final data array keyed by ISO code
        $countryData[$isoCode] = [
            "total_spent"  => $spent,
            "total_orders" => $orders,
            "continent"    => $continent
        ];
    }
}

$conn->close();

// 5. Return JSON response
header('Content-Type: application/json');
echo json_encode($countryData, JSON_PRETTY_PRINT);
?>
