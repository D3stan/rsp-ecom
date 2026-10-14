{{--
    Shared HTML layout for every transactional email.
    Table-based and inline-styled (Gmail/Outlook safe: no grid, flex, CSS variables or background images);
    the <style> block holds progressive enhancements only (mobile padding, reset).
    Every cell sets its own background and text colour so dark-mode clients cannot produce unreadable text.

    Sections: title (required), preheader (optional), content (required).
    Options (pass as @extends second argument):
      internal    true for alerts to the shop owner: no footer links, no automatic-message notice
      noReply     false to leave the automatic-message notice out of a customer email
      logoUrl     override of the logo
      senderIdentity  true for consent and marketing emails: the footer adds the legal name and address of the sender
      unsubscribeUrl  marketing emails: the footer says why the mail arrives and links to the (signed) unsubscribe page
      hideHeading     true when the content brings its own heading (newsletter campaigns): no <h1> from the title
--}}
@php
    $appName = settings('store.name');
    $logoUrl = $logoUrl ?? asset('images/rlogo.png');
    $internal = $internal ?? false;
    $showNoReply = ! $internal && ($noReply ?? true);
    $htmlLang = str_replace('_', '-', app()->getLocale());
    $font = "Arial, Helvetica, sans-serif";
    $senderLines = ($senderIdentity ?? false)
        ? array_values(array_filter(array_map('trim', array_merge([(string) settings('store.legal_name')], preg_split('/\R/', (string) settings('store.address')))), fn ($line) => $line !== ''))
        : [];
@endphp
<!DOCTYPE html>
<html lang="{{ $htmlLang }}" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>@yield('title')</title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
    <style>
        :root { color-scheme: light; supported-color-schemes: light; }
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
        img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        @media only screen and (max-width: 620px) {
            .px { padding-left: 20px !important; padding-right: 20px !important; }
            .h1 { font-size: 22px !important; line-height: 28px !important; }
            .stack { display: block !important; width: 100% !important; box-sizing: border-box; }
            .btn-cell { width: 100% !important; }
            .btn-cell a { display: block !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; color: #222222; font-family: {{ $font }};" bgcolor="#f4f4f4">
@hasSection('preheader')
    <div style="display: none; max-height: 0; max-width: 0; overflow: hidden; mso-hide: all; font-size: 1px; line-height: 1px; color: #f4f4f4; opacity: 0;">
        @yield('preheader')
        {!! str_repeat('&#847;&zwnj;&nbsp;', 60) !!}
    </div>
@endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f4f4f4" style="width: 100%; background-color: #f4f4f4;">
        <tr>
            <td align="center" bgcolor="#f4f4f4" style="padding: 24px 12px; background-color: #f4f4f4;">
                <!--[if mso]><table role="presentation" width="600" align="center" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width: 100%; max-width: 600px; background-color: #ffffff; border: 1px solid #e5e5e5;">
                    {{-- Brand header --}}
                    <tr>
                        <td class="px" bgcolor="#000000" style="padding: 20px 32px; background-color: #000000;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="48" bgcolor="#ffffff" style="width: 48px; padding: 6px; background-color: #ffffff; border-radius: 8px;">
                                        <img src="{{ $logoUrl }}" width="36" height="36" alt="{{ __('emails.common.logo_alt', ['app' => $appName]) }}" style="display: block; width: 36px; height: 36px; background-color: #ffffff;">
                                    </td>
                                    <td bgcolor="#000000" style="padding-left: 14px; background-color: #000000; color: #ffffff; font-family: {{ $font }}; font-size: 18px; line-height: 24px; font-weight: bold; letter-spacing: 0.5px;">
                                        {{ $appName }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td class="px" bgcolor="#ffffff" style="padding: 32px 32px 8px; background-color: #ffffff; color: #222222; font-family: {{ $font }}; font-size: 16px; line-height: 24px;">
@unless($hideHeading ?? false)
                            <h1 class="h1" style="margin: 0 0 20px; color: #111111; font-family: {{ $font }}; font-size: 26px; line-height: 32px; font-weight: bold;">@yield('title')</h1>
@endunless
                            @yield('content')
                        </td>
                    </tr>
@if($showNoReply)
                    <tr>
                        <td class="px" bgcolor="#ffffff" style="padding: 8px 32px 32px; background-color: #ffffff;">
                            @include('emails.partials.no-reply-notice')
                        </td>
                    </tr>
@else
                    <tr><td bgcolor="#ffffff" style="height: 24px; line-height: 24px; font-size: 1px; background-color: #ffffff;">&nbsp;</td></tr>
@endif

                    {{-- Footer --}}
                    <tr>
                        <td class="px" align="center" bgcolor="#f9f9f9" style="padding: 24px 32px; background-color: #f9f9f9; border-top: 1px solid #e5e5e5; color: #555555; font-family: {{ $font }}; font-size: 13px; line-height: 20px;">
@unless($internal)
                            <a href="{{ route('terms') }}" style="color: #333333; text-decoration: underline;">{{ __('emails.links.terms') }}</a>
                            &nbsp;&middot;&nbsp;
                            <a href="{{ route('shipping-returns') }}" style="color: #333333; text-decoration: underline;">{{ __('emails.links.returns') }}</a>
                            &nbsp;&middot;&nbsp;
                            <a href="{{ route('privacy') }}" style="color: #333333; text-decoration: underline;">{{ __('emails.links.privacy') }}</a>
                            &nbsp;&middot;&nbsp;
                            <a href="{{ route('contact') }}" style="color: #333333; text-decoration: underline;">{{ __('emails.links.contact') }}</a>
                            <br>
@endunless
                            <span style="color: #555555;">{{ $appName }}</span>
@foreach($senderLines as $line)
                            <br><span style="color: #555555;">{{ $line }}</span>
@endforeach
@if(! empty($unsubscribeUrl))
                            <br><br><span style="color: #555555;">{{ __('newsletter.campaign.footer_reason', ['store' => $appName]) }}</span>
                            <br><a href="{{ $unsubscribeUrl }}" style="color: #333333; text-decoration: underline;">{{ __('newsletter.campaign.unsubscribe') }}</a>
@endif
                        </td>
                    </tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
