{{--
    Right-of-withdrawal block (order confirmation + shipped). Same text in both mails.
    Days come from config('shipping.withdrawal_days') (EU statutory minimum: 14).
--}}
@php
    $withdrawalDays = (int) config('shipping.withdrawal_days');
    $supportAddress = config('mail.support_address');
@endphp
@include('emails.partials.section-title', ['text' => __('emails.withdrawal.title')])
<p style="margin: 0 0 10px; color: #333333; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 21px;">{{ __('emails.withdrawal.body', ['days' => $withdrawalDays]) }}</p>
<p style="margin: 0 0 10px; color: #333333; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 21px;">{{ __('emails.withdrawal.how', ['address' => $supportAddress]) }}</p>
<p style="margin: 0 0 16px; color: #333333; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 21px;">
    <a href="{{ route('terms') }}" style="color: #0b3d91; text-decoration: underline;">{{ __('emails.links.terms') }}</a>
    &nbsp;&middot;&nbsp;
    <a href="{{ route('shipping-returns') }}" style="color: #0b3d91; text-decoration: underline;">{{ __('emails.links.returns') }}</a>
    &nbsp;&middot;&nbsp;
    <a href="{{ route('privacy') }}" style="color: #0b3d91; text-decoration: underline;">{{ __('emails.links.privacy') }}</a>
</p>
