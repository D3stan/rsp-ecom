<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benvenuto {{ $user->name }}</title>
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
            padding: 30px 20px;
        }

        .welcome-message {
            font-size: 16px;
            margin-bottom: 20px;
        }

        .features-list {
            background-color: #f8f8f8;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .features-list h2 {
            color: #333333;
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 15px;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
            font-size: 14px;
            color: #555555;
        }

        .feature-icon {
            margin-right: 10px;
            font-size: 16px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            border-radius: 4px;
            text-align: center;
            margin: 10px 0;
        }

        .btn-primary {
            background-color: #000000;
            color: #ffffff;
        }

        .btn-secondary {
            background-color: transparent;
            color: #333333;
            border: 1px solid #333333;
        }

        .help-section {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 20px;
            margin: 20px 0;
        }

        .help-section h2 {
            color: #1565c0;
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 10px;
        }

        .help-section p {
            color: #424242;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .cta-section {
            text-align: center;
            padding: 30px 20px;
            background-color: #f8f8f8;
            border-top: 1px solid #e5e5e5;
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

        .footer-links {
            margin: 25px 0;
        }

        .footer-links a {
            color: #cccccc;
            text-decoration: none;
            margin: 0 15px;
            font-size: 12px;
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
                padding: 20px 15px;
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
                <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" style="height: 40px; width: auto; max-width: 150px;">
            </div>
            <h1>Benvenuto nel nostro store! 🎉</h1>
            <p>Siamo felici di averti con noi</p>
        </div>

        <!-- Content -->
        <div class="content-section">
            <p class="welcome-message">
                Ciao <strong>{{ $user->name }}</strong>,
            </p>

            <p style="margin-bottom: 20px;">
                Grazie per esserti registrato al nostro store! Siamo entusiasti di averti come nuovo cliente.
            </p>

            <!-- Features -->
            <div class="features-list">
                <h2>Cosa puoi fare ora:</h2>
                <div class="feature-item">
                    <span class="feature-icon">✅</span>
                    <span><strong>Sfoglia i nostri prodotti</strong> - Scopri la nostra collezione di prodotti di alta qualità</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">✅</span>
                    <span><strong>Aggiungi ai preferiti</strong> - Salva i prodotti che ti piacciono per dopo</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">✅</span>
                    <span><strong>Checkout veloce</strong> - I tuoi dati sono salvati per acquisti più rapidi</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">✅</span>
                    <span><strong>Traccia i tuoi ordini</strong> - Monitora lo stato dei tuoi acquisti</span>
                </div>
            </div>

            <!-- CTA -->
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ route('products') }}" class="btn btn-primary">
                    Inizia a fare shopping
                </a>
            </div>

            <!-- Help Section -->
            <div class="help-section">
                <h2>Hai bisogno di aiuto?</h2>
                <p>Se hai domande o hai bisogno di assistenza, non esitare a contattarci:</p>
                <p style="margin-top: 10px;">
                    <strong>Email:</strong> {{ config('mail.from.address') }}<br>
                </p>
                <div style="text-align: center; margin-top: 15px;">
                    <a href="{{ route('contact') }}" class="btn btn-secondary">
                        Contattaci
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <h3>{{ config('app.name') }}</h3>
            <p>Grazie ancora per aver scelto il nostro store!</p>

            <div class="footer-links">
                <a href="{{ route('about') }}">Chi Siamo</a>
                <a href="{{ route('contact') }}">Contatti</a>
                <a href="{{ route('privacy') }}">Privacy</a>
                <a href="{{ route('terms') }}">Termini</a>
            </div>

            <p style="font-size: 11px; margin-top: 25px; color: #888888;">
                Questa email è stata inviata automaticamente, non rispondere a questo messaggio.
            </p>
        </div>
    </div>
</body>
</html>
