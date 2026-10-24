# Wealth Portfolio Tracker - API Contract

Status: living contract. Implemented behavior is described directly; deferred behavior is labeled explicitly.

## 1. Conventions

- Base path: `/api/v1`
- Transport: HTTPS in non-local environments.
- Authentication: Laravel Sanctum stateful SPA session cookie.
- Request/response media type: `application/json`, except assistant chat streaming.
- Timestamps: RFC 3339 UTC, for example `2026-10-24T12:30:00Z`.
- Currency: uppercase ISO 4217 code.
- Financial values: JSON strings, never JSON numbers.
- IDs: JSON integers. Clients must treat them as identifiers, not infer authorization from them.
- Unknown request fields are rejected for mutation endpoints to surface contract drift.

The browser first calls `GET /sanctum/csrf-cookie` outside the versioned API, then sends the `X-XSRF-TOKEN` header on state-changing requests.

## 2. Response and error shapes

### Single resource

```json
{
  "data": {
    "id": 42
  }
}
```

### Collection

```json
{
  "data": [],
  "meta": {
    "next_cursor": null
  }
}
```

### Error

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The submitted data is invalid.",
    "fields": {
      "quantity": ["Quantity must have at most 8 decimal places."]
    },
    "request_id": "01JAZ..."
  }
}
```

`fields` is present only for field validation. Stable `code` values are for program behavior; `message` is for people.

### Status codes

| Status | Meaning |
| ---: | --- |
| `200` | Successful read/update/action |
| `201` | Resource created |
| `204` | Successful response with no body |
| `401` | No valid authenticated session |
| `403` | Authenticated but lacks capability |
| `404` | Resource absent or intentionally not exposed to this actor |
| `409` | Domain conflict such as last Owner, negative holdings, duplicate reversal, or replay |
| `422` | Request validation failure |
| `429` | Rate limit exceeded |
| `502` / `503` | Upstream assistant unavailable / temporary service failure |

## 3. Shared types

### Decimal string

Canonical request pattern:

```text
^-?(0|[1-9][0-9]*)(\.[0-9]{1,8})?$
```

Additional range/sign rules apply per field. Responses use a consistent fixed scale where practical, such as `"10.50000000"`; clients must not depend on insignificant trailing zeros.

### Role

```text
owner | editor | viewer
```

### Asset type

```text
stock | crypto | real_estate | cash
```

### Transaction kind

```text
buy | sell | deposit | withdrawal | adjustment | reversal
```

## 4. Authentication

### `POST /auth/register`

Public, rate limited.

Request:

```json
{
  "name": "Maya Haddad",
  "email": "maya@example.com",
  "password": "a-long-password",
  "password_confirmation": "a-long-password"
}
```

Response `201`: current `User` resource and an authenticated session.

### `POST /auth/login`

Public, aggressively rate limited by email plus IP.

```json
{
  "email": "maya@example.com",
  "password": "a-long-password",
  "remember": false
}
```

Response `200`: current `User` resource. The session ID is rotated.

### `POST /auth/logout`

Authenticated. Invalidates the current session. Response `204`.

### `GET /user`

Authenticated. Response:

```json
{
  "data": {
    "id": 7,
    "name": "Maya Haddad",
    "email": "maya@example.com"
  }
}
```

## 5. Portfolios

### Portfolio resource

```json
{
  "id": 42,
  "name": "Haddad Household",
  "base_currency": "USD",
  "role": "owner",
  "capabilities": {
    "update_portfolio": true,
    "manage_members": true,
    "manage_assets": true,
    "record_transactions": true,
    "delete_portfolio": true
  },
  "created_at": "2026-10-24T12:30:00Z",
  "updated_at": "2026-10-24T12:30:00Z"
}
```

`role` and `capabilities` describe the current authenticated user and are convenience fields for UI affordances. The server reauthorizes every action.

### `GET /portfolios`

Lists only portfolios where the actor is a member. Optional `cursor` query. Response `200` collection.

### `POST /portfolios`

Authenticated. Creates the portfolio and creator's Owner membership atomically.

```json
{
  "name": "Haddad Household",
  "base_currency": "USD"
}
```

Response `201`: Portfolio resource.

### `GET /portfolios/{portfolio}`

Any member. Response `200`: Portfolio resource.

### `PATCH /portfolios/{portfolio}`

Owner only.

```json
{
  "name": "Haddad Family Portfolio"
}
```

Changing `base_currency` is allowed only while the portfolio has no assets/transactions. Otherwise return `409 currency_change_not_allowed`; conversion is outside MVP scope.

### `DELETE /portfolios/{portfolio}`

Owner only. Soft-deletes the portfolio. Response `204`. Restoration is not exposed in the MVP.

## 6. Memberships

### Membership resource

```json
{
  "id": 81,
  "user": {
    "id": 9,
    "name": "Omar Saleh",
    "email": "omar@example.com"
  },
  "role": "editor",
  "created_at": "2026-10-24T13:00:00Z"
}
```

### `GET /portfolios/{portfolio}/members`

Any member. The list is unpaginated for the household-sized MVP. Response `200`.

### `POST /portfolios/{portfolio}/members`

Owner only. Adds an existing user; invitation delivery is deferred.

```json
{
  "email": "omar@example.com",
  "role": "editor"
}
```

Response `201`. Unknown email returns a generic `422 member_not_found` without exposing a public user directory. An existing membership returns `409 membership_exists`.

### `PATCH /portfolios/{portfolio}/members/{membership}`

Owner only.

```json
{
  "role": "viewer"
}
```

Response `200`. Rejects demoting the last Owner with `409 last_owner_required`.

### `DELETE /portfolios/{portfolio}/members/{membership}`

Owner only. Response `204`. Rejects removal of the last Owner with `409 last_owner_required`.

The nested membership must belong to the route portfolio; cross-portfolio IDs return `404`.

## 7. Assets

### Asset resource

```json
{
  "id": 310,
  "portfolio_id": 42,
  "type": "stock",
  "name": "Example Corp",
  "symbol": "EXM",
  "currency": "USD",
  "current_price": "125.34000000",
  "price_as_of": "2026-10-24T10:00:00Z",
  "quantity": "12.50000000",
  "value": "1566.75000000",
  "metadata": {
    "exchange": "NASDAQ"
  },
  "archived_at": null,
  "created_at": "2026-10-24T12:30:00Z",
  "updated_at": "2026-10-24T12:30:00Z"
}
```

`quantity` and `value` are computed fields. Clients never send them when updating an asset.

### `GET /portfolios/{portfolio}/assets`

Any member. Query parameters:

- `type`: optional asset type;
- `include_archived`: `false` by default;
- `cursor`: optional pagination cursor.

Response `200` collection.

### `POST /portfolios/{portfolio}/assets`

Owner or Editor. May create an opening transaction atomically.

```json
{
  "type": "stock",
  "name": "Example Corp",
  "symbol": "EXM",
  "currency": "USD",
  "current_price": "125.34000000",
  "price_as_of": "2026-10-24T10:00:00Z",
  "metadata": {
    "exchange": "NASDAQ"
  },
  "opening": {
    "quantity": "12.50000000",
    "unit_price": "110.00000000",
    "occurred_at": "2026-09-01T09:00:00Z",
    "note": "Opening position"
  }
}
```

Rules:

- `currency` must equal the portfolio base currency.
- `cash` forces `current_price` to `"1.00000000"` and uses an opening `deposit`.
- `symbol` is optional and not globally unique.
- `opening` is optional; if present, quantity is positive.

Response `201`: Asset resource.

### `GET /portfolios/{portfolio}/assets/{asset}`

Any member. Response `200`. Asset must belong to portfolio.

### `PATCH /portfolios/{portfolio}/assets/{asset}`

Owner or Editor. Supports descriptive fields and manual current price; never quantity.

```json
{
  "name": "Example Corporation",
  "current_price": "129.10000000",
  "price_as_of": "2026-10-24T14:00:00Z",
  "metadata": {
    "exchange": "NASDAQ"
  }
}
```

Asset `type` and `currency` are immutable once transactions exist. Cash price cannot be changed from `1`.

### `DELETE /portfolios/{portfolio}/assets/{asset}`

Owner or Editor. Archives rather than hard-deletes. Requires computed quantity `0`; otherwise `409 asset_has_balance`. Response `204`.

## 8. Transactions

### Transaction resource

```json
{
  "id": 9001,
  "asset": {
    "id": 310,
    "name": "Example Corp",
    "type": "stock",
    "symbol": "EXM"
  },
  "kind": "buy",
  "quantity_delta": "2.50000000",
  "unit_price": "120.00000000",
  "fees": "1.25000000",
  "occurred_at": "2026-10-20T09:30:00Z",
  "note": "Monthly purchase",
  "source": "api",
  "reversal_of_id": null,
  "created_by": {
    "id": 7,
    "name": "Maya Haddad"
  },
  "created_at": "2026-10-24T12:30:00Z"
}
```

### `GET /portfolios/{portfolio}/transactions`

Any member. Reverse chronological cursor pagination. Optional filters:

- `asset_id`;
- `kind`;
- `occurred_from` and `occurred_to`;
- `cursor`.

Response `200` collection.

### `POST /portfolios/{portfolio}/assets/{asset}/transactions`

Owner or Editor. Accepts optional `Idempotency-Key` header. The same actor, route, and key with the same normalized request returns the original response; a different payload returns `409 idempotency_conflict`.

```json
{
  "kind": "buy",
  "quantity": "2.50000000",
  "unit_price": "120.00000000",
  "fees": "1.25000000",
  "occurred_at": "2026-10-20T09:30:00Z",
  "note": "Monthly purchase"
}
```

The client sends a positive `quantity`. The server derives the signed `quantity_delta` from `kind`:

- `buy`, `deposit`: positive;
- `sell`, `withdrawal`: negative;
- `adjustment`: request uses signed `quantity` and requires `note`.

Stock/crypto/real-estate accept buy, sell, adjustment. Cash accepts deposit, withdrawal, adjustment. A result below zero returns `409 insufficient_quantity`.

Response `201`: Transaction resource.

### `POST /portfolios/{portfolio}/transactions/{transaction}/reverse`

P1; Owner or Editor. Accepts optional `Idempotency-Key`.

```json
{
  "reason": "Duplicate entry"
}
```

Creates a linked reversal with the negated quantity delta and fee and the original unit price. Rejects reversal of a reversal and a second reversal with `409 transaction_already_reversed`. Response `201`: reversal Transaction resource.

There are intentionally no transaction update or delete endpoints.

## 9. Dashboard

### `GET /portfolios/{portfolio}/dashboard`

Any member. Values are calculated at request time from transactions plus manual prices.

```json
{
  "data": {
    "portfolio": {
      "id": 42,
      "name": "Haddad Household",
      "base_currency": "USD"
    },
    "total_value": "251566.75000000",
    "valued_at": "2026-10-24T14:05:00Z",
    "allocation_by_type": [
      {
        "type": "real_estate",
        "value": "220000.00000000",
        "percentage": "87.45390000"
      },
      {
        "type": "stock",
        "value": "31566.75000000",
        "percentage": "12.54610000"
      }
    ],
    "assets": [
      {
        "asset_id": 310,
        "name": "Example Corp",
        "type": "stock",
        "quantity": "12.50000000",
        "current_price": "125.34000000",
        "value": "1566.75000000",
        "percentage": "0.62280000",
        "price_as_of": "2026-10-24T10:00:00Z",
        "price_status": "manual"
      }
    ]
  }
}
```

If `total_value` is zero, all percentages are `"0.00000000"`. The response does not imply live market data.

## 10. Assistant

### `POST /portfolios/{portfolio}/assistant/chat`

Authenticated and rate limited per user. Content type is JSON; response content type is `text/event-stream`. The endpoint is consumed with streaming `fetch`, not browser `EventSource`, because it is a POST with a body and CSRF header.

```json
{
  "messages": [
    {
      "role": "user",
      "content": "How is this portfolio allocated?"
    }
  ]
}
```

Rules:

- The actor must be a portfolio member before any model call.
- Only `user` and `assistant` history roles are accepted from the client; tool messages are server-owned.
- Message count, per-message size, total size, and tool rounds are bounded.
- Client history is conversational context only and never authorization context.

SSE frames use an explicit `event` and JSON `data`:

```text
event: meta
data: {"request_id":"01JAZ..."}

event: text_delta
data: {"text":"Your largest allocation is "}

event: tool_started
data: {"tool":"get_portfolio_summary"}

event: tool_completed
data: {"tool":"get_portfolio_summary"}

event: tool_error
data: {"tool":"list_portfolio_assets","code":"invalid_tool_arguments","message":"The assistant supplied invalid tool arguments."}

event: done
data: {"finish_reason":"stop"}
```

`error` event:

```text
event: error
data: {"code":"assistant_upstream_unavailable","message":"The assistant is temporarily unavailable.","request_id":"01JAZ...","retryable":true}
```

The server never streams secrets, stack traces, raw provider errors, or hidden prompts. State-changing assistant tools and confirmation endpoints are intentionally not implemented.

### Tool contracts

Tool schemas are backend-owned JSON Schemas. The model cannot supply `user_id`, `role`, or an authorization decision.

Provider-facing function definitions use strict schemas. The Laravel adapter correlates each Responses API function call and function output with its provider `call_id`, then maps provider-specific typed stream events to the stable application SSE events above.

#### `get_portfolio_summary`

Input:

```json
{
  "portfolio_id": 42
}
```

Read permission: any member. Output is a compact form of dashboard totals and allocation.

#### `list_portfolio_assets`

Input:

```json
{
  "portfolio_id": 42,
  "type": "stock",
  "limit": 20
}
```

Read permission: any member. Server caps `limit` and minimizes fields.

#### `list_portfolio_transactions`

Input:

```json
{
  "portfolio_id": 42,
  "asset_id": 310,
  "kind": null,
  "limit": 20
}
```

Read permission: any member. Server caps history and does not expose unrelated portfolios.

## 11. Contract integrity between Laravel and React

Contract maintenance strategy:

1. The current React client keeps explicit request/response types beside a small fetch client that handles CSRF, credentials, errors, request IDs, and SSE parsing.
2. Commit an OpenAPI 3.1 specification and generate TypeScript types when contract size or additional clients justify the generation step.
3. Use Laravel feature tests to assert response shape, decimal serialization, authorization, and examples for high-risk endpoints.
4. Run type checking in CI; once generation is introduced, fail when committed generated output is stale.
5. Keep Zod focused on form UX. It mirrors relevant request constraints but does not replace server validation.

This strategy gives a decoupled PHP/TypeScript stack an explicit boundary without introducing a second full-stack framework.

## 12. Security checklist for every new endpoint/tool

- Is authentication required and rate limiting appropriate?
- Which exact policy ability is checked?
- Is the resource resolved within the authorized portfolio?
- Does the application service independently enforce the ability?
- Are decimal, enum, length, date, and unknown-field rules validated?
- Does the write need a database transaction, lock, idempotency, or confirmation?
- Could the response leak another user's identity or portfolio data?
- Are user-controlled strings treated as untrusted in HTML, logs, and LLM context?
- Is the action represented in tests for all three roles plus a non-member?
