# WP3 Windows build follow-up — 2026-11-03

The owner supplied Windows CMD/XAMPP output from `C:\xampp\htdocs\DawaTrack` on the delivered WP3 branch. WP3 is still pending functional manual acceptance; this output is recorded as setup/check evidence only.

## Reported results

| Step | Owner-reported result |
|---|---|
| Fetch and switch to WP3 | Successful |
| composer install | Lock verified; nothing to install/update/remove |
| npm ci | Successful install; three high-severity audit findings |
| npm run build: CSS | Compiled with Bootstrap/Sass deprecation warnings |
| npm run build: assets | **Failed**: Windows CMD could not find `sh` |
| App migration status/apply | All existing App migrations registered through the seven WP2 commercial migrations; no new pending migration reported |
| WP1 contracts | 20 passed |
| WP2 validation/SQL-source parity | 61 passed; script explicitly executes no SQL |
| WP3 money/state contracts | 2,031 passed; script explicitly executes no MySQL/HTTP |
| WP3 posting orchestration | 101 passed against doubles; script explicitly excludes native MySQL/HTTP/concurrency acceptance |

The output identifies CodeIgniter 4.7.3 and Windows/XAMPP. It does not identify the database server vendor/version or independently establish MySQL 8.4 compatibility, actual posted Sale flows, rollback/concurrency or browser acceptance. It does show migrations registered successfully in the owner's checkout. No database reset/rollback is indicated by this report.

## Fix

Branch: `codex/wp3-windows-build-fix-20261103`, based on WP3 `f42f8281904ff93aea892eb826076fdaf0622f11`.

`npm run build:assets` now calls a Node ES module. It resolves the project from the script path, compiles the template using `process.execPath` with an explicit working directory, copies pinned assets/source modules, removes stale destination directories and excludes template/backup files using Node filesystem APIs. No external Unix commands are needed. Existing Unix script callers delegate to the same Node implementation.

The three audit findings were independently reproduced. Targeted patch updates pin Vue/compiler/runtime to 3.5.44 and update the existing transitive source-map-js to 1.2.2, with regenerated lock file and self-hosted assets. See `docs/dependencies.md` for upstream advisories. No forced/major upgrade, PHP, database or commercial behavior change is introduced.

Checks executed: clean lock-file install, complete frontend build, existing WP2/WP3 mocked DOM checks against the updated Vue runtime, Node syntax check, build from a different working directory, repeated-build output parity and npm audit (zero reported vulnerabilities at verification time). These ran on Linux; native Windows execution of the fix remains for the owner's recheck. Sass deprecation warnings from Bootstrap remain non-fatal and are separate from the missing-shell failure.

## Windows recheck

From the existing checkout in Command Prompt:

```bat
git fetch origin
git switch codex/wp3-windows-build-fix-20261103
npm ci
npm run build
npm audit
```

The build must reach `Frontend assets built.` without a missing-shell error. No migration rerun/reset is needed for this patch. Then reload the Shop page (hard refresh if needed), sign in as Owner and continue the WP3 payment/receipt/history/manual checklist. Record observed results; do not infer WP3 acceptance from setup checks alone.
