<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conferma Ordine #{{ $order->order_number }}</title>
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
        
        .product-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .product-table th {
            background-color: #f8f8f8;
            padding: 15px 12px;
            text-align: left;
            font-weight: 500;
            color: #666666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .product-table td {
            padding: 20px 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 15px;
        }
        
        .product-info {
            display: flex;
            align-items: center;
        }
        
        .product-details h4 {
            font-size: 14px;
            font-weight: 500;
            color: #333333;
            margin-bottom: 4px;
        }
        
        .product-details p {
            font-size: 12px;
            color: #888888;
        }
        
        .price-breakdown {
            background-color: #f8f8f8;
            padding: 20px;
            margin-top: 30px;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
            color: #333333;
        }
        
        .price-row.total {
            border-top: 1px solid #e5e5e5;
            margin-top: 15px;
            padding-top: 15px;
            font-weight: 500;
            font-size: 16px;
            color: #000000;
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
        
        .cta-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 25px;
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
        
        .btn-secondary {
            background-color: white !important;
            color: #333333 !important;
            border-color: #333333 !important;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            color: white;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
        
        .footer-links a:hover {
            color: white;
        }
        
        .social-links {
            margin-top: 25px;
        }
        
        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #cccccc;
            font-size: 16px;
            text-decoration: none;
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
            
            .product-table th:nth-child(3),
            .product-table td:nth-child(3) {
                display: none;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .cta-buttons a {
                width: 100%;
                max-width: 200px;
                margin: 5px 0 !important;
                display: block !important;
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
                                @if($item->product && $item->product->main_image_url )
                                    <img src="{{ $item->product->main_image_url }}" alt="{{ $item->product->name }}" class="product-image">
                                @else
                                    <img src="{{ asset('images/product.png') }}" alt="Product" class="product-image">

                                    <!-- <div style="width: 60px; height: 60px; background-color: #e9ecef; border-radius: 8px; margin-right: 12px; display: flex; align-items: center; justify-content: center; color: #6c757d; font-size: 12px;">
                                        IMG
                                    </div> -->
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
                        <td style="text-align: right;">€{{ number_format($item->price, 2) }} <span style="font-size: 11px; color: #666;">(incl. IVA)</span></td>
                        <td style="text-align: right; font-weight: 600;">€{{ number_format($item->quantity * $item->price, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Price Breakdown -->
            <div class="price-breakdown">
                <div class="price-row">
                    <span>Subtotale (esclusa IVA): </span>
                    <span>€{{ number_format($order->subtotal, 2) }}</span>
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