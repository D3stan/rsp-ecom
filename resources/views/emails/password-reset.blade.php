@extends('emails.layouts.base')

@section('title', __('emails.password_reset.title'))
@section('preheader', __('emails.password_reset.preheader'))

@section('content')
    @include('emails.partials.paragraph', ['text' => $greeting])
    @include('emails.partials.paragraph', ['text' => __('emails.password_reset.intro')])
    @include('emails.partials.button', ['url' => $resetUrl, 'label' => __('emails.password_reset.button')])
    @include('emails.partials.paragraph', ['text' => __('emails.password_reset.expiry', ['minutes' => $expiresInMinutes]), 'muted' => true])
    @include('emails.partials.paragraph', ['text' => __('emails.password_reset.ignore'), 'muted' => true])
    @include('emails.partials.paragraph', ['text' => __('emails.common.button_fallback'), 'muted' => true])
    <p style="margin: 0 0 16px; color: #0b3d91; font-family: 'Courier New', Courier, monospace; font-size: 13px; line-height: 19px; word-break: break-all; overflow-wrap: anywhere;"><a href="{{ $resetUrl }}" style="color: #0b3d91; text-decoration: underline;">{{ $resetUrl }}</a></p>
@endsection
