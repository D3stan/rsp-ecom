{{--
    Bulletproof button (table cell with its own background, so it renders without CSS support).
    Params: url, label, align (left|center, default left)
--}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="{{ $align ?? 'left' }}" style="margin: 8px 0 20px;">
    <tr>
        <td class="btn-cell" align="center" bgcolor="#000000" style="background-color: #000000; border-radius: 6px; mso-padding-alt: 14px 28px;">
            <a href="{{ $url }}" target="_blank" style="display: inline-block; padding: 14px 28px; background-color: #000000; border-radius: 6px; color: #ffffff; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 20px; font-weight: bold; text-decoration: none;">{{ $label }}</a>
        </td>
    </tr>
</table>
<div style="clear: both;"></div>
