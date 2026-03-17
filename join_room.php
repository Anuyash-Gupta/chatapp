<?php
// =============================================================
//  join_room.php
//  Validates that a room code exists in the database.
//  Called when a user enters a code to join someone's room.
//
//  Method : POST
//  Body   : { "room_code": "AB3X9K" }
//  Returns: JSON  { ok: true }
//              or { ok: false, error: "Room not found" }
// =============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

require_once 'config.php';

// -----------------------------------------------------------------
// Read + validate input
// -----------------------------------------------------------------
$body      = json_decode(file_get_contents('php://input'), true);
$room_code = strtoupper(trim($body['room_code'] ?? ''));

if (strlen($room_code) !== 6) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Room code must be 6 characters.']);
    exit;
}

// -----------------------------------------------------------------
// Check DB
// -----------------------------------------------------------------
try {
    $pdo  = get_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM rooms WHERE room_code = ?');
    $stmt->execute([$room_code]);
    $found = (int) $stmt->fetchColumn();

    if ($found === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Room not found. Check the code and try again.']);
    } else {
        echo json_encode(['ok' => true]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error.']);
}
