# Production Migration & Deploy Runbook: VPS (`laravel.service`) to Dokploy

This guide covers the complete cutover of production from the legacy systemd/host setup on the VPS to the Docker Compose stack managed by Dokploy (`fixes` merged into `main`: 26 new migrations, `queue` and `scheduler` containers, updated Stripe webhooks, and Brevo SMTP).

Because brief downtime (~10 minutes, mostly image build and migration time) is acceptable, this runbook uses a **single-phase direct hotswap**: prepare and verify prerequisites first, then put the old site into maintenance mode, dump the database **once**, import it into Dokploy, deploy, copy uploads, and switch the Cloudflare tunnel.

---

## Where Commands Run & Naming Reference

- **`[laptop]`** — Run locally in this repository (`~/Documents/GitHub/rsp-ecom`).
- **`[vps]`** — Run over SSH on the VPS (`ssh vps`, user `deploy`). Docker commands require `sudo`.
- **`[app]`** — Run inside the Dokploy `app` container:
  ```bash
  sudo docker exec -it rsp-ecommerce-web-jp6mz0-app-1 php artisan <command>
  ```
- **`[mysql]`** — Run against the Dokploy MySQL container (`rsp-db-kydbyt`).

| Component | Old Production (VPS Host) | New Production (Dokploy) |
|---|---|---|
| **Code & Runtime** | `~/ecommerce/current` (`systemd laravel.service`, port `8000`) | Compose app `ecommerce`: `app` (`rsp-ecommerce-web-jp6mz0-app-1`, port `8080`), `queue`, `scheduler` |
| **Database** | Host MySQL, db `rsp_ecom`, user `rsp_user` | Container `rsp-db-kydbyt`, db `rsp`, user `mysql` |
| **Uploads** | `~/ecommerce/shared/storage/app` | Docker volume `storage-app` $\rightarrow$ `/var/www/html/storage/app` |
| **Git Source** | Deployer (`deploy.php`) from `main` | GitHub `D3stan/rsp-ecom`, branch `main` (`./docker-compose.yml`) |

---

## Phase 1: Prerequisites & Pre-Cutover Preparation (No Downtime)

Everything in this section is read-only on old production or staged in Dokploy without affecting live traffic.

### 1.1 Code Preflight (If `fixes` is not yet on `main`)

Dokploy builds from `main`. If you haven't merged `fixes` into `main` yet, verify tests and fast-forward `main` on your laptop:

```bash
# [laptop] Verify tests pass and fast-forward main
cd ~/Documents/GitHub/rsp-ecom
git checkout fixes && git status -sb
npm ci && npm run build
APP_KEY=base64:$(openssl rand -base64 32) php artisan test --compact
git checkout public/robots.txt                # tests rewrite robots.txt; discard that change

git fetch origin
git checkout main && git pull --ff-only origin main
git merge --ff-only fixes
git push origin main
```

### 1.2 Inspect Old Production `.env`

On the VPS, list all environment variable names currently in old production (names only, no values):

```bash
# [vps]
grep -oE '^[A-Z_]+=' ~/ecommerce/current/.env
```

Compare that list with the environment table in **Step 1.6** below. If old production has a variable the app reads that isn't in the table (for example `APP_NAME`, `APP_LOCALE`, or `CONTACT_EMAIL`), include it in Dokploy. Skip `DB_*`, `REDIS_*`, and `PHP_CLI_SERVER_WORKERS`.

### 1.3 Verify Stripe Webhook Secret & `APP_URL`

1. **Check that old production has a Stripe webhook signing secret:**
   ```bash
   # [vps]
   grep -c '^STRIPE_WEBHOOK_SECRET=whsec_' ~/ecommerce/current/.env    # must print 1
   ```
   If this prints `0`, copy the signing secret from **Stripe Dashboard > Developers > Webhooks > `<PROD_URL>/stripe/webhook`**. The new code returns HTTP `403` to every webhook in production without a valid `STRIPE_WEBHOOK_SECRET`.

2. **Check old production's exact `APP_URL` (apex or `www`):**
   ```bash
   # [vps]
   grep '^APP_URL=' ~/ecommerce/current/.env
   ```
   Use that exact host as `<PROD_URL>` everywhere below. Both the Stripe webhook endpoint and the Google OAuth redirect URI are registered on it, and signed order/unsubscribe links plus `sitemap.xml`/`robots.txt` depend on it.

3. **Read the secret values to copy into Dokploy:**
   ```bash
   # [vps]
   grep -E '^(APP_KEY|STRIPE_[A-Z_]+|GOOGLE_CLIENT_[A-Z]+|MAIL_[A-Z_]+)=' ~/ecommerce/current/.env
   ```

### 1.4 Pre-Cutover Database Checks on Old Production

Connect to the old host MySQL database to verify there are no migration blockers and preview recent orders:

```bash
# [vps]
cd ~/ecommerce/current
export MYSQL_PWD="$(grep '^DB_PASSWORD=' .env | cut -d= -f2- | sed 's/^"\(.*\)"$/\1/')"
```

1. **Blocker check — duplicate Stripe checkout sessions (MUST return zero rows):**
   The new migrations add a unique index on `orders.stripe_checkout_session_id`. If duplicates exist, the migration will abort and the `app` container will fail to start.
   ```bash
   # [vps]
   mysql -u rsp_user rsp_ecom -e "SELECT stripe_checkout_session_id, COUNT(*) AS n, GROUP_CONCAT(id) AS order_ids
     FROM orders WHERE stripe_checkout_session_id IS NOT NULL
     GROUP BY stripe_checkout_session_id HAVING COUNT(*) > 1;"
   ```
   *If rows are returned:* inspect those orders in the admin panel and merge/remove the duplicate before starting cutover.

2. **Preview recent orders that the new scheduler would email again:**
   The new scheduler looks back 7 days for succeeded orders with `confirmation_email_sent = 0`. Step 2.4 below sets `confirmation_email_sent = 1` on all existing succeeded orders so customers do not receive duplicate emails. Run this first in case an order in the last 7 days genuinely missed its confirmation email:
   ```bash
   # [vps]
   mysql -u rsp_user rsp_ecom -e "SELECT order_number, created_at, confirmation_email_sent FROM orders
     WHERE payment_status='succeeded' AND created_at >= NOW() - INTERVAL 7 DAY;"
   ```

3. **Check admin accounts and legacy DB settings:**
   ```bash
   # [vps]
   mysql -u rsp_user rsp_ecom -e "SELECT id, email, role, is_active FROM users WHERE role = 'admin';
     SELECT id, email FROM users WHERE email = 'admin@example.com';
     SELECT \`key\` FROM settings WHERE \`key\` LIKE 'stripe%';"
   ```

### 1.5 Configure Dokploy Domains

In **Dokploy > `RSP` > `ecommerce` > Domains**:
- Add `rsp-industries.com` and `www.rsp-industries.com`, each pointing to:
  - **Service:** `app`
  - **Port:** `8080`
  - **Path:** `/`
  - **HTTPS:** `off` (TLS is terminated by the Cloudflare tunnel in front of Traefik)

These domain rules have no effect on live traffic until the Cloudflare tunnel is updated in Step 2.8.

### 1.6 Configure Dokploy Environment (Final Cutover Values)

In **Dokploy > `RSP` > `ecommerce` > Environment**, set the production variables directly. **Save, but do not click Deploy yet.**

| Variable | Value to Set | Notes |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` / `LOG_LEVEL` | `production` / `false` / `warning` | Must be `production` before running `db:seed`. |
| `APP_KEY` | Copy from old prod `.env` | **Never generate a new one** (encrypts sessions/cookies/data). |
| `APP_URL` | `<PROD_URL>` | Exact URL from Step 1.3 (e.g. `https://rsp-industries.com`). |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Copy from old prod `.env` | Google OAuth credentials. |
| `GOOGLE_REDIRECT_URL` | `<PROD_URL>/auth/google/callback` | Set as a literal value matching `<PROD_URL>`. |
| `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | Copy from old prod `.env` | See Step 1.3. Webhook secret is required (`whsec_...`). |
| `MAIL_MAILER` | `smtp` | Real SMTP transport for production. |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` *(+ `MAIL_SCHEME` if set in prod)* | Copy old prod's Brevo values | Replaces the old Mailgun variables. Ensure SPF/DKIM/DMARC are set on the sender domain. |
| `MAIL_SUPPORT_ADDRESS` | Monitored support inbox | Shown in customer emails (e.g. `support@rsp-industries.com`). |
| `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` | `database` | Shared across `app`, `queue`, and `scheduler` (required for one-time registration login nonce). |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Keep Dokploy values (`host: rsp-db-kydbyt`, `db: rsp`, `user: mysql`) | Shared MySQL container for all three services. |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Delete if present | Real admin (`admin@rsp.com`) is imported with the prod DB dump. |
| `AUTORUN_ENABLED` | **Do not set** | Leave unset; `docker-compose.yml` controls container startup behavior. |
| `SHIPPING_*` | Leave unset | Tracking defaults to `manual` and label creation defaults to `off` (optional: `SHIPPING_TRACKING_SPEDIAMO_TOKEN`). |

---

## Phase 2: Cutover & Hotswap (~10 Minutes Downtime)

Execute these steps in order. Because we put the old site down first, we only dump the database and copy uploads once.

### 2.1 Put Old Production into Maintenance Mode

```bash
# [vps] Stop taking new orders or file uploads on the old site
cd ~/ecommerce/current && php artisan down
```

### 2.2 Dump the Old Production Database (Single Final Dump)

```bash
# [vps]
mkdir -p ~/pgbackup && cd ~/ecommerce/current
export MYSQL_PWD="$(grep '^DB_PASSWORD=' .env | cut -d= -f2- | sed 's/^"\(.*\)"$/\1/')"
D=~/pgbackup/rsp-prod-$(date +%F-%H%M).sql.gz

mysqldump -u rsp_user --single-transaction --routines --triggers --no-tablespaces --set-gtid-purged=OFF \
  --default-character-set=utf8mb4 rsp_ecom | gzip > "$D"

gunzip -c "$D" | tail -1        # must print "-- Dump completed on …"
ls -lh "$D"                     # verify non-empty size
```

*(Optional but recommended)* From your laptop, copy this dump off the server as a permanent pre-migration backup:
```bash
# [laptop]
scp vps:pgbackup/rsp-prod-*.sql.gz .
```

### 2.3 Stop the Dokploy Stack

In **Dokploy > `RSP` > `ecommerce`**, click **Stop**.

Wait until `rsp-ecommerce-web-jp6mz0-app-1`, `queue`, and `scheduler` are stopped before touching the Dokploy database.

### 2.4 Replace the Dokploy Database with the Production Dump

Wiping the `rsp` database first is required: otherwise, importing the dump replaces the `migrations` table with old production's 37 rows while leaving tables created by earlier Dokploy runs behind, causing the 26 new migrations to fail on existing tables.

```bash
# [vps]
D=$(ls -t ~/pgbackup/rsp-prod-*.sql.gz | head -1)          # newest dump only
M=$(sudo docker ps -q -f name=rsp-db-kydbyt)
sql() { sudo docker exec -i "$M" sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$@"' _ "$@"; }

# 1. Wipe and recreate with utf8mb4
sql -e "DROP DATABASE IF EXISTS rsp; CREATE DATABASE rsp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Import the production dump
gunzip -c "$D" | sql rsp

# 3. Verify counts (expect 37 migrations, existing orders & users, and grants on `rsp`.* for 'mysql'@'%')
sql rsp -e "SELECT COUNT(*) migrations FROM migrations; SELECT COUNT(*) orders FROM orders; SELECT COUNT(*) users FROM users; SHOW GRANTS FOR 'mysql'@'%';"

# 4. Mark existing succeeded orders as confirmed so the new scheduler does not re-email old customers
sql rsp -e "UPDATE orders SET confirmation_email_sent=1 WHERE payment_status='succeeded' AND confirmation_email_sent=0;"
```

### 2.5 Deploy in Dokploy

In **Dokploy > `RSP` > `ecommerce`**, click **Deploy**.

What happens during Deploy:
1. Builds the Docker image (`composer install`, `npm run build`, PHP `gd` extension).
2. Applies the cutover environment variables and Traefik domain rules (`rsp-industries.com` and `www`).
3. Starts `app`, `queue`, and `scheduler`.
4. `app` runs `php artisan migrate --force` (executing the 26 new migrations to reach 63 total, merging legacy settings, and adding the unique index on `orders.stripe_checkout_session_id`), runs `storage:link`, clears caches, and regenerates the sitemap.

> [!NOTE]
> The `queue` and `scheduler` containers start at the same moment as `app`, before its migrations finish. Their logs may show a few transient errors during the first minute while `app` migrates — this is expected as long as they settle once migrations complete.

### 2.6 Copy Uploaded Files into the Dokploy Volume

Once the `app` container is running, stream all uploaded files from the old shared directory into the container's `/var/www/html/storage/app` volume and fix ownership:

```bash
# [vps]
APP=$(sudo docker ps -q -f name=rsp-ecommerce-web-jp6mz0-app-1)
tar -C ~/ecommerce/shared/storage/app -cf - . \
  | sudo docker exec -i -u root "$APP" sh -c 'tar -C /var/www/html/storage/app -xf - && chown -R www-data:www-data /var/www/html/storage/app'

# Verify file count (expect >= 102)
sudo docker exec "$APP" sh -c 'find /var/www/html/storage/app/public -type f | wc -l'
```

### 2.7 Post-Boot Verification & Seeding

1. **Check container logs and health on the VPS:**
   ```bash
   # [vps]
   sudo docker logs --tail 80 rsp-ecommerce-web-jp6mz0-app-1 2>&1 | grep -iE "migrat|error|exception"
   sudo docker ps --format '{{.Names}}\t{{.Status}}' | grep rsp-ecommerce
   # Expect: app, queue, scheduler all "Up", and queue/scheduler "(healthy)"
   ```

2. **Run post-deploy Artisan checks inside the `app` container:**
   ```bash
   # [app] Run via: sudo docker exec -it rsp-ecommerce-web-jp6mz0-app-1 php artisan <command>
   php artisan env                                   # -> production (REQUIRED before running db:seed)
   php artisan migrate:status | grep -v ' Ran'       # nothing but the header: all 63 migrations ran
   php artisan db:seed --force                       # adds missing settings defaults; "no admin user created" is expected
   php artisan settings:list --group=tax
   php artisan stripe:check-config
   php artisan schedule:list
   php artisan email:send-test <your-email-address>  # must arrive in your inbox via Brevo SMTP
   ```

3. **Remove demo admin if present:**
   If Step 1.4 showed a row for `admin@example.com`, remove it now:
   ```bash
   # [vps]
   sql rsp -e "DELETE FROM users WHERE email = 'admin@example.com';"
   ```

### 2.8 Hotswap the Cloudflare Tunnel

Update your Cloudflare tunnel configuration so `rsp-industries.com` and `www.rsp-industries.com` point to the Dokploy/Traefik ingress instead of `http://localhost:8000`.

### 2.9 Update & Test Stripe Webhooks

In **Stripe Dashboard > Developers > Webhooks > `<PROD_URL>/stripe/webhook`**:
1. Keep the existing events (`checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `checkout.session.expired`).
2. **Add** the three refund events:
   - `charge.refunded`
   - `charge.refund.updated`
   - `refund.updated`
3. Send a **test webhook** from Stripe and verify it returns HTTP `200` (an HTTP `403` means `STRIPE_WEBHOOK_SECRET` in Dokploy does not match this endpoint).

### 2.10 Smoke Test & Live Order Verification

1. **Automated HTTP & SEO check:**
   ```bash
   # [vps or laptop]
   for p in / /products /cart /contact /up /sitemap.xml /robots.txt; do
     printf '%s ' "$p"
     curl -s -o /dev/null -w '%{http_code}\n' "<PROD_URL>$p"
   done
   # Expect 200 on every route

   curl -s "<PROD_URL>/robots.txt" | tail -2
   # Sitemap: line must show <PROD_URL>/sitemap.xml
   ```

2. **Admin & Customer UI check:**
   - Verify `/products` loads with product images from the migrated storage volume.
   - Log in as `admin@rsp.com`. Open an existing order, verify the dashboard and pending-orders badge look right, and confirm **Settings** (tax rate, shipping destinations, support email).
   - Log in as an existing customer (or register a new test customer to verify the verification email and post-registration one-time login nonce).

3. **End-to-end live order check:**
   - Place **one small real order**. Confirm it appears as paid in the admin panel and the order confirmation email arrives.
   - Check the email audit table in MySQL:
     ```bash
     # [vps]
     sql rsp -e "SELECT type, sent_at, failed_at, error FROM order_emails ORDER BY id DESC LIMIT 5;"
     # Expect: sent_at set, failed_at NULL
     ```
   - Mark the order **shipped** in the admin panel $\rightarrow$ verify one "shipped" email arrives.
   - Refund the order from the admin panel $\rightarrow$ verify the refund email arrives and the order's payment status updates.
   - Check the queue tables:
     ```bash
     # [vps]
     sql rsp -e "SELECT COUNT(*) AS jobs FROM jobs; SELECT COUNT(*) AS failed_jobs FROM failed_jobs;"
     # Expect: jobs ~ 0, failed_jobs = 0
     ```

4. **Purge legacy Stripe secrets from the database:**
   Once your first real payment has succeeded and `STRIPE_KEY` / `STRIPE_SECRET` are confirmed working from the environment:
   ```bash
   # [app]
   php artisan settings:purge-legacy-secrets
   ```

---

## Rollback Plan (Decide Quickly)

Because old production (`~/ecommerce/current` and host MySQL `rsp_ecom`) was never modified after Step 2.1, rolling back is immediate:

1. **Point the Cloudflare tunnel back** at `http://localhost:8000`.
2. **Bring the old site out of maintenance mode:**
   ```bash
   # [vps]
   cd ~/ecommerce/current && php artisan up
   ```
3. **Reconcile any post-cutover orders:** Any orders placed while traffic was routed to Dokploy exist only in the Dokploy `rsp` database. Check the Stripe dashboard for payments created after the cutover timestamp and recreate those orders by hand if needed.

---

## Post-Migration Cleanup (After One Week of Stable Running)

- **Stop the legacy systemd service:**
  ```bash
  # [vps]
  sudo systemctl stop laravel.service
  sudo systemctl disable laravel.service
  ```
- **Clean up old tunnel routes:** Remove the unused `localhost:8000` route from `cloudflared` if nothing else uses it.
- **Archive and drop the old host database:** Verify you have copied the final dump from `~/pgbackup/rsp-prod-*.sql.gz` off the server, then drop `rsp_ecom` from host MySQL.
- **Optional Dokploy domain cleanup:** Remove `rsp.0xpuddu.com` from **Dokploy > `RSP` > `ecommerce` > Domains** if you no longer need it.

---

## Operational Notes & Known Behaviors

- **Spediamo label creation (`shipping.labels_enabled`, default `off`):** The adapter was built against the Spediamo API docs (`POST /v1/shipments` JSON body, no separate pay call, waybill PDF via `?download=true`). Test with one real shipment before enabling it in Admin > Settings.
- **Worker startup race:** `docker-compose.yml` does not block `queue` and `scheduler` on `app` migration completion. Transient log errors during the first 60 seconds of a fresh deploy are benign.
- **Config caching:** The Dockerfile keeps `config:cache` off by design. Do not run `php artisan config:cache` manually inside the container.
- **Customer deactivation vs. GDPR erasure:** Deactivating a customer in Admin > Customers blocks login, but full GDPR erasure remains a manual process.
