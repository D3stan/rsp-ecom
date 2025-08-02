<x-mail::message>
# Il tuo ordine è stato spedito! 📦

Ciao {{ $customer->name }},

Ottime notizie! Il tuo ordine #{{ $order->order_number }} è stato spedito e sarà presto da te.

## Dettagli spedizione

**Ordine:** #{{ $order->order_number }}
**Data spedizione:** {{ now()->format('d/m/Y') }}
@if($trackingNumber)
**Numero di tracking:** {{ $trackingNumber }}
@endif

### Indirizzo di consegna:
@if($order->shippingAddress)
{{ $order->shippingAddress->first_name }} {{ $order->shippingAddress->last_name }}<br>
{{ $order->shippingAddress->address_line_1 }}<br>
@if($order->shippingAddress->address_line_2)
{{ $order->shippingAddress->address_line_2 }}<br>
@endif
{{ $order->shippingAddress->postal_code }} {{ $order->shippingAddress->city }}<br>
{{ $order->shippingAddress->state }}, {{ $order->shippingAddress->country }}
@endif

@if($trackingNumber)
<x-mail::button url="#">
Traccia il tuo pacco
</x-mail::button>
@endif

<x-mail::button :url="route('dashboard')">
Visualizza il tuo ordine
</x-mail::button>

**Tempo di consegna stimato:** 2-5 giorni lavorativi

Grazie per aver scelto il nostro store!

Cordiali saluti,<br>
{{ config('app.name') }}
</x-mail::message>