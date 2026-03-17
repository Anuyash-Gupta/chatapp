<?php
// =============================================================
//  fetch_messages.php
//  Returns messages for a room, optionally only those AFTER
//  a given message ID (for efficient polling — only fetch new ones).
//
//  Method : GET
//  Params : room_code=AB3X9K   (required)
//           after_id=0         (optional, default 0 = fetch all)
//
//  Returns: JSON {
//               ok: true,
//               messages: [
//                 { id, username, message, sent_at },
//                 ...
//               ]
//           }
// =============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

// -----------------------------------------------------------------
// Read + validate inputs
// -----------------------------------------------------------------
$room_code = strtoupper(trim($_GET['room_code'] ?? ''));
$after_id  = max(0, (int) ($_GET['after_id'] ?? 0)); // must be >= 0

if (!$room_code) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'room_code is required.']);
    exit;
}

// -----------------------------------------------------------------
// Fetch messages
// -----------------------------------------------------------------
try {
    $pdo = get_db();

    // Select messages in this room with id > after_id.
    // This means the frontend only downloads NEW messages each poll cycle
    // instead of re-downloading the whole history every second.
    // Fetch up to 100 new messages (only those with id > after_id)
    $sql  = 'SELECT id, username, message, sent_at
             FROM   messages
             WHERE  room_code = :room_code
               AND  id > :after_id
             ORDER  BY id ASC
             LIMIT  100';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':room_code' => $room_code,
        ':after_id'  => $after_id,
    ]);

    $rows = $stmt->fetchAll();   // returns [] if none found

    echo json_encode(['ok' => true, 'messages' => $rows]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not fetch messages.']);
}
