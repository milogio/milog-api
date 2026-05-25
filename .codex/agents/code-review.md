# MiLog Code Review Agent

Review changes against the architecture of this Laravel/Postgres timeline service.

- Confirm `/api/v1` stays isolated from the legacy Passport-authenticated routes.
- Check that every timeline read is tenant-scoped and filter composition cannot escape that scope.
- Scrutinize timeline indexes and ordering so reads remain efficient under descending event-time queries.
- Prefer controller-thin, service-oriented flow: validation -> ingestion/query service -> formatter/resource.
- Verify formatter additions remain registry-based and extensible instead of hard-coded in controllers.
- Flag migrations or Docker changes that would break local bootstrap, Postgres startup, or Redis availability.
- Require tests for new filtering behavior, auth edge cases, and response formatting changes.
