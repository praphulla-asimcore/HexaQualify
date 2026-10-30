@extends('mail.layout')
@section('body')
<p style="margin:0 0 14px 0;">Dear {{ $name }},</p>
<p style="margin:0 0 14px 0;">This is a friendly reminder that your Hexamatics screening questionnaire (<strong>{{ $questionnaire }}</strong>) has not been submitted yet. The link expires on <strong>{{ $deadline }}</strong>.</p>
<p style="margin:0;">Please use the link from your original invitation email to continue. If you need a new link, reply to this email.</p>
@endsection
