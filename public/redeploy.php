<?php

declare(strict_types=1);

/*
 * Single-application deployment endpoint for Trendy.
 *
 * The web root is public/, while Laravel, Composer, and Mix live one level
 * above it. Run this endpoint only from a protected deployment URL.
 */

set_time_limit(0);
ini_set('memory_limit', '512M');
ini_set('output_buffering', '0');
ini_set('zlib.output_compression', '0');

$baseDir = dirname(__DIR__);
chdir($baseDir);
putenv('COMPOSER_ALLOW_SUPERUSER=1');
putenv('COMPOSER_NO_INTERACTION=1');

// Match the cPanel-safe Composer setup used by TrackPal. Shared hosting
// accounts often do not provide a writable global HOME or Composer cache.
$composerHome = $baseDir . DIRECTORY_SEPARATOR . '.composer';
$composerCache = $composerHome . DIRECTORY_SEPARATOR . 'cache';

foreach ([$composerHome, $composerCache] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException("Could not create Composer directory: {$directory}");
    }
}

putenv('HOME=' . $composerHome);
putenv('COMPOSER_HOME=' . $composerHome);
putenv('COMPOSER_CACHE_DIR=' . $composerCache);

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    header('X-Accel-Buffering: no');
}

while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

$write = static function (string $message): void {
    echo $message;
    flush();
};

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
    foreach ($candidates as $candidate) {
        if (str_contains($candidate, DIRECTORY_SEPARATOR) && is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }

        $lookup = PHP_OS_FAMILY === 'Windows'
            ? 'where ' . escapeshellarg($candidate)
            : 'command -v ' . escapeshellarg($candidate) . ' 2>/dev/null';
        exec($lookup, $output, $exitCode);

        if ($exitCode === 0 && isset($output[0]) && $output[0] !== '') {
            return trim($output[0]);
        }
    }

    return null;
};

$phpCandidates = [
    // PHP_BINARY is lsphp when this endpoint is invoked through LiteSpeed;
    // Composer and Artisan require a CLI PHP binary instead.
    '/opt/cpanel/ea-php84/root/usr/bin/php',
    '/opt/cpanel/ea-php83/root/usr/bin/php',
    '/opt/cpanel/ea-php82/root/usr/bin/php',
    '/usr/local/bin/php',
    '/usr/bin/php',
    'php',
];

if (PHP_SAPI === 'cli') {
    array_unshift($phpCandidates, PHP_BINARY);
}

$php = $findExecutable($phpCandidates);

$composer = $findExecutable(['composer', '/usr/local/bin/composer', '/usr/bin/composer']);
$npm = $findExecutable([
    '/opt/cpanel/ea-nodejs24/bin/npm',
    '/opt/cpanel/ea-nodejs22/bin/npm',
    '/opt/cpanel/ea-nodejs20/bin/npm',
    '/opt/alt/alt-nodejs24/root/usr/bin/npm',
    '/opt/alt/alt-nodejs22/root/usr/bin/npm',
    '/opt/alt/alt-nodejs20/root/usr/bin/npm',
    '/usr/local/bin/npm',
    '/usr/bin/npm',
    'npm',
]);

if ($php === null || $npm === null) {
    if ($php === null) {
        $write("Could not find CLI PHP.\n");
    }
    if ($npm === null) {
        $write("Could not find npm; enable Node.js 20 or newer in cPanel.\n");
    }
    exit(127);
}

$nodeBinDir = dirname($npm);
$currentPath = (string) (getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin');
putenv('PATH=' . $nodeBinDir . PATH_SEPARATOR . $currentPath);

$phpCommand = escapeshellarg($php);
$composerPhar = $baseDir . DIRECTORY_SEPARATOR . 'composer.phar';

if (is_file($composerPhar)) {
    $composerCommand = $phpCommand . ' ' . escapeshellarg($composerPhar);
} elseif ($composer !== null) {
    $composerCommand = escapeshellarg($composer);
} else {
    $write("Composer was not found on PATH. Downloading local composer.phar...\n");
    $composerContents = @file_get_contents('https://getcomposer.org/download/latest-stable/composer.phar');

    if ($composerContents === false || @file_put_contents($composerPhar, $composerContents) === false) {
        $write("Could not download Composer. Upload composer.phar to the Trendy project root, then run redeploy again.\n");
        exit(127);
    }

    $write("Saved local Composer to {$composerPhar}.\n");
    $composerCommand = $phpCommand . ' ' . escapeshellarg($composerPhar);
}

$npmCommand = escapeshellarg($npm);
$frontendOnly = filter_var($_GET['frontend_only'] ?? false, FILTER_VALIDATE_BOOLEAN);

$commands = [
    ['label' => 'Pulling latest Trendy code', 'command' => 'git pull --ff-only origin main'],
    ['label' => 'Installing frontend dependencies', 'command' => $npmCommand . ' ci --no-audit --no-fund'],
    ['label' => 'Building frontend assets', 'command' => $npmCommand . ' run production'],
    ['label' => 'Clearing and rebuilding Laravel caches', 'command' => $phpCommand . ' artisan optimize --no-ansi'],
];

if (!$frontendOnly) {
    array_splice($commands, 1, 0,
        ['label' => 'Installing Composer dependencies', 'command' => $composerCommand . ' install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader --no-ansi'],
        ['label' => 'Running database migrations', 'command' => $phpCommand . ' artisan migrate --force --no-ansi'],
    );
} else {
    $write("Frontend-only redeploy: Composer installation and database migrations are skipped.\n");
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

$write("\nTrendy redeploy completed successfully in " . (time() - $startedAt) . "s.\n");
