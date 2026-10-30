@extends('mail.layout')
@section('body')
<p style="margin:0 0 14px 0;"><strong>{{ $company }}</strong> has submitted the <strong>{{ $questionnaire }}</strong> questionnaire.</p>
<p style="margin:0;">The automatic score is ready for review.</p>
@include('mail.button', ['href' => $link, 'label' => 'Review response'])
@endsection
