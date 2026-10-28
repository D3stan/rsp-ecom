---
paths:
  - Dockerfile
  - docker/**
  - docker-compose*.yml
  - .github/workflows/**
---
# Deployment

Dokploy Docker Compose stacks (production and sandbox), the image they build, and CI. Not covered: env var meanings (`.ai/rules/environment.md`), monitoring behaviour (`.ai/rules/ops-observability.md`).

## Entry points
- `Dockerfile`: one multi-stage image used by every role.
- `docker-compose.yml`: production stack (`app`, `queue`, `scheduler`).
- `docker-compose.sandbox.yml`: sandbox stack, same roles plus in-stack MariaDB and a mail catcher.
- `docker/entrypoint.d/60-rsp-startup.sh`: startup tasks run after migrate.
- `.github/workflows/tests.yml`, `lint.yml`, `security.yml`: CI only.
- `.ai/skills/deploying-to-dokploy/SKILL.md`: MCP call sequences.

## Rules
- NEVER push or merge to `main` without explicit user approval: it deploys production (unenforced).
- NEVER pass `freshVolumes: true` on a deploy: it wipes `storage-app` uploads (unenforced).
- NEVER call `compose-saveEnvironment` with a partial block: it replaces the whole env block (unenforced).
- NEVER run `php artisan config:cache` or enable `AUTORUN_LARAVEL_*_CACHE` (unenforced; see Traps).
- MUST keep `queue` and `scheduler` on `AUTORUN_ENABLED=false` and `RSP_STARTUP_TASKS=false`, or every worker restart migrates and rebuilds sitemaps (unenforced; set in the compose files).
- MUST keep sessions, cache and queue on a store all three containers share (`database`; unenforced).
- MUST NOT add Redis settings: the image has no redis extension (unenforced).
- Real values live only in the Dokploy Environment tab; never commit them (unenforced).

## How it works
1. Two Dokploy compose stacks in project `RSP`, both GitHub-sourced with auto-deploy on push. Live ids and domains: `compose-search`, `domain-byComposeId`.
2. `main` deploys production (`docker-compose.yml`, apex + `www`); `sandbox` deploys the sandbox (`docker-compose.sandbox.yml`, `test.` subdomain).
3. A push deploys only when a changed file matches the stack's watch paths: `app/`, `bootstrap/`, `config/`, `database/`, `docker/`, `lang/`, `public/`, `resources/`, `routes/`, `storage/`, `artisan`, `Dockerfile`, `.dockerignore`, the stack's compose file, composer and npm manifests plus locks, `vite.config.ts`, `tsconfig.json`, `components.json`. Docs, `.ai/`, `tests/`, `.github/`, `graphify-out/` then do not deploy. Check `compose-one` → `watchPaths`: an empty list means every push deploys.
4. CI never deploys and does not gate the deploy. Workflows run on pushes and PRs to `main` and `sandbox`.
5. Build: serversideup php 8.5 fpm-nginx (+bcmath, exif, gd), `composer --no-dev`, then node 22 `npm ci` + `npm run build`. Optional Sentry source-map upload via build args.
6. One image, three roles. `app` serves HTTP on 8080; serversideup AUTORUN runs `migrate --force` and `storage:link` at boot, then `60-rsp-startup.sh` clears caches and generates the sitemap index.
7. `queue` runs `queue:work --tries=3 --max-time=3600 --sleep=3 --timeout=60` (stop grace 90s); `scheduler` runs `schedule:work`. Both have image-native healthchecks.
8. Production uses a separate Dokploy MySQL service; the sandbox runs MariaDB 11.2 and a mail catcher inside its stack.
9. TLS ends at a Cloudflare tunnel in front of Traefik. Dokploy domains use `https: false`, service `app`, port 8080.

## Traps
- With watch paths set, a docs-only push does not deploy; a code change in the same push does.
- Workers do not wait for `app` migrations: errors in the first seconds of a deploy are expected.
- Config/route/view/event caches are off on purpose: `env()` outside `config/` was removed but caching has not been verified end to end (comment in `Dockerfile`).
- Build args and `VITE_*` vars are baked at build time; changing them needs a rebuild, not a restart.
- `queue:work` exits every `--max-time` seconds by design; the restart policy brings it back.
- Migrations are not reversed on rollback.

## Operate
- Live state: `compose-search`, `compose-one`, `domain-byComposeId`, `deployment-allByCompose`, `deployment-readLogs`.
- Env templates: `.env.production.example`, `.env.sandbox.example`; paste into the Dokploy Environment tab (it writes `.env`).
- Health: `GET /api/health` (503 on failure), `GET /up`. Sentry, Telegram and heartbeat: `.ai/rules/ops-observability.md`.
- Procedures: `docs/runbooks.md`.

## Tests
- No test covers these files. `tests/Feature/Ops/HealthEndpointTest.php` guards `/api/health`; `tests/Feature/Ops/SchedulerHeartbeatTest.php` guards the scheduler heartbeat.
