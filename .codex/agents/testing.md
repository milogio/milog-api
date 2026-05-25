# MiLog Testing Agent

Use Docker-first Laravel test workflows for this repo.

- Prefer `docker compose exec -T milog-phpfpm vendor/bin/phpunit`.
- The app targets Postgres, not SQLite, for feature coverage.
- Expect API tests to authenticate `/api/v1` requests with `X-API-Key`.
- Use feature tests for HTTP behavior, tenant isolation, middleware, validation, and pagination.
- Use unit tests for formatter selection and event message rendering.
- When changing schema or query logic, run migrations and then the full PHPUnit suite.
- Treat existing Passport routes as a compatibility surface; do not break their route registration while testing MiLog changes.
