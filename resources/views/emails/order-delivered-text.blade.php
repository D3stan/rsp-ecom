@extends('emails.layouts.base-text')

@section('title'){!! __('emails.order_delivered.title') !!} {!! $orderNumber !!}@endsection

@section('content')
{!! $greeting !!}

{!! __('emails.order_delivered.intro', ['number' => $orderNumber]) !!}
@if(count($items))

{!! __('emails.common.items_title') !!}
@include('emails.partials.items-text', ['items' => $items])
@endif

{!! __('emails.order_delivered.problem', ['address' => $supportAddress]) !!}
{!! __('emails.common.view_order') !!}: {!! $orderUrl !!}

{!! __('emails.order_delivered.closing') !!}
@endsection
