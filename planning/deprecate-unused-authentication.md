# Deprecating Unused Authentication Code and Routes

## Goal

Remove the legacy Laravel UI/session authentication and unused OAuth-protected
site/page API without disrupting:

- the public tenant-scoped `X-API-Key` event API; or
- the planned tenant-bound authentication API for `milog-ui`.

Removal must follow observed usage, publish an explicit compatibility window,
and leave no reachable legacy login, registration, password-reset, implicit
grant, or unscoped user API path.

## Scope

### Candidate legacy surfaces

- Web routes registered by `Auth::routes()` plus `/`, `/home`, and `/logout`.
- Blade authentication views and the old Laravel-rendered frontend.
- `HomeController` and controllers under `app/Http/Controllers/Auth`.
- `GateFrontendAccess`, its `milog.frontend` configuration, and the
  `MILOG_FRONTEND_*` environment settings.
- Unversioned Passport-protected API routes:
  - `GET /api/user`;
  - `/api/sites` resource routes;
  - `/api/pages` resource routes.
- `SiteController`, `PageController`, and the legacy `Site`, `Page`, and
  `Component` domain if no non-authenticated code still uses it.
- Legacy seeders, factories, pivot tables, frontend assets, and npm build
  dependencies that exist only for the old UI.
- Passport's implicit grant and any obsolete password-grant/OAuth clients.

### Explicitly out of scope

- `/api/v1/events` and `/api/v1/timeline` or their `X-API-Key` middleware.
- `users`, password hashing, and framework auth abstractions needed by the new
  tenant-bound UI login.
- Passport itself while the tenant-bound authentication plan still selects it
  as the token authority. Its final removal or retention is an architecture
  decision, not part of the initial cleanup.
- Tenant membership, new login/refresh/logout endpoints, and token-family
  implementation, which are covered by
  `planning/tenant-bound-ui-authentication.md`.

## Preconditions and Evidence Gate

Before announcing removal, establish that the routes are unused:

1. Export the route list and classify every web, `auth:api`, and OAuth route by
   owner, known client, environment, and intended replacement.
2. Search the `milog-ui` repository, deployment configuration, API clients,
   runbooks, synthetic checks, and CI for the candidate URLs, OAuth client IDs,
   and `MILOG_FRONTEND_*` settings.
3. Inventory active OAuth clients and access/refresh tokens by grant/client,
   last use, and environment. Do not log token values.
4. Use the existing frontend access audit records and add metrics for the
   unversioned API and OAuth endpoints. Record route name, client ID where safe,
   status, user ID, environment, and timestamp; exclude credentials and tokens.
5. Observe production for at least one normal release cycle and a period long
   enough to cover infrequent administrative use. The owner must approve a
   documented exception if fewer than 30 days are observed.
6. Identify owners for every detected consumer. A route with unexplained
   traffic is not considered unused.

The exit criterion is zero unexplained successful requests during the agreed
observation window, plus confirmation that `milog-ui` does not depend on the
legacy surfaces.

## Deprecation Contract

### Legacy web routes

They are already disabled by default through `MILOG_FRONTEND_ENABLED=false`
and return `404`. Keep them disabled in every deployed environment during the
observation window. Do not re-enable them merely to return a deprecation page,
because doing so would restore login and registration handlers.

For known internal users, communicate the replacement and removal date through
release notes and operational channels. Requests seen in audit logs should be
traced to an owner directly.

### Legacy API routes

During a time-boxed compatibility release:

- mark `GET /api/user`, `/api/sites`, and `/api/pages` as deprecated in the
  private API specification;
- add `Deprecation: true`, a standards-formatted `Sunset` date, and a `Link`
  header pointing to migration documentation on all responses, including
  authorization failures where practical;
- emit a structured `legacy_api_request` metric/event;
- freeze functionality and accept only security fixes;
- keep existing response shapes until the sunset date.

After the published date, remove the route registrations. Prefer returning
`404` after the transition so the API does not permanently advertise removed
resources. A short `410 Gone` interval is acceptable only if clients need a
clearer migration signal and it has an explicit end date.

### OAuth routes and clients

- Disable `Passport::enableImplicitGrant()` in the first deprecation release;
  no new browser integration may use it.
- Revoke and remove confirmed-unused implicit/password grant clients after the
  observation window. Client secrets must never appear in reports or logs.
- Do not disable the password grant or remove Passport tables, keys, provider,
  guard, package, or `HasApiTokens` until the tenant-bound authentication spike
  confirms which Passport capabilities the replacement requires.
- When the replacement launches, stop generic browser access to `/oauth/token`
  and expose only the versioned MiLog auth contract. Preserve only the internal
  grant support demonstrably required by that adapter.

## Delivery Plan

### Phase 1: Inventory and Instrumentation

- Produce a checked-in route/consumer inventory with an owner and disposition
  for each candidate surface.
- Add focused request metrics around the legacy unversioned API and OAuth
  clients; confirm the existing frontend audit channel is retained and
  monitored.
- Record baseline database row counts for users, OAuth clients/tokens, sites,
  pages, components, and pivot tables. Counts are operational evidence, not a
  reason to infer ownership.
- Add characterization tests for the current disabled-web behavior and legacy
  API responses so the compatibility window is controlled.

### Phase 2: Announce and Freeze

- Publish the route list, replacements, sunset date, and support owner.
- Add API deprecation/sunset headers and documentation.
- Remove implicit-grant enablement and prevent creation of new legacy clients.
- Keep `MILOG_FRONTEND_ENABLED=false` everywhere and fail deployment validation
  if production enables it.
- Freeze the legacy controllers and UI to security fixes only.

### Phase 3: Introduce the Replacement

- Deliver and verify the tenant-bound UI auth endpoints described in
  `planning/tenant-bound-ui-authentication.md`.
- Migrate `milog-ui`, synthetic tests, and operational tooling to the new
  versioned endpoints.
- Verify login, tenant selection, refresh, revocation, and logout in production
  before starting the final removal clock.
- Re-run consumer discovery and resolve any remaining traffic.

### Phase 4: Remove Reachable Legacy Surfaces

- Delete `Auth::routes()`, `/`, and `/home` registrations from `routes/web.php`.
- Delete `GET /api/user` and the site/page resource registrations from
  `routes/api.php`.
- Remove legacy auth and home controllers, Blade views, frontend gate
  middleware registration/implementation, configuration, environment examples,
  and tests that only exercise those routes.
- Remove the implicit grant and revoke/delete its obsolete clients.
- Run `route:list` and assert no removed route names or paths remain, including
  package-provided OAuth authorization routes that are no longer intended.
- Keep an explicit JSON `404` contract for unknown API routes and ensure no web
  login redirects can be triggered by API requests.

### Phase 5: Remove Orphaned Code and Data

- Use static/reference searches plus test coverage to decide whether
  `SiteController`, `PageController`, models, factories, seeders, and
  relationships are fully orphaned.
- If orphaned, remove application code first. Preserve tables read-only for a
  defined recovery window and take a verified backup/export before any schema
  deletion.
- Drop `site_user`, `page_site`, component/page/site tables only in a later,
  separately reviewed migration after retention and recovery requirements are
  approved. Do not rewrite old migrations that may already have run.
- Remove Laravel UI/Mix/Vue/Bootstrap/jQuery assets and npm dependencies only
  after confirming no retained asset pipeline consumes them.
- Remove unused session, mail, verification-listener, password-reset, and web
  middleware configuration only when reference checks prove they are not used
  by the replacement.

### Phase 6: Decide Passport's Final Status

After the tenant-auth implementation stabilizes:

- if Passport remains the token authority, retain only required grants,
  routes, models, migrations, keys, config, and cleanup jobs;
- if it was replaced, revoke all remaining Passport tokens, remove the
  `passport` guard and `HasApiTokens`, uninstall `laravel/passport`, remove its
  configuration/keys, and drop OAuth tables in a later recoverable migration;
- document the decision and migration path in an ADR.

## Verification

Automated checks must cover:

- public API-key ingestion and timeline behavior remaining unchanged;
- legacy web paths returning `404` throughout deprecation and after removal;
- legacy API responses carrying deprecation headers before sunset;
- removed API routes returning JSON `404`, never HTML or a login redirect;
- implicit-grant requests and revoked legacy clients being rejected;
- the new UI auth path continuing to issue, refresh, and revoke tenant-bound
  tokens;
- no cross-tenant authorization regression;
- `route:list`, dependency/reference scans, Composer tests, and production
  container builds succeeding after cleanup;
- secrets and bearer tokens remaining absent from deprecation telemetry.

Run the full PHP test suite after every phase. Run frontend/API end-to-end smoke
tests before removing routes and again after deployment.

## Rollout and Rollback

- Deploy route removal separately from destructive schema changes.
- Roll back the application release to restore routes during the recovery
  window; never depend on recreating dropped data.
- Keep database backups and OAuth revocation audit records for the approved
  retention period.
- If unexplained traffic appears before sunset, pause removal and assign an
  owner. If it appears after removal, prefer migrating the caller; temporarily
  restoring a route requires a security review and a new sunset date.

## Acceptance Criteria

- Every removed route has an inventory record, owner/disposition, replacement,
  and observed-usage evidence.
- No legacy Blade login, registration, password reset, verification, dashboard,
  user, site, or page route is reachable.
- No implicit OAuth grant is enabled and no confirmed-obsolete OAuth client can
  obtain tokens.
- `milog-ui` authenticates exclusively through the versioned tenant-bound
  contract and supports revocation before legacy removal.
- The `X-API-Key` public API remains behaviorally unchanged.
- Route, controller, model, dependency, asset, and schema cleanup happens in
  separable, reviewable changes with a rollback path.
- Passport is retained or removed through an explicit post-implementation ADR,
  rather than accidentally deleted with the legacy UI.

## Suggested Change Sets

1. Inventory, instrumentation, and characterization tests.
2. Deprecation headers/docs, implicit-grant disablement, and deployment guard.
3. Tenant-bound auth replacement and UI migration (tracked by the companion
   plan).
4. Route/controller/view removal.
5. Orphaned PHP/frontend dependency cleanup.
6. Data archival and schema cleanup.
7. Passport minimization or removal following the ADR.
