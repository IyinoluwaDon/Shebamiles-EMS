<?php
putenv('EMS_ADMIN_USERNAME');
putenv('EMS_ADMIN_EMAIL');
putenv('EMS_ADMIN_PASSWORD');
function runChild(array $command): array
{
    $pipes = [];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start a PHP child process.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $stdout, $stderr];
}

[$status, $stdout, $stderr] = runChild([PHP_BINARY, __DIR__ . '/../scripts/create_admin.php']);
if ($status !== 2 || strpos($stderr, 'Set EMS_ADMIN_USERNAME') === false || $stdout !== '') {
    fwrite(STDERR, "Missing setup values should fail before touching the database.\n");
    exit(1);
}

$config = var_export(__DIR__ . '/../includes/config.php', true);
 $code = 'putenv("APP_ENV=production"); foreach (["DB_HOST", "DB_PORT", "DB_USER", "DB_PASS", "DB_NAME"] as $key) { putenv($key); } try { require ' . $config . '; } catch (Throwable $error) { fwrite(STDOUT, $error->getMessage()); exit(4); }';
[$status, $stdout, $stderr] = runChild([PHP_BINARY, '-r', $code]);
if ($status !== 4 || strpos($stdout, 'Production database credentials are required.') === false) {
    fwrite(STDERR, "Production config must reject missing database credentials. Exit: {$status}. Output: {$stdout}{$stderr}\n");
    exit(1);
}
fwrite(STDOUT, "Admin provisioning rejects missing setup values; production config fails closed without database credentials.\n");
