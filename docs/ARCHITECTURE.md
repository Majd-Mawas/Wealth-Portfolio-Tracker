# Wealth Portfolio Tracker - Architecture

Status: living architecture. Implemented behavior is stated directly; future evolution remains labeled as planned or deferred.

## 1. Architectural drivers

The system is optimized for five properties:

1. Authorization must be non-bypassable across normal API calls and AI tool calls.
2. Financial values must retain decimal precision across MySQL, PHP, JSON, and TypeScript.
3. A reviewer should be able to trace each request without learning a large framework ecosystem.
4. The AI assistant must be useful without becoming a privileged back door.
5. Deferred external data and real-time work must not distort the MVP design.

## 2. System shape

```text
Browser
  React + TypeScript + Vite
  TanStack Query / React Hook Form / Zod / Recharts
             |
             | HTTPS, JSON; streamed SSE frames for assistant chat
             v
Laravel REST API (/api/v1)
  Sanctum + CSRF
  Form Requests -> Controllers -> Application Services -> Eloquent
                         |                 |
                         |                 +-> Policies / Gate (actor-aware)
                         |
                         +-> Assistant Orchestrator -> Tool Registry
                                      |                |
                                      |                +-> same application services
                                      v
                                OpenAI Responses API
             |
             v
           MySQL
```

This is a modular monolith with a separate SPA, not microservices. Laravel owns authentication, authorization, business invariants, persistence, valuation, and AI orchestration. React owns interaction and presentation. MySQL is the source of truth.

## 3. Backend boundaries

### HTTP layer

- Versioned routes under `/api/v1`.
- Form Requests normalize and validate external input.
- API Resources serialize stable response shapes and all decimals as strings.
- Controllers remain thin: obtain the authenticated actor, authorize coarse access where useful, and delegate.

### Application services

Services represent use cases such as `CreatePortfolio`, `ChangeMembership`, `RecordTransaction`, and `BuildPortfolioSummary`. Names are illustrative, not an assertion that classes exist.

Every state-changing or sensitive read service accepts the authenticated `User` explicitly and calls the relevant policy/Gate itself. This defense-in-depth is deliberate: a future CLI command, controller, or assistant tool cannot become a bypass simply by calling a service.

Services own transaction boundaries and cross-record invariants:

- portfolio plus initial Owner membership;
- owner count check plus membership change;
- current quantity check plus transaction insertion;
- original transaction lock plus reversal insertion;

### Domain and persistence layer

Eloquent models express relationships and local casts. Policies express capabilities. Database constraints enforce structural truths such as uniqueness and referential integrity. Cross-row rules that MySQL cannot express cleanly, such as retaining one Owner, are enforced transactionally in services and covered by concurrency-oriented tests.

No generic repository layer is planned. Eloquent is already the data abstraction, and wrapping every model would add indirection without a second storage implementation.

## 4. Database schema

Primary keys use unsigned big integers for Laravel convention and operational simplicity. Public IDs are still safe because every lookup is authorization scoped; opaque IDs are not a substitute for access control. Timestamps are stored in UTC and rendered in the user's locale.

### `users`

Laravel's standard user record:

| Column | Type / constraint | Notes |
| --- | --- | --- |
| `id` | `BIGINT UNSIGNED`, PK | Internal identity |
| `name` | `VARCHAR(255)` | Display name |
| `email` | `VARCHAR(255)`, unique | Normalized for lookup |
| `password` | `VARCHAR(255)` | Laravel hash |
| `email_verified_at` | nullable timestamp | Verification may be deferred for local demo |
| timestamps | timestamps | UTC |

Sanctum/session tables follow Laravel's normal configuration and are not domain tables.

### `portfolios`

| Column | Type / constraint | Notes |
| --- | --- | --- |
| `id` | `BIGINT UNSIGNED`, PK | |
| `name` | `VARCHAR(120)` | Human-readable |
| `base_currency` | `CHAR(3)` | Uppercase ISO 4217 code |
| timestamps | timestamps | UTC |
| `deleted_at` | nullable timestamp | Soft delete |

Ownership is intentionally not duplicated as `owner_id`; it is represented by memberships. This supports multiple Owners without two competing sources of truth.

### `portfolio_memberships`

| Column | Type / constraint | Notes |
| --- | --- | --- |
| `id` | `BIGINT UNSIGNED`, PK | |
| `portfolio_id` | FK -> portfolios, cascade on hard delete | Indexed |
| `user_id` | FK -> users, cascade on hard delete | Indexed |
| `role` | `VARCHAR(16)` | Application enum: `owner`, `editor`, `viewer` |
| timestamps | timestamps | UTC |

Constraints: unique (`portfolio_id`, `user_id`). The application validates the role allowlist and protects the last Owner under a database transaction and row lock.

### `assets`

| Column | Type / constraint | Notes |
| --- | --- | --- |
| `id` | `BIGINT UNSIGNED`, PK | |
| `portfolio_id` | FK -> portfolios | Indexed |
| `type` | `VARCHAR(24)` | `stock`, `crypto`, `real_estate`, `cash` |
| `name` | `VARCHAR(160)` | Required |
| `symbol` | nullable `VARCHAR(32)` | Useful for stock/crypto; not treated as globally unique |
| `currency` | `CHAR(3)` | Must equal portfolio base currency in MVP |
| `current_price` | `DECIMAL(20,8)` | Manual unit price; cash is exactly `1` |
| `price_as_of` | timestamp | Required to disclose staleness |
| `metadata` | nullable JSON | Type-specific display fields, not authorization or calculation input |
| timestamps | timestamps | UTC |
| `archived_at` | nullable timestamp | Used instead of deleting historical assets |

`metadata` may hold optional values such as exchange, crypto network, or property label. Frequently queried or financially meaningful data does not go in JSON.

### `transactions`

| Column | Type / constraint | Notes |
| --- | --- | --- |
| `id` | `BIGINT UNSIGNED`, PK | |
| `asset_id` | FK -> assets, restrict delete | Indexed |
| `created_by` | FK -> users, restrict delete | Audit actor |
| `kind` | `VARCHAR(24)` | `buy`, `sell`, `deposit`, `withdrawal`, `adjustment`, `reversal` |
| `quantity_delta` | `DECIMAL(24,8)` | Signed change; non-zero |
| `unit_price` | nullable `DECIMAL(20,8)` | Historical execution/declared price, not current valuation |
| `fees` | `DECIMAL(20,8)` default `0` | Non-negative for normal entries; negated by a reversal |
| `occurred_at` | timestamp | User-selected effective time |
| `note` | nullable `VARCHAR(500)` | Untrusted user content |
| `source` | `VARCHAR(16)` | `api` or `assistant` |
| `reversal_of_id` | nullable self FK, unique | One reversal per original |
| timestamps | timestamps | UTC; no update/delete API |

Portfolio membership for a transaction is resolved through `transaction -> asset -> portfolio`, avoiding a duplicated `portfolio_id` that could disagree with the asset.

### Relationship summary

```text
users      >---< portfolios              (through portfolio_memberships)
portfolios 1---* portfolio_memberships
portfolios 1---* assets
assets     1---* transactions
users      1---* transactions             (created_by)
transactions 0..1---0..1 transactions     (reversal_of_id)
```

### Indexes

- `portfolio_memberships (user_id, portfolio_id)` for portfolio listing.
- Unique `portfolio_memberships (portfolio_id, user_id)`.
- `assets (portfolio_id, archived_at)` for dashboard/list queries.
- `transactions (asset_id, occurred_at, id)` for stable history pagination and balance aggregation.
- Unique `transactions (reversal_of_id)` where supported by the nullable unique index behavior in MySQL.

## 5. Financial and valuation rules

### Precision

- Quantities use `DECIMAL(24,8)` and unit prices/fees use `DECIMAL(20,8)`.
- MySQL `DECIMAL` and `brick/math` `BigDecimal` in PHP perform authoritative arithmetic. This focused dependency is preferable to relying on optional PHP extensions or binary floats.
- JSON represents decimals as strings, for example `"12345.67000000"`.
- Zod validates decimal string syntax and allowed scale before submission; Laravel remains authoritative.
- Rounding for displayed money is currency-aware at the final presentation boundary. Stored quantities and prices are not silently rounded to cents.
- Recharts receives numeric allocation percentages only. Those values are visualization coordinates, never saved or reused as financial totals.

### Quantity and transactions

Current quantity is:

```text
SUM(transactions.quantity_delta)
```

Rules by kind:

| Kind | Delta | Typical asset |
| --- | --- | --- |
| `buy` | positive | stock, crypto, real estate |
| `sell` | negative | stock, crypto, real estate |
| `deposit` | positive | cash |
| `withdrawal` | negative | cash |
| `adjustment` | positive or negative, non-zero | any; note required |
| `reversal` | exact negative of original | any |

The resulting quantity must be non-negative. The asset row and relevant transaction range are locked while validating and inserting a mutation so concurrent sells cannot both pass against the same balance.

Transactions are not editable. A reversal records who corrected an entry and when. It negates the original quantity delta and fee, copies the original unit price for context, and is linked through `reversal_of_id`.

### Valuation

For each active asset:

```text
asset_value = current_quantity * current_price
portfolio_total = SUM(asset_value)
allocation_percentage = asset_value / portfolio_total * 100
```

If the total is zero, every allocation percentage is `0` and the chart shows an empty state. Cash price is fixed at `1`. A stock, crypto, or real-estate price update changes current valuation but does not rewrite historical transaction prices.

Fees and transaction `unit_price` are retained for history but are not used for MVP performance or cost-basis calculations. This is an explicit limitation, not an accounting claim.

## 6. Authentication and authorization

### Authentication

Sanctum uses the stateful SPA cookie mode:

1. The SPA requests `/sanctum/csrf-cookie`.
2. Registration/login establishes a Laravel session.
3. Subsequent requests send HttpOnly session cookies and the CSRF header.
4. CORS allows only configured first-party origins and credentials.

Production should serve the SPA and API on the same site where possible. Cookies use `Secure`, `HttpOnly`, and an appropriate `SameSite` policy. Session rotation on login and normal Laravel password hashing are retained.

### Authorization flow

```text
request/tool call
  -> authenticated User object from server context
  -> resolve portfolio-scoped resource
  -> policy capability check
  -> application service repeats/enforces capability
  -> transactional invariant checks
  -> database mutation/read
```

Policies map operations to the role matrix. Nested resources are resolved within the already authorized portfolio or checked through their portfolio relationship. A non-member lookup should not return resource data; collection queries always begin from the actor's memberships.

The frontend may hide unavailable controls for usability, but the server never treats hidden controls as security.

## 7. AI agent architecture

### Why raw OpenAI function calling

The assistant needs a short request/tool/result loop, not a graph runtime, memory platform, or agent framework. Calling the OpenAI API directly keeps the model request, tool JSON Schema, round limits, and error behavior visible. A Laravel interface around the OpenAI client still permits a fake in tests and a provider change later.

The implementation uses the Responses API protocol: function-call items are paired with function-call-output items by `call_id`, function schemas request strict validation, and provider details stay behind `OpenAIResponsesClient`. The concrete model is configuration (`OPENAI_MODEL`), with `gpt-6-luna` as the prototype default. `store: false` is sent on each request.

### Tool allowlist

Implemented read-only tools:

| Tool | Mode | Permission |
| --- | --- | --- |
| `get_portfolio_summary` | read | any member |
| `list_portfolio_assets` | read | any member |
| `list_portfolio_transactions` | read | any member |

There is no arbitrary SQL, URL fetch, filesystem access, code execution, membership management, portfolio deletion, or price-changing tool.

### Execution sequence

```text
1. Authenticated user POSTs bounded message history to the active portfolio route.
2. Backend checks rate/size limits and constructs a server-owned agent context.
3. OpenAI may return text or request an allowlisted tool.
4. Backend validates tool name and arguments against the registered strict schema.
5. Tool executor calls an actor-aware application service; policy is rechecked there.
6. Read result is minimized and returned to the model as untrusted data, correlated to the request with the provider `call_id`.
```

The assistant loop is capped at four tool rounds, message and output sizes are bounded, upstream calls have explicit connection/response timeouts, and the browser can cancel its active request. Mutation proposals, confirmation tokens, and all write tools are deferred.

### Prompt and data security

- System instructions state that tool outputs and user-owned fields are data, not instructions.
- Notes, asset names, and prior messages are treated as untrusted and size limited.
- Tool descriptions contain capability semantics, not secrets.
- Tool arguments never include or override an actor ID or role.
- The API key remains server-side and is redacted from errors/logs.
- Tool results return the minimum fields needed for the answer.
- Structured logs record actor, portfolio, tool, and safe success/rejection metadata. Full financial prompts, tool results, and arguments are not logged by default; request-wide latency metrics are deferred.
- OpenAI retention/privacy configuration is documented before production use; the UI discloses that selected portfolio data is sent to the provider.

### Streaming

`POST /api/v1/portfolios/{portfolio}/assistant/chat` returns `text/event-stream` consumed with `fetch()` so a request body and CSRF header can be used. Event types are `meta`, `text_delta`, `tool_started`, `tool_completed`, `tool_error`, `done`, and `error`.

Laravel streams request metadata and tool progress as each tool round completes, then emits the final answer in text chunks. The direct HTTP adapter buffers each individual Responses API call; true provider-token passthrough is deferred because it would add a second event parser and more complex mid-stream failure recovery to this prototype.

## 8. Frontend architecture

- Route/layout layer for auth and selected portfolio context.
- Feature folders for auth, portfolios, members, assets, transactions, dashboard, and assistant.
- An explicit TypeScript API type layer wrapped by a small `fetch` client. OpenAPI generation is deferred until contract size or additional consumers justify it.
- TanStack Query owns server cache, invalidation, and request states. It does not duplicate domain state in another global store.
- React Hook Form and Zod handle interactive form state and fast client feedback. Server validation errors are mapped back to fields.
- shadcn/ui primitives plus Tailwind produce accessible, intentional views without inventing a design-system framework.
- Recharts renders allocation charts from server-returned values. A table remains the precise and accessible representation.

Query keys are resource scoped, for example `['portfolio', id, 'assets']`. After a confirmed transaction, invalidate asset balance, transaction history, dashboard, and relevant assistant summary queries.

Permission flags returned with resources may guide UI affordances, but the role/capability calculation remains authoritative on the server.

## 9. API contract strategy

- [API.md](./API.md) is the current human-readable contract, mirrored by explicit TypeScript request/response types in the client.
- An OpenAPI 3.1 document and generated client types are an evolution point, not an implemented build step.
- Laravel API Resources and feature tests verify examples and serialization, especially decimal strings and errors.
- All endpoints are versioned under `/api/v1`; breaking changes require a new version or a coordinated migration.
- Timestamps are RFC 3339 UTC strings. Currency codes and enum values use stable lowercase/uppercase conventions documented in the schema.
- Lists use cursor pagination where history may grow; small membership lists can return bounded arrays.

The shared contract and TypeScript checking reduce drift but do not replace runtime validation. Laravel validates all requests, and the browser treats every network failure as possible.

## 10. Operational and failure behavior

- Each response/stream includes a request ID that also appears in structured logs.
- Validation failures are `422`; unauthenticated is `401`; forbidden is `403`; missing is `404`; conflicts such as last-owner or negative holdings are `409`; rate limiting is `429`.
- Database transactions wrap invariant-sensitive writes. Expected conflicts produce stable machine-readable error codes.
- OpenAI timeout/failure returns an assistant-specific error without degrading portfolio CRUD or dashboard availability.
- No queue or Redis is required for the MVP. Add them when shared assistant state, provider ingestion, or measured latency makes the operational cost worthwhile.

## 11. Deliberate trade-offs

| Decision | Benefit | Cost / future change |
| --- | --- | --- |
| Modular monolith | Easy to run, trace, test, and explain | Independent scaling requires later extraction |
| Sanctum cookie auth | Strong first-party SPA defaults and CSRF protection | Requires careful same-site/CORS configuration |
| Direct membership table | Transparent portfolio-scoped roles | More complex permission models may later need capabilities |
| Transaction-derived quantities | Auditable and avoids duplicate balance drift | Aggregate cost grows; snapshot/cache only after measurement |
| Manual one-currency valuation | Honest, coherent MVP | No cross-currency or live-market accuracy |
| Raw OpenAI tool loop | Minimal dependencies and visible security boundary | More orchestration code if workflows become complex |
| Stateless bounded chat | Less sensitive storage and schema | No durable cross-device conversation history |
| Read-only AI tools for this phase | Keeps the authorization boundary small and prevents model-initiated mutations | A separately confirmed write workflow remains future work |

## 12. Evolution points

- Market data: add provider adapters, a `price_quotes` table, scheduled jobs, and provenance/staleness policy without changing transaction history.
- FX: add dated FX quotes and define valuation timestamps before allowing asset currency to differ from portfolio base.
- Scale: add materialized balance snapshots/cache keyed by last transaction, with reconciliation against the ledger.
- Advanced accounting: introduce accounts, double-entry postings, lots, and performance calculations as a separate, explicit domain migration.
- Real time: publish domain events after committed writes, then add SSE/WebSocket fan-out if collaboration becomes a product need.
