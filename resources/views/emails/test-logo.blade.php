<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email - {{ $appName }}</title>
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
        
        .logo-test-section {
            background-color: #f8f8f8;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: center;
        }
        
        .logo-test-section h3 {
            color: #333333;
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        .inline-logo {
            height: 40px;
            width: auto;
            vertical-align: middle;
            margin: 0 10px;
        }
        
        .footer {
            background-color: #000000;
            color: #cccccc;
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .success-indicator {
            background-color: #d4edda;
            color: #155724;
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
            <h1>Logo Test Email</h1>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="success-indicator">
                ✅ Logo successfully embedded in email!
            </div>
            
            <h2>Email Logo Test Results</h2>
            
            <p>This is a test email to verify that the company logo appears correctly in emails sent from <strong>{{ $appName }}</strong>.</p>
            
            <div class="logo-test-section">
                <h3>Logo Display Test</h3>
                <p>The logo should appear in the header above and also here inline: 
                   <img src="{{ $logoUrl }}" alt="{{ $appName }} Logo" class="inline-logo">
                </p>
                <p><strong>Logo URL:</strong> {{ $logoUrl }}</p>
                <p><strong>Note:</strong> Logo displayed via external URL for better compatibility</p>
            </div>
            
            <p><strong>What this test verifies:</strong></p>
            <ul>
                <li>Logo appears in email header</li>
                <li>Logo is attached as inline image</li>
                <li>Custom email headers are set for sender branding</li>
                <li>Email metadata includes brand information</li>
            </ul>
            
            <p><strong>Note:</strong> The visibility of the logo as a "sender profile picture" depends on your email client and service provider. Some clients like Gmail, Outlook, and Apple Mail may display sender logos based on various factors including authentication, sender reputation, and specific email headers.</p>
            
            <div class="logo-test-section">
                <h3>Email Client Compatibility</h3>
                <p>Different email clients handle sender branding differently:</p>
                <ul style="text-align: left; max-width: 400px; margin: 0 auto;">
                    <li><strong>Gmail:</strong> May show sender avatar based on Google Workspace settings</li>
                    <li><strong>Outlook:</strong> Shows sender photos from directory or contact info</li>
                    <li><strong>Apple Mail:</strong> Uses contact photos and sender authentication</li>
                    <li><strong>Others:</strong> Varies by client implementation</li>
                </ul>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Test email sent from {{ $appName }}</p>
            <p>Email functionality working correctly!</p>
        </div>
    </div>
</body>
</html>
