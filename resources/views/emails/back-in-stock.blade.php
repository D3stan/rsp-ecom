@extends('emails.layouts.base')

@section('title', __('emails.back_in_stock.title'))
@section('preheader', __('emails.back_in_stock.preheader', ['product' => $productName]))

@section('content')
    @include('emails.partials.paragraph', ['text' => $greeting])
    @include('emails.partials.paragraph', ['text' => $intro])

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin: 0 0 16px;">
        <tr>
            <td width="96" valign="top" style="width: 96px; padding: 12px 16px 12px 0; border-top: 1px solid #e5e5e5; border-bottom: 1px solid #e5e5e5;">
                <img src="{{ $image }}" width="96" alt="{{ $productName }}" style="display: block; width: 96px; max-width: 96px; height: auto; background-color: #f0f0f0; border-radius: 6px; color: #555555; font-size: 11px;">
            </td>
            <td valign="top" style="padding: 12px 0; border-top: 1px solid #e5e5e5; border-bottom: 1px solid #e5e5e5; color: #222222; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 22px; word-break: break-word; overflow-wrap: anywhere;">
                <strong style="color: #111111;">{{ $productName }}</strong>
@if($variantName)
                <br><span style="color: #555555; font-size: 14px; line-height: 20px;">{{ __('emails.common.variant', ['variant' => $variantName]) }}</span>
@endif
                <br><span style="color: #111111; font-size: 16px; line-height: 24px; font-weight: bold;">{{ $price }}</span>
            </td>
        </tr>
    </table>

    @include('emails.partials.paragraph', ['text' => __('emails.back_in_stock.hurry')])
    @include('emails.partials.button', ['url' => $actionUrl, 'label' => $actionLabel])

    <p style="margin: 8px 0 0; color: #555555; font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 20px;">
        {{ __('emails.back_in_stock.why') }}
        <a href="{{ $unsubscribeUrl }}" style="color: #333333; text-decoration: underline;">{{ __('emails.back_in_stock.unsubscribe') }}</a>
    </p>
@endsection
