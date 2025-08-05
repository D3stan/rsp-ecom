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
            line-height: 1.6;
            color: #333333;
            background-color: #f8f9fa;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .email-header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .email-header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .order-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-left: 4px solid #667eea;
            margin: 20px;
            border-radius: 8px;
        }
        
        .order-info h2 {
            color: #667eea;
            font-size: 20px;
            margin-bottom: 15px;
        }
        
        .order-details {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .order-detail-item {
            flex: 1;
            min-width: 120px;
        }
        
        .order-detail-item strong {
            display: block;
            color: #495057;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .order-detail-item span {
            font-size: 16px;
            font-weight: 600;
        }
        
        .content-section {
            padding: 20px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .product-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .product-table th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        
        .product-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 12px;
        }
        
        .product-info {
            display: flex;
            align-items: center;
        }
        
        .product-details h4 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .product-details p {
            font-size: 14px;
            color: #6c757d;
        }
        
        .price-breakdown {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 16px;
        }
        
        .price-row.total {
            border-top: 2px solid #dee2e6;
            margin-top: 10px;
            padding-top: 15px;
            font-weight: 700;
            font-size: 18px;
            color: #667eea;
        }
        
        .shipping-address {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .shipping-address h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 12px;
        }
        
        .address-details {
            line-height: 1.5;
            color: #495057;
        }
        
        .cta-section {
            text-align: center;
            padding: 30px 20px;
            background-color: #f8f9fa;
        }
        
        .cta-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background-color: white;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            background-color: #28a745;
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .footer {
            background-color: #343a40;
            color: #adb5bd;
            padding: 30px;
            text-align: center;
            font-size: 14px;
        }
        
        .footer h3 {
            color: white;
            margin-bottom: 15px;
        }
        
        .footer-links {
            margin: 20px 0;
        }
        
        .footer-links a {
            color: #adb5bd;
            text-decoration: none;
            margin: 0 10px;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        .social-links {
            margin-top: 20px;
        }
        
        .social-links a {
            display: inline-block;
            margin: 0 8px;
            color: #adb5bd;
            font-size: 18px;
        }
        
        @media screen and (max-width: 600px) {
            .email-container {
                margin: 0;
                box-shadow: none;
            }
            
            .email-header {
                padding: 20px;
            }
            
            .email-header h1 {
                font-size: 24px;
            }
            
            .order-details {
                flex-direction: column;
            }
            
            .product-table th:nth-child(3),
            .product-table td:nth-child(3) {
                display: none;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1>🎉 Grazie per il tuo ordine!</h1>
            <p>Il tuo ordine è stato ricevuto e verrà processato a breve</p>
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
                <div class="order-detail-item">
                    <strong>Stato</strong>
                    <span class="status-badge">{{ ucfirst($order->status) }}</span>
                </div>
                <div class="order-detail-item">
                    <strong>Pagamento</strong>
                    <span>{{ $order->payment_status === 'succeeded' ? 'Completato' : 'In elaborazione' }}</span>
                </div>
            </div>
        </div>

        <!-- Customer Greeting -->
        <div class="content-section">
            <p style="font-size: 16px; margin-bottom: 20px;">
                Ciao <strong>{{ $customer->name ?? 'Cliente' }}</strong>,
            </p>
            <p style="margin-bottom: 20px;">
                Abbiamo ricevuto il tuo ordine e stiamo preparando i tuoi articoli con la massima cura. 
                Riceverai un'email di conferma di spedizione non appena il tuo ordine sarà in viaggio.
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
                                @if($item->product && $item->product->main_image_url)
                                    <img src="{{ $item->product->main_image_url }}" alt="{{ $item->product->name }}" class="product-image">
                                @else
                                    <div style="width: 60px; height: 60px; background-color: #e9ecef; border-radius: 8px; margin-right: 12px; display: flex; align-items: center; justify-content: center; color: #6c757d; font-size: 12px;">
                                        IMG
                                    </div>
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
                    <span>Subtotale:</span>
                    <span>€{{ number_format($order->subtotal, 2) }}</span>
                </div>
                @if($order->tax_amount > 0)
                <div class="price-row">
                    <span>Tasse:</span>
                    <span>€{{ number_format($order->tax_amount, 2) }}</span>
                </div>
                @endif
                @if($order->shipping_amount > 0)
                <div class="price-row">
                    <span>Spedizione:</span>
                    <span>€{{ number_format($order->shipping_amount, 2) }}</span>
                </div>
                @endif
                <div class="price-row total">
                    <span>Totale:</span>
                    <span>€{{ number_format($order->total_amount, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Shipping Address -->
        @if($order->shippingAddress)
        <div class="content-section">
            <h3 class="section-title">Indirizzo di Spedizione</h3>
            <div class="shipping-address">
                <h3>📦 Spedizione a:</h3>
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
            <h3>Cosa fare ora?</h3>
            <p style="margin-bottom: 0;">Tieni traccia del tuo ordine e gestisci i tuoi acquisti dal tuo account</p>
            <div class="cta-buttons">
                <a href="{{ route('dashboard') }}" class="btn btn-primary">Visualizza Ordine</a>
                <a href="{{ route('products') }}" class="btn btn-secondary">Continua Shopping</a>
            </div>
        </div>

        <!-- Information Section -->
        <div class="content-section" style="background-color: #f8f9fa;">
            <h3 class="section-title">Informazioni Utili</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <h4 style="color: #667eea; margin-bottom: 8px;">📋 Tracking Ordine</h4>
                    <p style="font-size: 14px; color: #6c757d;">Riceverai un numero di tracking via email non appena il tuo ordine sarà spedito.</p>
                </div>
                <div>
                    <h4 style="color: #667eea; margin-bottom: 8px;">🚚 Tempi di Consegna</h4>
                    <p style="font-size: 14px; color: #6c757d;">La consegna avviene entro 3-5 giorni lavorativi in Italia.</p>
                </div>
                <div>
                    <h4 style="color: #667eea; margin-bottom: 8px;">🔄 Resi e Cambi</h4>
                    <p style="font-size: 14px; color: #6c757d;">Hai 30 giorni di tempo per resi e cambi gratuiti.</p>
                </div>
                <div>
                    <h4 style="color: #667eea; margin-bottom: 8px;">💬 Supporto</h4>
                    <p style="font-size: 14px; color: #6c757d;">Contattaci per qualsiasi domanda sul tuo ordine.</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <h3>{{ config('app.name') }}</h3>
            <p>Grazie per aver scelto il nostro store online!</p>
            
            <div class="footer-links">
                <a href="{{ route('about') }}">Chi Siamo</a>
                <a href="{{ route('contact') }}">Contatti</a>
                <a href="{{ route('privacy') }}">Privacy</a>
                <a href="{{ route('terms') }}">Termini</a>
            </div>
            
            <div class="social-links">
                <a href="#">📘</a>
                <a href="#">📷</a>
                <a href="#">🐦</a>
            </div>
            
            <p style="font-size: 12px; margin-top: 20px; opacity: 0.7;">
                Questa email è stata inviata automaticamente. Per favore non rispondere a questa email.
            </p>
        </div>
    </div>
</body>
</html>