# Deployment

- This application is deployed on [Dokploy](https://dokploy.com/) as Docker Compose stacks, managed through the `dokploy-mcp` server. There is a production stack (`docker-compose.yml`) and a sandbox stack (`docker-compose.sandbox.yml`, with Mailpit, MariaDB and Redis for testing).
- Activate the `deploying-to-dokploy` skill whenever deploying, changing Dokploy environment variables or domains, reading build or container logs, or troubleshooting a Dokploy deployment.
- Never deploy production unless the user explicitly asks.
