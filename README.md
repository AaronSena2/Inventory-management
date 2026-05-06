# IT Inventory Management System

A full-stack **Computer Inventory Tracking and Remote Management** web application built with PHP (MVC, no framework), MySQL, Bootstrap 5, and a Python agent.

---

## Table of Contents

1. [Features](#features)
2. [Prerequisites](#prerequisites)
3. [Directory Structure](#directory-structure)
4. [Database Setup](#database-setup)
5. [Configuration](#configuration)
6. [Running Locally](#running-locally)
7. [Apache / Nginx VHost](#apache--nginx-vhost)
8. [Default Credentials](#default-credentials)
9. [Agent Setup](#agent-setup)
10. [REST API Reference](#rest-api-reference)
11. [Security Hardening](#security-hardening)

---

## Features

- **Computer inventory** – automatic registration via agent, hardware/OS details, status tracking
- **Software inventory** – full installed-software list pushed on every heartbeat
- **Remote commands** – send patch, install, uninstall, shell, restart, shutdown commands
- **Command scheduling** – queue commands to run at a future date/time
- **Role-based access** – admin (full) vs viewer (read-only) accounts
- **Live dashboard** – online/offline stats, auto-refreshing every 30 s
- **REST API** – agent communication + admin API with JSON responses
- **Python agent** – cross-platform (Windows, Linux, macOS), auto-registers, executes commands

---

## Prerequisites

| Component | Minimum Version |
|-----------|----------------|
| PHP       | 8.0+           |
| MySQL     | 8.0+           |
| Python    | 3.9+           |
| Apache    | 2.4+ (optional)|
| Nginx     | 1.18+ (optional)|

PHP extensions required: `pdo`, `pdo_mysql`, `json`, `session`, `mbstring`

---

## Directory Structure

```
├── agent/python/          Python monitoring agent
├── app/
│   ├── controllers/       Web + API controllers
│   ├── core/              Router, DB singleton, Auth, Base controller
│   ├── models/            PDO-based models
│   └── views/             PHP/HTML views
├── config/                config.php (DB credentials, constants)
├── database/              schema.sql
└── public/                Web root (index.php, .htaccess, assets)
```

---

## Database Setup

```bash
# 1. Create the database and import schema
mysql -u root -p < database/schema.sql
```

This creates the `inventory_db` database with all tables and a default admin user.

---

## Configuration

```bash
cp config/config.example.php config/config.php
```

Edit `config/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'inventory_db');
define('DB_USER', 'your_mysql_user');
define('DB_PASS', 'your_mysql_password');
define('APP_URL', 'http://yourdomain.com');
```

You can also use environment variables instead of editing the file:

```bash
export DB_USER=inventory_user
export DB_PASS=supersecret
```

---

## Running Locally

```bash
# PHP built-in server (development only)
cd /path/to/repo
php -S 0.0.0.0:8000 -t public/

# Then visit:
# http://localhost:8000
```

---

## Apache / Nginx VHost

### Apache

```apache
<VirtualHost *:80>
    ServerName inventory.example.com
    DocumentRoot /path/to/repo/public

    <Directory /path/to/repo/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/inventory-error.log
    CustomLog ${APACHE_LOG_DIR}/inventory-access.log combined
</VirtualHost>
```

Ensure `mod_rewrite` is enabled: `a2enmod rewrite`

### Nginx

```nginx
server {
    listen 80;
    server_name inventory.example.com;
    root /path/to/repo/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass  unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include       fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

---

## Default Credentials

| Field    | Value      |
|----------|-----------|
| Username | `admin`   |
| Password | `password`|

**Change the password immediately after first login** by updating the `password_hash` in the `users` table:

```php
<?php
echo password_hash('your_new_password', PASSWORD_BCRYPT);
```

```sql
UPDATE users SET password_hash = '<output_from_above>' WHERE username = 'admin';
```

---

## Agent Setup

```bash
cd agent/python

# 1. Install dependencies
pip install -r requirements.txt

# 2. Configure
cp config.ini.example config.ini
# Edit config.ini – set server.url to your web app URL

# 3. Run (first run will auto-register and save the token)
python agent.py

# 4. Run as a background service (Linux systemd example)
```

### Linux systemd service

```ini
# /etc/systemd/system/inv-agent.service
[Unit]
Description=IT Inventory Agent
After=network-online.target

[Service]
Type=simple
User=nobody
WorkingDirectory=/opt/inv-agent
ExecStart=/usr/bin/python3 /opt/inv-agent/agent.py
Restart=on-failure
RestartSec=30

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable --now inv-agent
```

---

## REST API Reference

All agent API endpoints accept/return `application/json`.

### Register Agent

```bash
POST /api/agent/register
Content-Type: application/json

{
  "hostname": "workstation-01",
  "ip_address": "192.168.1.10",
  "mac_address": "aa:bb:cc:dd:ee:ff",
  "os_name": "Linux",
  "os_version": "Ubuntu 22.04",
  "os_arch": "x86_64",
  "cpu_info": "Intel Core i7-12700",
  "ram_gb": 16.0,
  "storage_gb": 512.0
}
```

**Response:**
```json
{ "token": "<plaintext_token>", "computer_id": 1, "message": "Registered successfully" }
```

Store the `token` in `config.ini` — it is shown **once only**.

---

### Heartbeat

```bash
POST /api/agent/heartbeat
X-Agent-Token: <token>
Content-Type: application/json

{
  "hostname": "workstation-01",
  "ip_address": "192.168.1.10",
  "ram_gb": 15.7,
  "software": [
    {"name": "curl", "version": "7.88", "publisher": "haxx.se", "install_date": null}
  ]
}
```

---

### Poll Commands

```bash
GET /api/agent/commands
X-Agent-Token: <token>
```

**Response:**
```json
{
  "commands": [
    {
      "id": 42,
      "command_type": "install",
      "payload": {"package": "htop"},
      "scheduled_at": null
    }
  ]
}
```

---

### Post Command Result

```bash
POST /api/agent/command-result
X-Agent-Token: <token>
Content-Type: application/json

{
  "command_id": 42,
  "exit_code": 0,
  "output": "htop installed successfully",
  "error_output": "",
  "executed_at": "2024-01-15 14:30:00"
}
```

---

### Admin API – List Computers

```bash
GET /api/computers
# Requires active admin session cookie, OR:
X-Admin-Token: <ADMIN_API_TOKEN env var>
```

---

### Admin API – Create Command

```bash
POST /api/commands
# Requires active admin session cookie
Content-Type: application/json

{
  "computer_id": 1,
  "command_type": "shell",
  "payload": {"command": "df -h"},
  "scheduled_at": null
}
```

---

## Security Hardening

1. **Change default password** immediately (see [Default Credentials](#default-credentials))
2. **Use HTTPS** in production – set `server.verify_ssl = true` in agent config
3. **Restrict web root** – only `public/` should be accessible via the web server
4. **Database user** – create a dedicated MySQL user with minimal privileges:
   ```sql
   CREATE USER 'inv_user'@'localhost' IDENTIFIED BY 'strong_password';
   GRANT SELECT, INSERT, UPDATE, DELETE ON inventory_db.* TO 'inv_user'@'localhost';
   FLUSH PRIVILEGES;
   ```
5. **Session security** – `SESSION_LIFETIME` defaults to 1 hour; reduce for sensitive environments
6. **CSRF protection** – all web POST forms include CSRF tokens (validated server-side)
7. **Agent tokens** – stored as SHA-256 hashes in the database; plaintext is never stored
8. **Firewall** – limit port 8000/80/443 access; agents connect outbound only
9. **Log rotation** – rotate `agent.log` with logrotate to prevent disk fill
10. **Prepared statements** – all database queries use PDO prepared statements to prevent SQL injection
