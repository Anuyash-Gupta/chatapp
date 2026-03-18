# chatapp
Simple PHP MySQL chat app
# ChatApp — Simple PHP + MySQL Chat

A minimal real-time chat app. Two users share a **room code** and chat instantly.
No login, no frameworks, no build step.

---

## Files

```
chatapp/
├── index.html          ← All frontend (HTML + CSS + JavaScript)
├── config.php          ← Database credentials (edit this first!)
├── schema.sql          ← Run once to create the database tables
├── create_room.php     ← API: creates a room, returns join code
├── join_room.php       ← API: validates a room code
├── send_message.php    ← API: saves a message to the DB
├── fetch_messages.php  ← API: returns new messages (polled every 1.5s)
└── README.md
```

---

## Setup (XAMPP — recommended for beginners)

### Step 1 — Install XAMPP
Download from https://www.apachefriends.org and install.

### Step 2 — Copy project files
Put the entire `chatapp/` folder inside XAMPP's web root:
```
C:\xampp\htdocs\chatapp\        (Windows)
/Applications/MAMP/htdocs/chatapp/  (Mac with MAMP)
```

### Step 3 — Start servers
Open the XAMPP Control Panel and click **Start** next to:
- **Apache**
- **MySQL**

### Step 4 — Create the database
1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click **New** in the left sidebar
3. Name the database `chatapp` and click **Create**
4. Click the `chatapp` database, then click the **SQL** tab
5. Paste the contents of `schema.sql` and click **Go**

### Step 5 — Configure the database connection
Open `config.php` and update the credentials if needed:
```php
define('DB_USER', 'root');   // your MySQL username
define('DB_PASS', '');       // your MySQL password (blank by default in XAMPP)
define('DB_NAME', 'chatapp');
```

### Step 6 — Open the app
Go to: **http://localhost/chatapp/index.html**

To test with two users, open it in **two different browser tabs** (or two browsers).

---

## How it works

| What happens                  | Which file handles it        |
|-------------------------------|------------------------------|
| User clicks "Create a room"   | `create_room.php`            |
| User clicks "Join room"       | `join_room.php`              |
| User sends a message          | `send_message.php`           |
| New messages appear every 1.5s| `fetch_messages.php` (AJAX)  |
| All UI / logic                | `index.html`                 |
| DB settings                   | `config.php`                 |

### Message polling explained
Instead of WebSockets, the frontend calls `fetch_messages.php` every **1.5 seconds**
passing `after_id` — the highest message ID it has already seen.
The server returns only messages newer than that ID, so polling is efficient
even with many messages in the room.

---

## Running via VS Code + PHP built-in server (no XAMPP needed for Apache)

You still need MySQL running. Then in VS Code terminal:
```bash
cd chatapp
php -S localhost:8000
```
Open: http://localhost:8000

> Note: You must have a MySQL server running separately (e.g. via XAMPP's MySQL,
> or `brew services start mysql` on Mac).

---

## Troubleshooting

| Problem                          | Fix                                              |
|----------------------------------|--------------------------------------------------|
| "Database connection failed"     | Check `config.php` credentials                  |
| "Room not found"                 | Make sure you typed the 6-char code exactly      |
| Messages not appearing           | Check browser console for network errors         |
| Blank page                       | Check Apache error log or PHP error display      |

To enable PHP error display (dev only), add to top of `config.php`:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```
