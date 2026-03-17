<?php
// =============================================================
//  create_room.php
//  Creates a new chat room and returns a unique 6-char code.
//
//  Method : POST
//  Returns: JSON  { ok: true, room_code: "AB3X9K" }
//              or { ok: false, error: "..." }
// =============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

require_once 'config.php';

// -----------------------------------------------------------------
// Generate a unique alphanumeric room code (6 characters).
// We loop until we find one that doesn't already exist in the DB.
// -----------------------------------------------------------------
function generate_room_code(PDO $pdo): string {
    // Characters that look distinct (no 0/O, 1/I confusion)
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $len   = strlen($chars);

    do {
        // Build a 6-char code
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, $len - 1)];
        }

        // Check if this code already exists
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM rooms WHERE room_code = ?');
        $stmt->execute([$code]);
        $exists = (int) $stmt->fetchColumn();

    } while ($exists > 0);   // repeat if collision (extremely rare)

    return $code;
}

// -----------------------------------------------------------------
// Main logic
// -----------------------------------------------------------------
try {
    $pdo  = get_db();
    $code = generate_room_code($pdo);

    // Insert the new room
    $stmt = $pdo->prepare('INSERT INTO rooms (room_code) VALUES (?)');
    $stmt->execute([$code]);

    echo json_encode(['ok' => true, 'room_code' => $code]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not create room.']);
}
