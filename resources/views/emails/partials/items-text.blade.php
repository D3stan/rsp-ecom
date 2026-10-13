{{-- Plain-text twin of items.blade.php. Output is not HTML-escaped on purpose. --}}
@foreach($items as $item)
- {!! $item['name'] !!}
@foreach($item['options'] as $option)
  {!! $option !!}
@endforeach
  {!! __('emails.common.qty_each', ['quantity' => $item['quantity'], 'price' => $item['unit']]) !!} = {!! $item['total'] !!}
@endforeach
