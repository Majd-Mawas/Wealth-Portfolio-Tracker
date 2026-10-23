# Wealth Portfolio Tracker API

Laravel 13 REST API for the Phase 1 authentication, portfolio, and membership slice.

Implemented in this phase:

- Sanctum first-party SPA session authentication;
- portfolio create/list/show/update/soft-delete;
- Owner, Editor, and Viewer portfolio memberships;
- policy enforcement for normal API requests and service calls;
- transactional protection against removing or demoting the last Owner;
- versioned JSON API resources and consistent error responses;
- authentication and authorization feature tests.

Assets, transactions, dashboard valuation, AI tools, and frontend code are intentionally not implemented yet.

## Requirements

- PHP 8.3 or newer
- Composer
- MySQL 8

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create an empty MySQL database named `wealth_portfolio_tracker`, or change the `DB_*` values in `.env`, then run:

```bash
php artisan migrate
php artisan serve
```

The default API URL is `http://localhost:8000`. A first-party SPA should first request `GET /sanctum/csrf-cookie`, then send credentials and the `X-XSRF-TOKEN` header to the `/api/v1` endpoints. Configure `FRONTEND_URL` and `SANCTUM_STATEFUL_DOMAINS` for the actual SPA origin.

## Tests and formatting

Tests use an in-memory SQLite database so they do not alter the local MySQL database.

```bash
php artisan test
vendor/bin/pint --test
```

## Phase 1 endpoints

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/user`
- `GET|POST /api/v1/portfolios`
- `GET|PATCH|DELETE /api/v1/portfolios/{portfolio}`
- `GET|POST /api/v1/portfolios/{portfolio}/members`
- `PATCH|DELETE /api/v1/portfolios/{portfolio}/members/{membership}`

See the repository-level `docs/API.md` and `docs/ARCHITECTURE.md` for the full planned contract and design.
