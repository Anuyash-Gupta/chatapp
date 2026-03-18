<?php
// =============================================================
//  heartbeat.php (v4)
//  - Keeps user slot alive
//  - Accepts is_typing flag to show typing indicator
//  - Returns active users list
// =============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

require_once 'config.php';

$body      = json_decode(file_get_contents('php://input'), true);
$room_code = strtoupper(trim($body['room_code'] ?? ''));
$username  = trim($body['username']  ?? '');
$leaving   = !empty($body['leaving']);
$is_typing = !empty($body['is_typing']) ? 1 : 0;

if (!$room_code || !$username) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'room_code and username required.']);
    exit;
}

try {
    $pdo = get_db();

    if ($leaving) {
        $pdo->prepare(
            'DELETE FROM room_users WHERE room_code = ? AND username = ?'
        )->execute([$room_code, $username]);

    } else {
        // Upsert: update last_seen AND typing status
        $pdo->prepare(
            'INSERT INTO room_users (room_code, username, last_seen, is_typing, typing_since)
             VALUES (?, ?, NOW(), ?, IF(? = 1, NOW(), NULL))
             ON DUPLICATE KEY UPDATE
               last_seen    = NOW(),
               is_typing    = VALUES(is_typing),
               typing_since = IF(VALUES(is_typing) = 1, NOW(), NULL)'
        )->execute([$room_code, $username, $is_typing, $is_typing]);
    }

    // Get active users list
    $stmt = $pdo->prepare(
        'SELECT username FROM room_users
         WHERE room_code = ? AND last_seen >= NOW() - INTERVAL 15 SECOND
         ORDER BY last_seen ASC'
    );
    $stmt->execute([$room_code]);
    $activeUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $activeCount = count($activeUsers);

    // Sync rooms.user_count
    $pdo->prepare('UPDATE rooms SET user_count = ? WHERE room_code = ?')
        ->execute([$activeCount, $room_code]);

    // If nobody is left in the room, delete it.
    // ON DELETE CASCADE wipes all messages (including images) automatically.
    if ($activeCount === 0) {
        $pdo->prepare('DELETE FROM rooms WHERE room_code = ?')
            ->execute([$room_code]);
    }

    echo json_encode([
        'ok'           => true,
        'active_users' => $activeUsers,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Heartbeat failed.']);
}
