{{--
    "Automatic message" notice: shown in every automated customer email, never in replyable ones (contact form).
    Standalone: @include('emails.partials.no-reply-notice'). Plain-text twin: no-reply-notice-text.
--}}
@php
    $supportAddress = config('mail.support_address');
    $noticeFont = 'Arial, Helvetica, sans-serif';
    $helpLine = e(__('emails.no_reply.help', ['address' => '[[address]]']));
    $helpLine = str_replace(
        '[[address]]',
        '<a href="mailto:'.e($supportAddress).'" style="color: #0b3d91; font-weight: bold; text-decoration: underline;">'.e($supportAddress).'</a>',
        $helpLine
    );
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin-top: 24px;">
    <tr>
        <td bgcolor="#f3f4f6" style="padding: 16px 18px; background-color: #f3f4f6; border: 1px solid #c9ced6; border-left: 4px solid #111111; border-radius: 6px; font-family: {{ $noticeFont }}; font-size: 14px; line-height: 21px; color: #222222;">
            <strong style="display: block; margin-bottom: 4px; font-size: 14px; color: #111111;">{{ __('emails.no_reply.title') }}</strong>
            <span style="color: #222222;">{{ __('emails.no_reply.body') }}</span>
            <br>
            <span style="color: #222222;">{!! $helpLine !!}</span>
            <br>
            <a href="{{ route('contact') }}" style="color: #0b3d91; font-weight: bold; text-decoration: underline;">{{ __('emails.no_reply.contact_link') }}</a>
        </td>
    </tr>
</table>
