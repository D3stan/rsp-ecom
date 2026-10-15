@extends('emails.layouts.base', ['senderIdentity' => true, 'noReply' => false, 'unsubscribeUrl' => $unsubscribeUrl])

@section('title', __('newsletter.reminder.title'))
@section('preheader', __('newsletter.reminder.preheader'))

@section('content')
    @include('emails.partials.paragraph', ['text' => $greeting])
    @include('emails.partials.paragraph', ['text' => __('newsletter.reminder.intro')])
    @include('emails.partials.items', ['items' => $items])
    <div style="height: 16px; line-height: 16px; font-size: 1px;">&nbsp;</div>
    @include('emails.partials.button', ['url' => $recoverUrl, 'label' => __('newsletter.reminder.button')])
    @include('emails.partials.paragraph', ['text' => __('newsletter.reminder.prices_note', ['days' => $validDays]), 'muted' => true])
    @include('emails.partials.paragraph', ['text' => __('newsletter.reminder.only_once'), 'muted' => true])
@endsection
