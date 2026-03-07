<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conferma Ordine #{{ $order->order_number }}</title>
    @include('emails.partials.styles')
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div style="margin-bottom: 20px;">
                <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" style="height: 40px; width: auto; max-width: 150px;">
            </div>
            <h1>Conferma Ordine</h1>
            <p>Il tuo ordine è stato ricevuto con successo</p>
        </div>

        <!-- Order Information -->
        <div class="order-info">
            <h2>Dettagli Ordine</h2>
            <div class="order-details">
                <div class="order-detail-item">
                    <strong>Numero Ordine</strong>
                    <span>{{ $order->order_number }}</span>
                </div>
                <div class="order-detail-item">
                    <strong>Data</strong>
                    <span>{{ $order->created_at->format('d/m/Y H:i') }}</span>
                </div>
            </div>
        </div>

        <!-- Customer Greeting -->
        <div class="content-section">
            <p style="font-size: 16px; margin-bottom: 20px;">
                Ciao <strong>{{ $customer->name ?? 'Cliente' }}</strong>,
            </p>
            <p style="margin-bottom: 20px;">
                Abbiamo ricevuto il tuo ordine e lo stiamo preparando. 
                Ti invieremo un aggiornamento non appena il tuo ordine sarà spedito.
            </p>
        </div>

        <!-- Products Section -->
        <div class="content-section">
            <h3 class="section-title">Prodotti Ordinati</h3>
            <table class="product-table">
                <thead>
                    <tr>
                        <th>Prodotto</th>
                        <th style="text-align: center;">Quantità</th>
                        <th style="text-align: right;">Prezzo</th>
                        <th style="text-align: right;">Totale</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr>
                        <td>
                            <div class="product-info">
                                @if($item->product && !empty($item->product->images))
                                    <img src="{{ $item->product->main_image_url }}" alt="{{ $item->product->name }}" class="product-image">
                                @else
                                    <img src="{{ asset('images/product.png') }}" alt="Product" class="product-image">
                                @endif
                                <div class="product-details">
                                    <h4>{{ $item->product->name ?? $item->product_name }}</h4>
                                    @if($item->product && $item->product->sku)
                                        <p>SKU: {{ $item->product->sku }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td style="text-align: center; font-weight: 600;">{{ $item->quantity }}</td>
                        <td style="text-align: right;">€{{ number_format($item->price, 2) }}</td>
                        <td style="text-align: right; font-weight: 600;">€{{ number_format($item->quantity * $item->price, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Price Breakdown -->
            <div class="price-breakdown">
                <div class="price-row">
                    <span>Subtotale: </span>
                    <span>€{{ number_format($order->subtotal - $order->tax_amount, 2) }}</span>
                </div>
                @if($order->tax_amount > 0)
                <div class="price-row">
                    <span>IVA (22%): </span>
                    <span>€{{ number_format($order->tax_amount, 2) }}</span>
                </div>
                @endif
                <div class="price-row">
                    <span>Spedizione: </span>
                    <span>{{ $order->shipping_amount > 0 ? '€' . number_format($order->shipping_amount, 2) : 'Gratuita' }}</span>
                </div>
                <div class="price-row total">
                    <span>Totale: </span>
                    <span>€{{ number_format($order->total_amount, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Shipping Address -->
        @if($order->shippingAddress)
        <div class="content-section">
            <h3 class="section-title">Indirizzo di Spedizione</h3>
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
            <h3>Gestisci il tuo ordine</h3>
            <p style="margin-bottom: 0;">Visualizza i dettagli e tieni traccia del tuo ordine</p>
            <div class="cta-buttons">
                <!--[if mso]>
                <table border="0" cellspacing="0" cellpadding="0" align="center" style="margin: 0 auto;">
                <tr>
                <td align="center" style="padding: 0 7px;">
                <![endif]-->
                <a href="{{ route('dashboard') }}" 
                   style="display: inline-block; padding: 12px 24px; text-decoration: none; font-weight: 500; font-size: 14px; text-align: center; background-color: #000000; color: #ffffff; border: 1px solid #000000; margin: 0 7px;">
                   Visualizza Ordine
                </a>
                <!--[if mso]>
                </td>
                <td align="center" style="padding: 0 7px;">
                <![endif]-->
                <a href="{{ route('products') }}" 
                   style="display: inline-block; padding: 12px 24px; text-decoration: none; font-weight: 500; font-size: 14px; text-align: center; background-color: #ffffff; color: #333333; border: 1px solid #333333; margin: 0 7px;">
                   Continua Shopping
                </a>
                <!--[if mso]>
                </td>
                </tr>
                </table>
                <![endif]-->
            </div>
        </div>

        <!-- Information Section -->
        <div class="content-section" style="background-color: #f8f8f8;">
            <h3 class="section-title">Informazioni</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 30px;">
                <div>
                    <h4 style="color: #333333; margin-bottom: 8px; font-size: 14px; font-weight: 500;">Tracking</h4>
                    <p style="font-size: 13px; color: #666666;">Riceverai il numero di tracking via email</p>
                </div>
                <div>
                    <h4 style="color: #333333; margin-bottom: 8px; font-size: 14px; font-weight: 500;">Consegna</h4>
                    <p style="font-size: 13px; color: #666666;">3-5 giorni lavorativi</p>
                </div>
                <div>
                    <h4 style="color: #333333; margin-bottom: 8px; font-size: 14px; font-weight: 500;">Resi</h4>
                    <p style="font-size: 13px; color: #666666;">30 giorni per resi gratuiti</p>
                </div>
                <div>
                    <h4 style="color: #333333; margin-bottom: 8px; font-size: 14px; font-weight: 500;">Supporto</h4>
                    <p style="font-size: 13px; color: #666666;">Contattaci per assistenza</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <h3>{{ config('app.name') }}</h3>
            <p>Grazie per il tuo ordine</p>
            
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