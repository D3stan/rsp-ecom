@extends('emails.layouts.base')

@section('title', __('emails.verify_pending.title'))
@section('preheader', __('emails.verify_pending.preheader'))

@section('content')
    @include('emails.partials.paragraph', ['text' => $greeting])
    @include('emails.partials.paragraph', ['text' => __('emails.verify_pending.intro', ['app' => $appName])])
    @include('emails.partials.button', ['url' => $verificationUrl, 'label' => __('emails.verify_pending.button')])
    @include('emails.partials.paragraph', ['text' => __('emails.verify_pending.expiry', ['hours' => $expiresInHours]), 'muted' => true])
    @include('emails.partials.paragraph', ['text' => __('emails.verify_pending.ignore'), 'muted' => true])
    @include('emails.partials.paragraph', ['text' => __('emails.common.button_fallback'), 'muted' => true])
    <p style="margin: 0 0 16px; color: #0b3d91; font-family: 'Courier New', Courier, monospace; font-size: 13px; line-height: 19px; word-break: break-all; overflow-wrap: anywhere;"><a href="{{ $verificationUrl }}" style="color: #0b3d91; text-decoration: underline;">{{ $verificationUrl }}</a></p>
@endsection
