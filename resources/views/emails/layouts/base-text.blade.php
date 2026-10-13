{{--
    Plain-text layout (twin of base.blade.php). Output is not HTML-escaped on purpose.
    Sections: title, content. Options: internal (no notice, no footer links), noReply.
--}}
{!! config('app.name') !!}
============================================================
@yield('title')

@yield('content')
@if(! ($internal ?? false) && ($noReply ?? true))

@include('emails.partials.no-reply-notice-text')
@endif
@unless($internal ?? false)

-----
{!! __('emails.links.terms') !!}: {!! route('terms') !!}
{!! __('emails.links.returns') !!}: {!! route('shipping-returns') !!}
{!! __('emails.links.privacy') !!}: {!! route('privacy') !!}
{!! __('emails.links.contact') !!}: {!! route('contact') !!}
@endunless
