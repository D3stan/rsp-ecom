@extends('emails.layouts.base')

@section('title', __('emails.welcome.title'))
@section('preheader', __('emails.welcome.preheader'))

@section('content')
    @include('emails.partials.paragraph', ['text' => $greeting])
    @include('emails.partials.paragraph', ['text' => __('emails.welcome.intro')])
    @include('emails.partials.paragraph', ['text' => __('emails.welcome.body')])
    @include('emails.partials.button', ['url' => $accountUrl, 'label' => __('emails.welcome.button')])
@endsection
