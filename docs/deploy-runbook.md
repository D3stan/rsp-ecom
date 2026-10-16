# Deploy runbook: `fixes` branch to production

Written 2026-10-16. Takes production from commit `5e03dd1` (what Dokploy last deployed, 2026-09-28) to the head of `fixes`
(117 commits, 602 files, 26 new migrations, two new worker containers).

Run the phases **in order**. Anything in `<angle brackets>` is a value you look up (the step says where).
Commands marked **[laptop]** run in this repo, **[server]** run over SSH on the Dokploy host, **[app]** run inside the `app` container
(`docker exec -it <APP_CONTAINER> <command>`, or Dokploy > ecommerce > Terminal), **[mysql]** run inside the MySQL container.

## What production looks like today (read from Dokploy, read-only)

| Item | Value |
|---|---|
| Dokploy project / service | `RSP` / compose app `ecommerce` (`composeType: docker-compose`, `./docker-compose.yml`) |
| Source | GitHub `D3stan/rsp-ecom`, branch **`main`**, `autoDeploy: true`, `triggerType: tag` |
| Domain | `ecom.rsp-industries.com` -> service `app`, port 8080, `https: false` in Dokploy (TLS is presumably terminated in front, e.g. the Cloudflare tunnel) |
| Database | a Dokploy MySQL service in the same project (`RSP` > production) |
| Last deploy | commit `5e03dd1`, status done. `origin/main` is still `5e03dd1`, so `fixes` is a pure fast-forward of `main`. |
| Services today | only `app`. The new compose file adds `queue` and `scheduler`. |

Why this order matters: the new code needs env vars that the old code did not (real mail transport, webhook secret), the migration
step can abort the `app` container, and `autoDeploy` is on. So: **back up, check, set env, and only then trigger the deploy.**

---

## Phase 0: preflight on your laptop

```bash
# [laptop] 0.1 you are on fixes, clean and pushed
cd ~/Documents/GitHub/rsp-ecom
git checkout fixes && git status -sb          # expect: "## fixes...origin/fixes", nothing modified

# [laptop] 0.2 fresh frontend build (this is what the Docker image does) and the full test suite
npm ci && npm run build
APP_KEY=base64:$(openssl rand -base64 32) php artisan test --compact
git checkout public/robots.txt                # the tests rewrite it; never commit that change

# [laptop] 0.3 (optional, slow) prove the production image builds, incl. the new gd extension
docker build -t rsp-ecom:preflight .
```

Expected test result when this runbook was written: **1164 passed, 11 failed**. All 11 are `ProductListPerPageTest`, which hits
`/categories/{slug}`; that page is missing from the repo (see "Known issues"). It was also missing in the production build, so
it is not a regression of this deploy. Any *other* failure: stop and investigate.

(Without `APP_KEY` in the environment the local suite shows ~225 `MissingAppKeyException` failures. That is a local `.env` issue, not a bug.)

---

## Phase 1: inspect production and take a backup (nothing changes yet)

```bash
# [laptop] 1.1 log in to the Dokploy host
ssh deploy@37.27.26.211        # host/user are from deploy.php; use whatever you normally use for the Dokploy server

# [server] 1.2 find the containers
docker ps --format '{{.Names}}\t{{.Image}}\t{{.Status}}' | grep -i -E 'rsp|mysql'
#   -> APP_CONTAINER   = the one named rsp-ecommerce-web-jp6mz0-app-1 (or similar)
#   -> MYSQL_CONTAINER = the Dokploy MySQL service container
```

```bash
# [server] 1.3 BACKUP. Do not continue without a file you have checked.
# The database name and root password: Dokploy > RSP > MySQL > General, or DB_DATABASE / DB_PASSWORD in the ecommerce Environment tab.
mkdir -p ~/backups
docker exec <MYSQL_CONTAINER> sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers <DB_NAME>' \
  | gzip > ~/backups/rsp-pre-fixes-$(date +%F-%H%M).sql.gz
ls -lh ~/backups/                       # not empty, more than a few KB
gunzip -c ~/backups/rsp-pre-fixes-*.sql.gz | tail -3     # should end with "-- Dump completed on ..."
```

Also copy the dump off the server (`scp deploy@37.27.26.211:backups/rsp-pre-fixes-*.sql.gz .`). Dokploy's MySQL > Backups tab is an alternative.
The `ecommerce` compose app has no backups configured, and the uploaded files live in the `storage-app` Docker volume.

```bash
# [server] 1.4 open a MySQL shell
docker exec -it <MYSQL_CONTAINER> sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" <DB_NAME>'
```

Run these SQL checks in that shell:

```sql
-- 1.5 BLOCKER CHECK: duplicate Stripe sessions. Must return ZERO rows, or the migration aborts and the app container fails to start.
-- (The `orders:duplicate-sessions` artisan command is not in the old production image, hence raw SQL.)
SELECT stripe_checkout_session_id, COUNT(*) AS n, GROUP_CONCAT(id) AS order_ids
FROM orders WHERE stripe_checkout_session_id IS NOT NULL
GROUP BY stripe_checkout_session_id HAVING COUNT(*) > 1;

-- 1.6 who can log in as admin? You need a REAL admin before the demo one is removed.
SELECT id, email, role, is_active FROM users WHERE role = 'admin';
SELECT id, email FROM users WHERE email = 'admin@example.com';     -- the demo admin: row here means delete it in Phase 5

-- 1.7 which Stripe keys does the DATABASE hold? (names only, never print values)
SELECT `key` FROM settings WHERE `key` LIKE 'stripe%';
```

- **1.5 returns rows:** open each pair in the admin, decide which order is the real one, and merge/remove the other by hand
  (the migration never touches data). Re-run until empty.
- **1.6 shows only `admin@example.com`:** you will create your real admin in Phase 4 (via `ADMIN_EMAIL`/`ADMIN_PASSWORD`) before deleting it.
- **1.7 lists `stripe_secret_key`:** the live key may be stored only in the DB. The new code still falls back to it
  (`StripeService`: `config('cashier.secret') ?: Setting::get('stripe_secret_key')`), so payments keep working, but copy the keys into the
  environment anyway (Phase 2) so the purge command in Phase 6 can be used.

---

## Phase 2: set the environment in Dokploy (before any deploy)

Dokploy > RSP > `ecommerce` > **Environment**. Keep every variable that is already there and add or change the ones below.
(Dokploy writes this tab to `.env`, which all three containers read.)

```dotenv
# --- must be exactly these in production ---
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<the exact public URL customers open>      # signed order/unsubscribe links and the sitemap use it
QUEUE_CONNECTION=database
CACHE_STORE=database                                       # shared by all containers; the login fix (A6) keeps a one-time nonce here
SESSION_DRIVER=database

# --- database: ONE shared MySQL for app, queue and scheduler (never sqlite) ---
DB_CONNECTION=mysql
DB_HOST=<Dokploy MySQL internal host name>
DB_PORT=3306
DB_DATABASE=<DB_NAME>
DB_USERNAME=<user>
DB_PASSWORD=<password>

# --- mail: a real transport, or no customer email ever leaves the server ---
MAIL_MAILER=smtp
MAIL_HOST=<smtp host>
MAIL_PORT=<587 or 465>
MAIL_USERNAME=<user>
MAIL_PASSWORD=<password>
MAIL_FROM_ADDRESS="noreply@<your domain>"                  # real address, SPF/DKIM/DMARC set up on that domain
MAIL_FROM_NAME="<shop name>"
MAIL_SUPPORT_ADDRESS="support@<your domain>"               # monitored inbox, shown in every email

# --- Stripe ---
STRIPE_KEY=<pk_live_...>
STRIPE_SECRET=<sk_live_...>
STRIPE_WEBHOOK_SECRET=<whsec_...>                          # REQUIRED now: without it the webhook answers 403 in production

# --- first admin (used once by db:seed in Phase 4, then delete ADMIN_PASSWORD) ---
ADMIN_EMAIL=<your real admin email>
ADMIN_PASSWORD=<strong password>
```

Do **not** set `AUTORUN_ENABLED`. Leave `SHIPPING_*` unset: tracking defaults to `manual` and label creation to off.
Optional: `SHIPPING_TRACKING_SPEDIAMO_TOKEN` (only for the `spediamo` tracking driver).

Check `STRIPE_WEBHOOK_SECRET` against Stripe (Developers > Webhooks > your endpoint > Signing secret). If the secret you have is for
a different endpoint, signatures fail.

Save the Environment tab. **Do not click Deploy yet.**

---

## Phase 3: ship the code and deploy

`autoDeploy` is on and the trigger type is `tag`. Both Dokploy and `deploy.php` use `main`, so `fixes` has to land on `main`.
Because production is exactly `5e03dd1` and `origin/main` has not moved, this is a fast-forward:

```bash
# [laptop] 3.1 merge fixes into main
git fetch origin
git checkout main && git pull --ff-only origin main
git merge --ff-only fixes          # if this refuses, main moved: stop and look, do not force
git push origin main               # may start a deploy by itself (depending on the trigger); that is fine now that env is set
```

```bash
# [laptop] 3.2 only if Dokploy did NOT start a deploy within a minute: tag, or press Deploy in the UI
git tag release-2026-10-16 && git push origin release-2026-10-16
```

Otherwise: Dokploy > `ecommerce` > **Deploy** (it also lets you watch the log live).

What happens, in order: image build (composer, `npm run build`, the `gd` extension) -> three containers start -> `app` runs
`php artisan migrate --force` (26 migrations; the settings merge and the unique index run here) -> `storage:link` -> entrypoint clears caches
and regenerates the sitemap. The `queue` and `scheduler` containers skip migrations and startup tasks (`AUTORUN_ENABLED=false`, `RSP_STARTUP_TASKS=false`).

```bash
# [server] 3.3 watch it come up (a minute or two)
docker ps --format '{{.Names}}\t{{.Status}}' | grep rsp-ecommerce      # expect app, queue, scheduler all "Up", queue/scheduler "(healthy)"
docker logs --tail 80 <APP_CONTAINER> 2>&1 | grep -i -E "migrat|error|exception|fail"
curl -s -o /dev/null -w '%{http_code}\n' https://<domain>/up           # 200
```

**If `app` restarts in a loop** and the log shows `Cannot add a unique index on orders.stripe_checkout_session_id`: you skipped 1.5. Fix the duplicates
(MySQL shell from 1.4), then `docker restart <APP_CONTAINER>`; the migration is safe to re-run ("No data was changed").
Any other migration error: go to **Rollback**.

The workers start at the same moment as `app`, before its migrations finish, so the first minute of their logs may show a few
errors. Ignore that unless it continues; confirm in Phase 4.

---

## Phase 4: post-deploy commands in the `app` container

```bash
# [app] 4.1 sanity: MUST say production before the seeder below (non-production seeds demo users with a known password)
php artisan env                              # -> production
php artisan migrate:status | tail -30        # every row "Ran", last one 2026_10_16_200100_add_featured_to_review_media_table

# [app] 4.2 settings defaults + your real admin. Safe on live data: never overwrites existing settings, no demo data in production.
php artisan db:seed --force                  # prints "Production: demo data skipped" and "Admin user created/updated: <ADMIN_EMAIL>"
php artisan settings:list --group=tax        # shows the settings catalogue reading correctly

# [app] 4.3 configuration checks
php artisan stripe:check-config              # STRIPE_SECRET / STRIPE_KEY set (and shows whether the DB still holds legacy copies)
php artisan schedule:list                    # includes ops:check-queue, newsletter:dispatch-scheduled, reviews:send-requests, tracking:sync

# [app] 4.4 mail end to end (this one sends synchronously, so you see success or failure immediately)
php artisan email:send-test <your-own-address>
```

```bash
# [server] 4.5 workers are alive and healthy
docker logs --tail 30 <QUEUE_CONTAINER>
docker logs --tail 30 <SCHEDULER_CONTAINER>        # "Running scheduled tasks" lines
```

Then in the **admin panel**: log in with `ADMIN_EMAIL`, open Settings, confirm tax rate, shipping destinations and support email look right.

```bash
# [laptop] 4.6 remove the seed password from the environment
#   Dokploy > Environment: delete ADMIN_PASSWORD (and ADMIN_EMAIL), save, then Redeploy.
```

---

## Phase 5: security cleanup

```bash
# [mysql] 5.1 delete the demo admin, ONLY after you have logged in as the real admin in Phase 4
#   (the shop has a real admin: SELECT email FROM users WHERE role='admin')
DELETE FROM users WHERE email = 'admin@example.com';
SELECT COUNT(*) FROM users WHERE email = 'admin@example.com';     -- 0
```

This matters even with the A6 fix deployed: a known `admin@example.com` / `password` login must not exist in production.

---

## Phase 6: Stripe dashboard

Stripe > Developers > Webhooks > your endpoint `https://<domain>/stripe/webhook` (the URL did not change).
Keep the existing events and **add**:

- `charge.refunded`
- `charge.refund.updated`
- `refund.updated`

The already-handled ones are `checkout.session.completed`, `.async_payment_succeeded`, `.async_payment_failed`, `.expired`.
Use "Send test webhook" and confirm a `200` (a `403` means `STRIPE_WEBHOOK_SECRET` is wrong or missing).

After one successful real payment (Phase 7), and only then:

```bash
# [app] 6.1 remove the legacy Stripe keys the old settings page stored in the database (it refuses unless STRIPE_KEY and STRIPE_SECRET are in the env)
php artisan settings:purge-legacy-secrets
```

---

## Phase 7: smoke test

```bash
# [laptop] 7.1 public pages and SEO files
for p in / /products /cart /contact /up /sitemap.xml /robots.txt; do printf '%s ' $p; curl -s -o /dev/null -w '%{http_code}\n' https://<domain>$p; done
curl -s https://<domain>/robots.txt | tail -2        # "Sitemap:" line must show your real https domain; if it shows localhost, APP_URL is wrong
```

Manual checks:

1. Place a **real order** with a small amount (or your usual Stripe test procedure). Confirm the order appears in the admin, paid, and
   the confirmation email arrives.
2. In MySQL: `SELECT type, sent_at, failed_at, error FROM order_emails ORDER BY id DESC LIMIT 5;` -> `sent_at` set, `failed_at` null.
3. Mark the order shipped in the admin. One "shipped" email goes out (the payment guard on the ship form is part of this release).
4. Issue a partial refund from the admin. The refund email goes out and the order's payment status updates.
5. `SELECT COUNT(*) FROM jobs;` goes back to ~0 and `SELECT COUNT(*) FROM failed_jobs;` stays 0.
6. Register a new test customer: verification email arrives, and the one-time login after registration works (this is the A6 fix; it relies on the shared cache).

---

## Rollback

Decide quickly. The longer the new version takes real orders, the more a rollback costs.

- **Code only** (the migrations ran but the app is misbehaving, and nothing important was written): Dokploy > `ecommerce` > Deployments > pick the
  `5e03dd1` deployment > Rollback. The old code runs against the new schema; the schema is additive apart from the foreign-key changes and the unique index, which the old code tolerates.
- **Full rollback** (migration failed halfway, or data is wrong): stop the stack in Dokploy, restore the dump from 1.3, redeploy `5e03dd1`:

```bash
# [server]
gunzip -c ~/backups/rsp-pre-fixes-<timestamp>.sql.gz | docker exec -i <MYSQL_CONTAINER> sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" <DB_NAME>'
```

  This loses any order placed after the backup. Check Stripe for payments made in that window.
- Do not rely on `php artisan migrate:rollback`: the legacy-settings merge cannot restore keys it deleted.

---

## Known issues this deploy does not fix

- **`/categories/{slug}` returns HTTP 500.** `CategoryController` renders the React page `Categories/Show`, but `resources/js/pages/Categories/Show.tsx`
  does not exist (not at `5e03dd1` either, so production has this today). The sitemap still lists these URLs. It also causes the 11 `ProductListPerPageTest` failures.
  Needs either the page restored or the route/sitemap entries removed. Separate follow-up.
- **Spediamo label creation** (`shipping.labels_enabled`, default off): the adapter was built from the docs with assumptions (JSON body on
  `POST /v1/shipments`, no explicit pay step, waybill PDF via `?download=true`). Test it with one real shipment before switching it on in the admin settings.
- **Workstream I (i18n sweep)** is not on `fixes` yet. Deploying now ships without it.
- The new compose file has no `depends_on`, and `app` has no healthcheck. The workers may log errors for a minute while `app` migrates.
- The Dockerfile still keeps `config:cache` off by choice. Do not turn it on during this deploy.
