<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light only">
<meta name="supported-color-schemes" content="light only">
<title>HexaQualify</title>
</head>
<body style="margin:0;padding:0;background:#F3F1FB;font-family:'Plus Jakarta Sans',Segoe UI,Helvetica,Arial,sans-serif;color:#15123A;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F1FB;padding:32px 12px;">
  <tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border-radius:16px;border:1px solid #E4E0F5;">
      <tr><td style="padding:28px 32px 18px 32px;">
        <table role="presentation" cellpadding="0" cellspacing="0"><tr>
          <td style="vertical-align:middle;"><img src="{{ asset('brand/hexa-logo.png') }}" width="110" alt="Hexa" style="display:block;width:110px;height:auto;border:0;"></td>
          <td style="vertical-align:middle;padding-left:14px;border-left:1px solid #D9D4EE;"><span style="font-size:16px;font-weight:700;color:#15123A;padding-left:14px;">HexaQualify</span></td>
        </tr></table>
      </td></tr>
      <tr><td style="height:3px;background:#BC0FA5;background-image:linear-gradient(90deg,#BC0FA5,#6A10CD,#0013FF);font-size:0;line-height:0;">&nbsp;</td></tr>
      <tr><td style="padding:28px 32px;font-size:15px;line-height:1.6;color:#15123A;">
        @yield('body')
      </td></tr>
      <tr><td style="padding:18px 32px 26px 32px;border-top:1px solid #EEEBF8;font-size:12px;line-height:1.6;color:#4F4A70;">
        {{ config('hexaqualify.company.legal_name') }}, {{ config('hexaqualify.company.address') }}, {{ config('hexaqualify.company.website') }}
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
