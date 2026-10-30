@extends('mail.layout')
@section('body')
<p style="margin:0 0 14px 0;">Dear {{ $name }},</p>
<p style="margin:0 0 14px 0;">Thank you for your interest in working with Hexamatics. Before we schedule a pitch meeting, we ask every vendor to complete a short pre-pitch screening questionnaire.</p>
<p style="margin:0 0 4px 0;"><strong>Company:</strong> {{ $company }}</p>
<p style="margin:0 0 4px 0;"><strong>Questionnaire:</strong> {{ $questionnaire }}</p>
<p style="margin:0 0 14px 0;"><strong>Please submit by:</strong> {{ $deadline }}</p>
@include('mail.button', ['href' => $link, 'label' => 'Open questionnaire'])
<p style="margin:0 0 14px 0;font-size:13px;color:#4F4A70;">This link is personal to you. When you open it we will email a 6-digit code to this address to confirm it is you. You can save your answers and continue later from the same link.</p>
<p style="margin:0;font-size:12px;color:#4F4A70;word-break:break-all;">If the button does not work, copy this address into your browser:<br>{{ $link }}</p>
@endsection
