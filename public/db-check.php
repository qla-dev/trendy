<?php
// Temporary SQL Server connectivity check. Open: /db-check.php?key=<token>
// Delete this file once the connection issue is resolved.

if (($_GET['key'] ?? '') !== 'a715a129975cd12c1fc8a3ded86f1a3c') {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(60);

$env = [];
foreach (@file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if ($line[0] === '#' || strpos($line, '=') === false) {
        continue;
    }
    [$name, $value] = explode('=', $line, 2);
    $env[trim($name)] = trim(trim($value), "\"'");
}

$host = $_GET['host'] ?? ($env['DB_HOST'] ?? 'hostBApa1.datalab.ba');
$port = (int) ($_GET['port'] ?? ($env['DB_PORT'] ?? 50387));
$database = $env['DB_DATABASE'] ?? '';
$username = $env['DB_USERNAME'] ?? '';
$password = $env['DB_PASSWORD'] ?? '';

echo "== Target: {$host}:{$port} / {$database} as {$username}\n";
echo "PHP " . PHP_VERSION . ", pdo_sqlsrv: " . (extension_loaded('pdo_sqlsrv') ? phpversion('pdo_sqlsrv') : 'NOT LOADED') . "\n";
echo "DB_ENCRYPT=" . ($env['DB_ENCRYPT'] ?? '(unset)') . ", DB_TRUST_SERVER_CERTIFICATE=" . ($env['DB_TRUST_SERVER_CERTIFICATE'] ?? '(unset)') . "\n";

echo "\n== DNS\n";
$ips = @gethostbynamel($host);
echo $ips ? implode(', ', $ips) : 'DNS resolution FAILED';
echo "\n";

echo "\n== TCP connect (5s timeout)\n";
$start = microtime(true);
$socket = @fsockopen($host, $port, $errno, $errstr, 5);
$ms = round((microtime(true) - $start) * 1000);
if ($socket) {
    fclose($socket);
    echo "OK in {$ms} ms\n";
} else {
    echo "FAILED after {$ms} ms: [{$errno}] {$errstr}\n";
}

echo "\n== SQL Server login (pdo_sqlsrv)\n";
if (!extension_loaded('pdo_sqlsrv')) {
    echo "skipped, pdo_sqlsrv not loaded\n";
} else {
    $encrypt = filter_var($env['DB_ENCRYPT'] ?? false, FILTER_VALIDATE_BOOL) ? 'yes' : 'no';
    $trust = filter_var($env['DB_TRUST_SERVER_CERTIFICATE'] ?? true, FILTER_VALIDATE_BOOL) ? 1 : 0;
    $start = microtime(true);
    try {
        $pdo = new PDO(
            "sqlsrv:Server={$host},{$port};Database={$database};Encrypt={$encrypt};TrustServerCertificate={$trust};LoginTimeout=15",
            $username,
            $password
        );
        $row = $pdo->query('SELECT DB_NAME() AS db, @@SERVERNAME AS srv, GETDATE() AS now')->fetch(PDO::FETCH_ASSOC);
        echo 'OK in ' . round((microtime(true) - $start) * 1000) . " ms: " . json_encode($row) . "\n";
    } catch (Throwable $e) {
        echo 'FAILED after ' . round((microtime(true) - $start) * 1000) . " ms: " . $e->getMessage() . "\n";
    }
}

echo "\n== Public outbound IP of this server\n";
$ip = @file_get_contents('https://ifconfig.me/ip', false, stream_context_create(['http' => ['timeout' => 10]]));
echo ($ip ?: 'could not determine') . "\n";
