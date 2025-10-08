<?php

declare(strict_types=1);

namespace ChatGPTProxy;

use PDO;
use RuntimeException;

header('Content-Type: application/json');

// ---------- bootstrap ----------
const DB_FILE = __DIR__ . '/requests.sqlite';
const LOG_DIR = __DIR__ . '/logs';
if (!is_dir(LOG_DIR)) mkdir(LOG_DIR, 0777, true);

$config = require __DIR__ . '/.env.php';
$apiKey = (string)(
    $config['api_key']
);

if ($apiKey === '') {
    http_response_code(500);
    echo json_encode(['error' => 'OPENAI_API_KEY environment variable is not set']);
    exit;
}

$db = new PDO('sqlite:' . DB_FILE, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec("
CREATE TABLE IF NOT EXISTS requests (
  id TEXT PRIMARY KEY,
  prompt TEXT NOT NULL,
  status TEXT NOT NULL,
  response TEXT NULL,
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL,
  attempts INTEGER NOT NULL DEFAULT 0
)");
$db->exec("
CREATE TABLE IF NOT EXISTS rate_limit (
  ip TEXT NOT NULL,
  hour INTEGER NOT NULL,
  count INTEGER NOT NULL,
  PRIMARY KEY(ip, hour)
)");

// ---------- helpers ----------
final class Http
{
    public static function ok(array $data, int $code = 200): never
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    public static function fail(string $msg, int $code = 400, array $ctx = []): never
    {
        self::ok(['error' => $msg, 'context' => $ctx], $code);
    }
}
final class Log
{
    public static function write(string $msg, array $ctx = []): void
    {
        $line = date('c') . ' ' . $msg . ' ' . json_encode($ctx, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents(LOG_DIR . '/proxy.log', $line, FILE_APPEND);
    }
}
final class Auth
{
    public static function requireProxyKey(array $cfg): void
    {
        $k = $_SERVER['HTTP_X_PROXY_KEY'] ?? '';
        if ($k !== $cfg['proxy_key']) Http::fail('Unauthorized', 401);
    }
    public static function verifyHmac(string $payload, string $sig, string $secret): void
    {
        $exp = hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($exp, $sig)) Http::fail('Invalid signature', 403);
    }
}
final class Rate
{
    public static function hit(PDO $db, int $limit): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $hour = intdiv(time(), 3600);
        $stmt = $db->prepare("SELECT count FROM rate_limit WHERE ip=:ip AND hour=:h");
        $stmt->execute([':ip' => $ip, ':h' => $hour]);
        $count = (int)($stmt->fetchColumn() ?: 0);
        if ($count >= $limit) Http::fail('Rate limit exceeded', 429);
        if ($count === 0) {
            $ins = $db->prepare("INSERT OR REPLACE INTO rate_limit(ip,hour,count) VALUES(:ip,:h,1)");
            $ins->execute([':ip' => $ip, ':h' => $hour]);
        } else {
            $upd = $db->prepare("UPDATE rate_limit SET count=count+1 WHERE ip=:ip AND hour=:h");
            $upd->execute([':ip' => $ip, ':h' => $hour]);
        }
    }
}
final class Store
{
    public static function create(PDO $db, string $prompt): string
    {
        $id = bin2hex(random_bytes(8));
        $ts = time();
        $stmt = $db->prepare("
          INSERT INTO requests(id,prompt,status,response,created_at,updated_at)
          VALUES(:id,:p,'pending',NULL,:ts,:ts)
        ");
        $stmt->execute([':id' => $id, ':p' => $prompt, ':ts' => $ts]);
        return $id;
    }
    public static function get(PDO $db, string $id): array
    {
        $stmt = $db->prepare("SELECT * FROM requests WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) Http::fail('Request not found', 404);
        $row['response'] = $row['response'] ? json_decode($row['response'], true) : null;
        return $row;
    }
    public static function cleanup(PDO $db, int $ttl): void
    {
        $cut = time() - $ttl;
        $stmt = $db->prepare("DELETE FROM requests WHERE created_at < :cut");
        $stmt->execute([':cut' => $cut]);
    }
    public static function pending(PDO $db): array
    {
        return $db->query("SELECT * FROM requests WHERE status='pending' ORDER BY created_at ASC")->fetchAll();
    }
    public static function update(PDO $db, string $id, string $status, ?array $resp, int $attempts): void
    {
        $stmt = $db->prepare("
          UPDATE requests SET status=:s,response=:r,attempts=:a,updated_at=:ts WHERE id=:id
        ");
        $stmt->execute([
            ':s' => $status,
            ':r' => $resp ? json_encode($resp, JSON_UNESCAPED_UNICODE) : null,
            ':a' => $attempts,
            ':ts' => time(),
            ':id' => $id
        ]);
    }
}
final class OpenAI
{
    public static function call(string $url, string $key, string $model, int $timeout, string $prompt): array
    {
        $payload = [
            'model'    => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $key,
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false || $http >= 400) {
            throw new RuntimeException("OpenAI error HTTP $http: $err");
        }
        $json = json_decode($resp, true);
        if (!is_array($json)) throw new RuntimeException('Invalid JSON from OpenAI');
        return $json;
    }
}
final class Worker
{
    public static function run(PDO $db, array $cfg, string $apiKey): array
    {
        Store::cleanup($db, $cfg['ttl']);
        $jobs = Store::pending($db);
        $ok = 0;
        $fail = 0;

        foreach ($jobs as $j) {
            $attempts = (int)$j['attempts'] + 1;
            try {
                $resp = OpenAI::call($cfg['openai_url'], $apiKey, $cfg['model'], $cfg['timeout'], $j['prompt']);
                Store::update($db, $j['id'], 'done', $resp, $attempts);
                $ok++;
            } catch (\Throwable $e) {
                if ($attempts >= $cfg['max_retries']) {
                    Store::update($db, $j['id'], 'error', ['error' => $e->getMessage()], $attempts);
                    $fail++;
                } else {
                    // exponential backoff by re-marking pending and sleeping briefly
                    Store::update($db, $j['id'], 'pending', null, $attempts);
                    sleep($attempts * 2);
                }
                Log::write('job_failed', ['id' => $j['id'], 'attempts' => $attempts, 'error' => $e->getMessage()]);
            }
        }
        return ['status' => 'worker_finished', 'processed' => $ok, 'failed' => $fail, 'time' => time()];
    }
}

// ---------- router ----------
$action = $_GET['action'] ?? ($_SERVER['argv'][1] ?? '');

switch ($action) {
    case 'health':
        Http::ok(['status' => 'ok', 'time' => time()]);
        break;

    case 'v1/request':
    case 'request':
        Auth::requireProxyKey($config);
        Rate::hit($db, (int)$config['rate_limit']);
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) Http::fail('Invalid JSON');

        $prompt = $input['prompt'] ?? null;
        $sign   = $input['sign'] ?? null;
        if (!$prompt || !$sign) Http::fail('Missing prompt or sign');

        Auth::verifyHmac($prompt, $sign, $config['secret']);
        $id = Store::create($db, $prompt);
        Http::ok(['id' => $id, 'status' => 'pending']);
        break;

    case 'v1/result':
    case 'result':
        $id = $_GET['id'] ?? '';
        if ($id === '') Http::fail('Missing id');
        Http::ok(Store::get($db, $id));
        break;

    case 'worker':
        Http::ok(Worker::run($db, $config, $apiKey));
        break;

    case 'daemon':
        while (true) {
            $res = Worker::run($db, $config, $apiKey);
            Log::write('daemon_cycle', $res);
            sleep((int)$config['daemon_sleep']);
        }
        // no break
    default:
        Http::fail('Unknown action', 404);
}
