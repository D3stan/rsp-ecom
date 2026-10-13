@extends('emails.layouts.base-text')

@section('title'){!! __('emails.password_reset.title') !!}@endsection

@section('content')
{!! $greeting !!}

{!! __('emails.password_reset.intro') !!}

{!! __('emails.password_reset.button') !!}:
{!! $resetUrl !!}

{!! __('emails.password_reset.expiry', ['minutes' => $expiresInMinutes]) !!}
{!! __('emails.password_reset.ignore') !!}
@endsection
