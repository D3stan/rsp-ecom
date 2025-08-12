# Google Cloud Console Configuration for OAuth 2.0

## Critical Steps to Fix "Access blocked" Error

Based on the error message and Google's OAuth 2.0 policies, here are the essential configuration steps you need to complete in Google Cloud Console:

### 1. OAuth Consent Screen Configuration

**Go to: Google Cloud Console > APIs & Services > OAuth consent screen**

#### Required Settings:
- **User Type**: Choose "External" (unless you have Google Workspace)
- **App name**: Enter your application name (e.g., "RSP Ecommerce")
- **User support email**: Your valid email address
- **App logo**: Upload a logo (optional but recommended)
- **App domain**: 
  - Application homepage: `https://yourdomain.com` (or `http://localhost:8000` for development)
  - Application privacy policy link: `https://yourdomain.com/privacy`
  - Application terms of service link: `https://yourdomain.com/terms`
- **Authorized domains**: Add your domain without protocol
  - For production: `yourdomain.com`
  - For development: `localhost` (if needed)
- **Developer contact information**: Your email address

#### Scopes Configuration:
Add these scopes in the "Scopes" section:
- `../auth/userinfo.email`
- `../auth/userinfo.profile`
- `openid`

#### Publishing Status:
- **Testing**: Only test users can access your app
- **In production**: App is available to all users
- **Needs verification**: Required for sensitive scopes

### 2. OAuth Client Configuration

**Go to: Google Cloud Console > APIs & Services > Credentials**

#### Create OAuth 2.0 Client ID:
- **Application type**: Web application
- **Name**: Give it a descriptive name
- **Authorized JavaScript origins**:
  - `http://localhost:8000` (for development)
  - `https://yourdomain.com` (for production)
- **Authorized redirect URIs**:
  - `http://localhost:8000/auth/google/callback` (for development)
  - `https://yourdomain.com/auth/google/callback` (for production)

### 3. Enable Required APIs

**Go to: Google Cloud Console > APIs & Services > Library**

Enable these APIs:
- Google+ API (if available)
- People API
- Gmail API (if you need email access)

### 4. Verification Requirements

For production apps, you may need verification if:
- You request sensitive or restricted scopes
- Your app will be used by more than 100 users
- You want to remove the "unverified app" warning

### 5. Environment Variables

Update your `.env` file with the correct values:

```env
GOOGLE_CLIENT_ID=your_client_id_from_credentials_page
GOOGLE_CLIENT_SECRET=your_client_secret_from_credentials_page
GOOGLE_REDIRECT_URL="${APP_URL}/auth/google/callback"
```

### 6. Common Configuration Mistakes

❌ **Wrong Application Type**: Using "Desktop app" instead of "Web application"
❌ **Missing Redirect URIs**: Not adding the exact callback URL
❌ **Unpublished Consent Screen**: Leaving the app in "Testing" status without adding test users
❌ **Missing Required Fields**: Not filling in privacy policy, terms of service URLs
❌ **Domain Mismatch**: Redirect URI domain doesn't match authorized domains
❌ **Protocol Mismatch**: Using HTTP in production or HTTPS in development incorrectly

### 7. Testing Configuration

1. **Clear Laravel cache**: `php artisan config:clear`
2. **Test in incognito window**: Avoid cached authentication
3. **Check browser console**: Look for any JavaScript errors
4. **Verify redirect URL**: Ensure it matches exactly what's configured
5. **Test with different Google accounts**: Some accounts may have restrictions

### 8. Troubleshooting Steps

If you still get the error:

1. **Double-check OAuth consent screen is published**
2. **Verify all URLs are exactly correct** (including trailing slashes)
3. **Ensure your app domain is verified** in Google Search Console
4. **Check if you need app verification** for the scopes you're using
5. **Try with a test user** if your app is in testing mode
6. **Contact Google Support** if configuration appears correct

### 9. Development vs Production

**Development (localhost)**:
- Can use HTTP
- OAuth consent screen can be in "Testing" mode
- Add your Google account as a test user

**Production**:
- Must use HTTPS
- OAuth consent screen should be "In production"
- May require app verification
- Must have valid privacy policy and terms of service

### 10. Security Best Practices

- Never commit client secrets to version control
- Use environment variables for all OAuth credentials
- Regularly rotate client secrets
- Monitor OAuth usage in Google Cloud Console
- Keep consent screen information up to date

This configuration should resolve the "Access blocked: This request is blocked by Google's policies" error you're experiencing.
