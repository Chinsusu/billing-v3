# Production Runbook

## Readiness

Use the public readiness endpoint for load balancer and deployment checks:

```bash
curl -fsS https://<host>/ops/readiness
```

Expected healthy response:

```json
{"status":"ok","checks":{"database":"ok"}}
```

The endpoint returns HTTP 503 if a required dependency check fails.

## Audit Log Retention

Admin audit logs are retained for `AUDIT_LOG_RETENTION_DAYS`, defaulting to 365 days.

Run a manual purge:

```bash
php artisan ops:purge-audit-logs
```

Override the retention window for one run:

```bash
php artisan ops:purge-audit-logs --days=90
```

Schedule this command from the host scheduler or Laravel scheduler after confirming compliance retention requirements.

## Deployment Checklist

- Pull the target commit.
- Run `php artisan migrate --force`.
- Run `php artisan optimize:clear`.
- Check `/ops/readiness`.
- Confirm worker and scheduler containers are running.
- Inspect `/admin/ops-health` for scheduler freshness, queues, and overdue services.
- Confirm `APP_DEBUG=false` and secrets are provided through environment variables only.
