{{-- Postal address block. Params: title, lines --}}
@if(count($lines))
@include('emails.partials.section-title', ['text' => $title])
<p style="margin: 0 0 16px; color: #222222; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 22px; word-break: break-word; overflow-wrap: anywhere;">
@foreach($lines as $line)
    {{ $line }}@unless($loop->last)<br>@endunless
@endforeach
</p>
@endif
