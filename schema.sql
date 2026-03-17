-- =============================================================
--  ChatApp — MySQL Database Schema
--  Run this file once to set up the database.
--
--  Steps:
--    1. Open phpMyAdmin (or MySQL CLI)
--    2. Create a database named: chatapp
--    3. Run this SQL file
-- =============================================================

CREATE DATABASE IF NOT EXISTS chatapp
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE chatapp;

-- -----------------------------------------------------------
-- Table: rooms
-- Stores each chat room with its unique join code
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS rooms (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    room_code   VARCHAR(8)   NOT NULL UNIQUE,   -- e.g. "AB3X9K"
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Table: messages
-- Stores every chat message linked to a room
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    room_code   VARCHAR(8)   NOT NULL,
    username    VARCHAR(32)  NOT NULL,
    message     TEXT         NOT NULL,
    sent_at     DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                                                -- millisecond precision
    FOREIGN KEY (room_code) REFERENCES rooms(room_code)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index for fast polling (most common query: "give me new messages in room X")
CREATE INDEX idx_messages_room_sent ON messages (room_code, sent_at);
