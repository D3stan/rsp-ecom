@extends('emails.layouts.base-text', ['internal' => true])

@section('title'){!! __('emails.admin.oversold.title') !!} #{!! $order->order_number !!}@endsection

@section('content')
{!! __('emails.admin.oversold.intro', ['number' => '#'.$order->order_number]) !!}

@foreach($lines as $line)
- {!! $line['name'] !!}: {!! $line['detail'] !!}
@endforeach

{!! __('emails.admin.oversold.action') !!}

{!! __('emails.admin.new_order.open_order') !!}: {!! $adminUrl !!}

{!! __('emails.admin.internal_notice') !!}
@endsection
