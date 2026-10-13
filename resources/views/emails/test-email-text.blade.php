@extends('emails.layouts.base-text', ['internal' => true])

@section('title'){!! __('emails.test.title') !!}@endsection

@section('content')
{!! __('emails.test.body') !!}
@endsection
