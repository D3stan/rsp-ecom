<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordine Spedito #{{ $order->order_number }}</title>
    @include('emails.partials.styles')
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div style="margin-bottom: 20px;">
                <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" style="height: 40px; width: auto; max-width: 150px;">
            </div>
            <h1>Il tuo ordine è stato spedito! 📦</h1>
            <p>Ottime notizie! Il tuo ordine è in viaggio</p>
        </div>

        <!-- Order Information -->
        <div class="order-info">
            <h2>Dettagli Spedizione</h2>
            <div class="order-details">
                <div class="order-detail-item">
                    <strong>Numero Ordine</strong>
                    <span>{{ $order->order_number }}</span>
                </div>
                <div class="order-detail-item">
                    <strong>Data Spedizione</strong>
                    <span>{{ now()->format('d/m/Y') }}</span>
                </div>
            </div>
        </div>

        <!-- Customer Greeting -->
        <div class="content-section">
            <p style="font-size: 16px; margin-bottom: 20px;">
                Ciao <strong>{{ $customer->name ?? 'Cliente' }}</strong>,
            </p>
            <p style="margin-bottom: 20px;">
                Ottime notizie! Il tuo ordine <strong>#{{ $order->order_number }}</strong> è stato spedito e sarà presto da te.
            </p>

            @if($trackingNumber)
            <!-- Tracking Information -->
            <div class="tracking-box">
                <h3>📍 Tracking</h3>
                <div class="tracking-number">{{ $trackingNumber }}</div>
                <p style="margin-top: 10px; color: #4caf50; font-size: 12px;">
                    Usa questo link per tracciare il tuo pacco
                </p>
            </div>
            @endif

            <!-- Delivery Estimate -->
            <div class="delivery-info">
                <p><strong>🚚 Tempo di consegna stimato:</strong> 2-5 giorni lavorativi</p>
            </div>
        </div>

        <!-- Shipping Address -->
        @if($order->shippingAddress)
        <div class="content-section">
            <h3 class="section-title">Indirizzo di Consegna</h3>
            <div class="shipping-address">
                <div class="address-details">
                    <strong>{{ $order->shippingAddress->first_name }} {{ $order->shippingAddress->last_name }}</strong><br>
                    {{ $order->shippingAddress->address_line_1 }}<br>
                    @if($order->shippingAddress->address_line_2)
                        {{ $order->shippingAddress->address_line_2 }}<br>
                    @endif
                    {{ $order->shippingAddress->postal_code }} {{ $order->shippingAddress->city }}<br>
                    {{ $order->shippingAddress->state }}, {{ $order->shippingAddress->country }}
                </div>
            </div>
        </div>
        @endif

        <!-- Call to Action -->
        <div class="cta-section">
            <h3>Visualizza il tuo ordine</h3>
            <p style="margin-bottom: 20px;">Tieni traccia del tuo ordine nel tuo account</p>
            <a href="{{ route('dashboard') }}"
               style="display: inline-block; padding: 12px 24px; text-decoration: none; font-weight: 500; font-size: 14px; text-align: center; background-color: #000000; color: #ffffff; border: 1px solid #000000;">
               Visualizza Ordine
            </a>
        </div>

        <!-- Footer -->
        <div class="footer">
            <h3>{{ config('app.name') }}</h3>
            <p>Grazie per aver scelto il nostro store!</p>

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
