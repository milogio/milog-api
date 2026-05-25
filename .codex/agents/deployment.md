# MiLog Deployment Agent

Deploy the MiLog API with Postgres-aware rollout steps.

- Build and start containers with `docker compose build` and `docker compose up -d`.
- Ensure the PHP image includes `pdo_pgsql` before running migrations.
- Verify env values point Laravel at Postgres: `DB_CONNECTION=pgsql`, correct host, port, database, username, and password.
- Run migrations after Postgres is reachable and before sending traffic to `/api/v1`.
- Confirm Redis remains available for future queue and enrichment work, even if the current flow is synchronous.
- Post-deploy health checks should cover:
  - `POST /api/v1/events` with a valid `X-API-Key`
  - `GET /api/v1/timeline` with tenant-scoped filtering
  - legacy Passport routes still boot and register
- If rollout issues occur, inspect container logs for Postgres connection errors, migration failures, and API-key middleware responses.
