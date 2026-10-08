# MiLog UI handover: signup and API credentials

Status: API implementation available for UI integration. Hosted checkout,
subscription webhooks, and self-service paid upgrades are **not implemented**
because no payment processor has been selected.

## Ownership and authentication

The MiLog UI owns screens and calls this API. The API owns users, tenants,
memberships, trial entitlements, and API keys. Do not create these records in
the UI database. Use `Authorization: Bearer ACCESS_TOKEN` for account and key
management. Producer `X-API-Key` credentials are only for the public event and
timeline API; they must not be used for management endpoints.

The API base path below is `/api/v1`. Requests and responses are JSON. Send
`Accept: application/json` and `Content-Type: application/json`.

## UI flow

1. Show signup fields: owner name, organization name, email, password,
   confirmation, and required terms acceptance. Submit to `POST /signup`.
   Always show a generic “check your email” page after `202`; the response
   intentionally does not reveal whether the address already exists.
2. The email contains a link to
   `{MILOG_UI_URL}/verify-email?token=...`. Build that UI route and submit
   the token from the URL body to `POST /signup/verify`. Do not log the token,
   store it in analytics, or send it to third-party scripts. Remove it from
   browser history after use. Offer `POST /signup/resend` if the link expires.
3. Verification activates the user, tenant, and owner membership and starts
   the evaluation period. Then use the existing `POST /auth/login` flow and
   store its tenant-bound access/refresh tokens according to the UI auth
   contract in [ui-api.oas.yaml](ui-api.oas.yaml). Pending users cannot log in.
4. After login, call `GET /entitlement` to decide what to show. Owners and
   admins can call `GET /api-keys`, `POST /api-keys`, and
   `DELETE /api-keys/{id}`. The login response contains the tenant role.
5. Show the newly created raw API key once, with copy/download guidance. The
   list response never returns it. Key creation requires the user's current
   password as a step-up check. Clear that password immediately after the
   request. Warn before revocation because it takes effect on the next API
   request.

## Endpoint contract

| Method and path | Auth | Request | Success |
| --- | --- | --- | --- |
| `POST /signup` | None | `name`, `tenant_name`, `email`, `password`, `password_confirmation`, `terms_accepted: true` | `202` with generic `message` |
| `POST /signup/resend` | None | `email` | `202` with same generic `message` |
| `POST /signup/verify` | None | `token` | `200` with `message` |
| `GET /entitlement` | UI bearer | None | `200` with `data` entitlement |
| `GET /api-keys` | UI bearer, owner/admin | None | `200` with `data` array of key metadata |
| `POST /api-keys` | UI bearer, owner/admin | `name`, `kind`, `password` | `201` with key metadata and one-time `api_key`; `Cache-Control` includes `no-store` |
| `DELETE /api-keys/{id}` | UI bearer, owner/admin | None | `204` |

Example signup:

```json
{
  "name": "Ada",
  "tenant_name": "Acme",
  "email": "ada@example.com",
  "password": "a-long-unique-password",
  "password_confirmation": "a-long-unique-password",
  "terms_accepted": true
}
```

The password must contain at least 12 characters. Email is normalized to
lowercase. The signup response is deliberately identical for a new address,
an existing account, or a pending account. A repeated signup for a pending
account refreshes its verification email. Signup, resend, and verification
are rate limited.

Example entitlement response during evaluation:

```json
{
  "data": {
    "state": "evaluation",
    "trial_ends_at": "2026-10-21T12:00:00.000000Z",
    "billing_status": "none",
    "paid_through_at": null,
    "grace_ends_at": null,
    "can_create_temporary_key": true,
    "can_create_paid_key": false
  }
}
```

For key creation, send `kind: "temporary"` during evaluation. The example
defaults are a 14-day evaluation, 7-day temporary key lifetime, and **two
temporary keys issued in total** per tenant. A temporary key expires at the
earlier of its own lifetime and the evaluation end. Paid keys have no preset
expiry, but `kind: "paid"` is unavailable until a trusted billing integration
marks the subscription in good standing. Do not display a functional paid
checkout or promise a paid key yet.

Example key creation response:

```json
{
  "data": {
    "id": "uuid",
    "name": "Production",
    "key_prefix": "milog_abc123",
    "kind": "temporary",
    "status": "active",
    "created_at": "2026-10-07T12:00:00.000000Z",
    "expires_at": "2026-10-14T12:00:00.000000Z",
    "revoked_at": null,
    "last_used_at": null
  },
  "api_key": "milog_full-secret-shown-once"
}
```

The list uses the same `data` fields without `api_key`. All timestamps are
UTC ISO 8601 values. A key has no raw-value recovery endpoint. An existing
CLI-issued key appears as `kind: "legacy"` and remains valid while its tenant
is active; the UI cannot issue legacy keys.

## Errors and UI handling

- `422` with Laravel `errors` means form validation failed.
- `422` with `error.code: "invalid_verification"` means the email token is
  invalid, expired, or already used. Offer resend.
- `401` on login means invalid credentials or pending validation. Keep the
  login response generic; show a verification reminder only when the user
  arrived from signup.
- `401` with `error.code: "invalid_credentials"` on key creation means the
  step-up password failed.
- `403` with `error.code: "forbidden"` means the member is not owner/admin.
  `403` with `error.code: "entitlement_required"` means this key class cannot
  be issued in the current account state.
- `409` with `error.code: "key_limit_reached"` means the issuance/active-key
  cap was reached. Temporary key revocation does not reset the lifetime
  issuance cap.
- `429` means the request was rate limited. Respect `Retry-After` if present.
- Public API calls with an expired, revoked, suspended, or otherwise
  disallowed key receive the existing generic `401` invalid-key response.
  Use the authenticated entitlement view to explain account status to the
  owner.

## Integration and launch requirements

- Configure `MILOG_UI_URL` to the actual UI origin. Build `/verify-email`
  before enabling signup. Production requires SMTP host, username, password,
  and from address. Set SPF/DKIM/DMARC for the sending domain.
- The current validation policy verifies the owner's email and starts a
  tenant for that owner. There is no separate legal/business ownership check
  or manual tenant approval yet. Add one before launch if required by product
  policy.
- Configure trial length, temporary key lifetime/cap, and paid-key cap with
  the `MILOG_*` values in [.env.example](../.env.example). The UI should read
  actual eligibility from `GET /entitlement`, not duplicate policy math.
- Choose a payment processor and implement checkout, verified webhooks,
  subscription projection, payment recovery, and billing portal before
  enabling the paid-key UI. A redirect from checkout cannot grant access.
- Existing CLI keys are explicitly classified as `legacy` by the migration;
  inventory and migrate them before enforcing paid entitlements on those
  integrations.
