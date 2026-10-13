{{-- Plain-text twin of address.blade.php. Output is not HTML-escaped on purpose. --}}
@if(count($lines))
{!! $title !!}
@foreach($lines as $line)
{!! $line !!}
@endforeach
@endif
