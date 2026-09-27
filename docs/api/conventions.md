# HTTP API conventions

APIs are versioned under `/api/v1`, use JSON, and require `Accept: application/json`. Successful and failed responses share this shape:

```json
{"success":true,"data":{},"meta":{"request_id":"..."},"error":null}
```

```json
{"success":false,"data":null,"meta":{"request_id":"..."},"error":{"code":"STABLE_CODE","message":"Safe message","details":{}}}
```

Validation details map field names to arrays of safe messages. Never expose SQL, paths, stack traces, secrets, or internal exception messages. Clients may send an `X-Request-ID` containing 8–100 safe characters; otherwise the server creates one. The response repeats it in the header and envelope.

Future collections use `meta.pagination` with `page`, `per_page`, `total`, and `total_pages`. `per_page` is server-capped. The centralized browser client supplies same-origin credentials, CSRF headers on writes, request IDs, JSON normalization, cancellation/timeouts, and stable client errors.
