{{--
    Order lines: image, name, options (variant / size / SKU), "qty x unit price", line total.
    Params: items (see DescribesOrder::orderLines)
--}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
@foreach($items as $item)
    <tr>
        <td width="64" valign="top" style="width: 64px; padding: 12px 12px 12px 0; border-bottom: 1px solid #e5e5e5;">
            <img src="{{ $item['image'] }}" width="64" alt="{{ $item['name'] }}" style="display: block; width: 64px; max-width: 64px; height: auto; background-color: #f0f0f0; border-radius: 6px; color: #555555; font-size: 11px;">
        </td>
        <td valign="top" style="padding: 12px 0; border-bottom: 1px solid #e5e5e5; color: #222222; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 21px; word-break: break-word; overflow-wrap: anywhere;">
            <strong style="color: #111111;">{{ $item['name'] }}</strong>
@foreach($item['options'] as $option)
            <br><span style="color: #555555; font-size: 13px; line-height: 19px;">{{ $option }}</span>
@endforeach
            <br><span style="color: #555555; font-size: 13px; line-height: 19px;">{{ __('emails.common.qty_each', ['quantity' => $item['quantity'], 'price' => $item['unit']]) }}</span>
        </td>
        <td valign="top" align="right" style="padding: 12px 0 12px 12px; border-bottom: 1px solid #e5e5e5; color: #111111; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 21px; font-weight: bold; white-space: nowrap;">
            {{ $item['total'] }}
        </td>
    </tr>
@endforeach
</table>
