#!/bin/sh
# Runs after the image's Laravel automations (storage:link, migrate), mirroring the old deploy.php post-deploy tasks.
# Sitemaps live in public/, which is rebuilt on every deploy, so they must be regenerated at startup.

cd /var/www/html || exit 0

php artisan app:clear-all-cache || echo "⚠️ app:clear-all-cache failed"
php artisan generate:sitemap-index || echo "⚠️ generate:sitemap-index failed"
