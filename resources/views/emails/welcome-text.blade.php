@extends('emails.layouts.base-text')

@section('title'){!! __('emails.welcome.title') !!}@endsection

@section('content')
{!! $greeting !!}

{!! __('emails.welcome.intro') !!}
{!! __('emails.welcome.body') !!}

{!! __('emails.welcome.button') !!}: {!! $accountUrl !!}
@endsection
