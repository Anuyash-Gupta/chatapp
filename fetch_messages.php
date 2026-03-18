<?php
// =============================================================
//  fetch_messages.php (v7 — simplified image handling)
//
//  Images are NO LONGER burned after viewing.
//  They stay in the DB and are visible to both users for the
//  entire session. They get deleted automatically when both
//  users leave the room (room row deleted → CASCADE wipes all
//  messages including images).
//
//  This file now simply:
//    1. Marks messages as seen (when tab is active)
//    2. Returns new messages
//    3. Returns seen_updates for the sender
//    4. Returns who is typing
// =============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

$room_code  = strtoupper(trim($_GET['room_code']  ?? ''));
$after_id   = max(0, (int)($_GET['after_id']      ?? 0));
$username   = trim($_GET['username']               ?? '');
$tab_active = (int)($_GET['tab_active']            ?? 0);

if (!$room_code) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'room_code is required.']);
    exit;
}

try {
    $pdo = get_db();

    // ── 1. Mark messages as seen (only when tab is visible) ───
    if ($username && $tab_active === 1) {
        $pdo->prepare(
            'UPDATE messages
             SET    seen_at = NOW(3)
             WHERE  room_code = ?
               AND  username  != ?
               AND  seen_at   IS NULL'
        )->execute([$room_code, $username]);
    }

    // ── 2. Fetch new messages ──────────────────────────────────
    $stmt = $pdo->prepare(
        'SELECT id, username, message, msg_type, image_data, seen_at, sent_at
         FROM   messages
         WHERE  room_code = :room_code
           AND  id > :after_id
         ORDER  BY id ASC
         LIMIT  100'
    );
    $stmt->execute([':room_code' => $room_code, ':after_id' => $after_id]);
    $rows = $stmt->fetchAll();

    // ── 3. Return seen_at for sender's own messages ────────────
    $seenUpdates = [];
    if ($username) {
        $seenStmt = $pdo->prepare(
            'SELECT id, seen_at FROM messages
             WHERE  room_code = ?
               AND  username  = ?
               AND  seen_at   IS NOT NULL'
        );
        $seenStmt->execute([$room_code, $username]);
        $seenUpdates = $seenStmt->fetchAll();
    }

    // ── 4. Who is typing ──────────────────────────────────────
    $typingArr = [];
    if ($username) {
        $typingStmt = $pdo->prepare(
            'SELECT username FROM room_users
             WHERE  room_code    = ?
               AND  username    != ?
               AND  is_typing    = 1
               AND  typing_since >= NOW() - INTERVAL 6 SECOND'
        );
        $typingStmt->execute([$room_code, $username]);
        $typingArr = $typingStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    echo json_encode([
        'ok'           => true,
        'messages'     => array_values($rows),
        'seen_updates' => $seenUpdates,
        'typing'       => $typingArr,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not fetch messages.']);
}
