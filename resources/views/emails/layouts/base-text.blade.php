{{--
    Plain-text layout (twin of base.blade.php). Output is not HTML-escaped on purpose.
    Sections: title, content. Options: internal (no notice, no footer links), noReply, senderIdentity (legal name + address),
    unsubscribeUrl (marketing emails).
--}}
{!! settings('store.name') !!}
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
@if($senderIdentity ?? false)

{!! settings('store.name') !!}
@if(trim((string) settings('store.legal_name')) !== '')
{!! settings('store.legal_name') !!}
@endif
@if(trim((string) settings('store.address')) !== '')
{!! settings('store.address') !!}
@endif
@endif
@if(! empty($unsubscribeUrl))

{!! __('newsletter.campaign.footer_reason', ['store' => settings('store.name')]) !!}
{!! __('newsletter.campaign.unsubscribe') !!}: {!! $unsubscribeUrl !!}
@endif
