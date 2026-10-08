# MiLog UI Signup, Billing, and API Credentials Plan

## Priority and product contract

Deliver this capability before the tech debt removal in
[`tech-debt-removal.md`](tech-debt-removal.md). The separate `milog-ui` owns the
screens; this API owns validation, tenant creation, credentials, and billing
entitlements. Keep the existing legacy `Auth::routes()` signup disabled. It
creates users through the old Laravel web UI and has no tenant or billing flow.

Assumed launch policy, to confirm before implementation:

- A new organization gets one tenant and its first user becomes `owner`.
- “Validated” means the user's email is verified and the tenant passes basic
  abuse/ownership checks. Any manual tenant approval requirement should be an
  explicit configurable step, not a hidden side effect of payment.
- A validated tenant may create short-lived **temporary** API credentials for
  evaluation without a payment method. Set a configurable trial end, key
  expiry, rate limits, and an issuance cap; do not allow repeated signups to
  reset the evaluation period.
- A paid tenant with a current subscription in good standing may create
  **non-expiring** API credentials. “Permanent” means no preset expiry; it does
  not mean irrevocable. A key remains usable only while the tenant's paid
  entitlement is valid and the key is active.
- The billing grace period, trial length, usage limits, and whether cancelled
  plans retain access until the paid period ends are product decisions. Keep
  them in policy/configuration rather than payment-provider status strings.

## Current gap

- `milog:provision-tenant` creates an active tenant and raw API key in one CLI
  operation. It does not create a user or membership.
- The `api_keys` table has only tenant, name, prefix, hash, and last-use fields.
  `ResolveTenantFromApiKey` accepts any matching key and does not check tenant
  status, expiry, revocation, trial, or subscription state.
- UI login/refresh and tenant membership already exist through Passport.
  `UiTokenService` requires active user, tenant, and membership; a separate
  billing entitlement is therefore needed so a delinquent owner can still log
  in and repair billing.
- There are no signup, email verification, key-management, checkout, webhook,
  subscription, or billing-portal endpoints in the supported API.

## States and access rules

| State | UI access | API-key issuance | Existing API-key use |
| --- | --- | --- | --- |
| Signup pending email/tenant validation | Verification and resend screens only | None | None |
| Validated evaluation, before trial end | Owner/admin can sign in and manage account | Temporary keys only | Temporary keys within expiry and limits |
| Paid and in good standing | Full tenant UI | Non-expiring keys; temporary keys may be rotated out | Active keys subject to their own expiry/revocation |
| Payment issue within an explicit grace period | Full billing/account UI; product access per chosen policy | No new non-expiring keys | Existing paid keys only through the configured grace deadline |
| Trial expired, payment grace exhausted, cancelled after paid-through date, or administratively suspended | Billing/account recovery UI for an eligible owner; support path for suspension | None | Denied, without deleting keys or event data |

Treat account/tenant suspension, trial expiry, and payment status as distinct
facts. Calculate one effective API entitlement for every ingestion and
API-key timeline request. Keep UI login independent of payment standing, while
still enforcing user and membership status. Preserve tenant isolation for UI
timeline reads and credential management.

## Delivery sequence

### 1. Decide the launch policy and provider

- Record trial duration, temporary-key TTL and cap, usage quotas, email and
  tenant validation method, grace period, cancellation behavior, refund/
  chargeback behavior, and who can manage billing and keys. Define what a
  support override can do and when it expires.
- Select one payment provider and create test-mode monthly and yearly recurring
  prices for the same plan. Keep provider IDs server-side and mapped to local
  plan/interval records; the UI must not choose an arbitrary provider price.

### 2. Add self-service signup and validation

- Add versioned, rate-limited endpoints for signup, email verification, resend,
  and signup status. The UI collects owner name, organization name, email,
  password, and acceptance of required terms. Normalize email and enforce its
  uniqueness; use generic responses where an address might already exist.
- Create user, tenant, and `owner` membership in one transaction with pending
  validation state. Store a hashed, single-use, expiring verification token;
  send an email with a safe link to `milog-ui`. Throttle signup and resend, and
  log decisions without passwords or tokens. Make retries idempotent.
- After email and tenant checks pass, activate the membership/tenant for the
  existing Passport login flow. Do not create an API key during signup itself.
  Provide an administrative review path if tenant approval is required.

### 3. Add credential lifecycle and entitlement enforcement

- Extend `api_keys` with kind (`temporary` or `paid`), status, expiry,
  revocation timestamps/reason, creator, and audit metadata. Store only a hash
  of the raw key; display it once. Add a server-side issuance service used by
  both the UI API and any retained provisioning command.
- Add tenant-bound owner/admin endpoints to list key metadata, create keys,
  rotate, and revoke. Enforce a cap and require recent UI authentication or a
  comparable step-up before showing a new raw key. Never return old raw keys.
- Add trial and paid entitlement records independent of the provider model.
  Check key status, expiry, tenant status, and effective entitlement on every
  API-key request. Define a stable `401`/`403` response contract for revoked,
  expired, or billing-blocked credentials without leaking account details to
  arbitrary callers. Apply evaluation quotas server-side.
- Inventory keys issued by the existing CLI before enabling these checks.
  Assign each an explicit legacy entitlement or migration deadline, notify
  its owner, and test that the rollout does not accidentally cut off an active
  integration. Never infer that an old key is paid merely because it exists.
- On payment activation, allow a new non-expiring key. Prefer issuing a new key
  over silently changing a temporary key's class; offer a documented overlap
  window for rotation. On payment recovery, restore eligibility based on the
  subscription state without resurrecting individually revoked keys.
- Update the CLI to require an explicit administrative override for issuing a
  key before validation or without paid standing. Audit every override.

### 4. Integrate hosted subscription billing

- Create checkout and billing-portal session endpoints available only to an
  authenticated tenant owner/admin. Bind the checkout customer and session to
  the tenant server-side. Support monthly and yearly prices, switching between
  them, cancellation, payment-method updates, and invoices through the
  provider's hosted flow.
- Verify webhook signatures against the raw request body. Persist provider
  event IDs for idempotency; handle duplicate and out-of-order events. Update a
  local subscription projection with provider customer/subscription IDs,
  interval, status, current paid-through time, and last synchronized time.
  Reconcile periodically against the provider if webhooks are missed.
- Derive the entitlement from verified billing state, paid-through date, and
  local policy. A checkout success redirect alone must never grant paid keys.
  Define exact treatment of trialing, active, past due, paused, cancelled,
  unpaid, refunds, and chargebacks for the chosen provider.

### 5. Roll out and verify

- First ship signup and validation; then temporary keys with expiry/limits;
  then provider sandbox billing and paid keys. Keep administrative provisioning
  for support until self-service recovery is proven.
- Test duplicate signup, email replay, pending tenant login, cross-tenant key
  management, owner/admin permissions, one-time key display, expiry, revoke,
  trial exhaustion, and tenant suspension. Test every billing transition,
  duplicate/out-of-order webhook, checkout abandonment, failed payment,
  recovery, interval switch, cancellation, and webhook outage reconciliation.
- Verify that API ingestion and API-key timeline access use the same effective
  entitlement, while a billing-blocked owner can still reach account recovery.
  Update OpenAPI, `milog-ui`, operational runbooks, and credential migration
  guidance before release.

## Payment processor shortlist (checked 2026-10-06)

| Provider | Fit for MiLog | Main consideration |
| --- | --- | --- |
| [Stripe Billing](https://docs.stripe.com/billing/subscriptions/metered-billing/thresholds) | Supports one SaaS product with monthly and yearly recurring prices; [Checkout and customer portal](https://docs.stripe.com/payments/checkout/pricing-table) cover purchase and self-service management. | Flexible default if MiLog wants direct control of the billing integration. The embedded pricing table cannot insert MiLog's signup step, so launch with signup first and create a hosted Checkout session from the authenticated UI. |
| [Paddle Billing](https://developer.paddle.com/get-started/how-paddle-works/saas/) | Built for SaaS subscriptions, with hosted checkout, customer portal, recovery flows, and [monthly/yearly prices](https://developer.paddle.com/build/products/create-products-prices/). | Merchant-of-record model may simplify global sales-tax operations; confirm supported markets, checkout flow, and contract fit for MiLog. |
| [Lemon Squeezy](https://docs.lemonsqueezy.com/guides/tutorials/saas-subscription-plans) | SaaS subscription variants can represent monthly and yearly billing; [checkout and webhook data](https://docs.lemonsqueezy.com/guides/developer-guide/taking-payments) support tenant mapping. | Another merchant-of-record option. Check plan-change, dunning, portal, and reporting needs against its specific subscription behavior before selecting it. |

**Recommendation:** Start a Stripe Billing sandbox integration unless MiLog
wants a merchant of record for global tax handling; in that case, evaluate
Paddle first. This is a product/integration recommendation, not a price ranking.
The entitlement and webhook adapter should keep the selected provider from
becoming the source of truth for API-key authorization on each request.

## Done when

- A new owner can sign up in `milog-ui`, verify the account/tenant, log in, and
  create a bounded temporary key without staff intervention.
- A successful verified monthly or yearly subscription enables creation of a
  non-expiring key; loss of good standing blocks API use according to the
  published grace policy while leaving billing recovery available.
- Revocation, expiration, trial limits, and billing entitlement are enforced
  for every API-key request; no raw credential is recoverable after creation.
- Provider events are authenticated, idempotent, reconciled, and covered by
  failure/recovery tests; tenant isolation and the public API remain intact.
