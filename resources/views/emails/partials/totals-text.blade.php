{{-- Plain-text twin of totals.blade.php. Output is not HTML-escaped on purpose. --}}
@foreach($totals['rows'] as $row)
{!! $row['label'] !!}: {!! $row['value'] !!}
@endforeach
@if(! empty($totals['vat_note']))
({!! $totals['vat_note'] !!})
@endif
