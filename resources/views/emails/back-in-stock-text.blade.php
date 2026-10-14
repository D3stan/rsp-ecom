@extends('emails.layouts.base-text')

@section('title'){!! __('emails.back_in_stock.title') !!}@endsection

@section('content')
{!! $greeting !!}

{!! $intro !!}

{!! $productName !!}
@if($variantName)
{!! __('emails.common.variant', ['variant' => $variantName]) !!}
@endif
{!! $price !!}

{!! __('emails.back_in_stock.hurry') !!}

{!! $actionLabel !!}: {!! $actionUrl !!}

{!! __('emails.back_in_stock.why') !!}
{!! __('emails.back_in_stock.unsubscribe') !!}: {!! $unsubscribeUrl !!}
@endsection
