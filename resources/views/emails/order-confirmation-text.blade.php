@extends('emails.layouts.base-text')

@section('title'){!! __('emails.order_confirmation.title') !!}@endsection

@section('content')
{!! $greeting !!}

{!! __('emails.order_confirmation.intro') !!}

{!! __('emails.common.order_number') !!}: {!! $order->order_number !!}
{!! __('emails.common.order_date') !!}: {!! $orderDate !!}

{!! __('emails.common.items_title') !!}
@include('emails.partials.items-text', ['items' => $items])

@include('emails.partials.totals-text', ['totals' => $totals])

@include('emails.partials.address-text', ['title' => __('emails.common.shipping_address'), 'lines' => $shippingAddress])

{!! __('emails.common.delivery_title') !!}
{!! __('emails.common.delivery_confirmation', $delivery) !!}

{!! __('emails.common.view_order') !!}: {!! $orderUrl !!}
{!! $isGuest ? __('emails.common.view_order_hint').' '.__('emails.common.view_order_guest_hint', ['days' => $publicUrlDays]) : __('emails.common.view_order_hint') !!}

@include('emails.partials.withdrawal-text')
@endsection
