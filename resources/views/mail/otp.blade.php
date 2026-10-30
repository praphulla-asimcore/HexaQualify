@extends('mail.layout')
@section('body')
<p style="margin:0 0 14px 0;">Your HexaQualify verification code is:</p>
<p style="margin:0 0 18px 0;font-family:'JetBrains Mono',Consolas,monospace;font-size:30px;font-weight:700;letter-spacing:6px;color:#15123A;">{{ $code }}</p>
<p style="margin:0;font-size:13px;color:#4F4A70;">The code is valid for {{ $minutes }} minutes. If you did not request it, you can ignore this email.</p>
@endsection
