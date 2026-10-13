{{-- Body paragraph. Params: text (escaped), muted (bool) --}}
<p style="margin: 0 0 16px; color: {{ ($muted ?? false) ? '#555555' : '#222222' }}; font-family: Arial, Helvetica, sans-serif; font-size: {{ ($muted ?? false) ? '14px' : '16px' }}; line-height: {{ ($muted ?? false) ? '21px' : '24px' }};">{{ $text }}</p>
