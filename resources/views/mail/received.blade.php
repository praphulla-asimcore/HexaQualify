@extends('mail.layout')
@section('body')
<p style="margin:0 0 14px 0;">Dear {{ $name }},</p>
<p style="margin:0 0 14px 0;">Thank you — your <strong>{{ $questionnaire }}</strong> questionnaire has been submitted to Hexamatics.</p>
<p style="margin:0 0 14px 0;">Our procurement team will review your answers and contact you about next steps. Your answers can no longer be changed; if you need to correct something, please reply to this email.</p>
<p style="margin:0;">Kind regards,<br>Hexamatics Procurement</p>
@endsection
