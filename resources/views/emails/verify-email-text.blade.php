@extends('emails.layouts.base-text')

@section('title'){!! __('emails.verify_email.title') !!}@endsection

@section('content')
{!! $greeting !!}

{!! __('emails.verify_email.intro', ['app' => $appName]) !!}

{!! __('emails.verify_email.button') !!}:
{!! $verificationUrl !!}

{!! __('emails.verify_email.ignore') !!}
@endsection
