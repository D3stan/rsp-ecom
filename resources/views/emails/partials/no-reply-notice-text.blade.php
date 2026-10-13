{{-- Plain-text twin of no-reply-notice.blade.php. Output is not HTML-escaped on purpose. --}}
-----
{!! __('emails.no_reply.title') !!}
{!! __('emails.no_reply.body') !!}
{!! __('emails.no_reply.help', ['address' => config('mail.support_address')]) !!}
{!! __('emails.no_reply.contact_link') !!}: {!! route('contact') !!}
