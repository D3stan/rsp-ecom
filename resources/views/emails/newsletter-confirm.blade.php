@extends('emails.layouts.base', ['senderIdentity' => true])

@section('title', __('newsletter.confirm_email.title'))
@section('preheader', __('newsletter.confirm_email.preheader'))

@section('content')
    @include('emails.partials.paragraph', ['text' => $greeting])
    @include('emails.partials.paragraph', ['text' => __('newsletter.confirm_email.intro', ['store' => $storeName])])
    @include('emails.partials.button', ['url' => $confirmUrl, 'label' => __('newsletter.confirm_email.button')])
    @include('emails.partials.paragraph', ['text' => __('newsletter.confirm_email.validity', ['days' => $validDays]), 'muted' => true])
    @include('emails.partials.paragraph', ['text' => __('newsletter.confirm_email.ignore'), 'muted' => true])
    @include('emails.partials.paragraph', ['text' => __('emails.common.button_fallback'), 'muted' => true])
    <p style="margin: 0 0 16px; color: #0b3d91; font-family: 'Courier New', Courier, monospace; font-size: 13px; line-height: 19px; word-break: break-all; overflow-wrap: anywhere;"><a href="{{ $confirmUrl }}" style="color: #0b3d91; text-decoration: underline;">{{ $confirmUrl }}</a></p>
@endsection
