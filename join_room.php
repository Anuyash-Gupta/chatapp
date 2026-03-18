<?php
// =============================================================
//  join_room.php (v3)
//  Checks room exists and counts only ACTIVE users (seen in
//  last 15 seconds) — so a refreshed/closed tab frees the slot.
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

if (strlen($room_code) !== 6) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Room code must be 6 characters.']);
    exit;
}

try {
    $pdo = get_db();

    // Check room exists
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM rooms WHERE room_code = ?');
    $stmt->execute([$room_code]);
    if ((int) $stmt->fetchColumn() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Room not found. Check the code and try again.']);
        exit;
    }

    // Count only ACTIVE users (heartbeat in last 15 seconds)
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM room_users
         WHERE room_code = ? AND last_seen >= NOW() - INTERVAL 15 SECOND'
    );
    $stmt->execute([$room_code]);
    $active = (int) $stmt->fetchColumn();

    if ($active >= 2) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Room is full. Only 2 users allowed per room.']);
        exit;
    }

    echo json_encode(['ok' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error.']);
}
