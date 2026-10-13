@extends('emails.layouts.base-text')

@section('title'){!! __('emails.verify_pending.title') !!}@endsection

@section('content')
{!! $greeting !!}

{!! __('emails.verify_pending.intro', ['app' => $appName]) !!}

{!! __('emails.verify_pending.button') !!}:
{!! $verificationUrl !!}

{!! __('emails.verify_pending.expiry', ['hours' => $expiresInHours]) !!}
{!! __('emails.verify_pending.ignore') !!}
@endsection
