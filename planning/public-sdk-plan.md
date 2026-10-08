# Public SDK plan

## Goal and decision

Build small, server-side SDKs for the supported public API: `POST /api/v1/events`
and `GET /api/v1/timeline`. Release **TypeScript/JavaScript first**, then
**Python**. Reassess **PHP** after those releases using actual integration
requests. Keep the first SDKs handwritten and small; use the OpenAPI document as
the contract and a source for type checks, not as a reason to expose generated
client machinery to users.

This is a prioritization forecast, not a measurement of current MiLog customers.
There is no SDK usage or consumer-language inventory in this repository. Revisit
the order if prospective users show different needs.

## Why this order

| Priority | SDK | Likely use for MiLog | Evidence and uncertainty |
| --- | --- | --- | --- |
| 1 | TypeScript with JavaScript support | High. SaaS services and Next.js backends can emit events and read timelines; one npm package can serve typed and plain JavaScript callers. | The neighboring `milog-ui` project is TypeScript and consumes the timeline API, although its UI bearer-token flow is separate from the proposed public SDK. GitHub's 2025 Octoverse ranked TypeScript first by contributors. This does not establish the language mix of MiLog's eventual producer integrations. |
| 2 | Python | High. Backend jobs, scripts, and agent workflows are plausible event producers and timeline readers. | GitHub reports Python as the leading language in AI-focused repositories; Stack Overflow's 2025 survey reports growing Python adoption. No MiLog-specific Python consumer has been confirmed. |
| 3 | PHP | Medium. Laravel/PHP producers could adopt a familiar Composer client. | MiLog itself is Laravel/PHP, which suggests an integration audience but does not prove its customers use PHP. Ship only after customer/partner demand or repeated copy-pasted PHP HTTP integrations. |
| Later | Go, Java, C# | Unknown. These could matter for larger backend teams. | Broad language popularity alone is too weak to justify maintaining three more SDKs for a two-endpoint API. Provide OpenAPI and curl examples until real integrations justify one. |

Research checked on 2026-10-06: [GitHub Octoverse 2025 language and AI-project
analysis](https://github.blog/news-insights/octoverse/what-the-fastest-growing-tools-reveal-about-how-software-is-being-built/)
and the [Stack Overflow 2025 Developer Survey](https://survey.stackoverflow.co/2025/).
Those are ecosystem signals, not projected install counts or MiLog market share.

## Supported contract and boundaries

- Source of truth: `docs/public-api.oas.yaml`, the route/controller/request code,
  and feature tests. Use only the versioned public event and timeline endpoints.
- The public SDK authenticates with a tenant-scoped `X-API-Key`. A key belongs on
  a trusted server or worker, never in browser code or a public mobile bundle.
- `GET /api/v1/timeline` also accepts a tenant-bound UI bearer token, but the
  first SDK does not implement login, refresh, or token storage. The existing
  `milog-ui` uses its own session adapter. If it adopts a shared transport later,
  design that separately against `docs/ui-api.oas.yaml`.
- The SDK accepts a `baseUrl` for local, staging, and production endpoints;
  document that production URLs should use HTTPS. Do not hard-code localhost as
  a production default.
- Do not wrap legacy `/api/user`, site/page, Passport, or private auth routes.

## User-facing shape

Prefer one client, two descriptive operations, and plain inputs/results. Keep
language conventions natural: `createEvent` / `listTimeline` in TypeScript and
`create_event` / `list_timeline` in Python. The exact package names can be
reserved at release time.

```ts
const milog = new MiLog({ apiKey: process.env.MILOG_API_KEY!, baseUrl: process.env.MILOG_URL! });
const event = await milog.createEvent({
  actorType: "user", actorId: "42", action: "created",
  targetType: "invoice", targetId: "inv_1",
}, { idempotencyKey: "invoice-inv_1-created" });
const page = await milog.listTimeline({ targetId: "inv_1", pagination: "cursor" });
console.log(event.id, page.data, page.nextCursor);
```

```python
milog = MiLog(api_key=os.environ["MILOG_API_KEY"], base_url=os.environ["MILOG_URL"])
event = milog.create_event(
    actor_type="user", actor_id="42", action="created",
    target_type="invoice", target_id="inv_1",
    idempotency_key="invoice-inv_1-created",
)
page = milog.list_timeline(target_id="inv_1", pagination="cursor")
print(event.id, page.data, page.next_cursor)
```

These examples define the intended ergonomics, not committed signatures.
Choose one idiom per language and document it consistently. Provide copyable
examples for create, filter, manual next-page fetch, validation errors, and
safe retries. An agent should be able to discover all public operations from a
short README without inspecting generated code.

## Shared behavior for the first releases

1. **Inputs and results.** Make the five event fields required; expose optional
   `log_level`, `metadata`, and `occurred_at`. Map idiomatic names to wire
   `snake_case` in one visible place. Return the event fields, including
   `message`, without discarding unknown future fields. Do not require users to
   instantiate nested request-model classes. Preserve arbitrary JSON metadata.
2. **Timeline filters.** Support `target_id`, `actor_id`, `type`, `page`,
   `pagination`, `cursor`, and the viewer-facing `log_level` filter. Document
   that ingestion accepts raw `trace/debug/info/warn/error/fatal`, while timeline
   filtering accepts `debug/info/success/warning/error`. For multiple filter
   levels, accept a small list and serialize a comma-separated value. Avoid
   presenting ingestion and display levels as one enum.
3. **Pagination.** Return `data`, `links`, and relevant `meta` plus a convenient
   `nextCursor`/`next_cursor`. Offset remains the API default; cursor is an
   explicit option and is preferable for walking a changing timeline. A small
   `iterTimeline`/`iter_timeline` helper may follow only API-provided next links
   after validating origin and path, with a documented stop/limit option. Never
   send the API key to an arbitrary URL from a response.
4. **Errors.** Raise one base API error with HTTP status and safe response
   details, plus clear auth (`401`), validation (`422`, field errors), conflict
   (`409`), rate limit (`429`), and transport/timeout variants. Preserve the
   response's error message; never include credentials or full request headers
   in exceptions or debug logs.
5. **Retries and timeouts.** Set a documented finite timeout and allow
   override. Do not automatically retry event creation unless the caller
   supplies an idempotency key. If retries are implemented, limit attempts,
   retry only transient network errors or `429`/`5xx`, honor `Retry-After` when
   present, and reuse the same serialized payload and idempotency key on each
   attempt. Do not retry `401`, `409`, or `422`. A first release with no
   automatic retry is acceptable if this behavior would otherwise complicate
   the client; examples must show explicit safe retry handling.
6. **Dependency and runtime footprint.** Prefer the platform HTTP client where
   it keeps behavior readable; allow an injectable transport for deterministic
   tests. Ship TypeScript declarations with the npm package and support plain
   JavaScript imports. Python starts with a synchronous client and type hints;
   add async only when integrations request it. Avoid a separate framework
   adapter, CLI, or configuration system in v1.

## Contract work before publishing

- Fix `docs/public-api.oas.yaml` to describe **both** offset and cursor page
  response shapes. Its current `TimelineEventCollection` requires offset-only
  `links.first/last` and `meta.current_page/total`, while the implementation
  uses Laravel cursor pagination for `pagination=cursor`. Add a cursor example
  from a real feature response. Make generated types distinguish the modes.
- Add the API-wide `429` response and rate-limit behavior to the public spec.
  The API middleware currently applies `throttle:60,1`; verify actual response
  headers and error shape before documenting SDK behavior.
- Check the spec examples against feature tests for `200` idempotent replay,
  `201` creation, `409` conflict, `401`, and `422`. Clarify whether callers
  should read `Idempotency-Replayed` from a result or only from response
  metadata. Update the public README to mention idempotency, cursor paging,
  log-level filters, and the third `id` ordering tie-breaker.
- Decide the published API compatibility policy: additive response fields are
  tolerated; request or response breaking changes require a new API version.
  SDK major versions signal SDK interface breaks and are not assumed to equal
  the API's `v1` number.

## Delivery sequence

### 1. Contract and examples

Correct the OpenAPI gaps above, lint the spec, and add two runnable, minimal
server-side curl examples. Record concrete JSON fixtures from the API feature
tests for offset and cursor pages. Confirm package names, supported runtime
versions, and release ownership at implementation time.

### 2. TypeScript/JavaScript MVP

Place the small package under `sdks/typescript/` in this repository (or record
a deliberate separate-repository decision before implementation). Expose one
public `MiLog` class, input/result types, and the error hierarchy. Publish a
README with a 30-second setup, Node server example, JavaScript example, and
pagination example. Keep the runtime entry point inspectable without a code
generation step. Test package installation and import from a clean consumer
project before publishing.

### 3. Python MVP

Mirror behavior, error meanings, and examples under `sdks/python/` while using
Python naming and typing conventions. Keep the public surface similarly small.
Test installation from a clean virtual environment and run an example against
a disposable local tenant.

### 4. Release and learn

Publish versioned packages and changelogs after contract and integration tests
pass. Add package release jobs, pinned test runtimes, provenance/checksums where
the registry supports them, and a documented security contact. Make the public
API docs link to both SDKs. Tag SDK requests with a nonsecret SDK name/version
user-agent so adoption can be counted in aggregate; do not log API keys,
payload metadata, or raw timeline content for this measurement.

Review after a release cycle: installs, distinct active tenant integrations
(where measurable without exposing identities), support questions, and requested
languages. Start PHP only if multiple real integrations need it or the measured
maintenance cost is small relative to demand. Re-rank Go/Java/C# from the same
evidence. Registry download counts alone are a weak proxy for active use.

## Verification and acceptance

- Contract tests cover `201` create, `200` replay, `409` changed-payload key
  reuse, `401`, `422`, `429`, `5xx`/network timeout, offset and cursor pages,
  filters, metadata, optional fields, and null timestamps/legacy log levels.
- Tests show the same key and body are preserved across any retry, that no
  unkeyed POST is automatically retried, and that a foreign `links.next` URL
  cannot receive credentials.
- A junior developer can run the quickstart in a fresh project using only an
  API key and base URL, then create an event and fetch its timeline without
  reading source code. A separate plain JavaScript quickstart works without
  TypeScript tooling.
- An agent can find the exact operation names, arguments, error handling, and
  pagination pattern in the README and OpenAPI contract. Examples use the
  published package, compile or execute in CI, and never contain real keys.
- The SDK does not broaden tenant access: it sends one configured key per
  client, never accepts tenant IDs as an auth override, and does not expose UI
  login or refresh flows.
