@extends('emails.layouts.base-text')

@section('title'){!! __('emails.order_cancelled.title') !!} {!! $orderNumber !!}@endsection

@section('content')
{!! $greeting !!}

{!! __('emails.order_cancelled.intro', ['number' => $orderNumber]) !!}
@if($refundDue)

{!! __('emails.order_cancelled.refund_due', ['amount' => $amount]) !!}
@endif
@if(count($items))

{!! __('emails.common.items_title') !!}
@include('emails.partials.items-text', ['items' => $items])

@include('emails.partials.totals-text', ['totals' => $totals])
@endif

{!! __('emails.order_cancelled.closing') !!}
@endsection
