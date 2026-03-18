-- ================================================================
--  ChatApp — COMPLETE DATABASE SCHEMA (Final)
--  Run this ONE file in phpMyAdmin to set up everything from scratch.
--
--  HOW TO RUN:
--    1. Open phpMyAdmin
--    2. Click on your database (e.g. if0_41417023_CHAT)
--    3. Click the SQL tab
--    4. Paste this entire file and click Go
-- ================================================================


-- ----------------------------------------------------------------
--  TABLE 1: rooms
--  One row per chat room.
--  Created when a user clicks "Create a new room".
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rooms (

    -- Auto-incrementing internal ID (not shown to users)
    id         INT          AUTO_INCREMENT PRIMARY KEY,

    -- The 6-character code users share to join e.g. "AB3X9K"
    -- UNIQUE ensures no two rooms ever get the same code
    room_code  VARCHAR(8)   NOT NULL UNIQUE,

    -- Tracks how many users are currently active in this room
    -- Updated by heartbeat.php every 5 seconds
    -- Used by join_room.php to enforce the 2-user limit
    user_count TINYINT      NOT NULL DEFAULT 0,

    -- When the room was created
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------
--  TABLE 2: messages
--  One row per message (text or image) sent in any room.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (

    -- Auto-incrementing ID — also used as the polling cursor
    -- Frontend passes after_id=47 to get only messages with id > 47
    id         INT          AUTO_INCREMENT PRIMARY KEY,

    -- Which room this message belongs to
    -- ON DELETE CASCADE: if the room is deleted, all its messages are deleted too
    room_code  VARCHAR(8)   NOT NULL,

    -- Who sent the message
    username   VARCHAR(32)  NOT NULL,

    -- Message text content (empty string for image messages)
    message    TEXT         NOT NULL DEFAULT '',

    -- 'text' = normal chat message
    -- 'image' = image message (image_data has the base64)
    msg_type   VARCHAR(10)  NOT NULL DEFAULT 'text',

    -- Base64-encoded image data (only for msg_type = 'image')
    -- NULL for text messages
    -- Set to NULL after receiver views it (burn-after-read)
    -- MEDIUMTEXT can hold up to 16MB — enough for a compressed image
    image_data MEDIUMTEXT   NULL DEFAULT NULL,

    -- 0 = image not yet seen by receiver
    -- 1 = receiver has seen it, image_data has been wiped from DB
    -- Always 0 for text messages (not used)
    viewed     TINYINT(1)   NOT NULL DEFAULT 0,

    -- When the message was sent (millisecond precision for correct ordering)
    sent_at    DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    -- Foreign key links every message to a room
    FOREIGN KEY (room_code) REFERENCES rooms(room_code) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index speeds up the most common query:
-- "give me all messages in room X with id greater than Y"
CREATE INDEX IF NOT EXISTS idx_messages_poll
    ON messages (room_code, id);


-- ----------------------------------------------------------------
--  TABLE 3: room_users
--  Tracks who is actively online in each room right now.
--  Each user gets one row per room. last_seen is updated every 5s.
--  If last_seen is older than 15 seconds, user is considered gone.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS room_users (

    -- Combined primary key: one row per user per room
    room_code  VARCHAR(8)   NOT NULL,
    username   VARCHAR(32)  NOT NULL,

    -- Updated every 5 seconds by heartbeat.php
    -- Checked by join_room.php: if last_seen < NOW() - 15s, slot is free
    last_seen  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (room_code, username),

    -- If the room is deleted, remove all its user tracking rows too
    FOREIGN KEY (room_code) REFERENCES rooms(room_code) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
--  HOW THE 3 TABLES WORK TOGETHER
-- ================================================================
--
--  rooms  ←──────────────────────────────────────────────┐
--    id, room_code, user_count, created_at               │
--                                                        │ room_code (FK)
--  messages                                              │
--    id, room_code*, username, message,                  │
--    msg_type, image_data, viewed, sent_at               │
--                                                        │ room_code (FK)
--  room_users                                            │
--    room_code*, username, last_seen  ───────────────────┘
--
--  * = foreign key referencing rooms.room_code
--
-- ================================================================


-- ================================================================
--  QUICK DATA FLOW REFERENCE
-- ================================================================
--
--  CREATE ROOM   → INSERT into rooms (user_count = 1)
--
--  JOIN ROOM     → SELECT COUNT from room_users WHERE last_seen > NOW()-15s
--                  If count < 2: allow join
--                  If count >= 2: reject "Room is full"
--
--  HEARTBEAT     → UPSERT into room_users (update last_seen = NOW())
--                  UPDATE rooms SET user_count = active count
--
--  SEND TEXT     → INSERT into messages (msg_type='text', image_data=NULL)
--
--  SEND IMAGE    → INSERT into messages (msg_type='image', image_data=base64)
--                  image is compressed to <1MB in browser before sending
--
--  FETCH MSGS    → SELECT from messages WHERE room_code=? AND id > after_id
--                  For image msgs sent by OTHER user with viewed=0:
--                    → Return image_data to receiver (they see it once)
--                    → UPDATE: set viewed=1, image_data=NULL  (burn!)
--
--  LEAVE ROOM    → DELETE from room_users WHERE room_code=? AND username=?
--                  UPDATE rooms SET user_count = new count
--
-- ================================================================
