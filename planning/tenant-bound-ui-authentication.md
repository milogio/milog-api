# Tenant-Bound UI Authentication and Token Revocation Plan

## Goal

Unblock `milog-ui` by providing first-party user authentication in which every
issued token is bound to one tenant, every protected UI request is evaluated in
that tenant context, and tokens can be revoked immediately.

This work is separate from the existing `X-API-Key` authentication used by
producer services on `POST /api/v1/events` and `GET /api/v1/timeline`. Those
keys remain tenant-bound machine credentials and are not UI login tokens.

## Current State

- `User` supports Laravel Passport, and the `api` guard uses Passport.
- `/oauth/token`, `/api/user`, `/api/sites`, and `/api/pages` are legacy
  user-facing surfaces, but tokens and users have no relationship to `Tenant`.
- Passport's access-token and refresh-token tables already contain revocation
  flags, but the application exposes no UI logout/revocation contract.
- Tenants currently own API keys and timeline events only.
- Users currently relate to legacy `Site` records. That relationship must not
  be treated as tenant membership.
- Both the password and implicit Passport grants are enabled. The implicit
  grant should not be part of the new browser authentication design.

## Proposed Contract

Use Passport as the token authority for this increment, but put a small,
versioned MiLog authentication API in front of it. Do not make the UI call the
generic `/oauth/token` endpoint directly.

### Endpoints

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| `POST` | `/api/v1/auth/login` | None | Validate credentials and requested tenant membership; issue tenant-bound access and refresh tokens. |
| `POST` | `/api/v1/auth/refresh` | Refresh token | Rotate the refresh token and issue a new access token for the same tenant. |
| `POST` | `/api/v1/auth/logout` | Bearer token | Revoke the current access token and its refresh-token family. |
| `POST` | `/api/v1/auth/logout-all` | Bearer token | Revoke all UI token families for the current user, across tenants. |
| `GET` | `/api/v1/auth/me` | Bearer token | Return the authenticated user, active tenant, memberships/roles needed by the UI, and token expiry. |

Login accepts `email`, `password`, and `tenant_id`. If a user has exactly one
active membership, `tenant_id` may be omitted and that tenant is selected. If
there are zero or multiple eligible tenants and no tenant is supplied, return a
stable `tenant_selection_required` error without issuing a token. Use the same
generic `invalid_credentials` response for an unknown user, wrong password,
unknown tenant, or missing membership so the endpoint does not disclose
accounts or memberships.

Successful login and refresh responses should contain `access_token`,
`refresh_token`, `token_type`, `expires_in`, and a `user` object containing the
selected tenant. Exact JSON and error schemas must be recorded in a private UI
OpenAPI document before implementation begins.

## Data Model

1. Add a `tenant_user` membership table with:
   - `tenant_id` UUID foreign key;
   - `user_id` foreign key matching `users.id`;
   - `role` (start with an explicit enum/string allow-list such as `owner`,
     `admin`, and `member`);
   - `status` (`active`, `suspended`);
   - timestamps;
   - a unique constraint on `(tenant_id, user_id)` and an index supporting
     lookup by `(user_id, status)`.
2. Add `tenants()` / `users()` model relationships and a membership model if
   membership state or role behavior warrants one.
3. Persist `tenant_id` and a UI-token marker on Passport access tokens. Treat
   the database value as authoritative; a JWT claim may mirror it for
   observability but must not be the only source of tenant context.
4. Associate refresh tokens with the same tenant-bound token family. If the
   installed Passport schema cannot express that directly, add a small token
   family table keyed to Passport token IDs rather than inferring tenant state
   from request input during refresh.
5. Provide an explicit migration/backfill command for existing users. Do not
   guess membership from `site_user`; require an operator-supplied tenant or a
   reviewed mapping.

## Request Authorization

Create one middleware that runs after `auth:api` for all UI routes. It must:

- load `tenant_id` from the persisted access-token record;
- reject missing, expired, or revoked tokens with `401`;
- reject tokens whose tenant is missing, disabled, or no longer has an active
  membership for the user;
- place an immutable tenant context object on the request/container;
- never accept `tenant_id` from a header, query string, or request body as a
  replacement for the token-bound tenant;
- return `403` for an authenticated tenant member who lacks a required role.

Controllers and query services must consume the resolved tenant context and
scope every tenant-owned query with it. Add policies for role-sensitive actions
instead of duplicating role checks in controllers. Route-model binding must be
scoped or followed by policy checks so IDs from another tenant resolve as
`404`, not as accessible resources.

## Token Lifecycle and Revocation

- Use short-lived access tokens (target: 15 minutes) and rotating refresh
  tokens (target: 30 days; finalize with the UI team).
- On refresh, revoke the presented refresh token and its access token before
  returning a replacement in the same tenant. Reuse of a rotated refresh token
  revokes the entire token family and emits a security event.
- Logout revokes the current access token and every refresh token in its family
  in one transaction. It is idempotent and returns `204` even if already
  revoked.
- Logout-all revokes every UI access/refresh token for the user, while leaving
  tenant API keys and unrelated OAuth clients untouched.
- Suspending/removing a membership or disabling a tenant revokes affected token
  families immediately. Middleware membership checks provide defense in depth
  if asynchronous revocation is delayed.
- Password changes and account disablement revoke all UI token families.
- Store no access or refresh token plaintext in application logs or database
  additions. Redact `Authorization`, password, and token response fields.
- Add scheduled cleanup for expired/revoked Passport records according to the
  product's audit-retention policy.

## Delivery Phases

### 1. Contract and Security Decisions

- Confirm the UI's storage/transport model. Prefer secure, `HttpOnly`,
  `SameSite` cookies when UI and API deployment topology permits; otherwise
  document bearer-token storage and XSS mitigations.
- Confirm tenant selection UX, role names, access/refresh TTLs, CORS origins,
  cookie/CSRF behavior, and whether logout-all is exposed in the first UI.
- Create `docs/ui-api.oas.yaml` with request, success, and stable error schemas.
- Disable the implicit grant. Keep the password grant only if it is required
  internally by the selected Passport adapter; do not expose it to the browser.

### 2. Membership and Token Persistence

- Add membership and token-family migrations with foreign keys, uniqueness,
  indexes, reversible down migrations, and PostgreSQL-compatible types.
- Add model relationships, factories, and seed/test helpers.
- Add a safe membership backfill/provisioning command with dry-run output.
- Configure token lifetimes through environment-backed settings.

### 3. Authentication API

- Implement login throttling by normalized identity plus IP.
- Implement login and `me` using application services rather than controller
  logic.
- Implement refresh rotation, logout, and logout-all with transactions and
  idempotent revocation behavior.
- Return consistent JSON errors with machine-readable codes and correlation
  IDs; never redirect an API client to a web login page.

### 4. Tenant Enforcement

- Add tenant-context middleware and policies to the UI route group.
- Move any retained `/api/sites` and `/api/pages` behavior behind the new
  middleware or retire it if it is not part of MiLog UI.
- Add tenant-scoped repositories/query objects for all UI-visible MiLog data.
- Ensure caches and rate-limit keys include tenant and user where appropriate.

### 5. Verification and Rollout

- Publish the contract to `milog-ui` and validate login, refresh, reload,
  logout, expired-token, and tenant-selection flows in a staging environment.
- Deploy additive migrations first, then the API, then the UI. Do not remove
  legacy auth until UI migration and an observation window are complete.
- Instrument issuance, refresh, revocation, rejection reason, and suspicious
  refresh reuse as structured events without recording secrets.
- After adoption, remove unused legacy web auth, grants, OAuth clients, and
  routes in a separate reviewed change.

## Test Matrix

Automated feature/integration coverage must include:

- valid login for one and multiple memberships;
- invalid credentials and membership enumeration resistance;
- suspended user, suspended membership, and disabled tenant;
- access to the bound tenant and denial of another tenant's resources;
- ignored/spoofed tenant headers, query values, and request fields;
- access-token expiry, current-token logout, repeated logout, and logout-all;
- refresh rotation, concurrent refresh attempts, and replay detection;
- membership removal and password change invalidating existing sessions;
- role enforcement and cross-tenant route-model binding;
- API-key ingestion remaining unaffected by UI token revocation;
- CORS, cookie, and CSRF behavior for the production UI origin;
- secrets absent from logs and validation/exception payloads.

Run the full PHP test suite and add an end-to-end UI/API authentication smoke
test to the release gate.

## Acceptance Criteria

- A UI token cannot authenticate without one persisted tenant binding and one
  active membership for its user.
- No request value can change the tenant represented by an issued token.
- Cross-tenant resource attempts consistently fail without leaking data.
- Logout makes the current access token and its refresh family unusable on the
  next request; logout-all invalidates every UI session for that user.
- Refresh tokens rotate, replay is detected, and the replacement remains bound
  to the original tenant.
- Tenant membership removal, tenant disablement, password change, and account
  disablement invalidate the appropriate active sessions.
- Existing `X-API-Key` producer integrations continue to pass their current
  tests and are not revoked by user-session operations.
- `milog-ui` can implement login, tenant selection, session restoration,
  refresh, logout, and unauthorized-state handling from the OpenAPI contract
  without relying on undocumented behavior.

## Risks and Follow-Ups

- Passport customization for tenant-bound refresh families should be proven in
  a small spike before migrations are finalized. If it requires overriding
  substantial OAuth internals, evaluate a dedicated first-party session-token
  implementation and record the decision in an ADR.
- Revocation is database-backed and adds a lookup to authenticated requests;
  measure it before introducing caching, and never cache beyond the revocation
  guarantees promised above.
- Tenant switching is intentionally modeled as issuing a new tenant-bound token,
  not mutating an existing token's context.
- Tenant administration, invitations, SSO/MFA, password reset, and machine API
  key rotation are adjacent projects and are not prerequisites unless the UI
  explicitly requires them for its first authenticated release.
