# Email Implementation Guide

## Overview
This guide provides complete instructions for implementing email functionality in your Laravel ecommerce project using your Register.it SMTP server.

## 1. Environment Configuration

### Update your .env file with Register.it SMTP settings:

```env
# Mail Configuration for Register.it SMTP
MAIL_MAILER=smtp
MAIL_HOST=authsmtp.securemail.pro
MAIL_PORT=465
MAIL_USERNAME=smtp@yourdomain.ext
MAIL_PASSWORD=YourSMTPPassword
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="smtp@yourdomain.ext"
MAIL_FROM_NAME="Your Store Name"

# For development/testing, you can use:
# MAIL_MAILER=log
```

**Important**: Replace:
- `smtp@yourdomain.ext` with your actual SMTP username
- `YourSMTPPassword` with your actual SMTP password from Register.it
- `"Your Store Name"` with your actual store name

## 2. Email Classes Created

### Mailable Classes:
- `app/Mail/OrderConfirmation.php` - Sends order confirmation emails
- `app/Mail/OrderShipped.php` - Sends shipping notification emails  
- `app/Mail/WelcomeEmail.php` - Sends welcome emails to new users

### Email Templates:
- `resources/views/emails/order-confirmation.blade.php`
- `resources/views/emails/order-shipped.blade.php`
- `resources/views/emails/welcome.blade.php`

### Email Service:
- `app/Services/EmailService.php` - Centralized email management service

## 3. Integration Points

### Automatic Email Sending:
1. **Order Confirmation**: Sent automatically when payment succeeds (via Stripe webhook)
2. **Welcome Email**: Sent automatically when user registers
3. **Shipping Notification**: Can be sent manually when order status changes

### Manual Email Sending:
You can send emails manually using the EmailService:

```php
use App\Services\EmailService;

$emailService = app(EmailService::class);

// Send order confirmation
$emailService->sendOrderConfirmation($order);

// Send shipping notification
$emailService->sendOrderShipped($order, 'TRACKING123');

// Send welcome email
$emailService->sendWelcomeEmail($user);

// Send custom email
$emailService->sendCustomEmail('customer@example.com', 'Subject', 'Message');
```

## 4. Testing Email Configuration

### Test Command:
Use the custom artisan command to test your email setup:

```bash
php artisan email:test your-email@example.com
```

This will send a test email to verify your SMTP configuration is working.

### Development Testing:
For development, you can set `MAIL_MAILER=log` in your .env file. Emails will be logged to `storage/logs/laravel.log` instead of being sent.

## 5. Email Queue (Recommended for Production)

For better performance, configure email queues:

```env
QUEUE_CONNECTION=database
```

Then run the queue worker:
```bash
php artisan queue:work
```

All emails implement `ShouldQueue` interface and will be processed asynchronously.

## 6. Email Templates Customization

### Template Structure:
All email templates use Laravel's Markdown mail components:
- `<x-mail::message>` - Main email wrapper
- `<x-mail::button>` - Call-to-action buttons
- Standard Markdown formatting for content

### Customizing Templates:
You can modify the templates in `resources/views/emails/` to match your brand:
- Change colors, fonts, and styling
- Add your logo
- Modify content and messaging
- Add additional information

## 7. Email Types and Triggers

### Order Confirmation Email:
- **Trigger**: Automatic when payment succeeds
- **Recipients**: Customer (user email or guest email)
- **Content**: Order details, items, totals, shipping address
- **Template**: `emails/order-confirmation.blade.php`

### Order Shipped Email:
- **Trigger**: Manual (when admin marks order as shipped)
- **Recipients**: Customer
- **Content**: Shipping details, tracking number, delivery address
- **Template**: `emails/order-shipped.blade.php`

### Welcome Email:
- **Trigger**: Automatic when user registers
- **Recipients**: New user
- **Content**: Welcome message, store information, getting started tips
- **Template**: `emails/welcome.blade.php`

## 8. Error Handling and Logging

The EmailService includes comprehensive error handling:
- All email sending attempts are logged
- Failed emails are logged with error details
- Methods return boolean success/failure status
- Graceful fallback if email sending fails

## 9. Production Considerations

### DNS Configuration:
For production, ensure your domain has proper email authentication:

```dns
# SPF Record
TXT @ "v=spf1 include:securemail.pro ~all"

# DKIM Record (get from Register.it)
TXT default._domainkey "v=DKIM1; k=rsa; p=YOUR_DKIM_PUBLIC_KEY"

# DMARC Record
TXT _dmarc "v=DMARC1; p=quarantine; rua=mailto:admin@yourdomain.ext"
```

### Email Deliverability:
- Use your domain's email address as sender
- Keep email content professional and avoid spam triggers
- Monitor bounce rates and spam complaints
- Consider using dedicated IP if sending high volume

## 10. Usage Examples

### Send Order Confirmation:
```php
// In your controller or service
use App\Services\EmailService;

$emailService = app(EmailService::class);
$emailService->sendOrderConfirmation($order);
```

### Send Shipping Notification:
```php
// When updating order status to shipped
$emailService = app(EmailService::class);
$emailService->sendOrderShipped($order, $trackingNumber);
```

### Test Email Setup:
```bash
# Test your email configuration
php artisan email:test admin@yourdomain.ext
```

## 11. Troubleshooting

### Common Issues:

1. **Emails not sending**:
   - Check SMTP credentials in .env
   - Verify Register.it SMTP settings
   - Check Laravel logs for errors

2. **Emails going to spam**:
   - Configure SPF/DKIM records
   - Use professional email content
   - Send from your domain's email

3. **Slow email sending**:
   - Enable queue processing
   - Use `QUEUE_CONNECTION=database`
   - Run `php artisan queue:work`

### Debug Commands:
```bash
# Check email configuration
php artisan config:show mail

# Test email sending
php artisan email:test test@example.com

# View email logs
tail -f storage/logs/laravel.log | grep -i mail
```

## 12. Next Steps

1. **Update .env file** with your Register.it SMTP credentials
2. **Test email configuration** using the test command
3. **Customize email templates** to match your brand
4. **Set up email queues** for production
5. **Configure DNS records** for better deliverability
6. **Monitor email logs** to ensure proper delivery

Your email system is now ready to send professional customer communications!