@extends('emails.layouts.base', ['senderIdentity' => true, 'noReply' => false, 'unsubscribeUrl' => $unsubscribeUrl])

@section('title', __('emails.review_request.title'))
@section('preheader', __('emails.review_request.preheader'))

@section('content')
    @include('emails.partials.paragraph', ['text' => $greeting])
    @include('emails.partials.paragraph', ['text' => __('emails.review_request.intro')])

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
    @foreach($products as $product)
        <tr>
            <td width="64" valign="top" style="width: 64px; padding: 12px 12px 12px 0; border-bottom: 1px solid #e5e5e5;">
                <img src="{{ $product['image'] }}" width="64" alt="{{ $product['name'] }}" style="display: block; width: 64px; max-width: 64px; height: auto; background-color: #f0f0f0; border-radius: 6px; color: #555555; font-size: 11px;">
            </td>
            <td valign="middle" style="padding: 12px 0; border-bottom: 1px solid #e5e5e5; color: #222222; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 21px; word-break: break-word; overflow-wrap: anywhere;">
                <strong style="color: #111111;">{{ $product['name'] }}</strong>
            </td>
            <td valign="middle" align="right" style="padding: 12px 0 12px 12px; border-bottom: 1px solid #e5e5e5; white-space: nowrap;">
                <a href="{{ $product['url'] }}" target="_blank" style="display: inline-block; padding: 8px 16px; background-color: #000000; border-radius: 6px; color: #ffffff; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 18px; font-weight: bold; text-decoration: none;">{{ __('emails.review_request.write') }}</a>
            </td>
        </tr>
    @endforeach
    </table>

    <div style="height: 16px; line-height: 16px; font-size: 1px;">&nbsp;</div>
    @include('emails.partials.paragraph', ['text' => __('emails.review_request.why'), 'muted' => true])
@endsection
