<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Form Message - {{ $appName }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.5;
            color: #333333;
            background-color: #f8f8f8;
            margin: 0;
            padding: 20px;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .email-header {
            background-color: #000000;
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        
        .logo {
            height: 60px;
            width: auto;
            max-width: 200px;
            margin-bottom: 20px;
        }
        
        .email-header h1 {
            font-size: 24px;
            font-weight: 500;
            margin: 0;
        }
        
        .content {
            padding: 40px 20px;
        }
        
        .content h2 {
            color: #333333;
            font-size: 20px;
            margin-bottom: 20px;
        }
        
        .content p {
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .contact-details {
            background-color: #f8f8f8;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        
        .contact-details h3 {
            color: #333333;
            font-size: 18px;
            margin-bottom: 15px;
            margin-top: 0;
        }
        
        .contact-details table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .contact-details td {
            padding: 8px 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .contact-details td:first-child {
            font-weight: 600;
            color: #555555;
            width: 120px;
        }
        
        .message-content {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            white-space: pre-wrap;
            font-family: inherit;
            line-height: 1.6;
        }
        
        .footer {
            background-color: #000000;
            color: #cccccc;
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .reply-notice {
            background-color: #e7f3ff;
            color: #0066cc;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header with Logo -->
        <div class="email-header">
            <img src="{{ $logoUrl }}" alt="{{ $appName }} Logo" class="logo">
            <h1>New Contact Form Message</h1>
        </div>

        <!-- Content -->
        <div class="content">
            <h2>Contact Form Submission</h2>
            
            <p>You have received a new message through the contact form on {{ $appName }}.</p>
            
            <div class="contact-details">
                <h3>Contact Information</h3>
                <table>
                    <tr>
                        <td>Name:</td>
                        <td>{{ $contactData['firstName'] }} {{ $contactData['lastName'] }}</td>
                    </tr>
                    <tr>
                        <td>Email:</td>
                        <td><a href="mailto:{{ $contactData['email'] }}">{{ $contactData['email'] }}</a></td>
                    </tr>
                    <tr>
                        <td>Subject:</td>
                        <td>{{ $contactData['subject'] }}</td>
                    </tr>
                    <tr>
                        <td>Submitted:</td>
                        <td>{{ now()->format('F j, Y \a\t g:i A') }}</td>
                    </tr>
                </table>
            </div>
            
            <h3>Message:</h3>
            <div class="message-content">{{ $contactData['message'] }}</div>
            
            <div class="reply-notice">
                💡 You can reply directly to this email to respond to {{ $contactData['firstName'] }}
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Contact form message from {{ $appName }}</p>
            <p>This email was automatically generated from your website's contact form.</p>
        </div>
    </div>
</body>
</html>
