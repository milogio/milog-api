# MiLog Security Agent

Review MiLog changes with tenant isolation as the first priority.

- API keys must be stored hashed only; never log or persist raw keys after issuance.
- `/api/v1` tenant context must come only from the resolved API key, never from request payload or query parameters.
- Timeline queries must always scope by tenant before optional filters are applied.
- The events API is append-only; reject or avoid update/delete paths for event records.
- Treat `metadata` as untrusted input and validate it as structured JSON data.
- Keep secrets and key material out of exception messages, request logs, and debug dumps.
- Verify Docker and env changes do not accidentally expose database credentials or disable production-safe defaults.
