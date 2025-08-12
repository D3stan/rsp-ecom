# Google Authentication Setup Guide

This guide explains how to set up and use Google authentication with Laravel Socialite in the RSP Ecommerce application.

## Prerequisites

1. **Laravel Socialite Package**: Already added to `composer.json`
   ```bash
   composer require laravel/socialite
   ```

2. **Google Cloud Console Project**: You need to create a project and configure OAuth2 credentials

## Google Cloud Console Setup

### Step 1: Create a Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Navigate to "APIs & Services" > "Credentials"

### Step 2: Configure OAuth Consent Screen

1. Go to "OAuth consent screen" tab
2. Choose "External" user type (unless you have a Google Workspace)
3. Fill in the required information:
   - App name: "RSP Ecommerce"
   - User support email: Your email
   - Developer contact email: Your email
4. Add scopes: `email`, `profile`, `openid`
5. Add test users if in testing mode

### Step 3: Create OAuth2 Credentials

1. Go to "Credentials" tab
2. Click "Create Credentials" > "OAuth client ID"
3. Choose "Web application"
4. Add authorized redirect URIs:
   - Development: `http://localhost:8000/auth/google/callback`
   - Production: `https://yourdomain.com/auth/google/callback`
5. Save and copy the Client ID and Client Secret

## Environment Configuration

Add the following variables to your `.env` file:

```env
GOOGLE_CLIENT_ID=your_google_client_id_here
GOOGLE_CLIENT_SECRET=your_google_client_secret_here
GOOGLE_REDIRECT_URL="${APP_URL}/auth/google/callback"
```

## Database Migration

Run the migration to add Google authentication fields to the users table:

```bash
php artisan migrate
```

This adds:
- `google_id` (nullable, unique): Google user identifier
- `avatar` (nullable): User's Google profile picture URL
- Makes `password` nullable for Google-only users

## Features

### 1. Login with Google
- Users can click "Continue with Google" on the login page
- Redirects to Google OAuth consent screen
- On approval, creates new user or logs in existing user
- Links Google account to existing email if found

### 2. Register with Google
- Similar flow to login
- Automatically creates new user account
- Sets `email_verified_at` since Google accounts are pre-verified
- Assigns default role as 'customer'

### 3. Account Linking
- Users with existing accounts can link their Google account
- Accessible from Settings > Profile page
- Shows connection status and management options

### 4. Account Unlinking
- Users can unlink their Google account from profile settings
- Requires a password to be set before unlinking
- Maintains account security

## User Experience Flow

### New User Registration via Google
1. User clicks "Continue with Google" on register page
2. Redirected to Google OAuth
3. User approves access
4. Account created with Google details
5. User logged in and redirected to dashboard

### Existing User Login via Google
1. User clicks "Continue with Google" on login page
2. If Google account linked: immediate login
3. If email exists but no Google link: accounts are linked
4. If new email: new account created

### Account Management
1. User goes to Settings > Profile
2. Sees "Connected Accounts" section
3. Can link/unlink Google account
4. Warning shown if no password set before unlinking

## Security Features

1. **Unique Google ID**: Prevents duplicate Google account linking
2. **Email Verification**: Google users are auto-verified
3. **Password Protection**: Prevents unlinking Google without password
4. **Exception Handling**: Graceful error handling for OAuth failures
5. **CSRF Protection**: All routes protected by Laravel's CSRF middleware

## Testing

### Running Tests
```bash
php artisan test --filter=GoogleAuth
```

### Test Coverage
- ✅ Google OAuth redirect
- ✅ New user creation via Google
- ✅ Existing user linking via Google
- ✅ Returning Google user login
- ✅ Error handling for OAuth failures
- ✅ Account unlinking with password check
- ✅ Profile page rendering with Google settings

## Troubleshooting

### Common Issues

1. **"Client ID not found"**
   - Check Google Cloud Console credentials
   - Verify environment variables are set correctly

2. **"Redirect URI mismatch"**
   - Ensure redirect URI in Google Console matches your environment
   - Check `GOOGLE_REDIRECT_URL` in `.env`

3. **"Access blocked: This app's request is invalid"**
   - Complete OAuth consent screen configuration
   - Add your domain to authorized domains

4. **"User cannot unlink Google account"**
   - User needs to set a password first
   - Handled gracefully with error message

### Debugging

Enable Laravel debugging to see detailed error messages:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

Check logs at `storage/logs/laravel.log` for OAuth-related errors.

## File Structure

```
app/
├── Http/Controllers/Auth/
│   └── GoogleAuthController.php          # Google OAuth logic
├── Models/
│   └── User.php                          # Updated with Google fields
database/
├── factories/
│   └── UserFactory.php                   # Updated with Google states
├── migrations/
│   └── *_add_google_auth_fields_to_users_table.php
resources/js/
├── components/
│   ├── google-button.tsx                 # Reusable Google auth button
│   └── google-account-settings.tsx       # Account management component
├── pages/auth/
│   ├── login.tsx                         # Updated with Google option
│   └── register.tsx                      # Updated with Google option
├── types/
│   └── index.d.ts                        # Updated User interface
routes/
└── auth.php                              # Google auth routes
tests/
├── Feature/Auth/
│   └── GoogleAuthTest.php                # Google auth feature tests
├── Feature/Settings/
│   └── ProfileGoogleSettingsTest.php     # Profile page tests
└── Unit/Models/
    └── UserGoogleAuthTest.php             # User model tests
```

## Best Practices

1. **Always validate Google user data** before saving
2. **Handle OAuth exceptions gracefully** with user-friendly messages
3. **Require password before unlinking** Google accounts
4. **Use proper scopes** (only request what you need)
5. **Test with different user scenarios** (new, existing, linked accounts)
6. **Keep Google credentials secure** and use environment variables
7. **Regularly review OAuth consent screen** information for compliance

## Production Considerations

1. **Domain Verification**: Verify your domain in Google Console
2. **Rate Limiting**: Implement rate limiting for auth endpoints
3. **Monitoring**: Monitor OAuth success/failure rates
4. **Backup Authentication**: Always provide password-based login option
5. **Privacy Policy**: Update privacy policy to mention Google data usage
6. **Terms of Service**: Include terms about third-party authentication

## Support

For issues related to:
- **Google Console**: [Google OAuth Documentation](https://developers.google.com/identity/protocols/oauth2)
- **Laravel Socialite**: [Socialite Documentation](https://laravel.com/docs/socialite)
- **Implementation**: Check the test files for usage examples
