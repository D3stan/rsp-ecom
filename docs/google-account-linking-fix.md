# Google Account Linking Fix

## Issue Description
Existing users were unable to connect their Google account to their base account. When clicking the "Connect" button in Settings > Profile, users were being redirected to the dashboard instead of linking their Google account.

## Root Cause
The issue was in the `GoogleAuthController::handleGoogleCallback()` method. The authentication flow was designed primarily for new user registration and login, but didn't properly handle the case where:

1. A user is already logged in (authenticated)
2. They want to link their Google account to their existing account
3. The Google email doesn't necessarily match their current account's email

The routes were also restricted to guest users only, preventing authenticated users from accessing the Google OAuth flow.

## Fix Applied

### 1. Updated GoogleAuthController Logic
**File:** `app/Http/Controllers/Auth/GoogleAuthController.php`

Added new logic in `handleGoogleCallback()` method to check if there's already a logged-in user wanting to link their account:

```php
// Check if there's already a logged-in user wanting to link their account
$currentUser = Auth::user();
if ($currentUser) {
    // Check if this Google account is already linked to another user
    $googleAccountAlreadyLinked = User::where('google_id', $googleUser->getId())->exists();
    
    if ($googleAccountAlreadyLinked) {
        return redirect()->route('profile.edit')->with('error', 'This Google account is already linked to another user account.');
    }
    
    // Link Google account to the current logged-in user
    $currentUser->update([
        'google_id' => $googleUser->getId(),
        'avatar' => $googleUser->getAvatar(),
        'email_verified_at' => now(), // Ensure Google accounts are verified
    ]);
    
    return redirect()->route('profile.edit')->with('success', 'Google account linked successfully!');
}
```

### 2. Updated Route Configuration
**File:** `routes/auth.php`

Moved Google authentication routes outside of the `guest` middleware group so they can be accessed by both authenticated and guest users:

```php
// Google Authentication Routes (accessible by both guest and authenticated users)
Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle'])
    ->name('auth.google');

Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])
    ->name('auth.google.callback');
```

### 3. Added Test Coverage
**File:** `tests/Feature/Auth/GoogleAuthTest.php`

Added comprehensive test cases to cover the new functionality:

- `test_authenticated_user_can_link_google_account()`: Tests successful linking
- `test_authenticated_user_cannot_link_already_linked_google_account()`: Tests prevention of duplicate linking

## User Experience Improvements

### Before the Fix
1. User clicks "Connect" button in profile settings
2. Gets redirected to Google OAuth
3. After authorization, gets redirected to dashboard (no linking occurs)
4. No feedback about what happened

### After the Fix
1. User clicks "Connect" button in profile settings
2. Gets redirected to Google OAuth
3. After authorization, Google account is linked to their existing account
4. User returns to profile page with success message: "Google account linked successfully!"
5. If Google account is already linked elsewhere: Error message shown

## Security Considerations

- **Prevents Duplicate Linking**: Checks if Google account is already linked to another user
- **Maintains User Session**: Authenticated users remain logged in during the linking process
- **Email Verification**: Linking a Google account automatically marks email as verified
- **Avatar Update**: User's avatar is updated with Google profile picture
- **Graceful Error Handling**: Clear error messages for edge cases

## Testing

The fix has been tested with the following scenarios:

1. ✅ Authenticated user linking new Google account
2. ✅ Authenticated user attempting to link already-linked Google account
3. ✅ Guest user registration via Google (existing functionality preserved)
4. ✅ Guest user login via Google (existing functionality preserved)
5. ✅ Error handling for OAuth failures

## Files Modified

1. `app/Http/Controllers/Auth/GoogleAuthController.php` - Added authenticated user linking logic
2. `routes/auth.php` - Moved Google routes outside guest middleware
3. `tests/Feature/Auth/GoogleAuthTest.php` - Added new test cases
4. `docs/google-authentication-setup.md` - Updated documentation
5. `docs/google-account-linking-fix.md` - Created this fix documentation

## Verification Steps

To verify the fix works:

1. Log in as an existing user
2. Go to Settings > Profile
3. In the "Connected Accounts" section, click "Connect" for Google
4. Complete Google OAuth authorization
5. Verify you're redirected back to profile page with success message
6. Confirm Google account shows as connected in the UI

## Backward Compatibility

This fix maintains full backward compatibility:
- All existing Google authentication flows continue to work
- Guest user registration/login via Google unchanged
- Existing linked accounts remain functional
- All security features preserved
