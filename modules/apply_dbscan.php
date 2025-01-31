<?php
$command = escapeshellcmd("python apply_dbscan.py");
$output = shell_exec($command);
echo "<pre>$output</pre>";

if (file_exists("../data/transactions.csv")) {
    echo "<a href='transactions.csv' download>Download Segmented Data</a>";
} else {
    echo "Error: DBSCAN output not found!";
}
?>
