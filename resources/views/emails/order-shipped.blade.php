<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordine Spedito #{{ $order->order_number }}</title>
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

        .order-info {
            background-color: #f8f8f8;
            padding: 20px;
            margin: 0;
            border-bottom: 1px solid #e5e5e5;
        }

        .order-info h2 {
            color: #333333;
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 20px;
        }

        .order-details {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .order-detail-item {
            flex: 1;
            min-width: 120px;
        }

        .order-detail-item strong {
            display: block;
            color: #666666;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .order-detail-item span {
            font-size: 14px;
            font-weight: 500;
            color: #333333;
        }

        .content-section {
            padding: 30px 20px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 500;
            color: #333333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e5e5;
        }

        .tracking-box {
            background-color: #e8f5e9;
            border: 1px solid #4caf50;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .tracking-box h3 {
            color: #2e7d32;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .tracking-number {
            font-family: 'Courier New', monospace;
            font-size: 18px;
            font-weight: 600;
            color: #1b5e20;
            letter-spacing: 1px;
        }

        .shipping-address {
            background-color: #f8f8f8;
            border: 1px solid #e5e5e5;
            padding: 20px;
            margin-top: 20px;
        }

        .shipping-address h3 {
            color: #333333;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 15px;
        }

        .address-details {
            line-height: 1.5;
            color: #666666;
            font-size: 14px;
        }

        .cta-section {
            text-align: center;
            padding: 40px 20px;
            background-color: #f8f8f8;
            border-top: 1px solid #e5e5e5;
        }

        .cta-section h3 {
            font-size: 16px;
            font-weight: 500;
            color: #333333;
            margin-bottom: 10px;
        }

        .cta-section p {
            color: #666666;
            font-size: 14px;
        }

        .btn {
            display: inline-block !important;
            padding: 12px 24px;
            text-decoration: none !important;
            font-weight: 500;
            font-size: 14px;
            border: 1px solid transparent;
            text-align: center;
            transition: all 0.2s ease;
            mso-hide: all;
        }

        .btn-primary {
            background-color: #000000 !important;
            color: white !important;
            border-color: #000000 !important;
        }

        .delivery-info {
            background-color: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 15px 20px;
            margin: 20px 0;
        }

        .delivery-info p {
            margin: 0;
            color: #e65100;
            font-size: 14px;
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

            .order-details {
                flex-direction: column;
                gap: 15px;
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
