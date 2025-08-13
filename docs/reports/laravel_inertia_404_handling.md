# 📘 Laravel + Inertia + React: Proper 404 Error Handling

This guide walks you through setting up a user-friendly and accurate **404 Not Found** page in a Laravel 12 + Inertia.js + React 19 stack.

---

## ✅ 1. Laravel: Add a Fallback Route

In your `routes/web.php`, add this at the bottom of the file to catch all undefined routes:

```php
use Inertia\Inertia;

Route::fallback(function () {
    return Inertia::render('Errors/NotFound');
});
```

This ensures that any request that doesn't match an existing route returns a 404 component via Inertia.

---

## ✅ 2. React: Create the 404 Page Component

In your frontend directory (`resources/js/pages/Errors/`), create a file called `NotFound.tsx`:

```tsx
// resources/js/pages/Errors/NotFound.tsx

import { Head } from '@inertiajs/react';

export default function NotFound() {
  return (
    <>
      <Head title="404 Not Found" />
        .....
    </>
  );
}
```

Make sure the folder structure and filename match exactly with the Inertia fallback definition.

---

## ✅ 3. Customize Laravel's Exception Handler

To ensure that exceptions thrown from controllers or the framework also lead to your custom 404 page, update `app/Exceptions/Handler.php`:

```php
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

public function render($request, Throwable $exception)
{
    if ($exception instanceof NotFoundHttpException && !$request->expectsJson()) {
        return Inertia::render('Errors/NotFound')
            ->toResponse($request)
            ->setStatusCode(404);
    }

    return parent::render($request, $exception);
}
```

This will ensure even non-route 404s (like missing resources or failed model lookups) are handled properly.

---

## ✅ 4. Set HTTP Status Code (Client-Side Awareness)

You can explicitly set the HTTP status code when rendering the Inertia response in Laravel (already handled in the step above), or document it via Inertia `Head` for clarity in browser tools.

```tsx
<Head title="404 Not Found" />
```

---

## ✅ 5. Testing

- Visit a known invalid URL (e.g. `/non-existent-page`)
- You should see the custom `NotFound` page rendered
- Confirm the status code is `404` using browser dev tools or curl:

```bash
curl -I http://localhost:8000/non-existent-page
```

Expected output:

```
HTTP/1.1 404 Not Found
```

---

## ✅ 6. Bonus: Set Up a Global 404 Style

You may optionally wrap the `NotFound` page in a consistent layout (`resources/js/layouts`) or apply special styles (dark background, illustrations, etc.)

---

### ✅ Done! You now have proper 404 handling in your Laravel + Inertia + React app.