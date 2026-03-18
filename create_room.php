<?php
// =============================================================
//  create_room.php — Creates a new room, returns join code
//  Method: POST
// =============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

require_once 'config.php';

function generate_room_code(PDO $pdo): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $len   = strlen($chars);
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $chars[random_int(0, $len - 1)];
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM rooms WHERE room_code = ?');
        $stmt->execute([$code]);
    } while ((int)$stmt->fetchColumn() > 0);
    return $code;
}

try {
    $pdo = get_db();

    // Clean up rooms older than 7 days
    $pdo->prepare("DELETE FROM rooms WHERE created_at < NOW() - INTERVAL 7 DAY")
        ->execute();

    $code = generate_room_code($pdo);

    // user_count starts at 1 (the creator)
    $pdo->prepare('INSERT INTO rooms (room_code, user_count) VALUES (?, 1)')
        ->execute([$code]);

    echo json_encode(['ok' => true, 'room_code' => $code]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not create room.']);
}
