# Ledgerly frontend

React client for the Wealth Portfolio Tracker Laravel API.

## Local setup

1. Copy `.env.example` to `.env` and set `VITE_API_URL` to the Laravel origin (no trailing slash).
2. Ensure the same frontend origin is configured as `FRONTEND_URL` in the backend so Sanctum and CORS accept credentialed requests.
3. Run `npm install`, then `npm run dev`.

Authentication uses Laravel Sanctum's stateful cookie flow. The client first requests `/sanctum/csrf-cookie`, sends all API requests with credentials, and forwards the decoded XSRF cookie for mutations.

The UI uses portfolio capability flags to avoid offering unavailable actions, while the Laravel policies remain the source of truth. Asset prices are displayed and submitted as manually dated prototype prices; the frontend does not fetch or infer market prices.

## Verification

Run `npm run build` to type-check and produce the optimized Vite build.
