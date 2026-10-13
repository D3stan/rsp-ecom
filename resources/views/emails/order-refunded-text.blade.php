@extends('emails.layouts.base-text')

@section('title'){!! __('emails.order_refunded.title') !!} {!! $orderNumber !!}@endsection

@section('content')
{!! $greeting !!}

{!! $isPartial
    ? __('emails.order_refunded.intro_partial', ['amount' => $amount, 'number' => $orderNumber, 'total' => $orderTotal])
    : __('emails.order_refunded.intro_full', ['amount' => $amount, 'number' => $orderNumber]) !!}

{!! __('emails.order_refunded.timing') !!}

{!! __('emails.order_refunded.problem', ['address' => $supportAddress]) !!}

{!! __('emails.order_refunded.closing') !!}
@endsection
