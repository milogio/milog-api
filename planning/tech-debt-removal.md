# Tech Debt and Unused Feature Removal Plan

## Goal and boundary

Reduce the application to its supported public event API and tenant-bound UI
authentication contract. First deliver the self-service signup, validation,
temporary credentials, and paid entitlement flow in
[`signup-billing-api-credentials.md`](signup-billing-api-credentials.md). Then
remove legacy features after their consumers are identified. This plan
coordinates the detailed authentication sunset in
[`deprecate-unused-authentication.md`](deprecate-unused-authentication.md); it
does not authorize an immediate route or data deletion.

The supported paths to preserve are `POST /api/v1/events`,
`GET /api/v1/timeline`, and the five `/api/v1/auth/*` endpoints. Preserve API-key
tenant isolation, UI token issue/refresh/revocation, and both timeline
pagination modes throughout cleanup.

## Current inventory (source inspection, 2026-10-06)

| Candidate | Evidence in this repository | Disposition |
| --- | --- | --- |
| Laravel-rendered web UI and session auth | `routes/web.php` registers `/`, `Auth::routes()`, and `/home` behind `milog.frontend`; `MILOG_FRONTEND_ENABLED` defaults to false. Blade auth/home views and `app/Http/Controllers/Auth/*` still exist. | Sunset and remove after deployed-route usage and `milog-ui` checks. Do not treat the default-off flag as proof of no consumers. |
| Unversioned user/site/page API | `routes/api.php` still exposes `/api/user`, `/api/sites`, and `/api/pages` under `auth:api`. These routes are outside the documented supported contracts. | Inventory clients, announce sunset, then remove routes before deleting domain code. Prioritize because the old site/page authorization model is separate from tenant-scoped events. |
| Legacy site/page/component domain | `SiteController`, `PageController`, `Site`, `Page`, `Component`, old factories/seeders, and `User::sites()` refer to the legacy domain. `ComponentController` is a stub with no route registration. | Remove code after route removal and reference scan. Keep historical migrations; drop tables only in a later data-retention change. |
| Laravel frontend build | `webpack.mix.js`, `resources/js`, `resources/sass`, compiled `public/js` and `public/css`, and all npm dependencies serve the old UI. The architecture review reports the Vue build is broken. | Remove after web-route sunset; then delete `package.json` and lockfile if no remaining Node-based repo task uses them. Recheck CI and deployment image references first. |
| Passport | `UiTokenService` calls `Passport::token()` and `User::createToken()`; `auth:api` protects UI endpoints. Production loads signing keys. | **Retain.** Separately review unused grants, clients, and package routes. Remove Passport only after a replacement token authority is designed and migrated. |
| Redis | Production sets `CACHE_DRIVER`, `QUEUE_CONNECTION`, and `SESSION_DRIVER` to Redis; the API uses throttling. No application job dispatch or explicit cache calls were found. | Measure actual Redis use. If retained for shared rate limits, keep cache and Redis service while removing unused queue/session settings. If removed entirely, provide a shared rate-limit store first. Never switch multi-replica throttling to per-process memory by accident. |
| Scaffold and optional services | `routes/channels.php`, `BroadcastServiceProvider`, `routes/console.php`'s `inspire`, `ExampleComponent.vue`, `ComponentController`, `guzzlehttp/guzzle`, `laravel/tinker`, mail/Pusher/AWS example env entries, and password-reset/failed-job schema have no obvious supported feature owner in source. | Run dependency and runtime checks. Remove isolated examples early; remove packages/config/schema only after confirming framework and operational use. |

Repository search is evidence of references, not evidence of production inactivity.
No production traffic, OAuth client inventory, `milog-ui` source, or deployment
telemetry was available in this review. The Docker daemon was inaccessible, so
runtime routes and tests were not verified here.

## Ordered change sets

### 1. Establish the baseline and owners

- Export `php artisan route:list` from a working environment, including
  Passport-provided routes. Map each candidate route to a client, owner,
  replacement, environment, and last observed successful request.
- Search `milog-ui`, other clients, CI, runbooks, and deployment manifests for
  candidate URLs, old OAuth flows/client IDs, frontend flags, asset URLs, and
  Redis/queue/session settings. Inventory OAuth clients and token use without
  recording secrets.
- Add aggregate route/client telemetry for the legacy API and review the
  existing frontend access audit. Observe at least one release cycle and the
  agreed infrequent-use window; follow the 30-day default in the auth sunset
  plan unless the owner records an exception. Resolve unexplained traffic.
- Capture baseline test results, route list, production configuration, database
  row counts, and backup/restore status. Keep this inventory in the repo.

### 2. Retire reachable legacy HTTP features

- Publish deprecation and sunset details for known `/api/user`, `/api/sites`,
  `/api/pages`, and web UI consumers. Keep the frontend disabled in deployed
  environments. Migrate callers to the versioned API or a documented
  replacement.
- After the evidence gate, remove old API route registrations and web routes in
  separate reviewable changes. Remove corresponding controllers, web-only
  middleware, and auth views. Delete frontend flag/config and obsolete route
  names only after routes are gone.
- Assert removed API paths return JSON `404` and never redirect to a web login.
  Confirm the public API and all UI auth endpoints still work across tenants.

### 3. Prune orphaned code and dependencies

- Remove the site/page/component models and `User::sites()` once no retained
  code or consumer needs them. Replace `DatabaseSeeder` with intentional tenant
  and user fixtures; remove legacy factories/seeders and the
  `laravel/legacy-factories` package if no tests use them.
- Remove Laravel UI/Mix/Vue/Bootstrap/jQuery assets and build files. Remove
  `laravel/ui` and unused direct npm packages; regenerate lockfiles and check
  production Docker builds and CI. Delete compiled assets only after confirming
  no external page still requests them.
- Remove obvious unregistered examples and scaffolding, then inspect actual
  package usage before pruning `guzzlehttp/guzzle`, `laravel/tinker`, mail,
  broadcasting, password reset, or framework middleware/config. Prefer small
  package/config changes with one focused verification pass each.

### 4. Simplify runtime services

- Compare Redis connection and command metrics with application behavior.
  Decide whether it is the shared rate-limit/cache store. Remove unused Redis
  queue/session settings and any worker assumptions if no jobs or web sessions
  exist; update Compose, PHP extensions, env examples, and infrastructure tests
  together. Remove the Redis service only when throttling and any other shared
  state have a tested replacement.
- Keep Passport's personal-access token path and signing keys. Inventory old
  grants and OAuth routes separately; disable and revoke only confirmed legacy
  clients. Record an architecture decision before any Passport replacement.

### 5. Archive and remove old data structures

- Inventory rows, ownership, retention obligations, and recovery needs for
  sites, pages, components, pivots, password resets, failed jobs, and OAuth
  tables. Take a verified backup/export before destructive changes.
- After application releases no longer read/write legacy tables and the
  recovery window ends, add new forward migrations to drop only approved
  tables. Never edit historical migrations already applied in deployed
  environments. Keep `users`, memberships, UI refresh tokens, and Passport
  tables needed by live UI auth.

## Verification and rollback gates

- For each code change: run the PHP suite, route-list assertions, public API
  contract tests, UI login/refresh/logout tests, tenant-isolation tests, and
  production image/config checks relevant to the change. Update tests that
  currently assert the old frontend can be enabled or require Redis to exist.
- For frontend removal: confirm build/CI no longer invoke Mix and smoke-test
  `milog-ui` against the API. For Redis changes: exercise rate limits with the
  intended replica topology. For schema changes: verify backup restoration in
  a disposable environment before migration.
- Deploy route removal separately from schema deletion. Roll back a route/code
  release if a missed client is found during the recovery window; restore data
  only from the verified backup path. Record route and data removal dates.

## Done when

- Every removed surface has an owner, usage evidence, replacement or explicit
  retirement decision, and a sunset record.
- No legacy web or unversioned site/page/user route is reachable; no orphaned
  models, views, assets, dependencies, or unused runtime service remain.
- Public event and timeline behavior, tenant-bound UI auth, and cross-tenant
  isolation pass their contract tests in the release environment.
- Destructive database changes have a tested restore path and documented
  retention decision.
