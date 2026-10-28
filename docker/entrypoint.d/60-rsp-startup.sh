#!/bin/sh
# Runs after the image's Laravel automations (storage:link, migrate): clears caches and builds the sitemap index.
# Sitemaps live in public/, which is rebuilt on every deploy, so they must be regenerated at startup.

# The queue and scheduler containers share this image and its entrypoint (the entrypoint.d scripts run
# for ANY command). They set RSP_STARTUP_TASKS=false so they don't clear caches or rebuild sitemaps
# every time they restart (queue:work exits every --max-time seconds by design).
if [ "${RSP_STARTUP_TASKS:-true}" = "false" ]; then
    exit 0
fi

cd /var/www/html || exit 0

php artisan app:clear-all-cache || echo "⚠️ app:clear-all-cache failed"
php artisan generate:sitemap-index || echo "⚠️ generate:sitemap-index failed"
