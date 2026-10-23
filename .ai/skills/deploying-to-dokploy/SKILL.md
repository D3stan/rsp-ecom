---
name: deploying-to-dokploy
description: "Deploys and manages this Laravel app on Dokploy (Docker Compose) through the dokploy-mcp tools. Use when the user wants to deploy, redeploy, change environment variables, manage domains, read build or container logs, or troubleshoot the production or sandbox stack on Dokploy."
license: MIT
metadata:
  author: custom
---

# Deploying with Dokploy

Use the `dokploy-mcp` tools for every Dokploy operation and read each tool's schema instead of guessing parameters. For the full production cutover procedure follow `docs/deploy-runbook.md`; do not restate it here.

## Two stacks

Dokploy runs two compose stacks of this repo. Find each one by name with `project-all` or `compose-search`; never hardcode compose IDs. Confirm which stack the user means before any write.

| | Production | Sandbox |
|---|---|---|
| Compose file | `docker-compose.yml` | `docker-compose.sandbox.yml` |
| Services | `app`, `queue`, `scheduler` | those three plus `mailpit`, `db` (MariaDB), `redis` |
| Database | external Dokploy database | in-stack `rsp_sandbox` |
| Volumes | `storage-app`, `storage-logs` | `sandbox-*` (storage, logs, db, redis, mailpit) |
| Mail | Brevo SMTP | Mailpit: `MAIL_HOST=mailpit`, `MAIL_PORT=1025` |
| Stripe | live keys | test keys only |

- Both build the same `Dockerfile` (`serversideup/php:fpm-nginx`). Only `app` runs migrations (`AUTORUN_ENABLED=true`); `queue` and `scheduler` are command overrides with it off.
- Domains: service `app`, port **8080** (not 80). Production uses `https: false` because TLS ends at the Cloudflare tunnel in front of Traefik. To read captured sandbox mail, add a domain for service `mailpit`, port `8025`.
- Sandbox secrets live in the compose file and are throwaway. Never copy production secrets, keys or database dumps into sandbox.

## Safety rules

- Never deploy or redeploy production unless the user explicitly asks. The user deploys everything at the end of a work stream, not incrementally. Sandbox is the place to test first.
- Pass `freshVolumes: false` (or omit it) on `compose-deploy` and `compose-redeploy`. On sandbox, `true` wipes the database, Redis and Mailpit data; on production it wipes uploaded files and logs. Only set it when the user asks for a wipe.
- Ask for explicit confirmation before `compose-delete`, `environment-remove`, `project-remove` or `domain-delete`.
- `compose-saveEnvironment` replaces the whole env block: read the current value with `compose-one` first, change only what was asked, and never print secrets in chat. A saved change takes effect on the next deploy.
- `compose-redeploy` takes the same parameters as `compose-deploy`; do not assume it skips the build.

## Verifying and troubleshooting

- After a deploy, `deployment-allByCompose` to find it, then `deployment-readLogs` to confirm the build succeeded.
- Runtime problems: `compose-loadServices` for the container list, then `compose-readLogs` per container (`app` for nginx/FPM and migration failures, `queue` for failed jobs, `scheduler`).
- 502 after a successful build: check `app` logs for a failed migration, then `domain-byComposeId` for the service and port.
- Build and container logs run to thousands of lines; delegate reading them to a subagent and keep only the conclusion.
