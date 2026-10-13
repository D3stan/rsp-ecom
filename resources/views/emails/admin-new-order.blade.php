@extends('emails.layouts.base', ['internal' => true])

@section('title', __('emails.admin.new_order.title'))

@section('content')
    @include('emails.partials.paragraph', ['text' => __('emails.admin.new_order.intro', ['number' => '#'.$order->order_number, 'total' => $totalFormatted])])

    @include('emails.partials.section-title', ['text' => __('emails.admin.new_order.customer')])
    <p style="margin: 0 0 16px; color: #222222; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 22px; word-break: break-word; overflow-wrap: anywhere;">
        {{ $customerName ?? '-' }}
        ({{ $isGuest ? __('emails.admin.new_order.guest') : __('emails.admin.new_order.registered') }})<br>
        {{ __('emails.admin.new_order.email') }}: {{ $customerEmail ?? '-' }}<br>
        {{ __('emails.admin.new_order.phone') }}: {{ $customerPhone ?: '-' }}
    </p>

    @include('emails.partials.address', ['title' => __('emails.admin.new_order.shipping_address'), 'lines' => $addressLines])

    @include('emails.partials.section-title', ['text' => __('emails.admin.new_order.items_title')])
    @include('emails.partials.items', ['items' => $items])
    @include('emails.partials.totals', ['totals' => $totals])

    @if($order->notes)
        @include('emails.partials.section-title', ['text' => __('emails.admin.new_order.notes')])
        <p style="margin: 0 0 16px; color: #222222; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 22px; word-break: break-word; overflow-wrap: anywhere;">{{ $order->notes }}</p>
    @endif

    @include('emails.partials.button', ['url' => $adminUrl, 'label' => __('emails.admin.new_order.open_order')])
    @include('emails.partials.paragraph', ['text' => __('emails.admin.internal_notice'), 'muted' => true])
@endsection
