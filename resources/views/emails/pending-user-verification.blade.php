<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email Address</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.5;
            color: #333333;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        
        .email-header {
            background-color: #000000;
            color: white;
            padding: 40px 20px;
            text-align: center;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .email-header h1 {
            font-size: 24px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .email-header p {
            font-size: 14px;
            color: #cccccc;
        }
        
        .content-section {
            padding: 40px 20px;
        }
        
        .content-section h2 {
            font-size: 18px;
            font-weight: 500;
            color: #333333;
            margin-bottom: 20px;
        }
        
        .content-section p {
            font-size: 16px;
            color: #666666;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .verification-button {
            text-align: center;
            margin: 40px 0;
        }
        
        .btn {
            display: inline-block !important;
            padding: 16px 32px;
            text-decoration: none !important;
            font-weight: 500;
            font-size: 16px;
            background-color: #000000 !important;
            color: white !important;
            border: 1px solid #000000 !important;
            text-align: center;
            transition: all 0.2s ease;
        }
        
        .security-notice {
            background-color: #f8f8f8;
            padding: 20px;
            border: 1px solid #e5e5e5;
            margin: 30px 0;
        }
        
        .security-notice h3 {
            font-size: 14px;
            font-weight: 500;
            color: #333333;
            margin-bottom: 10px;
        }
        
        .security-notice p {
            font-size: 13px;
            color: #666666;
            margin-bottom: 0;
        }
        
        .fallback-link {
            background-color: #f8f8f8;
            padding: 15px;
            margin: 20px 0;
            border: 1px solid #e5e5e5;
            word-break: break-all;
        }
        
        .fallback-link p {
            font-size: 12px;
            color: #666666;
            margin-bottom: 10px;
        }
        
        .fallback-link code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            color: #333333;
            background-color: #ffffff;
            padding: 5px;
            border: 1px solid #e5e5e5;
            display: block;
            margin-top: 5px;
        }
        
        .footer {
            background-color: #000000;
            color: #cccccc;
            padding: 40px 20px;
            text-align: center;
            font-size: 12px;
        }
        
        .footer h3 {
            color: white;
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 20px;
        }
        
        .footer p {
            margin-bottom: 20px;
        }
        
        @media screen and (max-width: 600px) {
            .email-container {
                margin: 0;
            }
            
            .email-header {
                padding: 30px 15px;
            }
            
            .email-header h1 {
                font-size: 20px;
            }
            
            .content-section {
                padding: 30px 15px;
            }
            
            .btn {
                width: 100%;
                max-width: 280px;
                display: block !important;
                margin: 0 auto !important;
            }
            
            .footer {
                padding: 30px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div style="margin-bottom: 20px;">
                <img src="{{ $logoUrl }}" alt="{{ $appName }}" style="height: 40px; width: auto; max-width: 150px;">
            </div>
            <h1>Verify Your Email</h1>
            <p>Complete your registration to get started</p>
        </div>

        <!-- Content -->
        <div class="content-section">
            <h2>Hello {{ $pendingUser->name }},</h2>
            
            <p>
                Thank you for registering with {{ $appName }}! To complete your registration and start shopping, 
                please verify your email address by clicking the button below.
            </p>
            
            <div class="verification-button">
                <!--[if mso]>
                <table border="0" cellspacing="0" cellpadding="0" align="center">
                <tr>
                <td align="center">
                <![endif]-->
                <a href="{{ $verificationUrl }}" 
                   style="display: inline-block; padding: 16px 32px; text-decoration: none; font-weight: 500; font-size: 16px; text-align: center; background-color: #000000; color: #ffffff; border: 1px solid #000000;">
                   Verify Email Address
                </a>
                <!--[if mso]>
                </td>
                </tr>
                </table>
                <![endif]-->
            </div>
            
            <p>
                This verification link will expire in 24 hours for security reasons. 
                If you didn't create an account with us, you can safely ignore this email.
            </p>
            
            <!-- Security Notice -->
            <div class="security-notice">
                <h3>Security Notice</h3>
                <p>
                    For your security, this verification link can only be used once and will expire in 24 hours. 
                    If you need a new verification link, you can request one by attempting to register again.
                </p>
            </div>
            
            <!-- Fallback Link -->
            <div class="fallback-link">
                <p>If the button above doesn't work, copy and paste this link into your browser:</p>
                <code>{{ $verificationUrl }}</code>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <h3>{{ $appName }}</h3>
            <p>Welcome to our community!</p>
            
            <p style="font-size: 11px; margin-top: 25px; color: #888888;">
                This email was sent automatically, please do not reply to this message.
            </p>
        </div>
    </div>
</body>
</html>
