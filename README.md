<p align="center"><a href="https://azelya.dev" target="_blank"><img src="https://azelya.dev/Logo_Graphics_white.webp" width="400" alt="Azelya Design Logo"></a></p>

[![License](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)

# SmsGateway


A Laravel 13 REST API for SMS gateway management with OAuth2 authentication, role-based access control, and real-time broadcasting.

Send SMS messages with no external services required or 3rd party integration — designed for devices (Android phones, IoT modems, etc.) to receive the broadcast via WebSocket events on device-type-specific channels, generate the SMS, and report back delivery status.

Receive SMS replies from the phone and store them in the database for retrieval via API. Accepted replies yes/no/1/2/3.

### Usage Destinations
> Small to medium businesses, developers, and hobbyists who want to send SMS messages programmatically without relying on external services. Ideal for OTP codes, reservation systems with the possibility of confirmation, reminders.

To terminate the SMS messages you need a device app (Android, IoT gateway, etc.) compatible with this API. The app listens for broadcast events on its device-type channel (`private-sms.android.{userId}`, `private-sms.iot.{userId}`, etc.), sends the SMS, and reports back the status.

This app is capable of terminating sms messages requests from the gateway [Sms Gateway Client - Android](https://github.com/azelyadsign/Sms-Gateway-Client-Android).

### 📜 Disclaimer
> Use responsibly and comply with local laws regarding SMS messaging. **Do not abuse the service for spam** or unsolicited messages. The author is not responsible for any misuse of this software.
> Charges for SMS delivery may be applied by your mobile operator. You must provide your own device capable of sending SMS messages.

## Tech Stack

- **PHP 8.4** · **Laravel 13** · **SQLite**
- **Laravel Passport** - OAuth2 server (`auth:api` guard)
- **Laravel Reverb** - WebSocket broadcasting
- **Spatie Laravel Permission** - role & permission system
- **Dedoc Scramble** - auto-generated OpenAPI docs



## Features

- **OAuth2 Authentication** - Personal Access Tokens (12-month expiry) for all clients
- **Role-Based Access** - Admin, Client, and AppClient roles with granular permissions
- **SMS Sending** - throttled at 30/min, dispatched to devices via Reverb broadcast on device-type-specific channels (`sms.android.{userId}`, `sms.iot.{userId}`, etc.), with automatic retry (3 attempts) and status tracking. Optionally specify `device_type` to target a specific device.
- **User Approval Flow** - new users are pending until an Admin approves them
- **Device API** - static device-token auth for broadcasting, SMS replies, and status updates. Each device type (Android, IoT) subscribes to its own channel.
- **Real-Time Broadcasting** - Laravel Reverb for pusher-compatible WebSocket events
- **Browser Auth UI**

## Roles & Permissions

| Role | Permissions | How Assigned |
|------|------------|--------------|
| **Admin** | `send-sms`, `approve-users` | Seeded via `php artisan db:seed` |
| **Client** | `send-sms` | Admin approves user via API |
| **AppClient** | `send-sms` | Auto-assigned on app registration |

- New users register with **no role** - must be approved by an Admin before they can send SMS.
- All roles use the `api` guard.

## API Endpoints

### Auth (Personal Access Tokens)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/v1/register` | Public | Register (throttled 6/min). No role assigned — pending approval. |
| `POST` | `/api/v1/login` | Public | Login, returns user + access token |
| `POST` | `/api/v1/logout` | Bearer | Revoke current token |
| `GET`  | `/api/v1/user` | Bearer | Get authenticated user profile |

### App Auth (Personal Access Tokens - long-lived, no refresh)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/v1/app/register` | Public | Register, auto-assigned `AppClient` role (token expires in 12 months) |
| `POST` | `/api/v1/app/login` | Public | Login for app users |
| `POST` | `/api/v1/app/logout` | Bearer | Revoke current token |

### SMS

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET`  | `/api/v1/sms` | Bearer + `send-sms` | List all SMS for the authenticated user (paginated, newest first) with the device that handled each one. Supports `per_page`. |
| `POST` | `/api/v1/sms/send` | Bearer + `send-sms` | Queue SMS for delivery (throttled 30/min). Optional `device_type` (`android`, `iot`). Returns `sms_log_id`. |
| `GET`  | `/api/v1/sms/{smsLog}` | Bearer + `send-sms` | Check delivery status of a sent SMS |
| `GET`  | `/api/v1/sms/{smsLog}/conversation` | Bearer + `send-sms` | Get a sent SMS together with its replies (matched by `external_id`) |
| `POST` | `/api/v1/sms/{smsLog}/retry` | Bearer + `send-sms` | Re-queue a failed SMS for another delivery attempt |

**SMS delivery flow:**
1. Client calls `POST /sms/send` → `SmsLog` created with status `pending`, `SendSmsJob` dispatched to queue, 202 returned immediately.
2. Queue worker picks up the job → resolves target device types (from the optional `device_type` parameter, or falls back to the user's registered device), broadcasts `SmsRequest` event on `sms.{deviceType}.{userId}` channels via Reverb, then polls the DB for up to 2 seconds waiting for the device to report back via `/api/v1/device/status`.
3. If the phone reports status (`sent`/`delivered`/`failed`) within the window, the job completes successfully.
4. If the phone doesn't respond, the job retries up to 3 times with progressive backoff (2s, 5s, 10s).
5. After all retries exhausted, the `SmsLog` is marked `failed`. The user can retry via the retry endpoint.

### User Device

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/v1/user/device` | Bearer + `send-sms` | Show registered device |
| `POST` | `/api/v1/user/device` | Bearer + `send-sms` | Register a device |
| `DELETE` | `/api/v1/user/device` | Bearer + `send-sms` | Remove registered device |

### Admin (requires `Admin` role)

| Method  | Endpoint | Auth | Description |
|---------|----------|------|-------------|
| `GET`   | `/api/v1/admin/users` | Bearer + Admin | List all users (paginated, sortable) |
| `PATCH` | `/api/v1/admin/users/{user}/approve` | Bearer + Admin | Assign `Client` role to user |
| `PATCH` | `/api/v1/admin/users/{user}/revoke` | Bearer + Admin | Remove all roles from user |

### Device API (device-token header)

Devices authenticate with a static `X-Device-Token` header. Each device type subscribes to its own broadcast channel (`private-sms.{deviceType}.{userId}`). Supported types: `android`, `iot`.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/v1/device/broadcasting/auth` | Device Token | Pusher broadcast auth for `private-sms.{deviceType}.{userId}` |
| `POST` | `/api/v1/device/reply` | Device Token | Incoming SMS reply handling |
| `POST` | `/api/v1/device/status` | Device Token | SMS status update |

## Setup

```bash
# Clone and install
git clone <repo-url> && cd smsgate
cp .env.example .env
touch database/database.sqlite

composer setup

# Or step by step
composer install
php artisan key:generate
php artisan migrate --force
npm install --ignore-scripts
npm run build

# Generate Passport keys & personal access client
php artisan passport:keys
php artisan passport:client --personal --no-interaction

# Seed roles, permissions, and admin user
php artisan db:seed
```
## Server Setup example
For ubuntu 24.04, you can use the following to set up the server:

### Supervisor
```bash
# create reverb and queue workers for supervisor - example config at /etc/supervisor/conf.d/smsgateway.conf

[program:sms-reverb]
directory=/var/www/laravel-sms-gateway
command=php artisan reverb:start
autostart=true
autorestart=true
startretries=5
stderr_logfile=/var/www/laravel-sms-gateway/storage/logs/reverb.err.log
stdout_logfile=/var/www/laravel-sms-gateway/storage/logs/reverb.out.log
user=www-data

[program:sms-queue]
directory=/var/www/laravel-sms-gateway
command=php artisan queue:work --sleep=1 --tries=3
autostart=true
autorestart=true
stderr_logfile=/var/www/laravel-sms-gateway/storage/logs/queue-worker.err.log
stdout_logfile=/var/www/laravel-sms-gateway/storage/logs/queue-worker.out.log
user=www-data
```
```bash
# Reload supervisor and start workers

supervisorctl reread
supervisorctl update
supervisorctl start all
```

### Nginx
```nginx
# Nginx config for SmsGateway example at /etc/nginx/sites-available/laravel-sms-gateway.conf

server {
    server_name example.domain;
    root /var/www/laravel-sms-gateway/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    error_log /var/log/nginx/laravel-sms-gateway-error.log;
    access_log /var/log/nginx/laravel-sms-gateway-access.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Reverb WebSocket proxy
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 1d;
        proxy_connect_timeout 60s;
        proxy_buffering off;
    }

    # (Optional) Reverb HTTP API, only if something external needs it
    location /apps {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location ~ \.php$ {
        try_files $uri =404 /index.php;
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_hide_header X-Powered-By;
    }

    location ~* \.(jpg|jpeg|png|gif|css|js|ico|svg|webp|ttf|woff|woff2|eot)$ {
        expires 30d;
        access_log off;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;

    location ^~ /.well-known/acme-challenge/ {
        root /var/www/lzfro/public;
        allow all;
    }

    location ~ /\.(?!well-known).* { deny all; }
    location ~ /\.git { deny all; }
    location ~* ^/storage/.*\.php$ { deny all; }

    listen 443 ssl;

    ssl_certificate /etc/letsencrypt/live/example.domain/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/example.domain/privkey.pem; # managed by Certbot
    include /etc/letsencrypt/options-ssl-nginx.conf; # managed by Certbot
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem; # managed by Certbot

}

server {
    server_name example.domain;
    listen 80;
    return 404;

    location ^~ /.well-known/acme-challenge/ {
        root /var/www/laravel-sms-gateway/public;
        allow all;
    }

    location / {
        return 301 https://example.domain$request_uri;
    }
}
```

### Certificate
```bash
# Generate certificate with certbot

certbot --nginx -d example.domain
```

The app will be available at `https://example.domain` (or `https://localhost` depending on your `.env`).

### Default Admin Credentials

| Email | Password |
|-------|----------|
| `admin@example.com` | `password` |

## Environment

```env
APP_NAME=SmsGateway
APP_URL=https://localhost

DB_CONNECTION=sqlite
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

BROADCAST_CONNECTION=reverb
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_APP_ID=81001
REVERB_HOST=your-app-host.tld
REVERB_PORT=443
REVERB_SCHEME=https
```

Everything runs off a single SQLite file - sessions, queue, and cache all use the `database` driver. No Redis or external services required.

If you switch broadcasting to Reverb, set `BROADCAST_CONNECTION=reverb` and fill in the Reverb variables. The Device endpoints expect Pusher-compatible broadcasting channels.

### SMS Polling Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `SMS_POLL_TIMEOUT_SECONDS` | `2` | Seconds to wait per job attempt for the phone to report delivery status |
| `SMS_POLL_INTERVAL_MS` | `300` | Milliseconds between DB polls within the window |
| `SMS_MAX_RETRIES` | `1` | Additional polling retries within a single job attempt |

## Useful Commands

```bash
composer run dev          # Start API + queue + Vite + logs concurrently
php artisan serve         # API server only
php artisan queue:work    # Queue worker
npm run dev               # Vite dev server
npm run build             # Production asset build

php artisan test --compact                           # Run all tests
php artisan test --compact --filter=TestName         # Single test

vendor/bin/pint --dirty --format agent               # Format changed files

php artisan scramble:cache                           # Generate OpenAPI docs
```

## Architecture

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── Admin/AdminController.php        # User management (index, approve, revoke)
│   │   │   ├── Device/                          # Broadcasting auth, reply, status (Android, IoT, etc.)
│   │   │   ├── Auth/AuthController.php          # PAT flow (register, login, logout)
│   │   │   ├── Auth/AppAuthController.php       # PAT flow (register → AppClient, login, logout)
│   │   │   ├── Sms/SmsController.php            # SMS list, send, status check, conversation, retry
│   │   │   └── User/DeviceController.php        # Device registration
│   │   └── Auth/                                # Web auth (Bootstrap 5 UI)
│   ├── Middleware/VerifyDeviceToken.php         # Static token auth for Device endpoints
│   └── Resources/UserResource.php               # JSON:API resource for users
├── Events/SmsRequest.php                        # Broadcast event on sms.{deviceType}.{userId} via Reverb
├── Exceptions/SmsNotDeliveredException.php      # Thrown when phone doesn't confirm delivery
├── Jobs/SendSmsJob.php                          # Queued job: broadcast → poll → retry (3 attempts)
└── Models/User.php                              # UUID PK, Passport tokens, spatie roles
database/
├── database.sqlite                              # Single-file database
├── migrations/                                  # All tables
└── seeders/
    ├── DatabaseSeeder.php                       # Seeds roles, admin user, Device token
    └── RolePermissionSeeder.php                 # firstOrCreate — safe to re-run
routes/
├── api.php                                      # /api/v1/* routes
└── web.php                                      # Browser auth routes
```

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
