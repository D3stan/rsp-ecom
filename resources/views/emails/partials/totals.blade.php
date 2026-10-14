{{--
    Totals block: subtotal, discount (with coupon), shipping, total, then either the muted "of which VAT (22%) €x" row
    (orders that stored their VAT rate) or the plain "VAT included" note (older orders).
    Params: totals (see DescribesOrder::orderTotals)
--}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin-top: 8px;">
@foreach($totals['rows'] as $row)
    @if(! empty($row['muted']))
    <tr>
        <td align="left" style="padding: 0 0 4px; color: #555555; font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 19px;">{{ $row['label'] }}</td>
        <td align="right" style="padding: 0 0 4px; color: #555555; font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 19px; white-space: nowrap;">{{ $row['value'] }}</td>
    </tr>
    @else
    <tr>
        <td align="left" style="padding: {{ $row['bold'] ? '12px 0 4px' : '6px 0' }}; {{ $row['bold'] ? 'border-top: 2px solid #111111;' : '' }} color: {{ $row['bold'] ? '#111111' : '#333333' }}; font-family: Arial, Helvetica, sans-serif; font-size: {{ $row['bold'] ? '18px' : '15px' }}; line-height: 22px; {{ $row['bold'] ? 'font-weight: bold;' : '' }}">{{ $row['label'] }}</td>
        <td align="right" style="padding: {{ $row['bold'] ? '12px 0 4px' : '6px 0' }}; {{ $row['bold'] ? 'border-top: 2px solid #111111;' : '' }} color: {{ $row['bold'] ? '#111111' : '#333333' }}; font-family: Arial, Helvetica, sans-serif; font-size: {{ $row['bold'] ? '18px' : '15px' }}; line-height: 22px; white-space: nowrap; {{ $row['bold'] ? 'font-weight: bold;' : '' }}">{{ $row['value'] }}</td>
    </tr>
    @endif
@endforeach
@if(! empty($totals['vat_note']))
    <tr>
        <td colspan="2" align="right" style="padding: 0 0 4px; color: #555555; font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 19px;">{{ $totals['vat_note'] }}</td>
    </tr>
@endif
</table>
