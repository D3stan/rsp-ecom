@extends('emails.layouts.base', ['senderIdentity' => true, 'noReply' => false, 'hideHeading' => true, 'unsubscribeUrl' => $unsubscribeUrl])

@section('title', $subjectLine)
@if($preheader !== '')
@section('preheader', $preheader)
@endif

@section('content')
    {!! $bodyHtml !!}
@endsection
