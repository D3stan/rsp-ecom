@extends('emails.layouts.base-text', ['internal' => true])

@section('title'){!! __('emails.admin.new_order.title') !!} #{!! $order->order_number !!}@endsection

@section('content')
{!! __('emails.admin.new_order.intro', ['number' => '#'.$order->order_number, 'total' => $totalFormatted]) !!}

{!! __('emails.admin.new_order.customer') !!}: {!! $customerName ?? '-' !!} ({!! $isGuest ? __('emails.admin.new_order.guest') : __('emails.admin.new_order.registered') !!})
{!! __('emails.admin.new_order.email') !!}: {!! $customerEmail ?? '-' !!}
{!! __('emails.admin.new_order.phone') !!}: {!! $customerPhone ?: '-' !!}

@include('emails.partials.address-text', ['title' => __('emails.admin.new_order.shipping_address'), 'lines' => $addressLines])

{!! __('emails.admin.new_order.items_title') !!}
@include('emails.partials.items-text', ['items' => $items])

@include('emails.partials.totals-text', ['totals' => $totals])
@if($order->notes)

{!! __('emails.admin.new_order.notes') !!}: {!! $order->notes !!}
@endif

{!! __('emails.admin.new_order.open_order') !!}: {!! $adminUrl !!}

{!! __('emails.admin.internal_notice') !!}
@endsection
