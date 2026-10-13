{{--
    Small label/value panel (order number, date, tracking...).
    Params: rows (array of ['label' => string, 'value' => string, 'mono' => bool?])
--}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f6f6f6" style="width: 100%; margin: 4px 0 8px; background-color: #f6f6f6; border: 1px solid #e5e5e5; border-radius: 6px;">
    <tr>
@foreach($rows as $row)
        <td class="stack" valign="top" width="{{ (int) floor(100 / count($rows)) }}%" bgcolor="#f6f6f6" style="padding: 12px 16px; background-color: #f6f6f6; font-family: Arial, Helvetica, sans-serif; word-break: break-word; overflow-wrap: anywhere;">
            <span style="display: block; color: #555555; font-size: 12px; line-height: 18px; text-transform: uppercase; letter-spacing: 0.4px;">{{ $row['label'] }}</span>
            <span style="display: block; color: #111111; font-size: 16px; line-height: 22px; font-weight: bold;{{ ($row['mono'] ?? false) ? " font-family: 'Courier New', Courier, monospace;" : '' }}">{{ $row['value'] }}</span>
        </td>
@endforeach
    </tr>
</table>
