# Logging and health operations

Each request receives a correlation ID and emits structured `request.started` and `request.completed` messages. Security and scheduled-job channels will use event names prefixed `security.` and `job.` while retaining the same correlation/context fields. Sensitive values are prohibited.

- `GET /api/v1/health/live` proves the PHP application can answer.
- `GET /api/v1/health/ready` verifies required safe configuration and returns HTTP 503 when it is absent.

These endpoints intentionally omit versions, environment values, credentials, host details, and database errors.
