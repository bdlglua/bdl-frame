<?php
declare(strict_types=1);

if (!defined('BDL_FRAME')) {
    http_response_code(404);
    exit;
}

const CONFIG_PATH = __DIR__ . '/config.php';
const SESSION_COOKIE = '__Host-BDLFrame';
const STUDIO_SESSION_SECONDS = 28800;

function html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function page_headers(bool $private = false): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; base-uri 'none'; frame-ancestors 'none'; object-src 'none'; form-action 'self'");
    if ($private) {
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex, nofollow');
    }
}

function site_config(): ?array
{
    if (!is_file(CONFIG_PATH)) {
        return null;
    }
    $config = require CONFIG_PATH;
    return is_array($config) ? $config : null;
}

function db_connect(array $config): PDO
{
    $dsn = 'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4';
    return new PDO($dsn, $config['db_user'], $config['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ]);
}

function open_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(SESSION_COOKIE);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!session_start(['use_strict_mode' => 1, 'use_only_cookies' => 1])) {
        throw new RuntimeException('Session indisponible.');
    }
}

function csrf_token(): string
{
    open_session();
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function is_csrf_valid(): bool
{
    $token = $_POST['csrf'] ?? null;
    return is_string($token) && hash_equals(csrf_token(), $token);
}

function studio_session(?array $config): bool
{
    if ($config === null || !isset($_COOKIE[SESSION_COOKIE])) {
        return false;
    }
    open_session();
    $stamp = $_SESSION['studio_at'] ?? 0;
    $version = $_SESSION['studio_version'] ?? '';
    return is_int($stamp) && $stamp > time() - STUDIO_SESSION_SECONDS
        && is_string($version) && hash_equals(hash('sha256', $config['admin_hash']), $version);
}

function start_studio_session(array $config): void
{
    open_session();
    session_regenerate_id(true);
    $_SESSION = [
        'studio_at' => time(),
        'studio_version' => hash('sha256', $config['admin_hash']),
    ];
}

function end_studio_session(): void
{
    open_session();
    $_SESSION = [];
    session_destroy();
    setcookie(SESSION_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function secure_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (getenv('BDL_FRAME_LOCAL') === '1'
            && preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/D', $_SERVER['HTTP_HOST'] ?? '') === 1);
}

function field(array $data, string $key, int $max): string
{
    $value = $data[$key] ?? null;
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    $length = preg_match_all('/./us', $value);
    return $length !== false && $length <= $max ? $value : '';
}

function sql(PDO $db, string $statement, array $params = []): PDOStatement
{
    $query = $db->prepare($statement);
    foreach ($params as $index => $value) {
        $query->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $query->execute();
    return $query;
}

function consume_limit(PDO $db, string $scope, int $window, int $max): bool
{
    $key = hash('sha256', $scope . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $bucket = intdiv(time(), $window) * $window;
    $db->beginTransaction();
    try {
        sql($db, 'INSERT INTO bdl_limits (bucket_key, window_start, count) VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE count = count + 1', [$key, $bucket]);
        $count = (int) sql($db, 'SELECT count FROM bdl_limits WHERE bucket_key = ? AND window_start = ?', [$key, $bucket])->fetchColumn();
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
    if (random_int(1, 100) === 1) {
        sql($db, 'DELETE FROM bdl_limits WHERE window_start < ? LIMIT 100', [time() - 86400]);
    }
    return $count <= $max;
}
