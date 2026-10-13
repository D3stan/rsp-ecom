{{-- Plain-text twin of totals.blade.php. Output is not HTML-escaped on purpose. --}}
@foreach($totals['rows'] as $row)
{!! $row['label'] !!}: {!! $row['value'] !!}
@endforeach
({!! $totals['vat_note'] !!})
