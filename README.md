# Virgosoft Assessment — Trading Platform

A trading platform built as a technical assessment. It features a real-time order book, atomic order matching engine, wallet/asset management, and a live dashboard UI.

---

## Features

- **Order Book** — place buy/sell orders with atomic matching and a 1.5% commission on matched trades
- **Price Improvement** — buyers always pay the maker's price, not their limit; overpay is refunded at settlement
- **Self-match Prevention** — users cannot trade against their own orders (enforced on both backend and UI)
- **Own Order Highlighting** — a user's own orders are visually flagged in the order book with a "·You" indicator
- **Order Cancellation** — cancel open orders with asset/balance refund
- **Wallet & Assets** — track user balances and asset holdings, updated in real time from match events (no extra fetch)
- **Commission Display** — buyers see the commission deducted in their toast notification and filled order details
- **Real-time Updates** — order book and wallet update live via Pusher/Laravel Echo
- **REST API** — token-based auth via Laravel Sanctum
- **SPA Frontend** — Vue 3 + Inertia.js + Tailwind CSS

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.4, Laravel 12 |
| Frontend | Vue 3, Inertia.js, Tailwind CSS 4 |
| Auth | Laravel Sanctum |
| Real-time | Pusher (Laravel Echo + pusher-js) |
| Database | MySQL 8.4 |
| Cache / Sessions | Redis 7 |
| Queue | Laravel Queue (Redis) |
| Build Tool | Vite 7 |
| Testing | PHPUnit 11, SQLite (in-memory) |
| Containerisation | Docker, Docker Compose |

---

## Requirements

- [Docker](https://docs.docker.com/get-docker/) & Docker Compose
- A [Pusher](https://pusher.com) account for real-time events

> No local PHP, Composer, or Node installation is required — everything runs inside the containers.

---

## Installation & Setup

### 1. Clone the repository

```bash
git clone git@github.com:GeneraalAladeen/virgosoft-assessment.git
cd virgosoft-assessment
```

### 2. Configure environment

```bash
cp .env.example .env
cp .env.example .env.testing
```

Fill in your Pusher credentials in `.env`:

```env
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=mt1
```

The database, Redis, and Docker port settings are already pre-configured in `.env.example`. The defaults are:

```env
DOCKER_DB_PORT=3307         # MySQL exposed on host port 3307
DOCKER_SERVER_PORT=8000     # App exposed on host port 8000
DOCKER_CONFIG_FOLDER=./storage/app/virgosoft-assessment   # Docker volumes stored inside the project
```

### 3. Build the Docker containers

```bash
docker compose build
```

### 4. Install PHP dependencies

Run this before starting the containers — the webserver will crash-loop on startup without `vendor/`:

```bash
docker compose run --rm webserver composer install
```

### 5. Start the containers

```bash
docker compose up -d
```

This starts:
- `webserver` — PHP app server (`php artisan serve`)
- `queue` — Laravel queue worker
- `database_server` — MySQL 8.4
- `redis` — Redis 7

### 6. Generate the application key

```bash
docker compose exec webserver php artisan key:generate
```

### 7. Run database migrations and seed

```bash
docker compose exec webserver php artisan migrate --seed
```

This creates two demo users with starting balances and assets:

| Name | Email | Password | USD Balance | BTC | ETH |
|------|-------|----------|-------------|-----|-----|
| Alice | alice@example.com | password | $100,000 | 1.0 | 10.0 |
| Bob | bob@example.com | password | $100,000 | 2.0 | 5.0 |

The seeder also populates a realistic order book for both BTC/USD and ETH/USD so the dashboard is usable immediately.

### 8. Install Node dependencies and build frontend assets

```bash
docker compose exec webserver npm install
docker compose exec webserver npm run build
```

The app is now available at [http://localhost:8000](http://localhost:8000).

---

## Testing

> **Prerequisites:** ensure you have copied `.env.example` to `.env.testing` (see step 2 of Installation). `.env.testing` is gitignored and must be created manually.

### Setup: dedicated MySQL test database

**1. Create the test database inside the running MySQL container:**

```bash
docker compose exec database_server mysql -uroot -psecret -e \
  "CREATE DATABASE IF NOT EXISTS virgosoft_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**2. Update `.env.testing` with the following values:**

```env
APP_ENV=testing

DB_CONNECTION=mysql
DB_HOST=database_server
DB_PORT=3306
DB_DATABASE=virgosoft_test
DB_USERNAME=root
DB_PASSWORD=secret

SESSION_DRIVER=array
QUEUE_CONNECTION=sync
CACHE_STORE=array
BROADCAST_CONNECTION=null
MAIL_MAILER=array
```

**3. Generate an application key for the test environment:**

```bash
docker compose exec webserver php artisan key:generate --env=testing
```

**4. Run migrations against the test database:**

```bash
docker compose exec webserver php artisan migrate --env=testing
```

### Running tests

```bash
docker compose exec webserver php artisan test --coverage --min=90
```

---

## License

MIT
