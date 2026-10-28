# Deployment

- Dokploy auto-deploys on every push: a push or merge to `main` deploys PRODUCTION (`docker-compose.yml`), a push to `sandbox` deploys the sandbox (`docker-compose.sandbox.yml`). CI runs alongside the deploy and does not gate it.
- Never push or merge to `main`, and never deploy production, unless the user explicitly asks for it in this conversation.
- Activate the `deploying-to-dokploy` skill for any Dokploy operation (deploy, env vars, domains, logs, troubleshooting). Current infrastructure facts live in `.ai/rules/deployment.md`.
