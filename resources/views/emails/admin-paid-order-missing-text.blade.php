@extends('emails.layouts.base-text', ['internal' => true])

@section('title'){!! __('emails.admin.paid_order_missing.title') !!}@endsection

@section('content')
{!! __('emails.admin.paid_order_missing.intro') !!}

@foreach($rows as $row)
{!! $row['label'] !!}: {!! $row['value'] !!}
@endforeach

{!! __('emails.admin.paid_order_missing.action') !!}

{!! __('emails.admin.internal_notice') !!}
@endsection
