<?php
// =============================================================
//  send_message.php
//  Saves a new chat message to the database.
//
//  Method : POST
//  Body   : { "room_code": "AB3X9K", "username": "Alice", "message": "Hello!" }
//  Returns: JSON  { ok: true, message_id: 42 }
//              or { ok: false, error: "..." }
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
// Read and sanitize inputs
// -----------------------------------------------------------------
$body      = json_decode(file_get_contents('php://input'), true);
$room_code = strtoupper(trim($body['room_code'] ?? ''));
$username  = trim($body['username']  ?? '');
$message   = trim($body['message']   ?? '');

// Basic validation
if (!$room_code || !$username || !$message) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'room_code, username, and message are all required.']);
    exit;
}

// Enforce max lengths
$username = mb_substr($username, 0, 32);
$message  = mb_substr($message,  0, 2000);

// -----------------------------------------------------------------
// Make sure the room exists before inserting a message
// -----------------------------------------------------------------
try {
    $pdo = get_db();

    $chk = $pdo->prepare('SELECT COUNT(*) FROM rooms WHERE room_code = ?');
    $chk->execute([$room_code]);
    if ((int) $chk->fetchColumn() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Room not found.']);
        exit;
    }

    // Insert the message
    // CURRENT_TIMESTAMP(3) gives millisecond precision so ordering is correct
    $ins = $pdo->prepare(
        'INSERT INTO messages (room_code, username, message) VALUES (?, ?, ?)'
    );
    $ins->execute([$room_code, $username, $message]);

    $new_id = (int) $pdo->lastInsertId();

    echo json_encode(['ok' => true, 'message_id' => $new_id]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save message.']);
}
