# Mixed Content HTTPS Fix Documentation

## Overview

This document describes the implementation of a fix for mixed content errors that occur when the application is served over HTTPS but makes HTTP requests to API endpoints.

**Error Fixed:**
```
Mixed Content: The page at 'https://test.rsp-industries.com/' was loaded over HTTPS, but requested an insecure XMLHttpRequest endpoint 'http://test.rsp-industries.com/products'. This request has been blocked; the content must be served over HTTPS.
```

## Problem Description

The mixed content error occurred because:
1. The application is served over HTTPS (`https://test.rsp-industries.com/`)
2. Laravel was generating HTTP URLs for AJAX requests (`http://test.rsp-industries.com/products`)
3. Modern browsers block HTTP requests from HTTPS pages for security reasons
4. This prevented the frontend from loading product data and other API endpoints

## Solution Implementation

### Files Modified/Created

1. **Created:** `app/Http/Middleware/TrustProxies.php`
2. **Modified:** `bootstrap/app.php`
3. **Modified:** `app/Providers/AppServiceProvider.php`

### 1. TrustProxies Middleware

**File:** `app/Http/Middleware/TrustProxies.php`

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    protected $proxies = '*';
    
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
```

**Purpose:** Ensures Laravel correctly detects HTTPS when running behind a reverse proxy or load balancer.

### 2. Middleware Registration

**File:** `bootstrap/app.php`

**Added Import:**
```php
use App\Http\Middleware\TrustProxies;
```

**Added Middleware Configuration:**
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(at: [
        TrustProxies::class,
    ]);
    
    // ... existing middleware configuration
})
```

### 3. Force HTTPS URL Generation

**File:** `app/Providers/AppServiceProvider.php`

**Added Import:**
```php
use Illuminate\Support\Facades\URL;
```

**Added HTTPS Forcing Logic:**
```php
public function boot(): void
{
    // Force HTTPS when APP_URL is HTTPS or when behind a proxy with HTTPS
    if (str_starts_with(config('app.url'), 'https://') || request()->header('X-Forwarded-Proto') === 'https') {
        URL::forceScheme('https');
    }
    
    // ... existing boot logic
}
```

## How the Solution Works

1. **TrustProxies Middleware:** Processes incoming requests to detect the proper scheme (HTTP/HTTPS) from proxy headers
2. **HTTPS URL Generation:** Forces all generated URLs to use HTTPS scheme when the application detects it's running over HTTPS
3. **Automatic Detection:** The solution automatically activates when:
   - `APP_URL` starts with `https://` (which it does: `https://test.rsp-industries.com`)
   - The request contains `X-Forwarded-Proto: https` header from a reverse proxy

## Configuration Cache

After implementing these changes, the configuration cache was cleared:
```bash
php artisan config:cache
```

---

## How to Remove This Fix

If you need to remove this HTTPS fix implementation, follow these steps in order:

### Step 1: Remove HTTPS Forcing from AppServiceProvider

**File:** `app/Providers/AppServiceProvider.php`

**Remove this import:**
```php
use Illuminate\Support\Facades\URL;
```

**Change the boot method from:**
```php
public function boot(): void
{
    // Force HTTPS when APP_URL is HTTPS or when behind a proxy with HTTPS
    if (str_starts_with(config('app.url'), 'https://') || request()->header('X-Forwarded-Proto') === 'https') {
        URL::forceScheme('https');
    }

    // Configure Cashier
    // ... rest of the method
}
```

**Back to:**
```php
public function boot(): void
{
    // Configure Cashier
    // ... rest of the method
}
```

### Step 2: Remove TrustProxies from Middleware Registration

**File:** `bootstrap/app.php`

**Remove this import:**
```php
use App\Http\Middleware\TrustProxies;
```

**Change the middleware configuration from:**
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(at: [
        TrustProxies::class,
    ]);

    $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

    $middleware->web(append: [
        HandleAppearance::class,
        HandleInertiaRequests::class,
        AddLinkHeadersForPreloadedAssets::class,
    ]);
})
```

**Back to:**
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

    $middleware->web(append: [
        HandleAppearance::class,
        HandleInertiaRequests::class,
        AddLinkHeadersForPreloadedAssets::class,
    ]);
})
```

### Step 3: Delete TrustProxies Middleware File

**Delete the file:**
```
app/Http/Middleware/TrustProxies.php
```

### Step 4: Clear Configuration Cache

After removing the changes, clear the configuration cache:

```bash
php artisan config:cache
```

### Step 5: Restart Web Server

Restart your web server to ensure all changes take effect.

---

## Verification

### To Verify Fix is Working:
1. Open browser developer tools (F12)
2. Navigate to your application
3. Check Network tab - all requests should use HTTPS URLs
4. Check Console tab - no mixed content errors should appear

### To Verify Removal is Complete:
1. Check that no TrustProxies file exists in `app/Http/Middleware/`
2. Verify `bootstrap/app.php` doesn't reference TrustProxies
3. Verify `AppServiceProvider.php` doesn't contain URL forcing logic
4. Test that the application still works (but mixed content errors may return if running over HTTPS)

## Notes

- This fix is only necessary when running the application over HTTPS
- The fix is backward compatible and won't affect HTTP-only deployments
- If you're running behind a reverse proxy (nginx, Apache, CloudFlare, etc.), the TrustProxies middleware is essential for proper HTTPS detection
- Always test thoroughly after implementing or removing these changes

## Related Environment Variables

Ensure your `.env` file has the correct APP_URL:
```env
APP_URL=https://test.rsp-industries.com
```

This should match the actual URL where your application is accessible.
