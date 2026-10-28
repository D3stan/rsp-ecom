# Runbooks

Release and incident procedures. MCP call sequences: `deploying-to-dokploy` skill (`.ai/skills/deploying-to-dokploy/SKILL.md`). Infrastructure facts: `.ai/rules/deployment.md`. Env vars: `.ai/rules/environment.md`.

## Routine release
1. Work on a branch; run the affected tests and `vendor/bin/pint` on new files.
2. Merge the branch into `sandbox` and push. Sandbox auto-deploys when a watched path changed.
3. Watch the build: `deployment-allByCompose`, then `deployment-readLogs` for the newest deployment.
4. Verify on the sandbox domain: the changed flow, `GET /api/health`, and the Sentry sandbox environment.
5. Only with explicit approval, merge `sandbox` into `main` and push. This deploys production.
6. Verify production the same way: `deployment-allByCompose`, `deployment-readLogs`, `GET /api/health`, Sentry production environment.
7. Docs-only changes do not deploy; do not expect a build for them.

## Rollback
1. Find the last good commit in `deployment-allByCompose` or `git log main`.
2. Preferred: `git revert` the bad commit(s) on `main` and push; auto-deploy rebuilds.
3. Alternative: redeploy the previous commit from Dokploy (see the skill); it is overwritten by the next push to `main`.
4. Migrations are NOT reversed. If the bad release added a migration, write a forward migration or fix the data by hand before redeploying.
5. Verify with `GET /api/health` and the logs.

## Failed migration on boot
1. `deployment-readLogs` / `compose-readLogs` on service `app`: find the migration error.
2. The `app` container exits or restarts; `queue` and `scheduler` may log transient errors meanwhile (expected).
3. Fix forward on a branch, test on `sandbox`, then ship through the normal release.
4. If production is down and the fix is not ready: roll back as above, but check whether the migration half-applied (`php artisan migrate:status` in the `app` container).
5. Never edit an already-applied migration; add a new one.

## Change an env var
1. Edit the template (`.env.production.example` or `.env.sandbox.example`) if the key is new, and commit it.
2. Set the value in the Dokploy Environment tab. `compose-saveEnvironment` replaces the whole block: read the current block first, change one line, save all of it.
3. Redeploy the stack. Runtime vars need only a restart-level redeploy.
4. Build-time vars (`SENTRY_AUTH_TOKEN`, `SENTRY_ORG`, `SENTRY_PROJECT`, `SENTRY_URL`, `VITE_*`) need a full rebuild.
5. Verify the effect, then `GET /api/health`.

## Queue backlog or stuck jobs
1. Check the `queue` container is running and healthy (`compose-loadServices`, `compose-readLogs`).
2. Check the `jobs` and `failed_jobs` tables with Boost `database-query` (read-only).
3. Worker down: redeploy or restart the stack; `queue:work` recycles every hour by design.
4. Failing jobs: read the exception in the logs or Sentry; fix and deploy, then `php artisan queue:retry all` in the `app` container.
5. Mail jobs are the main load: check SMTP settings (`MAIL_*`) before retrying.
6. `ops:check-queue` raises the alert; thresholds are `QUEUE_ALERT_*`.
7. Never run `queue:flush` or truncate `jobs` without user approval: customers lose emails.

## Invariant or Stripe-reconcile alert
1. Read the Telegram message and the matching Sentry issue; note the order ids.
2. Follow the rules for that alarm in `.ai/rules/ops-observability.md`.
3. For payment or refund questions, compare the order against Stripe before changing anything; rules in `.ai/rules/orders-refunds.md`.
4. Fix the cause, not the alert: a standing problem re-alerts once per window.
5. After the fix, run `php artisan ops:check-invariants` in the `app` container and confirm it is clean.
