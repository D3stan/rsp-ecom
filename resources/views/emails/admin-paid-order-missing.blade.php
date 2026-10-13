@extends('emails.layouts.base', ['internal' => true])

@section('title', __('emails.admin.paid_order_missing.title'))

@section('content')
    @include('emails.partials.paragraph', ['text' => __('emails.admin.paid_order_missing.intro')])

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin-bottom: 16px;">
@foreach($rows as $row)
        <tr>
            <td valign="top" style="padding: 6px 12px 6px 0; border-bottom: 1px solid #e5e5e5; color: #555555; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 20px; white-space: nowrap;">{{ $row['label'] }}</td>
            <td valign="top" style="padding: 6px 0; border-bottom: 1px solid #e5e5e5; color: #111111; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 20px; word-break: break-all; overflow-wrap: anywhere;">{{ $row['value'] }}</td>
        </tr>
@endforeach
    </table>

    @include('emails.partials.paragraph', ['text' => __('emails.admin.paid_order_missing.action')])
    @include('emails.partials.paragraph', ['text' => __('emails.admin.internal_notice'), 'muted' => true])
@endsection
