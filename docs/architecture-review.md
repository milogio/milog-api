# MiLog API Architecture Review

Date: 2026-09-17

## Decision

Keep MiLog as a Laravel modular monolith backed by PostgreSQL. This is the
right stack for the current API and its next stage of expansion. Do not split
event ingestion and timeline reads into separate services until measured load
or independent deployment requirements justify the operational cost.

Use Redis for rate limiting, caching, and queued enrichment only when those
features are introduced. Keep `milog-ui` as the separate frontend and retire
the Laravel Blade, Vue, Mix, and Bootstrap toolchain after the frontend access
log confirms that no required traffic remains.

## Current Architecture

```text
API client / milog-ui
        |
        v
      Nginx
        |
        v
 Laravel 13 / PHP 8.5
   |                |
   |                +-- Passport-protected legacy site/page API
   |
   +-- X-API-Key middleware
          |
          +-- POST /api/v1/events
          +-- GET  /api/v1/timeline
                     |
                     v
                PostgreSQL 17

Redis 5 is provisioned but is not currently used by application behavior.
The legacy Laravel frontend is disabled by default and access is logged.
```

The public API has a good initial separation of concerns: HTTP validation,
tenant resolution, event creation, resource serialization, and message
formatting are distinct. The OpenAPI document is also separated from the
legacy Passport API.

## Technology Decisions

| Area | Decision | Rationale |
| --- | --- | --- |
| Application | Keep Laravel 13 | Productive, supported, and sufficient for API, queue, auth, and operational needs. |
| Runtime | Keep PHP 8.5 | Supported by Laravel 13 and provides a long support runway. Pin production patch versions. |
| Primary data | Keep PostgreSQL 17 | Strong fit for append-heavy events, tenant indexes, JSONB, partitioning, and read replicas. |
| Cache and queue | Upgrade Redis, then adopt selectively | Redis 5 is obsolete. The API does not yet need Redis in its synchronous path. |
| Frontend | Use separate `milog-ui` | Avoids two frontend stacks and lets the API deploy independently. |
| Authentication | Keep API keys for ingestion; reassess Passport | API keys suit server-to-server ingestion. Passport is only justified if legacy OAuth consumers remain. |
| Deployment | Keep containers; separate development and production definitions | Current Compose setup is development-oriented and should not be treated as a production topology. |
| Service shape | Keep a modular monolith | Current size and coupling do not justify microservices. Extract only from measured pressure. |

## Findings And Risks

### High Priority

1. Timeline indexes do not match timeline ordering.

   Queries order by `occurred_at DESC, created_at DESC`, but all tenant and
   filter indexes end in `created_at`. Large tenant timelines will require
   increasing sort work and offset scans. Add indexes led by tenant/filter and
   ending in `occurred_at DESC, created_at DESC, id DESC`, then verify with
   `EXPLAIN (ANALYZE, BUFFERS)` against representative data.

2. Pagination is offset-based and has no stable final tie-breaker.

   Add `id` to ordering and move the public contract to cursor pagination.
   This avoids slow deep pages and reduces duplicate or skipped rows while new
   events arrive. Preserve v1 behavior until a compatible migration is agreed.

3. Event ingestion has no idempotency contract.

   Retries can create duplicate events. Accept an idempotency key or a
   producer-supplied event ID, enforce uniqueness per tenant, and return the
   original result for repeated requests.

4. Runtime infrastructure includes obsolete images and development defaults.

   Redis 5 and Nginx 1.17 should be replaced with supported pinned releases.
   Database credentials, published database ports, bind mounts, local TLS
   certificates, and `restart: always` belong in a development-only Compose
   file. Production should use secrets, health checks, immutable images,
   resource limits, backups, and managed data services where practical.

### Medium Priority

5. API-key authentication writes to PostgreSQL on every request.

   Updating `last_used_at` synchronously turns reads into writes and can become
   a hot path. Sample or debounce the update through Redis/queue, and add key
   status, expiry, revocation, rotation, and audit fields before broader use.

6. Tenant isolation depends on every query remembering the tenant predicate.

   The current controller does this correctly, but the invariant is manual.
   Introduce a tenant-scoped repository/query object and adversarial isolation
   tests. Consider PostgreSQL row-level security only if stronger defense in
   depth becomes necessary; it adds operational complexity.

7. Expansion controls are missing.

   Add endpoint-specific rate limits, payload-size limits, metadata depth/size
   validation, request IDs, structured logs, latency/error metrics, and health
   endpoints. Set retention and deletion policies before event volume grows.

8. Redis is deployed without an application workload.

   Remove it from the required local stack until queues or distributed rate
   limits are enabled, or upgrade it and add a worker service plus failed-job
   monitoring. Avoid infrastructure that appears essential but is untested.

9. The legacy and public APIs share a codebase without an explicit ownership
   boundary.

   Move public API code toward an `App\\MiLog` or domain-oriented namespace.
   Inventory `/api/user`, `/api/sites`, `/api/pages`, and Passport consumers;
   remove them and Passport if they are no longer used by `milog-ui` or another
   client.

### Lower Priority

10. The Laravel frontend build is broken and duplicates `milog-ui`.

    Vue 2 is paired with Vue Loader 17, causing the production build to fail.
    Since the frontend is disabled, do not invest in repairing this stack
    unless it must be restored. After the observation window, remove Laravel
    UI, Mix, Vue, Bootstrap, jQuery, Blade auth views, compiled assets, and web
    auth routes in one tested change.

11. Configuration naming is inconsistent.

    `MiLog_TIMELINE_PER_PAGE` should become `MILOG_TIMELINE_PER_PAGE`. Validate
    the value and cap client-controlled page sizes if that capability is added.

12. The event schema needs explicit lifecycle semantics.

    Make `occurred_at`, `log_level`, and `metadata` database defaults/nullability
    match application guarantees. Decide whether events are immutable, how
    tenant deletion interacts with audit retention, and whether late-arriving
    events are accepted indefinitely.

## Target Architecture

```text
milog-ui / producer services
            |
     CDN or load balancer
            |
      Laravel API replicas
       |       |       |
       |       |       +-- structured logs, metrics, traces
       |       +---------- Redis rate limits / cache
       +------------------ PostgreSQL primary
                              |
                              +-- backups / PITR
                              +-- optional read replica

Optional queue workers consume post-commit jobs for enrichment, webhooks, or
analytics. The durable event write remains synchronous and authoritative in
PostgreSQL. Queue adoption must not make accepted events lossy.
```

## Expansion Triggers

Do not add infrastructure based only on anticipated scale. Use these triggers:

| Signal | First response | Later response |
| --- | --- | --- |
| Timeline p95 exceeds target | Correct indexes and cursor pagination | Read replica or purpose-built read model |
| Ingestion p95 exceeds target | Remove synchronous side effects; tune DB pool | Batch endpoint or partitioning |
| Events table maintenance grows | Retention policy and time partitioning | Archive tier |
| Enrichment slows writes | Post-commit queued jobs | Separate worker deployment |
| Search becomes product-critical | PostgreSQL full text/trigram first | Dedicated search engine when measured limits appear |
| Teams need independent releases | Strengthen module contracts | Extract a service only with clear ownership |

## Delivery Roadmap

### Phase 1: Reliability Baseline

- Add matching timeline indexes and stable ordering.
- Add idempotent ingestion and tests.
- Add API-key lifecycle controls and endpoint-specific rate limiting.
- Add health/readiness endpoints, request IDs, structured logs, and baseline
  request/error/latency metrics.
- Upgrade Nginx and Redis images; split development and production deployment
  configuration.
- Add CI checks for PHPUnit, OpenAPI linting, Composer audit, and npm audit.

### Phase 2: API And Boundary Cleanup

- Confirm consumers of the legacy Passport API and Laravel frontend.
- Remove the dormant Laravel frontend dependencies after the access-log window.
- Remove Passport and legacy site/page models only after consumer verification.
- Encapsulate tenant-scoped event reads and writes in a domain module.
- Define retention, deletion, backup, restore, and disaster recovery policies.

### Phase 3: Measured Scale

- Load test ingestion and timeline reads with realistic tenant distributions.
- Adopt cursor pagination and publish its OpenAPI contract.
- Enable Redis-backed queues for enrichment/webhooks when a use case exists.
- Add PostgreSQL partitioning or a read model only when query plans and table
  maintenance demonstrate the need.

## Validation Gates

Before calling the API production-ready for broader expansion, require:

- Tenant-isolation, idempotency, rate-limit, pagination, and key-revocation tests.
- A passing dependency security audit with documented exceptions.
- Load-test targets for sustained ingestion, timeline p95/p99, and error rate.
- Backup restore rehearsal and a stated recovery point/time objective.
- Dashboards and alerts for request failures, queue failures, database pressure,
  and storage growth.
- A documented migration and rollback path for every public API contract change.

## Review Verification

- The latest successful Docker test run completed 12 tests with 41 assertions.
- The current review could not repeat PHP tests because Docker was unavailable
  and PHP/Composer are not installed on the host.
- `npm run production` currently fails in `ExampleComponent.vue` through the
  incompatible Vue 2 / Vue Loader 17 toolchain.
- A fresh npm audit could not run because registry network access was
  unavailable; the previously observed audit reported 19 vulnerabilities.
