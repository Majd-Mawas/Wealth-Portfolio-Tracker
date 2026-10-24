# Embedded AI Portfolio Assistant

## Scope

The implemented assistant is intentionally read-only. It can summarize one active portfolio, list its assets, and list its transactions. It cannot create or update records, manage memberships, fetch arbitrary URLs, execute code, access the filesystem, or run SQL supplied by either the user or model.

The integration follows the OpenAI Responses API function-calling loop described in the [official OpenAI function-calling guide](https://developers.openai.com/api/docs/guides/function-calling). The model is configured through `OPENAI_MODEL`; the prototype default is `gpt-6-luna` and may be changed without altering domain code.

## Request and tool execution flow

```text
Authenticated React client
  POST /api/v1/portfolios/{portfolio}/assistant/chat
        |
        | Sanctum session + CSRF + per-user rate limit
        v
Portfolio membership middleware + ChatRequest
        |  validates bounded user/assistant history
        v
AssistantService (maximum four tool rounds)
        |
        +--> OpenAIResponsesClient --> OpenAI Responses API
        |                                  |
        |                         function_call + call_id
        v                                  |
AssistantToolRegistry <--------------------+
        |  exact allowlist + server validation + active portfolio lock
        v
AssistantPortfolioReadService
        |  Gate::forUser(...)->authorize('view', $portfolio)
        v
Eloquent / ValuationService --> minimized structured JSON
        |
        +--> function_call_output paired by call_id --> model answer
        |
        +--> typed SSE events --> React chat panel
```

The authenticated `User` is always read from the Sanctum session. Neither the chat request nor a tool schema accepts `user_id`, `role`, a policy result, database connection details, or credentials.

## Tool contracts

| Tool | Arguments | Result | Authorization |
| --- | --- | --- | --- |
| `get_portfolio_summary` | active `portfolio_id` | total manual value, valuation time, allocation, asset count | portfolio `view` policy |
| `list_portfolio_assets` | active `portfolio_id`, nullable type, limit 1-50 | minimized active asset and manual-price fields | portfolio `view` policy |
| `list_portfolio_transactions` | active `portfolio_id`, nullable asset/kind, limit 1-50 | minimized reverse-chronological ledger entries | portfolio `view` policy |

Provider-facing schemas use strict JSON Schema and `additionalProperties: false`. Laravel independently validates every decoded call, rejects unknown fields, caps limits, scopes asset IDs to the active portfolio, and requires the model-supplied portfolio ID to match the route portfolio. Strict provider schemas improve model behavior but are not treated as a security control.

Tool results are JSON objects with either `{"ok": true, "tool": ..., "data": ...}` or a stable structured error. Results omit membership data, user emails, database fields unrelated to the question, credentials, and authorization internals.

## Security boundaries

- `auth:sanctum` protects the route; the actor is never client supplied.
- `portfolio.member` hides portfolio existence from outsiders before any OpenAI call.
- `ChatRequest` rechecks the portfolio `view` policy and accepts only bounded `user`/`assistant` history. Client-created tool messages are rejected.
- Every read service rechecks `Gate::forUser($actor)->authorize('view', $portfolio)` so a controller or tool-registry mistake cannot bypass policy enforcement.
- The registry contains a fixed `match` allowlist. There is no generic method invocation, SQL input, query-language tool, or dynamic class resolution.
- The active route portfolio is an immutable execution boundary. A prompt-injected call for another portfolio is returned to the model as `invalid_tool_arguments` without querying that portfolio.
- System instructions identify asset names, transaction notes, tool outputs, and prior messages as untrusted data rather than instructions. Authorization does not depend on prompt obedience.
- The API key exists only in backend environment configuration. Provider errors are reported server-side and converted to a generic SSE error; secrets, raw responses, hidden prompts, and stack traces are not streamed.
- Requests send `store: false`. Production deployment still requires review of the organization's OpenAI data controls, retention requirements, and financial-data disclosure language.
- Assistant requests are limited to ten per authenticated user per minute, twelve messages, 4,000 characters per message, 12,000 characters total, four tool rounds, and configured provider timeouts/output tokens.

## Streaming behavior

Laravel returns typed server-sent events over a credentialed `POST`, consumed with `fetch()` and a small incremental SSE parser:

- `meta`: application request ID;
- `tool_started`, `tool_completed`, `tool_error`: visible tool progress and safe failures;
- `text_delta`: answer text;
- `error`: safe assistant/provider failure;
- `done`: terminal reason.

The browser can abort the fetch. The application streams metadata and tool progress between Responses API rounds and splits the completed final answer into text frames. Individual provider responses are currently buffered by Laravel's HTTP client. Direct provider-token passthrough is a reasonable later optimization, but it would require parsing and reassembling provider SSE function-call argument deltas while preserving the stable application event contract.

## Framework selection trade-offs

The implementation uses Laravel's built-in HTTP client instead of adding an OpenAI PHP SDK, Agents SDK, LangChain, or a graph runtime.

Benefits:

- the request payload, strict tool schemas, `call_id` correlation, round cap, and failure behavior remain directly reviewable;
- `Http::fake()` isolates provider calls in feature tests without a second mocking layer;
- no framework receives database credentials or broad application access;
- three deterministic read tools do not justify memory, graph, retrieval, or agent-runtime abstractions.

Costs:

- provider response parsing and future token-level streaming are maintained locally;
- switching providers requires a small adapter refactor;
- a larger tool catalog or multi-agent workflow would benefit from a dedicated contract and orchestration library.

This trade-off is appropriate for the bounded take-home scope. The assistant service and tool registry provide explicit seams for later extraction if measured complexity warrants it.

## Verification coverage

`AssistantAuthorizationTest` fakes the exact OpenAI endpoint and prevents stray network requests. It verifies:

- unauthenticated requests return `401` without contacting OpenAI;
- Owner and Viewer memberships can execute read tools;
- an outsider receives `404` before any provider call;
- prompt injection that asks for a different portfolio produces a structured tool error and no leaked asset data;
- invalid limits and unexpected identity fields are rejected server-side;
- client-supplied tool history is rejected before any provider call.

## Configuration

Set `OPENAI_API_KEY` only in the backend runtime environment. Optional controls are documented in `backend/.env.example`: `OPENAI_MODEL`, `OPENAI_BASE_URL`, connection/response timeouts, and maximum output tokens. Never expose these values through Vite variables or browser responses.
