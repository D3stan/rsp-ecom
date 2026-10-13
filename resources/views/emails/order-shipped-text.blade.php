@extends('emails.layouts.base-text')

@section('title'){!! __('emails.order_shipped.title') !!}@endsection

@section('content')
{!! $greeting !!}

{!! __('emails.order_shipped.intro', ['number' => $orderNumber]) !!}

{!! __('emails.order_shipped.tracking_title') !!}
@if($trackingNumber)
@if($carrierName)
{!! __('emails.order_shipped.carrier', ['carrier' => $carrierName]) !!}
@endif
{!! __('emails.order_shipped.tracking_number') !!}: {!! $trackingNumber !!}
@if($trackingUrl)
{!! __('emails.order_shipped.track_button') !!}: {!! $trackingUrl !!}
@endif
@else
{!! __('emails.order_shipped.no_tracking') !!}
@endif

{!! __('emails.common.delivery_shipped', ['delivery' => $delivery['delivery']]) !!}

{!! __('emails.common.order_number') !!}: {!! $order->order_number !!}
{!! __('emails.common.order_date') !!}: {!! $orderDate !!}

{!! __('emails.common.items_title') !!}
@include('emails.partials.items-text', ['items' => $items])

@include('emails.partials.address-text', ['title' => __('emails.common.shipping_address'), 'lines' => $shippingAddress])

{!! __('emails.common.view_order') !!}: {!! $orderUrl !!}
{!! $isGuest ? __('emails.common.view_order_hint').' '.__('emails.common.view_order_guest_hint', ['days' => $publicUrlDays]) : __('emails.common.view_order_hint') !!}

@include('emails.partials.withdrawal-text')
@endsection
