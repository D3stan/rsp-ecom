@extends('emails.layouts.base')

@section('title', __('emails.order_refunded.title'))
@section('preheader', __('emails.order_refunded.preheader', ['number' => $orderNumber]))

@section('content')
    @include('emails.partials.paragraph', ['text' => $greeting])
    @include('emails.partials.paragraph', ['text' => $isPartial
        ? __('emails.order_refunded.intro_partial', ['amount' => $amount, 'number' => $orderNumber, 'total' => $orderTotal])
        : __('emails.order_refunded.intro_full', ['amount' => $amount, 'number' => $orderNumber])])
    @include('emails.partials.paragraph', ['text' => __('emails.order_refunded.timing')])
    @include('emails.partials.paragraph', ['text' => __('emails.order_refunded.problem', ['address' => $supportAddress])])
    @include('emails.partials.paragraph', ['text' => __('emails.order_refunded.closing')])
@endsection
