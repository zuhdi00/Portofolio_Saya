<?php

$serverName = "spsdmz";
$connectionOptions = [
    "Database" => "dbSopanusa",
    "Uid" => "sa",
    "PWD" => "supracor",
    "LoginTimeout" => 15,
    "Encrypt" => false,
    "TrustServerCertificate" => true
];

$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die("Connection failed: " . print_r(sqlsrv_errors(), true));
}


$sql = "SELECT TOP 5 cNamaus, cPassword FROM tbUserV2 WHERE Aktif = 1";
$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    die("Query failed: " . print_r(sqlsrv_errors(), true));
}

echo "<h2>Analisis Password Database</h2>\n";
echo "<table border='1'>\n";
echo "<tr><th>Username</th><th>Raw Password</th><th>Hex</th><th>Decoded Options</th></tr>\n";

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $username = $row['cNamaus'];
    $password = $row['cPassword'];
    
    echo "<tr>\n";
    echo "<td>" . htmlspecialchars($username) . "</td>\n";
    echo "<td>" . htmlspecialchars($password) . "</td>\n";
    echo "<td>" . bin2hex($password) . "</td>\n";
    echo "<td>\n";
    
    $decodingMethods = [
        'UTF-8 -> ISO-8859-1' => mb_convert_encoding($password, 'UTF-8', 'ISO-8859-1'),
        'Windows-1252 -> UTF-8' => mb_convert_encoding($password, 'UTF-8', 'Windows-1252'),
        'CP1252 -> UTF-8' => iconv('CP1252', 'UTF-8//IGNORE', $password),
        'Latin1 -> UTF-8' => mb_convert_encoding($password, 'UTF-8', 'ISO-8859-1'),
        'Base64 Decode' => base64_decode($password, true),
        'URL Decode' => urldecode($password),
    ];
    
    foreach ($decodingMethods as $method => $decoded) {
        if ($decoded !== false && $decoded !== $password) {
            echo "<strong>$method:</strong> " . htmlspecialchars($decoded) . "<br>\n";
        }
    }
    

    echo "<strong>Character Analysis:</strong><br>\n";
    for ($i = 0; $i < strlen($password); $i++) {
        $char = $password[$i];
        $ascii = ord($char);
        $hex = dechex($ascii);
        echo "[$i] '$char' (ASCII: $ascii, Hex: $hex) ";
    }
    
    echo "</td>\n";
    echo "</tr>\n";
}

echo "</table>\n";


function testPasswordConversion($storedPassword, $expectedPassword) {
    echo "<h3>Testing password conversion for expected: '$expectedPassword'</h3>\n";
    
    $methods = [
        'Direct' => $storedPassword,
        'UTF-8->ISO' => mb_convert_encoding($storedPassword, 'UTF-8', 'ISO-8859-1'),
        'Win1252->UTF8' => mb_convert_encoding($storedPassword, 'UTF-8', 'Windows-1252'),
        'CP1252->UTF8' => iconv('CP1252', 'UTF-8//IGNORE', $storedPassword),
        'Reverse UTF8->Latin1' => mb_convert_encoding($storedPassword, 'ISO-8859-1', 'UTF-8'),
    ];
    
    foreach ($methods as $method => $result) {
        $match = ($result === $expectedPassword) ? " ✓ MATCH!" : "";
        echo "$method: '" . htmlspecialchars($result) . "'$match<br>\n";
    }
}

echo "<hr>\n";
testPasswordConversion("¿ÍÊˆ¸ÑÚŽ"e„‰@", "ziahaha");

sqlsrv_close($conn);
?>