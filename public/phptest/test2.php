<?php

$server   = "hostBApa1.datalab.ba,50387";
$database = "BA_TRENDY";
$username = "SQLTREN_ADM2";
$password = "#4^Sdgfx3VHy5G";

$table = "tHE_Order";   // 👈 promijeni tabelu ovdje


$conn = sqlsrv_connect($server, [
    "Database" => $database,
    "UID"      => $username,
    "PWD"      => $password,
    "CharacterSet" => "UTF-8"
]);

if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}

echo "<h2>TABLE: $table</h2>";


// 🔥 SELECT * = sve kolone + svi redovi
$sql = "SELECT TOP 100 * FROM $table";  // TOP 100 = ograničava na prvih 100 redova (ako tabela ima puno podataka)

$stmt = sqlsrv_query($conn, $sql);

if (!$stmt) {
    die(print_r(sqlsrv_errors(), true));
}


// 🔹 Dohvati metapodatke (nazive kolona)
$meta = sqlsrv_field_metadata($stmt);

echo "<table border='1' cellpadding='4' cellspacing='0'>";

// HEADER
echo "<tr>";
foreach ($meta as $col) {
    echo "<th>{$col['Name']}</th>";
}
echo "</tr>";


// DATA
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

    echo "<tr>";

    foreach ($meta as $col) {

        $name = $col['Name'];
        $value = $row[$name];

        // format datuma
        if ($value instanceof DateTime) {
            $value = $value->format('Y-m-d H:i:s');
        }

        echo "<td>" . htmlspecialchars((string)$value) . "</td>";
    }

    echo "</tr>";
}

echo "</table>";

sqlsrv_close($conn);