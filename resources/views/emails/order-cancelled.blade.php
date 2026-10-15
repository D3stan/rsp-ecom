@extends('emails.layouts.base')

@section('title', __('emails.order_cancelled.title'))
@section('preheader', __('emails.order_cancelled.preheader', ['number' => $orderNumber]))

@section('content')
    @include('emails.partials.paragraph', ['text' => $greeting])
    @include('emails.partials.paragraph', ['text' => __('emails.order_cancelled.intro', ['number' => $orderNumber])])
    @if($refunded)
        @include('emails.partials.paragraph', ['text' => __('emails.order_cancelled.refunded', ['amount' => $refundedAmount])])
    @elseif($refundDue)
        @include('emails.partials.paragraph', ['text' => __('emails.order_cancelled.refund_due', ['amount' => $amount])])
    @endif

    @if(count($items))
        @include('emails.partials.section-title', ['text' => __('emails.common.items_title')])
        @include('emails.partials.items', ['items' => $items])
        @include('emails.partials.totals', ['totals' => $totals])
    @endif

    <p style="margin: 24px 0 16px; color: #222222; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 24px;">{{ __('emails.order_cancelled.closing') }}</p>
@endsection
