@extends('emails.layouts.base')

@section('title', __('emails.order_delivered.title'))
@section('preheader', __('emails.order_delivered.preheader', ['number' => $orderNumber]))

@section('content')
    @include('emails.partials.paragraph', ['text' => $greeting])
    @include('emails.partials.paragraph', ['text' => __('emails.order_delivered.intro', ['number' => $orderNumber])])

    @if(count($items))
        @include('emails.partials.section-title', ['text' => __('emails.common.items_title')])
        @include('emails.partials.items', ['items' => $items])
    @endif

    <p style="margin: 24px 0 16px; color: #222222; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 24px;">{{ __('emails.order_delivered.problem', ['address' => $supportAddress]) }}</p>
    @include('emails.partials.button', ['url' => $orderUrl, 'label' => __('emails.common.view_order')])
    <p style="margin: 0 0 16px; color: #222222; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 24px;">{{ __('emails.order_delivered.closing') }}</p>
@endsection
