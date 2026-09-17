<?php

declare(strict_types=1);

// Report failures before environment setup, binary discovery, or deployment.
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    header('X-Accel-Buffering: no');
}

set_exception_handler(static function (Throwable $error): void {
    echo "\nRedeploy stopped: " . get_class($error) . ': ' . $error->getMessage()
        . "\nLocation: " . basename($error->getFile()) . ':' . $error->getLine() . "\n";
    exit(1);
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        echo "\nFatal redeploy error: " . $error['message'] . "\n";
    }
});

ini_set('memory_limit', '512M');
ini_set('output_buffering', '0');
ini_set('zlib.output_compression', '0');

while (ob_get_level() > 0) {
    if (!ob_end_flush()) {
        break;
    }
}
ob_implicit_flush(true);
echo "eNalog redeploy revision 2026-09-17.4 (frontend only)\n";
flush();

if (filter_var($_GET['diagnostics'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
    echo 'PHP version: ' . PHP_VERSION . "\n";
    echo 'PHP handler: ' . PHP_SAPI . "\n";
    foreach (['putenv', 'proc_open', 'proc_get_status', 'proc_close', 'escapeshellarg', 'set_time_limit'] as $function) {
        echo $function . ': ' . (function_exists($function) ? 'available' : 'disabled') . "\n";
    }
    echo 'Laravel project above public folder: '
        . (is_file(dirname(__DIR__) . '/artisan') ? 'found' : 'NOT FOUND') . "\n";
    echo 'Project folder writable: ' . (is_writable(dirname(__DIR__)) ? 'yes' : 'no') . "\n";
    echo "Diagnostics complete. No deployment commands were executed.\n";
    exit(0);
}

/*
 * Single-application deployment endpoint for Trendy.
 *
 * The web root is public/, while Laravel, Composer, and Mix live one level
 * above it. Run this endpoint only from a protected deployment URL.
 */

if (function_exists('set_time_limit')) {
    set_time_limit(0);
}
$baseDir = dirname(__DIR__);
chdir($baseDir);

$write = static function (string $message): void {
    echo $message;
    flush();
};

if (!function_exists('proc_open')) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(503);
    }

    $write("Redeploy cannot run because this hosting account disables PHP proc_open(). Ask the hosting provider to allow proc_open for this protected endpoint.\n");
    exit(1);
}

$lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'trendy-redeploy.lock';
$lock = fopen($lockFile, 'c');

if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(409);
    }

    $write("Another Trendy redeploy is already running.\n");
    exit(1);
}

register_shutdown_function(static function () use ($lock): void {
    flock($lock, LOCK_UN);
    fclose($lock);
});

$run = static function (string $command, string $cwd, callable $write): int {
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $cwd);

    if (!is_resource($process)) {
        $write("Could not start: {$command}\n");

        return 1;
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $exitCode = null;
    $lastOutputAt = time();

    do {
        foreach ([1, 2] as $pipeNumber) {
            while (($line = fgets($pipes[$pipeNumber])) !== false) {
                $lastOutputAt = time();
                $write($line);
            }
        }

        $status = proc_get_status($process);
        if (!$status['running']) {
            $exitCode = $status['exitcode'];
            break;
        }

        if (time() - $lastOutputAt >= 15) {
            $lastOutputAt = time();
            $write('... still running at ' . date('H:i:s') . "\n");
        }

        usleep(100000);
    } while (true);

    foreach ([1, 2] as $pipeNumber) {
        while (($line = fgets($pipes[$pipeNumber])) !== false) {
            $write($line);
        }
        fclose($pipes[$pipeNumber]);
    }

    $closeCode = proc_close($process);

    return is_int($exitCode) && $exitCode >= 0 ? $exitCode : $closeCode;
};

$findExecutable = static function (array $candidates): ?string {
    $pathCommands = [];

    foreach ($candidates as $candidate) {
        if (str_contains($candidate, DIRECTORY_SEPARATOR) && is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }

        if (!str_contains($candidate, DIRECTORY_SEPARATOR)) {
            $pathCommands[] = $candidate;
        }
    }

    // Do not use exec()/shell_exec() for discovery: many shared cPanel hosts
    // disable those functions for web requests. proc_open() resolves commands
    // on PATH when the deployment step is executed and streams any error.
    return $pathCommands[0] ?? null;
};

$npmCandidates = [
    '/opt/cpanel/ea-nodejs24/bin/npm',
    '/opt/cpanel/ea-nodejs22/bin/npm',
    '/opt/cpanel/ea-nodejs20/bin/npm',
    '/opt/alt/alt-nodejs24/root/usr/bin/npm',
    '/opt/alt/alt-nodejs22/root/usr/bin/npm',
    '/opt/alt/alt-nodejs20/root/usr/bin/npm',
    '/usr/local/bin/npm',
    '/usr/bin/npm',
];

foreach ([
    '/opt/cpanel/ea-nodejs*/bin/npm',
    '/opt/alt/alt-nodejs*/root/usr/bin/npm',
] as $pattern) {
    foreach (glob($pattern) ?: [] as $candidate) {
        $npmCandidates[] = $candidate;
    }
}

$npm = $findExecutable(array_unique($npmCandidates));

if ($npm === null) {
    $write("Could not find npm; enable Node.js 20 or newer in cPanel.\n");
    exit(127);
}

$nodeBinDir = dirname($npm);
$currentPath = (string) (getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin');
putenv('PATH=' . $nodeBinDir . PATH_SEPARATOR . $currentPath);

$npmCommand = escapeshellarg($npm);
$offersOnly = filter_var($_GET['offers_only'] ?? false, FILTER_VALIDATE_BOOLEAN);

$commands = [
    ['label' => 'Pulling latest Trendy code', 'command' => 'git pull --ff-only origin main'],
    ['label' => 'Installing frontend dependencies', 'command' => $npmCommand . ' ci --no-audit --no-fund'],
    ['label' => 'Installing dependencies for both offers', 'command' => $npmCommand . ' --prefix resources/ponuda ci --no-audit --no-fund'],
    ['label' => 'Building frontend assets', 'command' => $npmCommand . ' run production'],
];

if ($offersOnly) {
    $commands = [
        ['label' => 'Pulling latest Trendy code', 'command' => 'git pull --ff-only origin main'],
        ['label' => 'Installing dependencies for both offers', 'command' => $npmCommand . ' --prefix resources/ponuda ci --no-audit --no-fund'],
        ['label' => 'Building both offer pages', 'command' => $npmCommand . ' run build:ponuda'],
    ];
    $write("Offers-only redeploy: builds /ponuda/ and /ponuda-2/ together.\n");
}

$startedAt = time();

foreach ($commands as $step) {
    $write("\n=== {$step['label']} ===\n");
    $write("Command: {$step['command']}\n");
    $exitCode = $run($step['command'], $baseDir, $write);

    if ($exitCode !== 0) {
        if (PHP_SAPI !== 'cli') {
            http_response_code(500);
        }
        $write("{$step['label']} failed with exit code {$exitCode}.\n");
        exit($exitCode);
    }
}

foreach (['ponuda', 'ponuda-2'] as $offerFolder) {
    if (!is_file($baseDir . '/public/' . $offerFolder . '/index.html')) {
        $write("Deployment incomplete: /{$offerFolder}/index.html was not generated.\n");
        exit(1);
    }
}

$write("\nBoth offer pages are ready: /ponuda/ and /ponuda-2/.\n");
$write("\nTrendy redeploy completed successfully in " . (time() - $startedAt) . "s.\n");
