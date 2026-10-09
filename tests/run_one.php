<?php
// Test runner: executes ONE procurement action or page render in a fresh PHP
// process with a simulated authenticated session.
//
// Usage: php run_one.php <payload.json>
// Payload: { mode: "action"|"render", user_id: int, action?: string, page?: string,
//            get?: {}, post?: {}, expect?: [strings] }

error_reporting(E_ALL);
$payloadFile = $argv[1] ?? '';
if ($payloadFile === '' || !is_file($payloadFile)) {
    fwrite(STDERR, "payload file required\n");
    exit(2);
}
$payload = json_decode(file_get_contents($payloadFile), true);
if (!is_array($payload)) {
    fwrite(STDERR, "invalid payload\n");
    exit(2);
}

$mode = $payload['mode'] ?? 'action';
$userId = (int) ($payload['user_id'] ?? 0);
$action = (string) ($payload['action'] ?? '');
$page = (string) ($payload['page'] ?? '');
$getExtra = is_array($payload['get'] ?? null) ? $payload['get'] : [];
$postExtra = is_array($payload['post'] ?? null) ? $payload['post'] : [];
$expect = is_array($payload['expect'] ?? null) ? $payload['expect'] : [];

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'proc-workflow-test';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/managers.php';

// Surface errors so the orchestrator can detect them in the captured output
ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

// ---- Simulate an authenticated session for the requested user ----
$st = $pdo->prepare("SELECT id, username, full_name, role FROM users WHERE id = ?");
$st->execute([$userId]);
$u = $st->fetch();
if (!$u) {
    echo json_encode(['ok' => false, 'error' => 'test user ' . $userId . ' not found']);
    exit(0);
}
$_SESSION['logged_in'] = true;
$_SESSION['2fa_required'] = false;
$_SESSION['2fa_verified'] = true;
$_SESSION['user_id'] = (int) $u['id'];
$_SESSION['username'] = $u['username'];
$_SESSION['full_name'] = $u['full_name'];
$_SESSION['role'] = $u['role'];
$_SESSION['last_activity'] = time();
$_SESSION['csrf_token'] = 'test-csrf-token';
$_POST['csrf_token'] = 'test-csrf-token';

$hasFatal = function ($text) {
    return strpos($text, 'Fatal error') !== false
        || strpos($text, 'Parse error') !== false
        || strpos($text, 'Uncaught ') !== false;
};

if ($mode === 'action') {
    $_GET = array_merge(['action' => $action], $getExtra);
    $_POST = array_merge($_POST, $postExtra);
    ob_start();
    require __DIR__ . '/../app/requests.php';
    $out = ob_get_clean();
    $decoded = json_decode($out, true);
    if (!is_array($decoded)) {
        echo json_encode(['ok' => false, 'error' => 'non-json response', 'raw' => substr($out, 0, 500)]);
        exit(0);
    }
    echo json_encode(['ok' => true, 'response' => $decoded]);
    exit(0);
}

// ---- render mode ----
$_GET = array_merge(['page' => $page], $getExtra);
$_GET['action'] = null;
unset($_GET['action']);
ob_start();
require __DIR__ . '/../index.php';
$html = ob_get_clean();

$result = [
    'ok' => !$hasFatal($html),
    'length' => strlen($html),
    'has_fatal' => $hasFatal($html),
    'missing' => [],
];
foreach ($expect as $needle) {
    if (strpos($html, $needle) === false) {
        $result['missing'][] = $needle;
    }
}
if ($hasFatal($html)) {
    if (preg_match('/(Fatal error|Parse error|Uncaught ).{0,300}/s', $html, $m)) {
        $result['error_excerpt'] = $m[0];
    }
}
$result['ok'] = $result['ok'] && count($result['missing']) === 0;
echo json_encode($result);
exit(0);
