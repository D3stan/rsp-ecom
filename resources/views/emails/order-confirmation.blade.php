<x-mail::message>
# Grazie per il tuo ordine!

Ciao {{ $customer->name }},

Abbiamo ricevuto il tuo ordine e lo stiamo preparando. Ecco i dettagli:

## Ordine #{{ $order->order_number }}

**Data ordine:** {{ $order->created_at->format('d/m/Y H:i') }}

### Prodotti ordinati:
@foreach($items as $item)
- **{{ $item->product->name }}** 
  - Quantità: {{ $item->quantity }}
  - Prezzo: €{{ number_format($item->price, 2) }}
  - Subtotale: €{{ number_format($item->quantity * $item->price, 2) }}
@endforeach

---

**Subtotale:** €{{ number_format($order->subtotal, 2) }}
@if($order->tax_amount > 0)
**Tasse:** €{{ number_format($order->tax_amount, 2) }}
@endif
@if($order->shipping_amount > 0)
**Spedizione:** €{{ number_format($order->shipping_amount, 2) }}
@endif
**Totale:** €{{ number_format($order->total_amount, 2) }}

### Indirizzo di spedizione:
@if($order->shippingAddress)
{{ $order->shippingAddress->first_name }} {{ $order->shippingAddress->last_name }}<br>
{{ $order->shippingAddress->address_line_1 }}<br>
@if($order->shippingAddress->address_line_2)
{{ $order->shippingAddress->address_line_2 }}<br>
@endif
{{ $order->shippingAddress->postal_code }} {{ $order->shippingAddress->city }}<br>
{{ $order->shippingAddress->state }}, {{ $order->shippingAddress->country }}
@endif

<x-mail::button :url="route('dashboard')">
Visualizza il tuo ordine
</x-mail::button>

Ti invieremo un'altra email quando il tuo ordine sarà spedito.

Grazie per aver scelto il nostro store!

Cordiali saluti,<br>
{{ config('app.name') }}
</x-mail::message>