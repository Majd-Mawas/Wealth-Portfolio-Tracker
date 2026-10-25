# Wealth Portfolio Tracker

A full-stack prototype for managing household wealth across stocks, cryptocurrency, real estate, and cash. The project emphasizes portfolio-scoped authorization, precise financial arithmetic, an append-only transaction history, and a read-only AI assistant with server-enforced tools.

## Architecture overview

```text
React + TypeScript SPA (frontend/)
  -> credentialed JSON API and assistant SSE
Laravel REST API (backend/)
  -> Sanctum session authentication
  -> Form Requests + portfolio membership middleware + policies
  -> application services + API Resources
  -> MySQL DECIMAL data and append-only transactions
  -> allowlisted OpenAI Responses API tools
```

Laravel is the source of truth for identity, permissions, validation, financial calculations, and persistence. React uses capability flags only to improve the interface; every protected operation is reauthorized by the backend. The AI assistant derives its actor from the Sanctum session, binds execution to the portfolio in the route, validates every tool call, and rechecks the same `view` policy used by normal API requests.

More detail is available in [the architecture](docs/ARCHITECTURE.md), [API contract](docs/API.md), and [AI assistant design](docs/AI_ASSISTANT.md).

## Prerequisites

- PHP 8.3 or newer with PDO MySQL, OpenSSL, Mbstring, and JSON support
- Composer 2
- MySQL 8 or a compatible recent MariaDB release
- Node.js `^20.19.0` or `>=22.12.0`
- npm
- An OpenAI API key only if the assistant will be used

## Installation

Clone the repository, then install each application separately.

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate

cd ../frontend
npm install
cp .env.example .env
```

On PowerShell, use `Copy-Item .env.example .env` instead of `cp` if no Unix-compatible shell is installed.

## Environment variables

Backend settings are in `backend/.env`:

| Variable | Purpose | Local example |
| --- | --- | --- |
| `APP_URL` | Laravel origin | `http://localhost:8000` |
| `FRONTEND_URL` | Exact allowed browser origin for CORS | `http://localhost:5173` |
| `DB_HOST`, `DB_PORT` | MySQL connection | `127.0.0.1`, `3306` |
| `DB_DATABASE` | Application database | `wealth_portfolio_tracker` |
| `DB_USERNAME`, `DB_PASSWORD` | Local database credentials | environment-specific |
| `SESSION_DRIVER` | Session persistence | `database` |
| `SANCTUM_STATEFUL_DOMAINS` | Comma-separated SPA/API hosts including ports | provided in `.env.example` |
| `OPENAI_API_KEY` | Server-only assistant credential | leave empty to disable assistant calls |
| `OPENAI_MODEL` | Responses API model | `gpt-6-luna` |
| `OPENAI_BASE_URL` | Provider API base URL | `https://api.openai.com/v1` |
| `OPENAI_CONNECT_TIMEOUT`, `OPENAI_TIMEOUT` | Provider timeouts in seconds | `5`, `45` |
| `OPENAI_MAX_OUTPUT_TOKENS` | Per-response output bound | `1200` |

The frontend has one setting in `frontend/.env`:

| Variable | Purpose | Local example |
| --- | --- | --- |
| `VITE_API_URL` | Laravel origin, without a trailing slash | `http://localhost:8000` |

Never put `OPENAI_API_KEY`, database credentials, `APP_KEY`, or other server secrets in a `VITE_*` variable. For production, use HTTPS, set secure session-cookie options, restrict CORS and Sanctum domains to the deployed origins, and disable debug mode.

## Database setup

Create an empty MySQL database and update `backend/.env` with credentials that can access it. For example:

```sql
CREATE DATABASE wealth_portfolio_tracker
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Run migrations, optionally including the deterministic local demo data:

```bash
cd backend
php artisan migrate
php artisan db:seed
```

`DatabaseSeeder` delegates to the dedicated `DemoPortfolioSeeder`. To invoke it directly, run:

```bash
php artisan db:seed --class='Database\Seeders\DemoPortfolioSeeder'
```

Both seed commands refuse to load demo records unless `APP_ENV` is `local` or `testing`. They are repeatable and never truncate tables or delete existing records.

For a disposable local database only, `php artisan migrate:fresh --seed` recreates all tables and loads the demo. This command drops every table first: never run it against production, a shared database, or any database containing data you need to keep. On a database that must be preserved, use `php artisan migrate` followed by `php artisan db:seed` instead.

## Running backend and frontend

Use two terminals:

```bash
cd backend
php artisan serve
```

```bash
cd frontend
npm run dev
```

Open `http://localhost:5173`. The Laravel API defaults to `http://localhost:8000`; visiting that origin directly returns a small status response. The SPA obtains a CSRF cookie before login or registration and sends credentialed requests through Sanctum's first-party session flow.

## Running tests and checks

Backend feature tests use in-memory SQLite and do not modify the configured MySQL database:

```bash
cd backend
php artisan test
vendor/bin/pint --format agent
composer validate --strict
composer audit
```

The frontend currently has no automated component or browser test suite. Its available verification is the TypeScript production build and dependency audit:

```bash
cd frontend
npm run build
npm audit
```

## Demo data

The demo dataset is entirely fictional and intended only for local development, automated tests, screenshots, and the assessment walkthrough. All four accounts use the local-only password `DemoOnly!2026`:

| User | Email | Primary demo scenario |
| --- | --- | --- |
| Alex Morgan | `alex@example.test` | Owns Growth Portfolio; Editor on Family Wealth; Viewer on Retirement Savings |
| Sarah Bennett | `sarah@example.test` | Owns Family Wealth; Editor on Growth Portfolio |
| Daniel Reed | `daniel@example.test` | Owns Retirement Savings; Viewer on Growth Portfolio |
| Emma Wilson | `emma@example.test` | Sole member and Owner of private Crypto Holdings; Editor on Retirement Savings; Viewer on Family Wealth |

The seeder creates four portfolios, ten memberships, thirteen assets, and thirty-nine transactions dated from November 2025 through October 2026:

- **Growth Portfolio (USD):** Apple, Microsoft, NVIDIA, and a USD cash balance. It contains 16 transactions so the transaction-history pagination and Buy/Sell filters are visible.
- **Family Wealth (USD):** a residential apartment, a rental property, and a family cash reserve, producing a clear real-estate-heavy allocation chart.
- **Retirement Savings (EUR):** European equity and bond ETFs plus an EUR cash balance. EUR assets live in an EUR portfolio because the prototype intentionally has no FX conversion.
- **Crypto Holdings (USD):** Bitcoin, Ethereum, and Solana. Emma is its only member, making it useful for cross-portfolio access checks.

All prices are fixed, fictional reference prices with a `price_as_of` timestamp of 20 October 2026. They are not current market quotes. The histories include valid buys, sells, deposits, and withdrawals without overselling or negative balances. Transaction fees and acquisition prices are historical context; current dashboard valuation continues to use each asset's documented manual reference price.

Useful walkthrough scenarios:

- Sign in as Alex to manage Growth Portfolio and demonstrate its dashboard, chart, filters, pagination, member roles, and assistant queries.
- Sign in as Sarah to demonstrate Editor asset and transaction access while membership controls remain unavailable.
- Sign in as Daniel to demonstrate Viewer access on Growth Portfolio and rejected write requests.
- Sign in as Emma to demonstrate the private Crypto Holdings portfolio and cross-portfolio isolation.
- Ask the assistant to summarize allocation, list the largest assets, or describe recent transactions. The assistant remains read-only and identifies valuations as manual.

The seeder identifies its records through reserved `.test` accounts, portfolio ownership, asset names, and `[DEMO]` transaction notes. Re-running it restores the defined demo users, memberships, asset reference data, and missing demo transactions without duplicating them. It deliberately does not delete extra records or user changes; use `migrate:fresh --seed` only when you explicitly want to rebuild a disposable local database from scratch.

## Implemented features

- Sanctum cookie authentication: registration, login, current user, and logout
- Portfolio CRUD with creator-derived ownership; no client-supplied owner identity
- Owner, Editor, and Viewer memberships with last-owner protection
- Cross-portfolio route scoping and consistent policy enforcement in controllers/services
- Asset CRUD for stock, crypto, real estate, and cash
- Manual, timestamped prices in the portfolio's single base currency
- Append-only transaction history, negative-balance prevention, filtering, cursor pagination, and explicit reversals
- Decimal-safe quantity, valuation, and allocation calculations using MySQL `DECIMAL`, decimal strings, and `brick/math`
- Responsive React dashboard, forms, tables, allocation chart, loading/empty/error states, and permission-aware controls
- Read-only OpenAI assistant tools for portfolio summary, assets, and transactions
- Strict AI tool schemas, server validation, route-bound portfolio IDs, policy rechecks, bounded tool rounds, rate limiting, safe tool errors, and application-level SSE events
- Feature coverage for authentication, the role matrix, tenant isolation, calculations, transaction invariants, AI authorization, prompt injection, and invalid tool arguments

## Deferred features and trade-offs

- **Live market and FX data:** prices are manual and labeled with `price_as_of`; no response implies real-time accuracy.
- **Background synchronization:** queues, schedulers, Redis, and valuation caches are deferred because synchronous household-scale aggregation is sufficient for this prototype.
- **Real-time collaboration:** no presence, WebSockets, or cross-client live updates.
- **Advanced accounting and analytics:** no double-entry ledger, tax lots, realized gains, performance returns, rebalancing engine, or trade execution.
- **AI mutations:** all assistant tools are read-only. A future write tool would need a separate human-confirmation flow, fresh authorization, idempotency, and transactional execution.
- **Provider token passthrough:** Laravel streams request/tool progress and final text chunks, but each OpenAI Responses API round is buffered to keep function-call reconstruction and failure handling simple.
- **Generated API clients:** the current boundary uses a documented contract plus explicit TypeScript types. OpenAPI generation is deferred until the contract or number of clients makes that maintenance step worthwhile.
- **Durable chat history:** conversations are bounded and browser-local; they are not stored in the database.

These boundaries keep the assessment focused on permission modeling, financial correctness, contract clarity, and secure agent integration without introducing infrastructure that the prototype does not need.
