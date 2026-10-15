@extends('emails.layouts.base-text', ['senderIdentity' => true])

@section('title'){!! __('newsletter.confirm_email.title') !!}@endsection

@section('content')
{!! $greeting !!}

{!! __('newsletter.confirm_email.intro', ['store' => $storeName]) !!}

{!! __('newsletter.confirm_email.button') !!}: {!! $confirmUrl !!}

{!! __('newsletter.confirm_email.validity', ['days' => $validDays]) !!}
{!! __('newsletter.confirm_email.ignore') !!}
@endsection
