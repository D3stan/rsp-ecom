# Email Logo Configuration Guide

This guide explains how the email logo functionality has been implemented and how to configure it properly.

## What Has Been Implemented

### 1. Logo Attachment System
- All email classes now include the company logo as an inline attachment
- Fallback system: `rlogo.png` → `favicon.ico` → `logo.svg`
- Centralized logo handling through the `HasLogo` trait

### 2. Email Headers for Sender Branding
- Custom headers added to all outgoing emails:
  - `X-Brand-Logo`: URL to the company logo
  - `X-Brand-Name`: Company/app name
  - `X-Sender-Image`: Logo URL for email clients that support it
  - `List-Unsubscribe`: For better deliverability

### 3. Enhanced Email Templates
- Logo displayed in email headers
- Customized Laravel mail templates with logo support
- Logo metadata in email layout

### 4. Updated Email Classes
The following mail classes have been updated:
- `OrderConfirmation` - Shows logo in order confirmation emails
- `OrderShipped` - Shows logo in shipping notification emails  
- `WelcomeEmail` - Shows logo in welcome emails
- `TestEmail` - New class for testing logo functionality

## Files Modified/Created

### Modified Files:
- `app/Mail/OrderConfirmation.php`
- `app/Mail/OrderShipped.php`
- `app/Mail/WelcomeEmail.php`
- `app/Services/EmailService.php`
- `resources/views/emails/order-confirmation.blade.php`
- `resources/views/vendor/mail/html/header.blade.php`
- `resources/views/vendor/mail/html/layout.blade.php`
- `bootstrap/providers.php`

### Created Files:
- `app/Mail/Traits/HasLogo.php`
- `app/Mail/TestEmail.php`
- `app/Providers/MailServiceProvider.php`
- `resources/views/emails/test-logo.blade.php`

## How to Configure and Test

### 1. Configure Email Settings
Update your `.env` file with proper mail configuration:

```bash
# For production with SMTP (example with Gmail)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="Your Company Name"

# For testing (logs emails to storage/logs/laravel.log)
MAIL_MAILER=log
```

### 2. Test the Logo Functionality

Run the test email command:
```bash
php artisan email:test your-email@example.com
```

This will send a comprehensive test email that shows:
- Logo in the email header
- Inline logo display
- Email metadata and headers
- Compatibility information for different email clients

### 3. Logo Files Location
Ensure your logo files exist in the correct locations:
- Primary: `public/images/rlogo.png`
- Fallback 1: `public/favicon.ico`
- Fallback 2: `public/logo.svg`

## Email Client Compatibility

### How Sender Profile Pictures Work

The sender profile picture/avatar that appears in email clients depends on several factors:

1. **Email Authentication**: SPF, DKIM, and DMARC records
2. **Sender Reputation**: How email providers view your domain
3. **Email Client**: Different clients handle sender images differently
4. **Contact Information**: Some clients use contact photos

### Client-Specific Behavior:

- **Gmail**: 
  - May show Google Workspace profile pictures
  - Uses sender authentication and reputation
  - May display BIMI (Brand Indicators for Message Identification) logos

- **Outlook**:
  - Shows sender photos from Active Directory or contacts
  - Supports sender photos for authenticated domains

- **Apple Mail**:
  - Uses contact photos when available
  - Respects sender authentication

- **Thunderbird & Others**:
  - Generally rely on contact information
  - Limited automatic sender image support

## Advanced Configuration

### BIMI (Brand Indicators for Message Identification)
For enterprise setups, consider implementing BIMI:
1. Create a BIMI DNS record
2. Verify with VMC (Verified Mark Certificate)
3. Ensure proper DMARC policy

### Custom Headers
The system automatically adds these headers to help with sender branding:
- `X-Brand-Logo`
- `X-Brand-Name`
- `X-Sender-Image`

## Troubleshooting

### Logo Not Displaying:
1. Check if logo files exist in `public/images/`
2. Verify file permissions
3. Test with different email clients
4. Check email logs: `storage/logs/laravel.log`

### Email Not Sending:
1. Verify SMTP settings in `.env`
2. Check firewall/port restrictions
3. Verify email credentials
4. Test with `MAIL_MAILER=log` first

### Commands for Testing:
```bash
# Test email functionality
php artisan email:test your-email@example.com

# Clear cache if needed
php artisan config:clear
php artisan view:clear

# Check email logs
tail -f storage/logs/laravel.log
```

## Security Considerations

1. **Logo File Size**: Keep logo files small (under 100KB)
2. **File Types**: Use PNG or JPG for better compatibility
3. **Path Security**: Logo URLs are public assets
4. **Email Authentication**: Implement SPF, DKIM, DMARC for better deliverability

## Notes

- Logo appearance as sender profile picture varies by email client
- The logo is always embedded in email content regardless of client support
- Custom headers provide additional metadata for supporting clients
- Fallback system ensures emails work even if primary logo is missing
