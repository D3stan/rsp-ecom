# Contact Form Setup Instructions

## Environment Variables Required

Add these variables to your `.env` file:

```env
# Contact Form Email Configuration
CONTACT_EMAIL=your-contact@yourdomain.com
```

If `CONTACT_EMAIL` is not set, the system falls back to `MAIL_SUPPORT_ADDRESS`, then to `MAIL_FROM_ADDRESS`
(`config('mail.contact_email')`). Note: the env var is `CONTACT_EMAIL`, not `MAIL_CONTACT_EMAIL` (older versions of this guide were wrong).

## Mail Configuration

The contact form uses your existing mail configuration. Make sure your mail settings are properly configured in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Your Store Name"
MAIL_SUPPORT_ADDRESS=support@yourdomain.com
```

## Features Implemented

✅ **Form Validation**
- All fields are required
- Email format validation
- Message minimum length (10 characters)
- Maximum lengths for all fields

✅ **Email Functionality**
- Sends formatted email to contact address
- Uses existing logo/branding system
- Reply-to header set to customer email
- Professional email template

✅ **User Experience**
- Loading states during submission
- Success/error message display
- Form reset after successful submission
- Field-level error display

✅ **Security**
- CSRF protection (automatic with Laravel/Inertia)
- Input sanitization and validation
- Rate limiting (can be added if needed)

## Testing the Contact Form

1. Fill out the contact form on `/contact`
2. Submit the form
3. Check the email configured in `CONTACT_EMAIL`
4. Verify the email contains all submitted information
5. Test reply functionality (reply should go to customer's email)

## Email Template

The email template includes:
- Professional branding with logo
- Customer information table
- Full message content
- Reply-to functionality
- Timestamp of submission
