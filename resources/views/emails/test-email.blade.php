@extends('emails.layouts.base', ['internal' => true])

@section('title', __('emails.test.title'))

@section('content')
    @include('emails.partials.paragraph', ['text' => __('emails.test.body')])
@endsection
