@extends('emails.layouts.base-text', ['senderIdentity' => true, 'noReply' => false, 'unsubscribeUrl' => $unsubscribeUrl])

@section('title'){!! __('newsletter.reminder.title') !!}@endsection

@section('content')
{!! $greeting !!}

{!! __('newsletter.reminder.intro') !!}

@include('emails.partials.items-text', ['items' => $items])

{!! __('newsletter.reminder.button') !!}: {!! $recoverUrl !!}

{!! __('newsletter.reminder.prices_note', ['days' => $validDays]) !!}
{!! __('newsletter.reminder.only_once') !!}
@endsection
