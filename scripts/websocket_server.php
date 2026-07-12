#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Services\ChatWebSocketAuthService;
use App\Services\PushNotificationService;
use CodeIgniter\Config\Factories;
use Workerman\Connection\TcpConnection;
use Workerman\Lib\Timer;
use Workerman\Worker;

require_once __DIR__ . '/../vendor/autoload.php';

define('FCPATH', __DIR__ . '/../public/');
define('SYSTEMPATH', __DIR__ . '/../vendor/codeigniter4/framework/system/');
define('APPPATH', __DIR__ . '/../app/');
define('WRITEPATH', __DIR__ . '/../writable/');
define('ROOTPATH', __DIR__ . '/../');

// Load .env without external dependency
if (is_file(ROOTPATH . '.env')) {
    foreach (file(ROOTPATH . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim(trim($v), "\"'");
        if ($k !== '' && getenv($k) === false) {
            putenv("$k=$v");
        }
    }
}

if (! function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = getenv($key);
        return ($value !== false) ? $value : $default;
    }
}

$wsHost = env('WEBSOCKET_HOST', '0.0.0.0');
$wsPort = (int) (env('WEBSOCKET_BIND_PORT') ?: env('WEBSOCKET_PORT', 8080));
$wsWorkers = max(1, (int) env('WEBSOCKET_WORKERS', 2));

$wsServer = new Worker(sprintf('websocket://%s:%d', $wsHost, $wsPort));
$wsServer->count = $wsWorkers;
$wsServer->name = 'ChatWebSocket';
$wsServer->connections = [];
$wsServer->users = [];
$wsServer->typing = [];
$wsServer->db = null;
$wsServer->authService = null;
$wsServer->pushService = null;

$wsServer->onWorkerStart = function (Worker $worker): void {
    $worker->connections = [];
    $worker->users = [];
    $worker->typing = [];
    $worker->authService = new ChatWebSocketAuthService();
    $worker->pushService = new PushNotificationService();

    Timer::add(30, static function () use ($worker): void {
        $now = time();
        foreach ($worker->users as $connId => $metadata) {
            $connection = $metadata['connection'] ?? null;
            if (! $connection instanceof TcpConnection) {
                continue;
            }

            $lastActivity = (int) ($metadata['last_activity'] ?? 0);
            if ($lastActivity > 0 && ($now - $lastActivity) > 90) {
                safeSend($connection, ['type' => 'error', 'message' => 'Conexão expirada por inatividade.']);
                $connection->close();
                continue;
            }

            safeSend($connection, ['type' => 'ping', 'timestamp' => $now]);
        }
    });

    Timer::add(5, static function () use ($worker): void {
        $now = time();
        foreach ($worker->typing as $roomId => $users) {
            foreach ($users as $userId => $timestamp) {
                if (($now - (int) $timestamp) > 3) {
                    unset($worker->typing[$roomId][$userId]);
                    broadcastToRoom($worker, (int) $roomId, [
                        'type' => 'typing',
                        'room_id' => (int) $roomId,
                        'user_id' => (int) $userId,
                        'typing' => false,
                    ], (int) $userId);
                }
            }
            if (empty($worker->typing[$roomId])) {
                unset($worker->typing[$roomId]);
            }
        }
    });

    echo "[Worker {$worker->id}] Started at " . date('Y-m-d H:i:s') . PHP_EOL;
};

$wsServer->onWebSocketConnect = function (TcpConnection $connection, $httpRequest): void {
    if (! isOriginAllowed((string) $httpRequest->header('origin', ''))) {
        $connection->close();
        return;
    }

    $connection->maxMessageSize = (int) env('WEBSOCKET_MAX_MESSAGE_BYTES', 65536);
};

$wsServer->onConnect = function (TcpConnection $connection): void {
    $connection->authenticated = false;
    $connection->rooms = [];
    $connection->authAttempts = 0;

    safeSend($connection, [
        'type' => 'auth_required',
        'message' => 'Autenticação obrigatória.',
        'timestamp' => time(),
    ]);

    $connection->authTimeout = Timer::add(15, static function () use ($connection): void {
        if (! ($connection->authenticated ?? false)) {
            safeSend($connection, ['type' => 'auth_error', 'message' => 'Tempo de autenticação expirado.']);
            $connection->close();
        }
    }, [], false);
};

$wsServer->onMessage = function (TcpConnection $connection, string $data) use ($wsServer): void {
    try {
        $message = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
    } catch (\Throwable) {
        safeSend($connection, ['type' => 'error', 'message' => 'Formato de mensagem inválido.']);
        return;
    }

    $type = $message['type'] ?? null;
    if (! is_string($type) || $type === '') {
        safeSend($connection, ['type' => 'error', 'message' => 'Tipo de mensagem ausente.']);
        return;
    }

    try {
        switch ($type) {
            case 'auth':
                handleAuth($wsServer, $connection, $message);
                return;
            case 'ping':
                touchConnectionActivity($wsServer, $connection);
                safeSend($connection, ['type' => 'pong', 'timestamp' => time()]);
                return;
            case 'pong':
                touchConnectionActivity($wsServer, $connection);
                return;
        }

        if (! ($connection->authenticated ?? false)) {
            safeSend($connection, ['type' => 'auth_error', 'message' => 'Conexão não autenticada.']);
            return;
        }

        touchConnectionActivity($wsServer, $connection);

        switch ($type) {
            case 'message':
                handleChatMessage($wsServer, $connection, $message);
                return;
            case 'typing':
                handleTyping($wsServer, $connection, $message);
                return;
            case 'read':
                handleRead($wsServer, $connection, $message);
                return;
            case 'join_room':
                handleJoinRoom($wsServer, $connection, $message);
                return;
            case 'leave_room':
                handleLeaveRoom($wsServer, $connection, $message);
                return;
            default:
                safeSend($connection, ['type' => 'error', 'message' => 'Tipo de mensagem não suportado.']);
        }
    } catch (\Throwable $e) {
        log_message('error', '[WebSocket] message handling failure: {message}', ['message' => $e->getMessage()]);
        safeSend($connection, ['type' => 'error', 'message' => 'Falha interna no processamento da mensagem.']);
    }
};

$wsServer->onClose = function (TcpConnection $connection) use ($wsServer): void {
    if (isset($connection->authTimeout)) {
        Timer::del($connection->authTimeout);
    }

    $userId = (int) ($connection->userId ?? 0);
    $employeeId = (int) ($connection->employeeId ?? 0);
    $connId = (int) $connection->id;

    if ($userId > 0 && isset($wsServer->connections[$userId][$connId])) {
        unset($wsServer->connections[$userId][$connId]);
        if (empty($wsServer->connections[$userId])) {
            unset($wsServer->connections[$userId]);
            if ($employeeId > 0) {
                updateOnlineStatus($wsServer, $employeeId, 'offline');
            }
            broadcastUserStatus($wsServer, $userId, 'offline');
        }
    }

    unset($wsServer->users[$connId]);
};

$wsServer->onError = function (TcpConnection $connection, int $code, string $msg): void {
    log_message('error', '[WebSocket] connection error {code}: {message}', ['code' => $code, 'message' => $msg]);
};

function handleAuth(Worker $worker, TcpConnection $connection, array $message): void
{
    $connection->authAttempts = ((int) ($connection->authAttempts ?? 0)) + 1;
    if ($connection->authAttempts > 3) {
        safeSend($connection, ['type' => 'auth_error', 'message' => 'Muitas tentativas de autenticação.']);
        $connection->close();
        return;
    }

    $token = (string) ($message['token'] ?? '');
    if ($token === '') {
        safeSend($connection, ['type' => 'auth_error', 'message' => 'Token obrigatório.']);
        return;
    }

    $userData = $worker->authService->validate($token);
    if (! is_array($userData) || (int) ($userData['employee_id'] ?? 0) <= 0) {
        safeSend($connection, ['type' => 'auth_error', 'message' => 'Token inválido ou expirado.']);
        $connection->close();
        return;
    }

    $userId = (int) ($userData['user_id'] ?? $userData['employee_id']);
    $employeeId = (int) ($userData['employee_id'] ?? $userId);
    $connId = (int) $connection->id;

    $connection->userId = $userId;
    $connection->employeeId = $employeeId;
    $connection->authenticated = true;
    $connection->lastActivity = time();
    $connection->rooms = [];

    $worker->connections[$userId] ??= [];
    $worker->connections[$userId][$connId] = $connection;
    $worker->users[$connId] = [
        'user_id' => $userId,
        'employee_id' => $employeeId,
        'authenticated' => true,
        'last_activity' => time(),
        'connection' => $connection,
        'auth_type' => (string) ($userData['auth_type'] ?? 'unknown'),
    ];

    if (isset($connection->authTimeout)) {
        Timer::del($connection->authTimeout);
    }

    updateOnlineStatus($worker, $employeeId, 'online', (string) $connId);
    broadcastUserStatus($worker, $userId, 'online');

    safeSend($connection, [
        'type' => 'auth_success',
        'user_id' => $userId,
        'employee_id' => $employeeId,
        'timestamp' => time(),
    ]);
}

function handleChatMessage(Worker $worker, TcpConnection $connection, array $message): void
{
    $roomId = (int) ($message['room_id'] ?? 0);
    $messageText = trim((string) ($message['message'] ?? ''));
    $replyTo = isset($message['reply_to']) ? (int) $message['reply_to'] : null;

    if ($roomId <= 0 || $messageText === '') {
        safeSend($connection, ['type' => 'error', 'message' => 'room_id e message são obrigatórios.']);
        return;
    }

    ensureRoomAccess($worker, $connection, $roomId);

    $db = getDatabase($worker);
    $db->table('chat_messages')->insert([
        'room_id' => $roomId,
        'sender_id' => (int) $connection->employeeId,
        'message' => $messageText,
        'type' => 'text',
        'reply_to' => $replyTo,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    $messageId = (int) $db->insertID();
    if ($messageId <= 0) {
        safeSend($connection, ['type' => 'error', 'message' => 'Falha ao persistir a mensagem.']);
        return;
    }

    $sender = $db->table('employees')->select('id, name')->where('id', (int) $connection->employeeId)->get()->getRow();
    $payload = [
        'type' => 'message',
        'message_id' => $messageId,
        'room_id' => $roomId,
        'sender_id' => (int) $connection->employeeId,
        'sender_name' => $sender->name ?? 'Usuário',
        'message' => $messageText,
        'reply_to' => $replyTo,
        'timestamp' => date('Y-m-d H:i:s'),
    ];

    broadcastToRoom($worker, $roomId, $payload, null);

    $offlineMembers = getOfflineRoomMembers($worker, $roomId, (int) $connection->employeeId);
    if ($offlineMembers !== []) {
        queuePushNotifications($worker, $offlineMembers, [
            'sender_name' => $sender->name ?? 'Nova mensagem',
            'message' => $messageText,
            'room_id' => $roomId,
        ]);
    }

    safeSend($connection, [
        'type' => 'message_sent',
        'message_id' => $messageId,
        'room_id' => $roomId,
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
}

function handleTyping(Worker $worker, TcpConnection $connection, array $message): void
{
    $roomId = (int) ($message['room_id'] ?? 0);
    if ($roomId <= 0) {
        return;
    }

    ensureRoomAccess($worker, $connection, $roomId);
    $typing = filter_var($message['typing'] ?? false, FILTER_VALIDATE_BOOL);
    $userId = (int) $connection->userId;

    $worker->typing[$roomId] ??= [];
    if ($typing) {
        $worker->typing[$roomId][$userId] = time();
    } else {
        unset($worker->typing[$roomId][$userId]);
    }

    broadcastToRoom($worker, $roomId, [
        'type' => 'typing',
        'room_id' => $roomId,
        'employee_id' => (int) $connection->employeeId,
        'typing' => $typing,
    ], $userId);
}

function handleRead(Worker $worker, TcpConnection $connection, array $message): void
{
    $roomId = (int) ($message['room_id'] ?? 0);
    if ($roomId <= 0) {
        return;
    }

    ensureRoomAccess($worker, $connection, $roomId);

    getDatabase($worker)->table('chat_room_members')
        ->where('room_id', $roomId)
        ->where('employee_id', (int) $connection->employeeId)
        ->update(['last_read_at' => date('Y-m-d H:i:s')]);

    broadcastToRoom($worker, $roomId, [
        'type' => 'read',
        'room_id' => $roomId,
        'employee_id' => (int) $connection->employeeId,
        'timestamp' => date('Y-m-d H:i:s'),
    ], (int) $connection->userId);
}

function handleJoinRoom(Worker $worker, TcpConnection $connection, array $message): void
{
    $roomId = (int) ($message['room_id'] ?? 0);
    if ($roomId <= 0) {
        safeSend($connection, ['type' => 'error', 'message' => 'room_id inválido.']);
        return;
    }

    ensureRoomAccess($worker, $connection, $roomId);
    $connection->rooms[$roomId] = true;
    safeSend($connection, ['type' => 'room_joined', 'room_id' => $roomId]);
}

function handleLeaveRoom(Worker $worker, TcpConnection $connection, array $message): void
{
    $roomId = (int) ($message['room_id'] ?? 0);
    if ($roomId > 0 && isset($connection->rooms[$roomId])) {
        unset($connection->rooms[$roomId]);
    }
    safeSend($connection, ['type' => 'room_left', 'room_id' => $roomId]);
}

function ensureRoomAccess(Worker $worker, TcpConnection $connection, int $roomId): void
{
    $employeeId = (int) ($connection->employeeId ?? 0);
    if ($employeeId <= 0 || ! hasRoomAccess($worker, $employeeId, $roomId)) {
        safeSend($connection, ['type' => 'error', 'message' => 'Acesso à sala negado.']);
        throw new \RuntimeException('Room access denied.');
    }

    if (! isset($connection->rooms[$roomId])) {
        $connection->rooms[$roomId] = true;
    }
}

function hasRoomAccess(Worker $worker, int $employeeId, int $roomId): bool
{
    static $membershipCache = [];
    $cacheKey = $employeeId . ':' . $roomId;
    if (array_key_exists($cacheKey, $membershipCache)) {
        return $membershipCache[$cacheKey];
    }

    $exists = getDatabase($worker)->table('chat_room_members')
        ->select('id')
        ->where('room_id', $roomId)
        ->where('employee_id', $employeeId)
        ->limit(1)
        ->get()
        ->getRow() !== null;

    $membershipCache[$cacheKey] = $exists;
    return $exists;
}

function broadcastToRoom(Worker $worker, int $roomId, array $data, ?int $excludeUserId = null): void
{
    $members = getDatabase($worker)->table('chat_room_members')->select('employee_id')->where('room_id', $roomId)->get()->getResult();
    foreach ($members as $member) {
        $memberId = (int) ($member->employee_id ?? 0);
        if ($memberId <= 0 || ($excludeUserId !== null && $memberId === $excludeUserId)) {
            continue;
        }

        if (! isset($worker->connections[$memberId])) {
            continue;
        }

        foreach ($worker->connections[$memberId] as $conn) {
            if (isset($conn->rooms[$roomId]) || $data['type'] === 'message') {
                safeSend($conn, $data);
            }
        }
    }
}

function getOfflineRoomMembers(Worker $worker, int $roomId, int $excludeEmployeeId): array
{
    $members = getDatabase($worker)->table('chat_room_members')->select('employee_id')->where('room_id', $roomId)->get()->getResult();
    $offline = [];
    foreach ($members as $member) {
        $memberId = (int) ($member->employee_id ?? 0);
        if ($memberId <= 0 || $memberId === $excludeEmployeeId) {
            continue;
        }
        if (! isset($worker->connections[$memberId])) {
            $offline[] = $memberId;
        }
    }
    return $offline;
}

function getDatabase(Worker $worker)
{
    if ($worker->db === null) {
        $worker->db = \Config\Database::connect();
    }
    return $worker->db;
}

function updateOnlineStatus(Worker $worker, int $employeeId, string $status, ?string $connectionId = null): void
{
    $db = getDatabase($worker);
    if ($status === 'online') {
        $existing = $db->table('chat_online_users')
            ->where('employee_id', $employeeId)
            ->where('connection_id', $connectionId)
            ->get()->getRow();

        if ($existing) {
            $db->table('chat_online_users')->where('id', $existing->id)->update([
                'status' => 'online',
                'last_activity' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        $db->table('chat_online_users')->insert([
            'employee_id' => $employeeId,
            'connection_id' => $connectionId,
            'status' => 'online',
            'last_activity' => date('Y-m-d H:i:s'),
        ]);
        return;
    }

    $db->table('chat_online_users')->where('employee_id', $employeeId)->delete();
}

function broadcastUserStatus(Worker $worker, int $userId, string $status): void
{
    $contacts = getDatabase($worker)->table('chat_room_members as m1')
        ->select('m2.employee_id')
        ->join('chat_room_members as m2', 'm1.room_id = m2.room_id')
        ->where('m1.employee_id', $userId)
        ->where('m2.employee_id !=', $userId)
        ->distinct()->get()->getResult();

    $payload = ['type' => 'user_status', 'user_id' => $userId, 'status' => $status, 'timestamp' => time()];
    foreach ($contacts as $contact) {
        $contactId = (int) ($contact->employee_id ?? 0);
        if ($contactId <= 0 || ! isset($worker->connections[$contactId])) {
            continue;
        }
        foreach ($worker->connections[$contactId] as $conn) {
            safeSend($conn, $payload);
        }
    }
}

function queuePushNotifications(Worker $worker, array $userIds, array $data): void
{
    foreach (array_unique($userIds) as $userId) {
        try {
            $worker->pushService->sendChatMessage((int) $userId, (string) ($data['sender_name'] ?? 'Nova mensagem'), (string) ($data['message'] ?? ''), (int) ($data['room_id'] ?? 0));
        } catch (\Throwable $e) {
            log_message('error', '[WebSocket] push delivery failure: {message}', ['message' => $e->getMessage()]);
        }
    }
}

function touchConnectionActivity(Worker $worker, TcpConnection $connection): void
{
    $connection->lastActivity = time();
    $connId = (int) $connection->id;
    if (isset($worker->users[$connId])) {
        $worker->users[$connId]['last_activity'] = $connection->lastActivity;
    }
}

function safeSend(TcpConnection $connection, array $payload): void
{
    try {
        $connection->send(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    } catch (\Throwable) {
    }
}

function isOriginAllowed(string $origin): bool
{
    $origin = trim($origin);
    if ($origin === '') {
        return false;
    }

    $configured = trim((string) env('WEBSOCKET_ALLOWED_ORIGINS', ''));
    if ($configured === '') {
        $baseUrl = rtrim((string) env('app.baseURL', ''), '/');
        return $baseUrl !== '' && stripos($origin, $baseUrl) === 0;
    }

    $allowedOrigins = array_values(array_filter(array_map('trim', explode(',', $configured))));
    foreach ($allowedOrigins as $allowedOrigin) {
        if (strcasecmp($allowedOrigin, $origin) === 0) {
            return true;
        }
    }

    return false;
}

Worker::runAll();
