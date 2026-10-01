# Phase 1 database bootstrap and recovery

## Requirements

- PHP 8.2 or later.
- Dependencies installed from `composer.lock` (CI4 4.7.3, Settings 2.4.0, Shield 1.4.1).
- MySQL 8.4.x, not MariaDB, using InnoDB.
- An explicitly named disposable development database, for example `dawatrack_phase1_dev`.
- The `default` group configured in `.env` with `utf8mb4`, `utf8mb4_unicode_ci`, strict mode, and UTC operation.

Never run destructive commands until `SELECT DATABASE()` identifies the intended disposable database.

## Configure

Copy `env` to `.env` and set values appropriate to the local installation:

```ini
CI_ENVIRONMENT = development
database.default.hostname = 127.0.0.1
database.default.database = dawatrack_phase1_dev
database.default.username = dawatrack_dev
database.default.password = replace-locally
database.default.DBDriver = MySQLi
database.default.DBPrefix =
database.default.port = 3306
database.default.charset = utf8mb4
database.default.DBCollat = utf8mb4_unicode_ci
database.default.strictOn = true
```

Do not commit real credentials.

## Prove the baseline

Using the same connection values as the application:

```sql
SELECT DATABASE();
SELECT VERSION();
SELECT @@session.time_zone;
SHOW TABLES;
```

Continue only when the selected database is the intended disposable schema, the server is MySQL 8.4.x, and `SHOW TABLES` is empty.

## Authoritative install sequence

CI4 `migrate --all` iterates discoverable namespaces; it is not the dependency contract for package migrations. Use the explicit package sequence:

```bash
composer install --no-interaction --prefer-dist
php spark migrate:status
php spark migrate --namespace CodeIgniter\\Settings
php spark migrate --namespace CodeIgniter\\Shield
php spark migrate --namespace App
php spark db:seed PhaseOneSeeder
php spark migrate:status
php spark migrate --namespace App
php spark db:seed PhaseOneSeeder
```

The second App migration run must report nothing pending. The second seed is a no-op only when controlled rows match exactly; drift causes a clear exception.

## Shield contract check

Before App migrations, inspect:

```sql
SHOW CREATE TABLE users;
SHOW COLUMNS FROM users LIKE 'id';
SHOW INDEX FROM users;
```

For pinned Shield 1.4.1 the required contract is an InnoDB `users` table with `id INT UNSIGNED NOT NULL PRIMARY KEY`. The first Shield-dependent App migration performs the same preflight.

## Roll back and rebuild in development

Rollback only the App namespace/batch created for Phase 1:

```bash
php spark migrate:rollback --namespace App
```

Verify all 12 DawaTrack tables are absent and vendor tables remain, then reapply:

```bash
php spark migrate --namespace App
php spark db:seed PhaseOneSeeder
```

If a migration fails after MySQL has committed earlier DDL, rollback cannot infer the unrecorded partial state. Verify `SELECT DATABASE()`, drop and recreate only the disposable database using an administrative connection, prove `SHOW TABLES` is empty, and execute the complete prerequisite sequence again. Never add ledger rows manually and never use `IF NOT EXISTS` to conceal the state.

## Migration locking

CI4's supported migration lock is enabled. It serializes migration runners that use the same configured lock mechanism; it does not make MySQL DDL transactional and does not protect arbitrary manual SQL. Local runs may wait for another active migration rather than racing it.

## Post-schema operational bootstrap

Create the first Platform Administrator through the controlled Shield CLI/bootstrap procedure, assign only the `platform_admin` group, verify the account email, and invalidate the bootstrap credential. Never create a synthetic Tenant membership for a Platform Administrator. The first login completes Shield's configured second factor and establishes DawaTrack's recent-MFA state.

Starter and Growth prices intentionally seed as `NULL` and must be explicitly configured through the approved commercial process before paid entitlement creation. After migration/bootstrap, restrict the runtime database account from `UPDATE` or `DELETE` on `audit_events` where operationally practical.

MFA reset/recovery remains an operator-controlled identity recovery: clear active sessions, confirm control of the verified Admin email, use the normal Shield recovery flow, and require a fresh password plus email factor. Never place an MFA code, magic link, or session value in tickets, logs, or Audit metadata.
