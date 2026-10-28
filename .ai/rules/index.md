# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/Http/**, routes/** | .ai/rules/boost/http-routes.md |
| resources/js/** | .ai/rules/boost/js.md |
| app/Models/** | .ai/rules/boost/models.md |
| tests/** | .ai/rules/boost/tests.md |
| app/Http/Controllers/Checkout*.php, app/Services/OrderCreationService.php, app/Services/PromotionService.php, app/Listeners/StripeWebhookListener.php, app/Support/CheckoutTotals.php, tests/Feature/Checkout/*.php | .ai/rules/checkout-payments.md |
| Dockerfile, docker/**, docker-compose*.yml, .github/workflows/** | .ai/rules/deployment.md |
| app/Services/Newsletter/*.php, app/Jobs/Newsletter/*.php, app/Jobs/SendOrderEmail.php, tests/Feature/Newsletter/*.php | .ai/rules/email-newsletter.md |
| .env.example, .env.production.example, .env.sandbox.example | .ai/rules/environment.md |
| app/Services/Ops/*.php, app/Console/Commands/*.php, config/ops.php, routes/console.php | .ai/rules/ops-observability.md |
| app/Domain/Orders/*.php, app/Models/Order*.php, app/Services/RefundService.php, tests/Feature/Orders/*.php | .ai/rules/orders-refunds.md |
| app/Settings/**/*.php, config/settings.php, tests/Feature/Settings/*.php, tests/Unit/Settings/*.php | .ai/rules/settings.md |
| app/Shipping/**/*.php, config/shipping.php, tests/Feature/Shipping/*.php, tests/Unit/Shipping/*.php | .ai/rules/shipping.md |
