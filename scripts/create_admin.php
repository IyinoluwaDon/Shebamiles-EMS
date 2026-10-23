<?php
/** Provision the first administrator from one-shot CLI environment values. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$username = getenv('EMS_ADMIN_USERNAME');
$email = getenv('EMS_ADMIN_EMAIL');
$password = getenv('EMS_ADMIN_PASSWORD');
if (!is_string($username) || !preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)
    || !is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    || !is_string($password) || strlen($password) < 16) {
    fwrite(STDERR, "Set EMS_ADMIN_USERNAME, EMS_ADMIN_EMAIL, and EMS_ADMIN_PASSWORD (16+ characters).\n");
    exit(2);
}

require_once __DIR__ . '/../includes/config.php';
$connection = (new Database())->getConnection();
if (!$connection instanceof PDO) {
    fwrite(STDERR, "Database connection failed. Check the DB_* environment values.\n");
    exit(1);
}

try {
    $connection->beginTransaction();
    $activeAdmin = $connection->query("SELECT 1 FROM users WHERE role = 'admin' AND status = 'active' LIMIT 1")->fetchColumn();
    if ($activeAdmin) {
        throw new RuntimeException('An active administrator already exists.');
    }

    $find = $connection->prepare('SELECT user_id, role FROM users WHERE username = ? FOR UPDATE');
    $find->execute([$username]);
    $existing = $find->fetch();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false) {
        throw new RuntimeException('Password hashing failed.');
    }

    if ($existing) {
        if ($existing['role'] !== 'admin') {
            throw new RuntimeException('That username is already assigned to a non-admin account.');
        }
        $save = $connection->prepare("UPDATE users SET email = ?, password = ?, status = 'active' WHERE user_id = ?");
        $save->execute([$email, $hash, $existing['user_id']]);
    } else {
        $save = $connection->prepare("INSERT INTO users (username, password, email, role, status) VALUES (?, ?, ?, 'admin', 'active')");
        $save->execute([$username, $hash, $email]);
    }

    $connection->commit();
    fwrite(STDOUT, "Administrator provisioned. Remove the EMS_ADMIN_* values from the environment.\n");
} catch (Throwable $error) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
