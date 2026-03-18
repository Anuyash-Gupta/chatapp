<?php
// =============================================================
//  send_message.php (v4 — fixed)
//  Accepts text OR image messages.
//  Image key is "image" (matches what JS sends).
//  Limit raised to 10MB.
// =============================================================

// Raise PHP limits for large image payloads (10MB)
ini_set('post_max_size',       '15M');
ini_set('upload_max_filesize', '15M');
ini_set('memory_limit',        '64M');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

require_once 'config.php';

// Read raw JSON body
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body.']);
    exit;
}

$room_code  = strtoupper(trim($body['room_code'] ?? ''));
$username   = trim($body['username']  ?? '');
$msg_type   = trim($body['msg_type']  ?? 'text');
$message    = trim($body['message']   ?? '');
$image_data = $body['image'] ?? null;   // JS sends key "image"

// ── Validate ────────────────────────────────────────────────
if (!$room_code || !$username) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'room_code and username are required.']);
    exit;
}

if ($msg_type === 'text' && !$message) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'message is required for text type.']);
    exit;
}

if ($msg_type === 'image') {
    if (!$image_data) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'image data is required for image type.']);
        exit;
    }

    // Must be a valid base64 image data URL
    if (!preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64,/i', $image_data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid image. Use JPG, PNG, GIF or WEBP.']);
        exit;
    }

    // After browser compression, images should be well under 2MB.
    // 2MB compressed image = ~2.7MB base64 string — safe for shared hosting
    if (strlen($image_data) > 3 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Compressed image still too large. Try a simpler image.']);
        exit;
    }
}

$username = mb_substr($username, 0, 32);
$message  = mb_substr($message,  0, 2000);

// ── Save to database ─────────────────────────────────────────
try {
    $pdo = get_db();

    // Verify room exists
    $chk = $pdo->prepare('SELECT COUNT(*) FROM rooms WHERE room_code = ?');
    $chk->execute([$room_code]);
    if ((int) $chk->fetchColumn() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Room not found.']);
        exit;
    }

    if ($msg_type === 'image') {
        // Store image — viewed=0, deleted from DB after receiver sees it
        $ins = $pdo->prepare(
            'INSERT INTO messages (room_code, username, message, msg_type, image_data, viewed)
             VALUES (?, ?, ?, "image", ?, 0)'
        );
        $ins->execute([$room_code, $username, '', $image_data]);
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO messages (room_code, username, message, msg_type, image_data, viewed)
             VALUES (?, ?, ?, "text", NULL, 0)'
        );
        $ins->execute([$room_code, $username, $message]);
    }

    $new_id = (int) $pdo->lastInsertId();

    // Fetch the just-inserted row so we can return sent_at (set by DB)
    $row = $pdo->prepare('SELECT id, username, message, msg_type, image_data, viewed, seen_at, sent_at FROM messages WHERE id = ?');
    $row->execute([$new_id]);
    $new_msg = $row->fetch();

    echo json_encode(['ok' => true, 'message_id' => $new_id, 'message' => $new_msg]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save message: ' . $e->getMessage()]);
}
