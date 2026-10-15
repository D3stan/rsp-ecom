@extends('emails.layouts.base-text', ['senderIdentity' => true, 'noReply' => false, 'unsubscribeUrl' => $unsubscribeUrl])

@section('title'){!! $subjectLine !!}@endsection

@section('content')
{!! $bodyText !!}
@endsection
