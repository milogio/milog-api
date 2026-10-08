# API Specs

This directory holds OpenAPI artifacts for MiLog.

- `public-api.oas.yaml` documents the implemented public `/api/v1` API.
- `ui-api.oas.yaml` documents signup, tenant-bound authentication, entitlement,
  and API-key management used by `milog-ui`.
- `milog-ui-handover.md` describes the implemented signup and API-key flow,
  plus the remaining billing integration work.

The legacy unversioned Passport API is not part of either supported contract.
