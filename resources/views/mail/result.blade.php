@extends('mail.layout')
@section('body')
<p style="margin:0 0 14px 0;">Dear {{ $name }},</p>
@if ($qualified)
<p style="margin:0 0 14px 0;">Thank you for completing the Hexamatics pre-pitch screening. We are pleased to let you know that {{ $company }} has qualified, and we will contact you to arrange a pitch meeting.</p>
@else
<p style="margin:0 0 14px 0;">Thank you for taking the time to complete the Hexamatics pre-pitch screening. After careful review, we will not be moving forward with a pitch meeting for {{ $company }} at this time.</p>
<p style="margin:0 0 14px 0;">We appreciate your interest and may be in touch should our requirements change.</p>
@endif
<p style="margin:0;">Kind regards,<br>Hexamatics Procurement</p>
@endsection
