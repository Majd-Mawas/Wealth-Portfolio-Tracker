# Wealth Portfolio Tracker - Delivery Plan

Status: planning only. Nothing described here should be read as implemented.

## 1. Goal and evaluation alignment

Build a focused prototype that demonstrates sound authorization, financial data modeling, a useful multi-asset dashboard, and a security-aware AI assistant. The plan intentionally favors a small, explainable system over breadth.

The assessment values prioritization, architectural pragmatism, permission modeling, agent integration quality, and effective use of AI coding agents. The implementation should therefore make its boundaries visible and test the dangerous paths rather than hide complexity behind frameworks.

## 2. Assumptions

- This is a single-region prototype for registered users and household-sized portfolios, not a brokerage or accounting ledger.
- A portfolio has one base currency. ISO 4217 codes are accepted, but the MVP does not perform foreign-exchange conversion.
- Every asset in a portfolio is valued in that portfolio's base currency.
- Market prices and real-estate estimates are entered manually. Live feeds and price-history charts are deferred.
- Memberships can initially be granted only to an already registered user by email. Invitation emails and pending invitations are deferred.
- Transactions describe changes in the quantity of one asset. The MVP is not double-entry accounting and does not automatically create a corresponding cash leg.
- Transactions are append-only. Corrections are represented by a reversal or a new adjustment, preserving history.
- The assistant is advisory by default. Any state-changing tool requires a separate, explicit confirmation from the user.
- The expected load is modest enough that synchronous dashboard aggregation is acceptable. Correctness and clarity are more valuable than premature caching.

## 3. Priorities and scope boundaries

### P0 - required vertical slice

These features form the minimum credible submission:

1. Authentication with Laravel Sanctum's first-party SPA cookie flow.
2. Portfolio CRUD for an authenticated owner.
3. Portfolio membership management with Owner, Editor, and Viewer roles.
4. Asset CRUD for stock, crypto, real estate, and cash assets.
5. Append-only transaction entry and history.
6. A dashboard showing total manual valuation and allocation by asset/type.
7. A streamed embedded AI assistant with read-only portfolio summary, asset, and transaction tools.
8. Consistent policy enforcement for HTTP controllers and AI tool execution.
9. Focused automated tests for permissions, decimal behavior, core calculations, and AI tool boundaries.
10. Clear setup, seed/demo instructions, and documentation of trade-offs.

### P1 - do if the P0 path is stable

- Transaction reversal endpoint with a link to the original transaction.
- Assistant cancellation/retry controls and richer tool-progress presentation.
- Dashboard breakdown by both asset and asset type.
- Accessible empty, loading, validation, and authorization-error states.
- Idempotency keys for transaction creation and confirmed assistant mutations.
- A small demo seed with users in all three roles.

### P2 - explicitly deferred

- Live or delayed market-data providers, FX feeds, and automated price refresh.
- Redis, queues, scheduled valuation jobs, or background synchronization.
- WebSockets, presence, collaborative editing, or cross-client live updates.
- Performance/time-weighted returns, realized gains, tax lots, dividends, and advanced analytics.
- Automated rebalancing or trade execution.
- Double-entry bookkeeping and linked cash settlement.
- Attachments, household entities beyond a portfolio, invitation emails, and social login.
- Long-term assistant conversation persistence, retrieval-augmented generation, vector databases, or arbitrary SQL tools.
- Native mobile applications and offline support.

The P2 items are valuable product work, but each introduces external reliability, accounting, infrastructure, or security concerns that would dilute the assessment's core signal.

## 4. Role capabilities

| Capability | Owner | Editor | Viewer |
| --- | ---: | ---: | ---: |
| View portfolio, assets, transactions, dashboard | Yes | Yes | Yes |
| Create/update/archive assets | Yes | Yes | No |
| Add/reverse transactions | Yes | Yes | No |
| Update portfolio settings | Yes | No | No |
| Add, change, or remove members | Yes | No | No |
| Delete portfolio | Yes | No | No |
| Use assistant read tools | Yes | Yes | Yes |
| Confirm assistant mutation tools | Deferred | Deferred | No |

Membership invariants:

- A newly created portfolio receives an Owner membership for its creator in the same database transaction.
- A portfolio must always have at least one Owner.
- Removing or demoting the last Owner is rejected. The affected membership rows are locked while this invariant is checked.
- Users cannot elevate their own role. Only an existing Owner can change memberships.
- Authorization is based on the authenticated user and database membership, never on a role supplied by the browser or language model.

## 5. Product behavior and acceptance criteria

### Authentication

- A user can register, sign in, retrieve the current session, and sign out.
- Authentication uses Secure, HttpOnly, SameSite cookies and CSRF protection in production.
- Unauthenticated API calls return `401`; authenticated but insufficiently privileged calls return `403`.

### Portfolios and memberships

- A user sees only portfolios where they have a membership.
- An Owner can edit portfolio name/base currency, manage members, and soft-delete the portfolio.
- An Editor or Viewer cannot access membership mutation endpoints.
- A non-member cannot infer private portfolio contents from nested endpoints.

### Assets and transactions

- Asset types are restricted to `stock`, `crypto`, `real_estate`, and `cash`.
- Financial inputs are decimal strings at API boundaries and are never calculated with binary floating point.
- Creating an asset may include an opening transaction; the asset and opening transaction commit atomically.
- Quantity is derived from the sum of all transaction deltas, including offsetting reversal records, avoiding a duplicated mutable balance.
- A transaction cannot reduce an asset quantity below zero in the MVP.
- A cash asset's valuation price is fixed at `1` and its quantity is the cash balance in the portfolio base currency.
- An asset with a non-zero balance cannot be archived until an adjustment or disposal brings it to zero.
- Reversal, if included, creates an equal and opposite record; it never edits or deletes the original.

### Dashboard

- The API computes asset values, portfolio total, and allocation percentages using decimal-safe operations.
- The dashboard identifies stale manual prices through `price_as_of` rather than implying real-time accuracy.
- Archived zero-balance assets are excluded from current allocation but remain available through transaction history.
- The frontend may convert server-provided percentages to JavaScript numbers for chart geometry only. Financial totals remain server-produced decimal strings.

### AI assistant

- Read tools can summarize only portfolios the current user may view.
- The implemented assistant tool set is read-only. State-changing tools and confirmation endpoints are deferred.
- Tool calls are allowlisted, schema validated, rate limited, round limited, and audit logged without storing secrets.
- The model never receives database credentials, the OpenAI API key, raw authorization objects, or arbitrary execution access.

## 6. Implementation phases

### Phase 0 - repository and contract foundation

- Create the Laravel API and React/TypeScript/Vite applications with one-command local setup.
- Configure MySQL, Sanctum stateful domains, CORS, CSRF, environment templates, linting, and test commands.
- Add the versioned `/api/v1` contract and consistent success/error shapes.
- Create migrations, factories, and representative seed data.

Exit criterion: both applications run locally, authentication smoke tests pass, and the schema is reproducible from an empty database.

### Phase 1 - authorization-first portfolio slice

- Implement memberships, policies, capability matrix, and portfolio application services.
- Implement portfolio and membership endpoints before asset mutations.
- Add feature tests covering every role and non-member access.

Exit criterion: the authorization matrix is executable as tests, including last-owner concurrency protection.

### Phase 2 - assets, transactions, and valuation

- Implement asset management and append-only transaction services.
- Add decimal-safe valuation queries/services and dashboard endpoint.
- Implement asset, transaction, and dashboard UI with robust form and error states.

Exit criterion: a seeded owner/editor/viewer scenario works end to end and calculation tests cover precision, zero totals, adjustments, negative-balance rejection, and reversals if the P1 endpoint is included.

### Phase 3 - secure AI vertical slice

- Define a small read-only tool registry: portfolio summary, assets, and transactions.
- Add the OpenAI function-calling loop with a strict maximum number of tool rounds.
- Add streamed text/tool events and a minimal assistant panel.
- Add fake-model contract tests and policy tests for every tool.

Exit criterion: Owner, Editor, and Viewer members can ask portfolio questions; outsiders cannot reach the model or read another portfolio; invalid tool arguments are rejected.

### Phase 4 - hardening and presentation

- Run backend/frontend tests, static analysis, formatting, and production builds.
- Review accessibility, responsive layout, log redaction, rate limits, and failure behavior.
- Finalize README setup, architecture notes, assumptions, trade-offs, and known gaps.
- Prepare a 10-15 minute walkthrough focused on scope, permission boundaries, agent tools, stack choices, and how AI coding agents were directed and reviewed.

Exit criterion: a fresh clone can be demonstrated without hidden setup steps, and documentation distinguishes implemented, partial, and deferred work.

## 7. Testing strategy

Testing follows risk, not just code coverage.

### Backend

- Feature tests for login/session behavior and CSRF-aware routes.
- Table-driven authorization tests for Owner, Editor, Viewer, non-member, and unauthenticated users across every resource action.
- Service tests proving controllers and assistant tools cannot bypass policy checks.
- Database tests for unique memberships, foreign keys, last-owner protection, transaction immutability, and non-negative holdings; add reversal uniqueness with the P1 reversal endpoint.
- Decimal tests with fractional crypto quantities, large real-estate values, rounding boundaries, and zero-total allocations.
- Contract tests for validation errors, decimal string serialization, pagination, and status codes.
- AI tests with a fake OpenAI endpoint: authorized read tools, malformed or excessive arguments, outsider denial before the provider call, and cross-portfolio prompt-injection attempts.

### Frontend

- Unit tests for schemas, API error normalization, permission-aware controls, decimal display formatting, and SSE parsing.
- Component tests for forms, assistant streaming/error states, loading/empty/error states, and chart fallbacks.
- A small set of end-to-end tests for owner setup, editor transaction entry, viewer mutation denial, and authorized assistant reads.

### Manual and security checks

- Attempt cross-portfolio ID substitution and direct calls to hidden UI actions.
- Confirm cookies, CSRF, CORS allowlist, rate limits, and log redaction under production-like settings.
- Verify the assistant cannot invoke unregistered tools or use tool output as instructions.
- Verify keyboard navigation, visible focus, labels, and readable dashboard states.

## 8. AI coding-agent execution discipline

AI coding agents will accelerate bounded implementation tasks, but responsibility stays with the candidate:

- Give each agent a narrow task with the relevant contract, authorization rule, and acceptance tests rather than asking it to "build the app."
- Establish migrations, policies, shared decimal rules, and API shapes before parallel feature work so generated code converges on one design.
- Prefer vertical slices that include tests. A task is not accepted because code was generated; it is accepted after diff review, test execution, and an explanation of its behavior and trade-offs.
- Do not give agents production secrets or allow them to invent provider credentials, financial rules, or authorization behavior.
- Use a clean Git history with small, reviewable commits and record material human corrections. Never claim generated or unverified behavior in the README or walkthrough.
- Run an integration pass after parallel work to find duplicated abstractions, inconsistent response shapes, policy gaps, and unnecessary dependencies.
- Keep the final implementation small enough that every included line and decision can be explained in the video.

## 9. Risks and mitigations

| Risk | Impact | Mitigation / conscious trade-off |
| --- | --- | --- |
| Authorization drift between controllers and AI tools | Critical data leak or mutation | Policies define capability; application services enforce them again and are the only mutation path. |
| LLM prompt/tool injection | Unauthorized data access | Fixed read-only tool allowlist, strict JSON schemas, route-bound portfolio context, server-derived actor context, policy rechecks, and round limits. |
| Decimal precision loss | Incorrect financial values | MySQL `DECIMAL`, decimal strings over JSON, decimal-safe PHP math, server-calculated totals, and no JS arithmetic for authoritative values. |
| Last Owner removed concurrently | Locked-out portfolio | Transaction plus row locking when changing/removing Owner memberships. |
| Duplicate transaction after retry | Incorrect holdings | Support an `Idempotency-Key` for write endpoints and confirmation token single-use semantics. |
| Manual prices become misleading | Incorrect expectations | Display `price_as_of`, label values as estimates, and defer claims of real-time pricing. |
| Stateless assistant history grows or is tampered with | Cost or confusing responses | Bound message count/size, treat history as untrusted conversation text, and never derive authorization from it. |
| Streaming complicates error handling | Broken UX after headers are sent | Emit typed error/done events with request IDs and keep portfolio CRUD independent of assistant availability. |
| OpenAI outage or timeout | Assistant unavailable | Core CRUD/dashboard remain independent; timeouts and friendly retryable errors isolate failure. |
| Scope expansion | Incomplete core | P0 exit criteria gate all P1/P2 work. Any cut is recorded honestly in the README and walkthrough. |

## 10. Key architectural decisions for review

1. **Laravel policies plus authorization inside application services.** HTTP endpoints and AI tools are adapters; neither is trusted to enforce access on its own.
2. **A relational membership table rather than a role package.** Three portfolio-scoped roles and a compact matrix do not justify another authorization framework.
3. **Append-only quantity transactions.** Holdings are derived from history, avoiding silent drift between a transaction ledger and a mutable quantity column.
4. **Manual prices in one base currency.** This yields a coherent valuation slice without pretending that provider/FX complexity is solved.
5. **Decimal strings end to end.** The database and backend own financial arithmetic; JavaScript numbers are limited to non-authoritative chart coordinates.
6. **Raw OpenAI tool calling behind a small registry.** The use case does not need LangChain/LangGraph. Fewer layers keep prompts, tool schemas, authorization, and failure handling inspectable.
7. **Read-only assistant first.** The model can retrieve only minimized portfolio data through three allowlisted tools; any future mutation workflow requires a separate design and explicit confirmation.
8. **Explicit cross-stack contracts.** A versioned human-readable API contract and checked TypeScript types keep the current scope inspectable; OpenAPI generation is deferred until its maintenance cost is justified.
9. **Synchronous valuation first.** Household-scale aggregates are cheap; queues and caches will be added only after measured need or external feeds.

See [ARCHITECTURE.md](./ARCHITECTURE.md) for component and data design, and [API.md](./API.md) for the proposed HTTP contract.
