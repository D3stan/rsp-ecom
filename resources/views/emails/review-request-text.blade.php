@extends('emails.layouts.base-text', ['senderIdentity' => true, 'noReply' => false, 'unsubscribeUrl' => $unsubscribeUrl])

@section('title'){!! __('emails.review_request.title') !!}@endsection

@section('content')
{!! $greeting !!}

{!! __('emails.review_request.intro') !!}

@foreach($products as $product)
- {!! $product['name'] !!}
  {!! __('emails.review_request.write') !!}: {!! $product['url'] !!}
@endforeach

{!! __('emails.review_request.why') !!}
@endsection
