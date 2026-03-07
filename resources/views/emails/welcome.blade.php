<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benvenuto {{ $user->name }}</title>
    @include('emails.partials.styles')
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
