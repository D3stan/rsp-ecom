@extends('emails.layouts.base')

@section('title', __('emails.order_confirmation.title'))
@section('preheader', __('emails.order_confirmation.preheader', ['number' => $orderNumber]))

@section('content')
    @include('emails.partials.paragraph', ['text' => $greeting])
    @include('emails.partials.paragraph', ['text' => __('emails.order_confirmation.intro')])

    @include('emails.partials.facts', ['rows' => [
        ['label' => __('emails.common.order_number'), 'value' => $order->order_number],
        ['label' => __('emails.common.order_date'), 'value' => $orderDate],
    ]])

    @include('emails.partials.section-title', ['text' => __('emails.common.items_title')])
    @include('emails.partials.items', ['items' => $items])
    @include('emails.partials.totals', ['totals' => $totals])

    @include('emails.partials.address', ['title' => __('emails.common.shipping_address'), 'lines' => $shippingAddress])

    @include('emails.partials.section-title', ['text' => __('emails.common.delivery_title')])
    @include('emails.partials.paragraph', ['text' => __('emails.common.delivery_confirmation', $delivery), 'muted' => true])

    @include('emails.partials.button', ['url' => $orderUrl, 'label' => __('emails.common.view_order')])
    @include('emails.partials.paragraph', ['text' => $isGuest
        ? __('emails.common.view_order_hint').' '.__('emails.common.view_order_guest_hint', ['days' => $publicUrlDays])
        : __('emails.common.view_order_hint'), 'muted' => true])

    @include('emails.partials.withdrawal')
@endsection
