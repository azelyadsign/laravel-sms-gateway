# SmsGateway

A Laravel 13 REST API for SMS gateway management with OAuth2 authentication, role-based access control, and real-time broadcasting.

## Tech Stack

- **PHP 8.4** · **Laravel 13** · **SQLite**
- **Laravel Passport 13** — OAuth2 server (`auth:api` guard)
- **Laravel Reverb** — WebSocket broadcasting
- **Spatie Laravel Permission** — role & permission system
- **Dedoc Scramble** — auto-generated OpenAPI docs
- **Vite 8** · **Tailwind CSS 4** · **Bootstrap 5** — frontend scaffolding
- **Laravel Telescope** — debugging & monitoring

## Features

- **OAuth2 Authentication** — Personal Access Tokens (12-month expiry) for all clients
- **Role-Based Access** — Admin, Client, and AppClient roles with granular permissions
- **SMS Sending** — throttled at 30/min, dispatched to Android phone via Reverb broadcast, with automatic retry (3 attempts) and status tracking
- **User Approval Flow** — new users are pending until an Admin approves them
- **Android Device API** — static device-token auth for broadcasting, SMS replies, and status updates
- **Real-Time Broadcasting** — Laravel Reverb for pusher-compatible WebSocket events
- **Browser Auth UI** — Bootstrap 5 login, register, and password reset pages

## Roles & Permissions

| Role | Permissions | How Assigned |
|------|------------|--------------|
| **Admin** | `send-sms`, `approve-users` | Seeded via `php artisan db:seed` |
| **Client** | `send-sms` | Admin approves user via API |
| **AppClient** | `send-sms` | Auto-assigned on app registration |

- New users register with **no role** — must be approved by an Admin before they can send SMS.
- All roles use the `api` guard.

## API Endpoints

### Auth (Personal Access Tokens)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/v1/register` | Public | Register (throttled 6/min). No role assigned — pending approval. |
| `POST` | `/api/v1/login` | Public | Login, returns user + access token |
| `POST` | `/api/v1/logout` | Bearer | Revoke current token |
| `GET`  | `/api/v1/user` | Bearer | Get authenticated user profile |

### App Auth (Personal Access Tokens — long-lived, no refresh)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/v1/app/register` | Public | Register, auto-assigned `AppClient` role (token expires in 12 months) |
| `POST` | `/api/v1/app/login` | Public | Login for app users |
| `POST` | `/api/v1/app/logout` | Bearer | Revoke current token |

### SMS

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/v1/sms/send` | Bearer + `send-sms` | Queue SMS for delivery (throttled 30/min). Returns `sms_log_id`. |
| `GET`  | `/api/v1/sms/{smsLog}` | Bearer + `send-sms` | Check delivery status of a sent SMS |
| `POST` | `/api/v1/sms/{smsLog}/retry` | Bearer + `send-sms` | Re-queue a failed SMS for another delivery attempt |

**SMS delivery flow:**
1. Client calls `POST /sms/send` → `SmsLog` created with status `pending`, `SendSmsJob` dispatched to queue, 202 returned immediately.
2. Queue worker picks up the job → broadcasts `SmsRequest` event to the Android phone via Reverb, then polls the DB for up to 2 seconds waiting for the phone to report back via `/android/status`.
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

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/api/v1/admin/users` | Bearer + Admin | List all users (paginated, sortable) |
| `POST` | `/api/v1/admin/users/{user}/approve` | Bearer + Admin | Assign `Client` role to user |
| `POST` | `/api/v1/admin/users/{user}/revoke` | Bearer + Admin | Remove all roles from user |

### Android Device (device-token header)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/v1/android/broadcasting/auth` | Device Token | Pusher broadcast auth |
| `POST` | `/api/v1/android/reply` | Device Token | Incoming SMS reply handling |
| `POST` | `/api/v1/android/status` | Device Token | SMS status update |

## Local Setup

```bash
# Clone and install
git clone <repo-url> && cd smsgate
composer setup

# Or step by step
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --force
npm install --ignore-scripts
npm run build

# Generate Passport keys & personal access client
php artisan passport:keys
php artisan passport:client --personal --no-interaction

# Seed roles, permissions, and admin user
php artisan db:seed

# Run the dev stack (API + queue + logs + Vite)
composer run dev
```

The app will be available at `https://smsgate.test` (or `https://localhost` depending on your `.env`).

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
REVERB_HOST=localhost
REVERB_PORT=443
REVERB_SCHEME=https
```

Everything runs off a single SQLite file — sessions, queue, and cache all use the `database` driver. No Redis or external services required.

If you switch broadcasting to Reverb, set `BROADCAST_CONNECTION=reverb` and fill in the Reverb variables. The Android endpoints expect Pusher-compatible broadcasting channels.

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
│   │   │   ├── Android/                         # Broadcasting auth, reply, status
│   │   │   ├── Auth/AuthController.php          # PAT flow (register, login, logout)
│   │   │   ├── Auth/AppAuthController.php       # PAT flow (register → AppClient, login, logout)
│   │   │   ├── Sms/SmsController.php            # SMS send, status check, retry
│   │   │   └── User/DeviceController.php        # Device registration
│   │   └── Auth/                                # Web auth (Bootstrap 5 UI)
│   ├── Middleware/VerifyDeviceToken.php          # Static token auth for Android endpoints
│   └── Resources/UserResource.php               # JSON:API resource for users
├── Events/SmsRequest.php                         # Broadcast event to Android phone via Reverb
├── Exceptions/SmsNotDeliveredException.php       # Thrown when phone doesn't confirm delivery
├── Jobs/SendSmsJob.php                           # Queued job: broadcast → poll → retry (3 attempts)
└── Models/User.php                               # UUID PK, Passport tokens, spatie roles
database/
├── database.sqlite                               # Single-file database
├── migrations/                                   # All tables
└── seeders/
    ├── DatabaseSeeder.php                        # Seeds roles, admin user, Android token
    └── RolePermissionSeeder.php                  # firstOrCreate — safe to re-run
routes/
├── api.php                                       # /api/v1/* routes
└── web.php                                       # Browser auth routes
```

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
