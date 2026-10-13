{{--
    Totals block: subtotal, discount (with coupon), shipping, total, then the "VAT included" note.
    Params: totals (see DescribesOrder::orderTotals)
--}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin-top: 8px;">
@foreach($totals['rows'] as $row)
    <tr>
        <td align="left" style="padding: {{ $row['bold'] ? '12px 0 4px' : '6px 0' }}; {{ $row['bold'] ? 'border-top: 2px solid #111111;' : '' }} color: {{ $row['bold'] ? '#111111' : '#333333' }}; font-family: Arial, Helvetica, sans-serif; font-size: {{ $row['bold'] ? '18px' : '15px' }}; line-height: 22px; {{ $row['bold'] ? 'font-weight: bold;' : '' }}">{{ $row['label'] }}</td>
        <td align="right" style="padding: {{ $row['bold'] ? '12px 0 4px' : '6px 0' }}; {{ $row['bold'] ? 'border-top: 2px solid #111111;' : '' }} color: {{ $row['bold'] ? '#111111' : '#333333' }}; font-family: Arial, Helvetica, sans-serif; font-size: {{ $row['bold'] ? '18px' : '15px' }}; line-height: 22px; white-space: nowrap; {{ $row['bold'] ? 'font-weight: bold;' : '' }}">{{ $row['value'] }}</td>
    </tr>
@endforeach
    <tr>
        <td colspan="2" align="right" style="padding: 0 0 4px; color: #555555; font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 19px;">{{ $totals['vat_note'] }}</td>
    </tr>
</table>
