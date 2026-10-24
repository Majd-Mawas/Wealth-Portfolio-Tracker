# Wealth Portfolio Tracker API

Laravel REST API for authentication, portfolio memberships, assets, append-only transactions, manual valuation, and the read-only OpenAI portfolio assistant.

The complete installation guide, environment reference, demo credentials, implemented scope, and trade-offs are maintained in the repository [README](../README.md). API behavior is documented in [docs/API.md](../docs/API.md), with assistant security details in [docs/AI_ASSISTANT.md](../docs/AI_ASSISTANT.md).

Backend-only commands:

```bash
composer install
php artisan migrate --seed
php artisan serve
php artisan test
vendor/bin/pint --format agent
```

Tests use in-memory SQLite and do not modify the configured MySQL database.
